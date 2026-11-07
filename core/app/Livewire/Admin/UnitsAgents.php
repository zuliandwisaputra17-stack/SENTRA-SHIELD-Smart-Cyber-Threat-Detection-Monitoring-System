<?php

namespace App\Livewire\Admin;

use App\Detection\SensorWatch;
use App\Enums\AgentStatus;
use App\Enums\AssetType;
use App\Enums\UnitLevel;
use App\Models\Agent;
use App\Models\Unit;
use App\Services\AgentEnrollment;
use App\Services\AuditLogger;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Halaman "Satuan dan Agent" (PRD bagian 11). Admin mengelola; Analis hanya melihat.
 */
#[Layout('layouts.app')]
#[Title('Satuan & Agent')]
class UnitsAgents extends Component
{
    // Form satuan
    public ?int $editingUnitId = null;

    public string $unitName = '';

    public string $unitCode = '';

    public string $unitLevel = 'kodim';

    public ?int $unitParentId = null;

    // Form agent
    public ?int $agentUnitId = null;

    public string $agentHostname = '';

    public string $agentAssetType = 'web';

    /** Kode pendaftaran yang baru dibuat; hanya tampil sekali. */
    public ?string $newCode = null;

    public ?string $newCodeHostname = null;

    public function mount(): void
    {
        $this->authorize('lihat-satuan-agent');
    }

    public function saveUnit(AuditLogger $audit): void
    {
        $this->authorize('kelola-sistem');

        $data = $this->validate([
            'unitName' => ['required', 'string', 'max:255'],
            'unitCode' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9-]+$/', Rule::unique('units', 'code')->ignore($this->editingUnitId)],
            'unitLevel' => ['required', Rule::enum(UnitLevel::class)],
            'unitParentId' => ['nullable', 'integer', 'exists:units,id', Rule::notIn(array_filter([$this->editingUnitId]))],
        ], [], ['unitName' => 'nama satuan', 'unitCode' => 'kode satuan', 'unitLevel' => 'tingkat', 'unitParentId' => 'induk']);

        if ($this->editingUnitId && $data['unitParentId'] && in_array($data['unitParentId'], Unit::subtreeIds($this->editingUnitId), true)) {
            $this->addError('unitParentId', 'Induk tidak boleh satuan di bawahnya sendiri.');

            return;
        }

        $attributes = [
            'name' => $data['unitName'],
            'code' => $data['unitCode'],
            'level' => $data['unitLevel'],
            'parent_id' => $data['unitParentId'],
        ];

        if ($this->editingUnitId) {
            $unit = Unit::findOrFail($this->editingUnitId);
            $unit->update($attributes);
            $audit->record('satuan_diubah', $unit, $attributes);
        } else {
            $unit = Unit::create($attributes);
            $audit->record('satuan_dibuat', $unit, $attributes);
        }

        $this->resetUnitForm();
    }

    public function editUnit(int $id): void
    {
        $this->authorize('kelola-sistem');
        $unit = Unit::findOrFail($id);
        $this->editingUnitId = $unit->id;
        $this->unitName = $unit->name;
        $this->unitCode = $unit->code;
        $this->unitLevel = $unit->level->value;
        $this->unitParentId = $unit->parent_id;
    }

    public function resetUnitForm(): void
    {
        $this->reset('editingUnitId', 'unitName', 'unitCode', 'unitLevel', 'unitParentId');
        $this->resetValidation();
    }

    public function createAgent(AgentEnrollment $enrollment, AuditLogger $audit): void
    {
        $this->authorize('kelola-sistem');

        $data = $this->validate([
            'agentUnitId' => ['required', 'integer', 'exists:units,id'],
            'agentHostname' => ['required', 'string', 'max:255'],
            'agentAssetType' => ['required', Rule::enum(AssetType::class)],
        ], [], ['agentUnitId' => 'satuan', 'agentHostname' => 'hostname', 'agentAssetType' => 'jenis aset']);

        $agent = Agent::create([
            'unit_id' => $data['agentUnitId'],
            'hostname' => $data['agentHostname'],
            'asset_type' => $data['agentAssetType'],
            'status' => AgentStatus::Menunggu,
        ]);
        $audit->record('agent_dibuat', $agent, ['hostname' => $agent->hostname, 'unit_id' => $agent->unit_id, 'asset_type' => $agent->asset_type->value]);

        $this->newCode = $enrollment->issueCode($agent);
        $this->newCodeHostname = $agent->hostname;
        $this->reset('agentHostname');
    }

    public function reissueCode(int $id, AgentEnrollment $enrollment): void
    {
        $this->authorize('kelola-sistem');
        $agent = Agent::findOrFail($id);
        if ($agent->status !== AgentStatus::Menunggu) {
            return;
        }

        $this->newCode = $enrollment->issueCode($agent);
        $this->newCodeHostname = $agent->hostname;
    }

    public function revokeAgent(int $id, AgentEnrollment $enrollment): void
    {
        $this->authorize('kelola-sistem');
        $agent = Agent::findOrFail($id);
        if ($agent->status !== AgentStatus::Dicabut) {
            $enrollment->revoke($agent);
        }
    }

    public function setAssetType(int $id, string $type, AuditLogger $audit): void
    {
        $this->authorize('kelola-sistem');
        $assetType = AssetType::tryFrom($type);
        if ($assetType === null) {
            return;
        }

        $agent = Agent::findOrFail($id);
        $agent->update(['asset_type' => $assetType]);
        $audit->record('agent_diubah', $agent, ['asset_type' => $assetType->value]);
    }

    public function dismissCode(): void
    {
        $this->reset('newCode', 'newCodeHostname');
    }

    #[On('sentra-refresh')]
    public function refreshLive(): void {}

    public function render()
    {
        return view('livewire.admin.units-agents', [
            'tree' => Unit::tree(),
            'units' => Unit::query()->orderBy('name')->get(),
            'agents' => Agent::query()->with('unit')->orderBy('unit_id')->orderBy('hostname')->get(),
            'canManage' => auth()->user()->can('kelola-sistem'),
            'warnings' => app(SensorWatch::class)->sourceWarnings()->groupBy(fn ($w) => $w['agent']->id),
        ]);
    }
}
