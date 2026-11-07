<?php

namespace App\Readiness;

use App\Enums\ActionStatus;
use App\Enums\ActionType;
use App\Enums\AgentStatus;
use App\Enums\ReadinessStatus;
use App\Models\Agent;
use App\Models\Detection;
use App\Models\DetectionRule;
use App\Models\ReadinessResult;
use App\Models\ReadinessRun;
use App\Models\ResponseAction;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\DbTime;
use App\Support\LiveUpdate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Uji Kesiapan (FR-08): Core memerintahkan agent menjalankan skenario uji
 * aman (run_test, daftar tetap), lalu mengukur apakah deteksi yang
 * diharapkan muncul dan berapa lama (waktu deteksi).
 */
class ReadinessService
{
    /** Batas waktu satu putaran uji. */
    public const TIMEOUT_S = 300;

    /** Tunggu deteksi terakhir setelah semua agent melapor selesai. */
    public const GRACE_S = 20;

    public function __construct(private AuditLogger $audit) {}

    /** Token uji per run (HMAC kunci aplikasi), tidak perlu disimpan. */
    public static function token(ReadinessRun $run): string
    {
        return substr(hash_hmac('sha256', 'uji-kesiapan-'.$run->id, (string) config('app.key')), 0, 32);
    }

    public function start(Unit $unit, User $user): ReadinessRun
    {
        Gate::forUser($user)->authorize('jalankan-uji-kesiapan');

        if (ReadinessRun::query()->where('unit_id', $unit->id)->where('status', ReadinessStatus::Berjalan)->exists()) {
            throw ValidationException::withMessages(['uji' => 'Uji Kesiapan untuk satuan ini masih berjalan.']);
        }

        $agents = Agent::query()->where('unit_id', $unit->id)->where('status', AgentStatus::Online)->orderBy('hostname')->get();
        if ($agents->isEmpty()) {
            throw ValidationException::withMessages(['uji' => 'Tidak ada agent online di satuan ini.']);
        }

        // Skenario dijalankan oleh agent pertama yang sumber telemetrinya aktif.
        $assignments = [];
        foreach (ScenarioCatalog::SCENARIOS as $code => $scenario) {
            $agent = $scenario['sumber'] === null ? null : $agents->first(
                fn (Agent $a) => ($a->sources[$scenario['sumber']]['status'] ?? null) === 'ok'
            );
            if ($scenario['sumber'] !== null && $agent === null) {
                continue;
            }
            $assignments[$code] = $agent;
        }
        // R12 dijalankan agent yang paling banyak mendapat skenario (biasanya server web).
        $primary = collect($assignments)->filter()->countBy(fn (Agent $a) => $a->id)->sortDesc()->keys()->first();
        $primaryAgent = $primary ? $agents->firstWhere('id', $primary) : $agents->first();
        $assignments['R12'] = $primaryAgent;

        $rules = DetectionRule::query()->whereIn('code', array_keys($assignments))->get()->keyBy('code');

        $run = DB::transaction(function () use ($unit, $user, $assignments, $rules, $primaryAgent) {
            $run = ReadinessRun::create([
                'started_by' => $user->id,
                'unit_id' => $unit->id,
                'agent_id' => $primaryAgent->id,
                'status' => ReadinessStatus::Berjalan,
                'started_at' => now(),
            ]);
            foreach ($assignments as $code => $agent) {
                ReadinessResult::create([
                    'run_id' => $run->id,
                    'scenario' => $code,
                    'rule_id' => $rules[$code]->id,
                    'agent_id' => $agent->id,
                    'detected' => false,
                ]);
            }

            $token = self::token($run);
            foreach (collect($assignments)->groupBy(fn (Agent $a) => $a->id) as $agentId => $items) {
                ResponseAction::create([
                    'group_uuid' => (string) Str::uuid(),
                    'type' => ActionType::RunTest,
                    'target_agent_id' => $agentId,
                    'params' => ['run_id' => $run->id, 'token' => $token, 'scenarios' => array_keys(collect($assignments)->filter(fn ($a) => $a->id === $agentId)->all())],
                    'status' => ActionStatus::Disetujui,
                    'proposed_by' => $user->id,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'decision_note' => 'Uji Kesiapan (skenario aman ke host sendiri)',
                ]);
            }
            $this->audit->record('uji_kesiapan_dimulai', $run, ['satuan' => $unit->name, 'skenario' => array_keys($assignments)], $user);

            return $run;
        });

        LiveUpdate::notify('uji', [$unit->id]);

        return $run;
    }

    /**
     * Penanda jeda heartbeat dari agent (skenario R12): mulai menghitung waktu
     * deteksi dan beri tahu SensorWatch bahwa jeda ini bagian dari uji.
     */
    public function heartbeatMarker(Agent $agent, string $token): bool
    {
        $result = ReadinessResult::query()
            ->where('agent_id', $agent->id)
            ->where('scenario', 'R12')
            ->whereNull('started_at')
            ->whereHas('run', fn ($q) => $q->where('status', ReadinessStatus::Berjalan))
            ->with('run')
            ->latest('id')
            ->first();
        if ($result === null || ! hash_equals(self::token($result->run), $token)) {
            return false;
        }

        $result->update(['started_at' => now()]);
        Cache::put(self::gapKey($agent->id), $result->id, now()->addSeconds(120));

        return true;
    }

    public static function gapKey(int $agentId): string
    {
        return 'sentra:uji-jeda:'.$agentId;
    }

    /** Hasil run_test dari agent: waktu mulai tiap skenario + catatan kegagalan. */
    public function onAgentResult(ResponseAction $action): void
    {
        $run = ReadinessRun::query()->find($action->params['run_id'] ?? 0);
        if ($run === null) {
            return;
        }
        $report = (array) ($action->result['details']['skenario'] ?? []);

        foreach ($run->results()->where('agent_id', $action->target_agent_id)->get() as $result) {
            $item = (array) ($report[$result->scenario] ?? []);
            $updates = [];
            if ($result->started_at === null && ! empty($item['mulai'])) {
                $updates['started_at'] = Carbon::parse($item['mulai']);
            }
            if ($item === []) {
                $updates['note'] = mb_strimwidth('Tidak dijalankan agent: '.($action->result['message'] ?? 'tanpa keterangan'), 0, 250, '...');
            } elseif (($item['ok'] ?? true) === false) {
                $updates['note'] = mb_strimwidth((string) ($item['pesan'] ?? 'Skenario gagal dijalankan'), 0, 250, '...');
            }
            if ($updates !== []) {
                $result->update($updates);
            }
        }

        $this->evaluate($run);
    }

    /** Cocokkan deteksi uji dengan skenario; selesaikan run bila waktunya. */
    public function evaluate(ReadinessRun $run): void
    {
        $changed = false;
        foreach ($run->results()->where('detected', false)->whereNotNull('started_at')->get() as $result) {
            $detection = Detection::query()
                ->where('rule_id', $result->rule_id)
                ->where('agent_id', $result->agent_id)
                ->whereNull('incident_id')
                ->where('detected_at', '>=', DbTime::of($result->started_at->copy()->subSeconds(2)))
                ->where(fn ($q) => $q->whereHas('event', fn ($e) => $e->where('is_test', true))->orWhereNull('event_id'))
                ->orderBy('detected_at')
                ->first();
            if ($detection !== null) {
                $result->update([
                    'detected' => true,
                    'ttd_ms' => max(0, (int) round($result->started_at->diffInMilliseconds($detection->detected_at, false))),
                ]);
                $changed = true;
            }
        }

        if ($run->status === ReadinessStatus::Berjalan && $this->shouldFinish($run)) {
            $this->finish($run);
            $changed = true;
        }
        if ($changed) {
            LiveUpdate::notify('uji', [$run->unit_id]);
        }
    }

    /** Dipanggil scheduler tiap 10 detik. */
    public function tick(): void
    {
        ReadinessRun::query()->where('status', ReadinessStatus::Berjalan)->get()->each(fn ($run) => $this->evaluate($run));
    }

    private function shouldFinish(ReadinessRun $run): bool
    {
        if ($run->started_at->diffInSeconds(now()) >= self::TIMEOUT_S) {
            return true;
        }
        $commands = ResponseAction::query()->where('type', ActionType::RunTest)->where('params->run_id', $run->id)->get();
        $allReported = $commands->isNotEmpty() && $commands->every(fn ($c) => in_array($c->status, [ActionStatus::Dieksekusi, ActionStatus::Gagal], true));
        if (! $allReported) {
            return false;
        }
        if ($run->results()->where('detected', false)->doesntExist()) {
            return true;
        }

        // executed_at = waktu agent mulai uji; tenggang dihitung dari laporan masuk.
        return $commands->max('updated_at')?->diffInSeconds(now()) >= self::GRACE_S;
    }

    private function finish(ReadinessRun $run): void
    {
        $results = $run->results()->get();
        $detected = $results->where('detected', true);
        $run->update([
            'status' => ReadinessStatus::Selesai,
            'coverage' => $results->isEmpty() ? 0 : round($detected->count() / $results->count(), 4),
            'avg_ttd_ms' => $detected->isEmpty() ? null : (int) round($detected->avg('ttd_ms')),
            'finished_at' => now(),
        ]);
        $this->audit->record('uji_kesiapan_selesai', $run, [
            'cakupan' => $detected->count().'/'.$results->count(),
            'lolos' => $results->where('detected', false)->pluck('scenario')->values()->all(),
        ], null);
    }
}
