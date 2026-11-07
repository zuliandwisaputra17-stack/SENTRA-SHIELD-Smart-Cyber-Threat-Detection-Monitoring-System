<?php

namespace App\Livewire\Incidents;

use App\Detection\CampaignLinker;
use App\Detection\RiskScorer;
use App\Enums\IncidentStatus;
use App\Enums\KillChainStage;
use App\Models\AuditLog;
use App\Models\Detection;
use App\Models\Incident;
use App\Models\Report;
use App\Models\ResponseAction;
use App\Reports\LapsitService;
use App\Response\ActionService;
use App\Services\AuditLogger;
use App\Support\LiveUpdate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Detail Insiden (FR-04, FR-05): garis waktu Attack Story per tahap, teknik
 * ATT&CK, rincian skor, dan event bukti.
 */
#[Layout('layouts.app')]
class IncidentShow extends Component
{
    use WithPagination;

    public Incident $incident;

    public string $ruleFilter = '';

    public int $duration = 3600;

    public ?string $flash = null;

    public function mount(Incident $incident): void
    {
        $this->authorize('view', $incident);
        $this->incident = $incident;
        $this->duration = ActionService::defaultDuration();
    }

    #[On('sentra-refresh')]
    public function refreshLive(): void {}

    public function proposeBlock(ActionService $actions): void
    {
        $this->resetErrorBag();
        try {
            $actions->proposeBlock($this->incident, auth()->user(), (int) $this->duration);
        } catch (ValidationException $e) {
            $this->addError('blokir', collect($e->errors())->flatten()->first());

            return;
        }
        $this->flash = 'Usulan blokir dikirim ke Antrean Persetujuan Komandan.';
    }

    public function cancelProposal(string $group, ActionService $actions): void
    {
        $actions->cancel($group, auth()->user());
        $this->flash = 'Usulan dibatalkan.';
    }

    public function rollback(int $actionId, ActionService $actions): void
    {
        $this->resetErrorBag();
        $block = ResponseAction::query()->where('incident_id', $this->incident->id)->findOrFail($actionId);
        try {
            $actions->rollback($block, auth()->user());
        } catch (ValidationException $e) {
            $this->addError('blokir', collect($e->errors())->flatten()->first());

            return;
        } catch (AuthorizationException $e) {
            $this->addError('blokir', $e->getMessage());

            return;
        }
        $this->flash = 'Perintah buka blokir dikirim ke agent.';
    }

    public function createReport(LapsitService $lapsit): void
    {
        $this->authorize('kelola-lapsit');
        $this->resetErrorBag();
        if (! $lapsit->request($this->incident, auth()->user())) {
            $this->addError('lapsit', 'LAPSIT untuk insiden ini masih disusun.');
        }
    }

    public function setStatus(string $status, AuditLogger $audit, RiskScorer $scorer): void
    {
        $this->authorize('updateStatus', $this->incident);
        $new = IncidentStatus::tryFrom($status);
        if ($new === null || $new === $this->incident->status) {
            return;
        }

        $old = $this->incident->status;
        $this->incident->update(['status' => $new]);
        $audit->record('status_insiden_diubah', $this->incident, ['dari' => $old->value, 'ke' => $new->value]);
        $scorer->refreshUnitAlert($this->incident->unit_id);
        app(CampaignLinker::class)->refreshStatus($this->incident->campaign);
        LiveUpdate::notify('insiden', [$this->incident->unit_id]);
    }

    public function filterRule(string $code): void
    {
        $this->ruleFilter = $this->ruleFilter === $code ? '' : $code;
        $this->resetPage();
    }

    public function render()
    {
        $incident = $this->incident->fresh(['unit', 'campaign']);
        $breakdown = $incident->score_breakdown ?? [];

        // Garis waktu: aturan per tahap beserta waktu pertama dan contoh bukti.
        $perRule = Detection::query()
            ->where('incident_id', $incident->id)
            ->with('rule')
            ->selectRaw('rule_id, count(*) as jumlah, min(detected_at) as pertama, max(detected_at) as terakhir, min(event_id) as event_pertama, max(attribution) as atribusi')
            ->groupBy('rule_id')
            ->get();

        $stages = collect(KillChainStage::cases())
            ->reject(fn ($s) => $s === KillChainStage::PertahananDilumpuhkan && ! $perRule->contains(fn ($r) => $r->rule->stage === $s))
            ->map(fn (KillChainStage $stage) => [
                'stage' => $stage,
                'rules' => $perRule->filter(fn ($r) => $r->rule->stage === $stage)->sortBy('pertama')->values(),
            ]);

        $evidence = Detection::query()
            ->where('incident_id', $incident->id)
            ->with(['rule', 'event', 'agent'])
            ->when($this->ruleFilter !== '', fn ($q) => $q->whereHas('rule', fn ($r) => $r->where('code', $this->ruleFilter)))
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.incidents.incident-show', [
            'inc' => $incident,
            'breakdown' => $breakdown,
            'stages' => $stages,
            'evidence' => $evidence,
            'timeline' => AuditLog::query()->with('user')->where('subject_type', 'Incident')->where('subject_id', $incident->id)->orderBy('id')->get(),
            'canUpdateStatus' => auth()->user()->can('updateStatus', $incident),
            'canPropose' => auth()->user()->can('proposeAction', $incident) && $incident->src_ip !== null,
            'actions' => ResponseAction::query()->with(['targetAgent', 'proposer', 'approver'])
                ->where('incident_id', $incident->id)->orderByDesc('id')->get(),
            'reports' => Report::query()->with('creator')->where('incident_id', $incident->id)->orderByDesc('id')->get(),
            'lapsitPending' => LapsitService::pending($incident),
            'canLapsit' => auth()->user()->can('kelola-lapsit'),
        ])->title('Insiden #'.$incident->id);
    }
}
