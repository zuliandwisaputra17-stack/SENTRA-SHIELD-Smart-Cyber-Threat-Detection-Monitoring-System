<?php

namespace App\Livewire\Incidents;

use App\Enums\IncidentStatus;
use App\Enums\Severity;
use App\Models\Incident;
use App\Models\Unit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Daftar Insiden (PRD bagian 11): filter severity, status, satuan, IP.
 * Operator hanya melihat insiden satuannya.
 */
#[Layout('layouts.app')]
#[Title('Daftar Insiden')]
class IncidentList extends Component
{
    use WithPagination;

    #[Url]
    public string $severity = '';

    #[Url]
    public string $status = 'terbuka';

    #[Url]
    public string $unit = '';

    #[Url]
    public string $ip = '';

    public function mount(): void
    {
        $this->authorize('lihat-dashboard');
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['severity', 'status', 'unit', 'ip'], true)) {
            $this->resetPage();
        }
    }

    #[On('sentra-refresh')]
    public function refreshLive(): void {}

    public function render()
    {
        $user = auth()->user();
        $query = Incident::query()->visibleTo($user)->with('unit')->withCount('detections');

        if ($this->severity !== '' && Severity::tryFrom($this->severity)) {
            $query->where('severity', $this->severity);
        }
        if ($this->status === 'terbuka') {
            $query->open();
        } elseif (IncidentStatus::tryFrom($this->status)) {
            $query->where('status', $this->status);
        }
        if ($this->unit !== '' && ctype_digit($this->unit)) {
            $query->where('unit_id', (int) $this->unit);
        }
        if (trim($this->ip) !== '') {
            $query->where('src_ip', 'like', '%'.str_replace(['%', '_'], '', trim($this->ip)).'%');
        }

        $visible = $user->visibleUnitIds();

        return view('livewire.incidents.incident-list', [
            'incidents' => $query->orderByDesc('last_seen_at')->paginate(20),
            'units' => Unit::query()->when($visible !== null, fn ($q) => $q->whereIn('id', $visible))->orderBy('name')->get(),
        ]);
    }
}
