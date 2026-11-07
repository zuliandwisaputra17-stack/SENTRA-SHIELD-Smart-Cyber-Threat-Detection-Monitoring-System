<?php

namespace App\Response;

use App\Enums\ActionStatus;
use App\Enums\ActionType;
use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Campaign;
use App\Models\Incident;
use App\Models\ResponseAction;
use App\Models\Setting;
use App\Models\User;
use App\Readiness\ReadinessService;
use App\Services\AuditLogger;
use App\Support\LiveUpdate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Respons berjenjang (FR-06): Operator/Analis mengusulkan, Komandan
 * memutuskan, agent mengeksekusi di firewall host.
 *
 * - Usulan blokir dari insiden membuat satu baris per agent aktif di satuan
 *   insiden itu, dengan group_uuid yang sama (satu keputusan untuk semua).
 * - Pengusul tidak pernah bisa memutuskan usulannya sendiri (policy).
 * - Rollback oleh Komandan langsung dikirim ke agent (keputusan B9); setelah
 *   agent melaporkan berhasil, blokir asal berstatus Dibatalkan.
 * - Blokir yang durasinya habis berstatus Kedaluwarsa (agent sudah membukanya
 *   sendiri lewat timeout nftables).
 */
class ActionService
{
    public const DURATIONS = [900 => '15 menit', 3600 => '1 jam', 21600 => '6 jam', 86400 => '24 jam'];

    public function __construct(private AuditLogger $audit) {}

    public static function defaultDuration(): int
    {
        $value = (int) Setting::get('block_duration_s', 3600);

        return $value >= 60 && $value <= 86400 ? $value : 3600;
    }

    /** @return string group_uuid */
    public function proposeBlock(Incident $incident, User $user, int $durationS): string
    {
        Gate::forUser($user)->authorize('proposeAction', $incident);

        if ($incident->src_ip === null) {
            throw ValidationException::withMessages(['blokir' => 'Insiden ini tidak memiliki IP penyerang untuk diblokir.']);
        }
        if ($durationS < 60 || $durationS > 86400) {
            throw ValidationException::withMessages(['blokir' => 'Durasi blokir harus antara 1 menit dan 24 jam.']);
        }
        if ($this->openBlockExists($incident->unit_id, $incident->src_ip)) {
            throw ValidationException::withMessages(['blokir' => "Sudah ada usulan atau blokir aktif untuk {$incident->src_ip} di satuan ini."]);
        }

        $agents = Agent::query()
            ->where('unit_id', $incident->unit_id)
            ->whereIn('status', [AgentStatus::Online->value, AgentStatus::Buta->value])
            ->orderBy('hostname')
            ->get();
        if ($agents->isEmpty()) {
            throw ValidationException::withMessages(['blokir' => 'Tidak ada agent aktif di satuan ini.']);
        }

        $group = (string) Str::uuid();
        DB::transaction(function () use ($agents, $incident, $user, $durationS, $group) {
            foreach ($agents as $agent) {
                ResponseAction::create([
                    'group_uuid' => $group,
                    'incident_id' => $incident->id,
                    'type' => ActionType::BlockIp,
                    'target_agent_id' => $agent->id,
                    'params' => ['ip' => $incident->src_ip, 'duration_s' => $durationS],
                    'status' => ActionStatus::Diusulkan,
                    'proposed_by' => $user->id,
                ]);
            }
            $this->audit->record('tindakan_diusulkan', $incident, [
                'grup' => $group,
                'ip' => $incident->src_ip,
                'durasi_s' => $durationS,
                'agent' => $agents->pluck('hostname')->all(),
            ], $user);
        });

        LiveUpdate::notify('tindakan', [$incident->unit_id]);

        return $group;
    }

    /**
     * Blokir serentak dari halaman Kampanye (FR-10): satu baris per agent aktif
     * di setiap satuan terdampak, satu grup = satu keputusan Komandan.
     * Satuan yang sudah punya usulan/blokir aktif untuk IP ini dilewati.
     *
     * @return string group_uuid
     */
    public function proposeCampaignBlock(Campaign $campaign, User $user, int $durationS): string
    {
        if (! $user->can('usul-tindakan') || ! $user->can('lihat-kampanye')) {
            throw new AuthorizationException('Anda tidak berwenang mengusulkan blokir serentak.');
        }
        if ($durationS < 60 || $durationS > 86400) {
            throw ValidationException::withMessages(['blokir' => 'Durasi blokir harus antara 1 menit dan 24 jam.']);
        }

        $incidents = $campaign->incidents()->get()->keyBy('unit_id');
        $agents = Agent::query()
            ->whereIn('unit_id', $incidents->keys())
            ->whereIn('status', [AgentStatus::Online->value, AgentStatus::Buta->value])
            ->orderBy('unit_id')->orderBy('hostname')
            ->get()
            ->reject(fn (Agent $a) => $this->openBlockExists($a->unit_id, $campaign->src_ip));
        if ($agents->isEmpty()) {
            throw ValidationException::withMessages(['blokir' => 'Semua satuan kampanye ini sudah punya usulan atau blokir aktif untuk IP ini.']);
        }

        $group = (string) Str::uuid();
        DB::transaction(function () use ($agents, $incidents, $campaign, $user, $durationS, $group) {
            foreach ($agents as $agent) {
                ResponseAction::create([
                    'group_uuid' => $group,
                    'incident_id' => $incidents[$agent->unit_id]->id ?? null,
                    'campaign_id' => $campaign->id,
                    'type' => ActionType::BlockIp,
                    'target_agent_id' => $agent->id,
                    'params' => ['ip' => $campaign->src_ip, 'duration_s' => $durationS],
                    'status' => ActionStatus::Diusulkan,
                    'proposed_by' => $user->id,
                ]);
            }
            $this->audit->record('tindakan_diusulkan', $campaign, [
                'grup' => $group,
                'ip' => $campaign->src_ip,
                'durasi_s' => $durationS,
                'agent' => $agents->pluck('hostname')->all(),
                'serentak' => true,
            ], $user);
        });

        LiveUpdate::notify('tindakan', $agents->pluck('unit_id')->all());

        return $group;
    }

    public function decide(string $group, User $user, bool $approve, ?string $note = null): int
    {
        $rows = $this->pendingGroup($group);
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['keputusan' => 'Usulan ini sudah diputuskan atau dibatalkan.']);
        }
        foreach ($rows as $row) {
            if (! $user->can('decide', $row)) {
                throw new AuthorizationException('Anda tidak berwenang memutuskan usulan ini (pengusul tidak boleh menyetujui usulannya sendiri).');
            }
        }
        $note = trim((string) $note);
        if (! $approve && $note === '') {
            throw ValidationException::withMessages(['keputusan' => 'Alasan penolakan wajib diisi.']);
        }

        DB::transaction(function () use ($rows, $user, $approve, $note, $group) {
            foreach ($rows as $row) {
                $row->update([
                    'status' => $approve ? ActionStatus::Disetujui : ActionStatus::Ditolak,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'decision_note' => $note !== '' ? $note : null,
                ]);
            }
            $first = $rows->first();
            $this->audit->record($approve ? 'tindakan_disetujui' : 'tindakan_ditolak', $first->incident ?? $first, [
                'grup' => $group,
                'ip' => $first->params['ip'] ?? null,
                'jumlah_agent' => $rows->count(),
                'alasan' => $note !== '' ? $note : null,
            ], $user);
        });

        LiveUpdate::notify('tindakan', $rows->map(fn ($r) => $r->targetAgent?->unit_id)->all());

        return $rows->count();
    }

    public function cancel(string $group, User $user): int
    {
        $rows = $this->pendingGroup($group);
        if ($rows->isEmpty() || $rows->contains(fn ($r) => $r->proposed_by !== $user->id)) {
            throw new AuthorizationException('Hanya pengusul yang bisa membatalkan usulan yang belum diputuskan.');
        }

        foreach ($rows as $row) {
            $row->update(['status' => ActionStatus::Dibatalkan]);
        }
        $this->audit->record('usulan_dibatalkan', $rows->first()->incident ?? $rows->first(), ['grup' => $group], $user);
        LiveUpdate::notify('tindakan', $rows->map(fn ($r) => $r->targetAgent?->unit_id)->all());

        return $rows->count();
    }

    public function rollback(ResponseAction $block, User $user): ResponseAction
    {
        Gate::forUser($user)->authorize('rollback', $block);

        $pending = ResponseAction::query()
            ->where('type', ActionType::UnblockIp)
            ->whereIn('status', [ActionStatus::Disetujui->value])
            ->where('params->rollback_of', $block->id)
            ->exists();
        if ($pending) {
            throw ValidationException::withMessages(['rollback' => 'Perintah buka blokir untuk tindakan ini sedang dikirim ke agent.']);
        }

        $unblock = ResponseAction::create([
            'group_uuid' => (string) Str::uuid(),
            'incident_id' => $block->incident_id,
            'campaign_id' => $block->campaign_id,
            'type' => ActionType::UnblockIp,
            'target_agent_id' => $block->target_agent_id,
            'params' => ['ip' => $block->params['ip'] ?? null, 'rollback_of' => $block->id],
            'status' => ActionStatus::Disetujui,
            'proposed_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'decision_note' => 'Rollback oleh Komandan',
        ]);
        $this->audit->record('rollback_diminta', $block, ['ip' => $block->params['ip'] ?? null, 'perintah_buka' => $unblock->id], $user);
        LiveUpdate::notify('tindakan', [$block->targetAgent?->unit_id]);

        return $unblock;
    }

    /** Dipanggil setelah agent melaporkan hasil sebuah perintah. */
    public function afterResult(ResponseAction $action): void
    {
        if ($action->type === ActionType::RunTest) {
            app(ReadinessService::class)->onAgentResult($action);
        }
        $rollbackOf = $action->params['rollback_of'] ?? null;
        if ($action->type === ActionType::UnblockIp && $action->status === ActionStatus::Dieksekusi && $rollbackOf) {
            $original = ResponseAction::query()->find($rollbackOf);
            if ($original && $original->status === ActionStatus::Dieksekusi) {
                $original->update([
                    'status' => ActionStatus::Dibatalkan,
                    'result' => array_merge($original->result ?? [], ['dibuka_oleh' => $action->id, 'dibuka_pada' => now()->toIso8601String()]),
                ]);
                $this->audit->record('blokir_dibuka', $original, ['perintah_buka' => $action->id], null);
            }
        }
        LiveUpdate::notify('tindakan', [$action->targetAgent?->unit_id]);
    }

    /** Tandai blokir yang durasinya habis sebagai Kedaluwarsa. */
    public function expireBlocks(): int
    {
        $expired = ResponseAction::query()
            ->with('targetAgent')
            ->where('type', ActionType::BlockIp)
            ->where('status', ActionStatus::Dieksekusi)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->utc()->format('Y-m-d H:i:s'))
            ->get();

        foreach ($expired as $action) {
            $action->update(['status' => ActionStatus::Kedaluwarsa]);
            $this->audit->record('blokir_kedaluwarsa', $action, ['ip' => $action->params['ip'] ?? null], null);
        }
        if ($expired->isNotEmpty()) {
            LiveUpdate::notify('tindakan', $expired->map(fn ($a) => $a->targetAgent?->unit_id)->all());
        }

        return $expired->count();
    }

    public static function activeBlocks(): Builder
    {
        return ResponseAction::query()
            ->where('type', ActionType::BlockIp)
            ->where('status', ActionStatus::Dieksekusi)
            ->where('expires_at', '>', now()->utc()->format('Y-m-d H:i:s'));
    }

    private function openBlockExists(int $unitId, string $ip): bool
    {
        return ResponseAction::query()
            ->where('type', ActionType::BlockIp)
            ->where('params->ip', $ip)
            ->whereHas('targetAgent', fn ($q) => $q->where('unit_id', $unitId))
            ->where(fn ($q) => $q
                ->whereIn('status', [ActionStatus::Diusulkan->value, ActionStatus::Disetujui->value])
                ->orWhere(fn ($q) => $q->where('status', ActionStatus::Dieksekusi->value)
                    ->where('expires_at', '>', now()->utc()->format('Y-m-d H:i:s'))))
            ->exists();
    }

    /** @return Collection<int, ResponseAction> */
    private function pendingGroup(string $group): Collection
    {
        return ResponseAction::query()
            ->with(['incident', 'targetAgent'])
            ->where('group_uuid', $group)
            ->where('status', ActionStatus::Diusulkan)
            ->get();
    }
}
