<?php

namespace App\Livewire\Readiness;

use App\Enums\AgentStatus;
use App\Models\ReadinessRun;
use App\Models\Unit;
use App\Readiness\ReadinessService;
use App\Readiness\ScenarioCatalog;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Uji Kesiapan (PRD bagian 11): tombol Jalankan, matriks skenario x hasil,
 * cakupan, rata-rata waktu deteksi, riwayat. Analis & Admin menjalankan;
 * Demo hanya melihat.
 */
#[Layout('layouts.app')]
#[Title('Uji Kesiapan')]
class ReadinessPage extends Component
{
    public ?int $unitId = null;

    #[Url(as: 'run')]
    public ?int $runId = null;

    public function mount(): void
    {
        $this->authorize('lihat-uji-kesiapan');
        $this->unitId = Unit::query()->whereHas('agents', fn ($q) => $q->where('status', AgentStatus::Online))->orderBy('name')->value('id')
            ?? Unit::query()->orderBy('name')->value('id');
        $this->runId ??= ReadinessRun::query()->latest('id')->value('id');
    }

    #[On('sentra-refresh')]
    public function refreshLive(): void {}

    public function run(ReadinessService $readiness): void
    {
        $this->authorize('jalankan-uji-kesiapan');
        $this->resetErrorBag();
        $unit = Unit::query()->findOrFail($this->unitId);
        try {
            $this->runId = $readiness->start($unit, auth()->user())->id;
        } catch (ValidationException $e) {
            $this->addError('uji', collect($e->errors())->flatten()->first());
        }
    }

    public function show(int $id): void
    {
        $this->runId = $id;
    }

    public function render()
    {
        $run = $this->runId ? ReadinessRun::query()->with(['unit', 'starter', 'results.rule', 'results.agent'])->find($this->runId) : null;

        return view('livewire.readiness.readiness-page', [
            'units' => Unit::query()->withCount(['agents' => fn ($q) => $q->where('status', AgentStatus::Online)])->orderBy('name')->get(),
            'run' => $run,
            'results' => $run?->results->sortBy(fn ($r) => array_search($r->scenario, ScenarioCatalog::codes(), true))->values() ?? collect(),
            'history' => ReadinessRun::query()->with(['unit', 'starter'])->withCount(['results', 'results as detected_count' => fn ($q) => $q->where('detected', true)])->latest('id')->limit(15)->get(),
            'canRun' => auth()->user()->can('jalankan-uji-kesiapan'),
        ]);
    }
}
