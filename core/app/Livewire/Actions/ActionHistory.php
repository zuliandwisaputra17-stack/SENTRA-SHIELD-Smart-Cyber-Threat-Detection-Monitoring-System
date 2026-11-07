<?php

namespace App\Livewire\Actions;

use App\Enums\ActionStatus;
use App\Models\ResponseAction;
use App\Response\ActionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Riwayat tindakan respons beserta hasil eksekusi agent. Tujuan klik dari
 * kartu "Blokir aktif" di Beranda (setiap angka bisa diklik ke datanya).
 */
#[Layout('layouts.app')]
#[Title('Tindakan')]
class ActionHistory extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('lihat-dashboard');
    }

    #[On('sentra-refresh')]
    public function refreshLive(): void {}

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $units = auth()->user()->visibleUnitIds();
        $query = $this->status === 'aktif'
            ? ActionService::activeBlocks()
            : ResponseAction::query()->when(ActionStatus::tryFrom($this->status), fn ($q, $s) => $q->where('status', $s));

        $actions = $query
            ->with(['targetAgent.unit', 'proposer', 'approver', 'incident'])
            ->when($units !== null, fn ($q) => $q->whereHas('targetAgent', fn ($a) => $a->whereIn('unit_id', $units)))
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.actions.action-history', ['actions' => $actions]);
    }
}
