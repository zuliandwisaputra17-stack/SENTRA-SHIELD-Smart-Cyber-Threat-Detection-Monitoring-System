<div>
    <x-ui.page-header title="Satuan & Agent" icon="server">
        Susunan satuan dan sensor (agent) yang mengirim telemetri ke pusat.
    </x-ui.page-header>

    <div class="mx-auto max-w-[1600px] space-y-4 px-4 py-5 sm:px-6 lg:px-8">
        @if ($newCode)
            <div class="panel border-aman/50 bg-aman/[0.07] p-4 sm:p-5" role="alert">
                <x-ui.stempel tone="aman" size="sm" class="float-right ms-3">Sekali pakai</x-ui.stempel>
                <p class="text-sm text-aman">Kode pendaftaran untuk <strong>{{ $newCodeHostname }}</strong>. Kode hanya ditampilkan sekali, sekali pakai, dan berlaku 24 jam.</p>
                <p class="mt-2 select-all font-mono text-2xl tracking-[0.18em] text-ink" data-testid="kode-pendaftaran">{{ $newCode }}</p>
                <button wire:click="dismissCode" class="mt-3 text-xs text-aman underline">Sudah dicatat, tutup</button>
            </div>
        @endif

        {{-- Agent --}}
        <section class="panel p-4 sm:p-6">
            <p class="flex items-baseline gap-2" aria-hidden="true"><span class="nomor-bagian">01 //</span><span class="font-stensil text-[19px] uppercase leading-none tracking-[0.08em] text-ink">Pos sensor</span></p>
            <h3 class="mt-1 text-sm font-semibold text-ink-2">Agent</h3>
            <p class="mt-1 text-xs text-ink-3">Satu host = satu agent. Heartbeat diharapkan tiap 10 detik.</p>

            <div class="mt-4 overflow-x-auto">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th class="py-2 pe-4">Host</th>
                            <th class="py-2 pe-4">Satuan</th>
                            <th class="py-2 pe-4">Status</th>
                            <th class="py-2 pe-4">Heartbeat terakhir</th>
                            <th class="py-2 pe-4">Sumber log</th>
                            <th class="py-2 pe-4">Jenis aset</th>
                            @if ($canManage)<th class="py-2">Aksi</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($agents as $agent)
                            <tr wire:key="agent-{{ $agent->id }}">
                                <td class="py-2 pe-4">
                                    <x-ui.dogtag><span class="font-semibold">{{ $agent->hostname }}</span><span class="text-ink-3">{{ $agent->ip ?? 'IP belum diketahui' }} · v{{ $agent->version ?? '—' }}</span></x-ui.dogtag>
                                </td>
                                <td class="py-2 pe-4 text-ink-2">{{ $agent->unit->name }}</td>
                                <td class="py-2 pe-4">
                                    <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-chip px-1.5 py-0.5 text-xs font-semibold uppercase tracking-[0.08em] ring-1 ring-inset {{ $agent->status->badgeClass() }}"><x-icon :name="['online' => 'radio', 'buta' => 'wifi-off', 'menunggu' => 'clock', 'dicabut' => 'ban'][$agent->status->value] ?? 'server'" class="size-3.5" />{{ $agent->status->label() }}</span>
                                </td>
                                <td class="py-2 pe-4 text-ink-2">
                                    @if ($agent->last_heartbeat_at)
                                        <div>{{ $agent->last_heartbeat_at->diffForHumans() }}</div>
                                        <div class="text-xs text-ink-3">{{ waktu_wib($agent->last_heartbeat_at) }}</div>
                                    @else
                                        <span class="text-ink-3">Belum pernah</span>
                                    @endif
                                </td>
                                <td class="py-2 pe-4">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse (collect($agent->sources ?? [])->reject(fn ($s) => ($s['status'] ?? '') === 'disabled') as $name => $source)
                                            <span title="{{ $source['detail'] ?? '' }}" class="rounded-chip px-1.5 py-0.5 font-mono text-xs ring-1 ring-inset {{ ($source['status'] ?? '') === 'ok' ? 'bg-aman/10 text-aman ring-aman/40' : 'bg-awas/15 text-awas ring-awas/50' }}">{{ $name }}: {{ $source['status'] ?? '?' }}</span>
                                        @empty
                                            <span class="text-xs text-ink-3">—</span>
                                        @endforelse
                                    </div>
                                    @foreach ($warnings->get($agent->id, []) as $w)
                                        <div class="mt-1 text-xs font-semibold text-waspada" title="{{ $w['detail'] }}">Peringatan: sumber {{ $w['source'] }} {{ $w['alasan'] }}</div>
                                    @endforeach
                                </td>
                                <td class="py-2 pe-4">
                                    @if ($canManage)
                                        <select wire:change="setAssetType({{ $agent->id }}, $event.target.value)" aria-label="Jenis aset {{ $agent->hostname }}" class="rounded-control border-line-control bg-canvas text-xs text-ink-2">
                                            @foreach (\App\Enums\AssetType::cases() as $type)
                                                <option value="{{ $type->value }}" @selected($agent->asset_type === $type)>{{ $type->label() }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="text-ink-2">{{ $agent->asset_type->label() }}</span>
                                    @endif
                                </td>
                                @if ($canManage)
                                    <td class="py-2 whitespace-nowrap">
                                        @if ($agent->status === \App\Enums\AgentStatus::Menunggu)
                                            <button wire:click="reissueCode({{ $agent->id }})" class="text-xs link">Buat kode baru</button>
                                        @endif
                                        @if ($agent->status !== \App\Enums\AgentStatus::Dicabut)
                                            <button wire:click="revokeAgent({{ $agent->id }})" wire:confirm="Cabut token agent {{ $agent->hostname }}? Agent langsung ditolak API." class="ms-2 text-xs text-awas hover:underline">Cabut token</button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-4 text-ink-3">Belum ada agent terdaftar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($canManage)
                <form wire:submit="createAgent" class="mt-6 grid gap-3 sm:grid-cols-4 sm:items-end border-t border-dashed border-line-strong pt-4">
                    <div>
                        <x-input-label for="agentUnitId" value="Satuan" />
                        <select wire:model="agentUnitId" id="agentUnitId" class="mt-1 block w-full rounded-control border-line-control bg-canvas text-ink-2 text-sm">
                            <option value="">Pilih satuan</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('agentUnitId')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="agentHostname" value="Hostname" />
                        <x-text-input wire:model="agentHostname" id="agentHostname" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('agentHostname')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="agentAssetType" value="Jenis aset" />
                        <select wire:model="agentAssetType" id="agentAssetType" class="mt-1 block w-full rounded-control border-line-control bg-canvas text-ink-2 text-sm">
                            @foreach (\App\Enums\AssetType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><x-primary-button>Tambah agent</x-primary-button></div>
                </form>
            @endif
        </section>

        {{-- Satuan --}}
        <section class="panel p-4 sm:p-6">
            <p class="flex items-baseline gap-2" aria-hidden="true"><span class="nomor-bagian">02 //</span><span class="font-stensil text-[19px] uppercase leading-none tracking-[0.08em] text-ink">Susunan satuan</span></p>
            <h3 class="mt-1 text-sm font-semibold text-ink-2">Hierarki satuan</h3>
            <ul class="mt-4 space-y-1">
                @foreach ($tree as $unit)
                    @include('livewire.admin.partials.unit-row', ['unit' => $unit, 'depth' => 0, 'canManage' => $canManage])
                @endforeach
            </ul>

            @if ($canManage)
                <form wire:submit="saveUnit" class="mt-6 grid gap-3 sm:grid-cols-5 sm:items-end border-t border-dashed border-line-strong pt-4">
                    <div>
                        <x-input-label for="unitName" value="Nama satuan" />
                        <x-text-input wire:model="unitName" id="unitName" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('unitName')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="unitCode" value="Kode (huruf besar)" />
                        <x-text-input wire:model="unitCode" id="unitCode" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('unitCode')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="unitLevel" value="Tingkat" />
                        <select wire:model="unitLevel" id="unitLevel" class="mt-1 block w-full rounded-control border-line-control bg-canvas text-ink-2 text-sm">
                            @foreach (\App\Enums\UnitLevel::cases() as $level)
                                <option value="{{ $level->value }}">{{ $level->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="unitParentId" value="Induk" />
                        <select wire:model="unitParentId" id="unitParentId" class="mt-1 block w-full rounded-control border-line-control bg-canvas text-ink-2 text-sm">
                            <option value="">— tanpa induk —</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('unitParentId')" class="mt-1" />
                    </div>
                    <div class="flex gap-2">
                        <x-primary-button>{{ $editingUnitId ? 'Simpan perubahan' : 'Tambah satuan' }}</x-primary-button>
                        @if ($editingUnitId)
                            <button type="button" wire:click="resetUnitForm" class="text-xs text-ink-3 underline">Batal</button>
                        @endif
                    </div>
                </form>
            @endif
        </section>
    </div>
</div>
