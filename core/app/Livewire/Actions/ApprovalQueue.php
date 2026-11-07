<?php

namespace App\Livewire\Actions;

use App\Enums\ActionStatus;
use App\Models\ResponseAction;
use App\Response\ActionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Antrean Persetujuan (PRD bagian 11): Komandan menyetujui atau menolak
 * (dengan alasan) usulan tindakan. Dirancang untuk layar HP.
 */
#[Layout('layouts.app')]
#[Title('Antrean Persetujuan')]
class ApprovalQueue extends Component
{
    /** @var array<string, string> alasan per grup usulan */
    public array $note = [];

    public ?string $flash = null;

    public function mount(): void
    {
        $this->authorize('putuskan-tindakan');
    }

    #[On('sentra-refresh')]
    public function refreshLive(): void {}

    public function approve(string $group, ActionService $actions): void
    {
        $this->decide($group, true, $actions);
    }

    public function reject(string $group, ActionService $actions): void
    {
        $this->decide($group, false, $actions);
    }

    private function decide(string $group, bool $approve, ActionService $actions): void
    {
        $this->authorize('putuskan-tindakan');
        $this->resetErrorBag();
        try {
            $count = $actions->decide($group, auth()->user(), $approve, $this->note[$group] ?? null);
        } catch (ValidationException $e) {
            $this->addError('keputusan.'.$group, collect($e->errors())->flatten()->first());

            return;
        } catch (AuthorizationException $e) {
            $this->addError('keputusan.'.$group, $e->getMessage());

            return;
        }
        unset($this->note[$group]);
        $this->flash = $approve
            ? "Disetujui. Perintah dikirim ke {$count} agent; blokir aktif dalam beberapa detik."
            : 'Usulan ditolak.';
    }

    public function render()
    {
        $pending = ResponseAction::query()
            ->with(['incident.unit', 'targetAgent.unit', 'proposer'])
            ->where('status', ActionStatus::Diusulkan)
            ->orderBy('created_at')
            ->get()
            ->groupBy('group_uuid');

        $decided = ResponseAction::query()
            ->with(['targetAgent', 'approver'])
            ->whereNotNull('approved_at')
            ->latest('approved_at')
            ->limit(30)
            ->get()
            ->groupBy('group_uuid')
            ->take(8);

        return view('livewire.actions.approval-queue', ['pending' => $pending, 'decided' => $decided]);
    }
}
