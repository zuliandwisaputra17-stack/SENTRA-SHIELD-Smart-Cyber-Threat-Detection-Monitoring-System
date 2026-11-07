<div>
    <x-ui.page-header title="Tindakan" icon="shield-ban">
        <span class="block max-w-3xl">Perintah ke agent hanya dari daftar tetap: blokir IP, buka blokir, jalankan uji.</span>
    </x-ui.page-header>

    <div class="mx-auto max-w-[1600px] space-y-4 px-4 py-5 sm:px-6 lg:px-8">
        {{-- 01 // SARINGAN --}}
        <div class="panel flex flex-wrap items-end gap-x-5 gap-y-3 p-4 sm:p-5">
            <p class="flex items-baseline gap-2 self-center" aria-hidden="true"><span class="nomor-bagian">01 //</span><span class="font-stensil text-[17px] uppercase leading-none tracking-[0.08em] text-ink">Saringan</span></p>
            <div class="w-full max-w-xs">
                <x-input-label for="f-status" value="Status" />
                <select wire:model.live="status" id="f-status" class="mt-1.5 block h-10 w-full text-sm">
                    <option value="">Semua</option>
                    <option value="aktif">Blokir aktif</option>
                    @foreach (\App\Enums\ActionStatus::cases() as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- 02 // LOG PERINTAH --}}
        <x-ui.panel nomor="02" kode="Log perintah" title="Perintah ke agent" icon="terminal" :flush="true">
            {{-- HP: kartu perintah --}}
            <ul class="md:hidden">
                @forelse ($actions as $a)
                    <li class="border-b border-line/70 px-4 py-3 last:border-b-0" wire:key="act-hp-{{ $a->id }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold uppercase tracking-[0.06em] text-ink"><span class="font-mono text-xs normal-case text-ink-3">#{{ $a->id }}</span> {{ $a->type->label() }} <span class="data normal-case">{{ $a->params['ip'] ?? '' }}</span></span>
                            <x-action-status :status="$a->status" />
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-ink-3">
                            <x-ui.dogtag class="py-1"><span class="font-semibold">{{ $a->targetAgent->hostname }}</span><span class="text-ink-3">{{ $a->targetAgent->unit->name }}</span></x-ui.dogtag>
                            @if ($a->incident)<a href="{{ route('incidents.show', $a->incident) }}" wire:navigate class="link">Insiden #{{ $a->incident_id }}</a>@endif
                        </div>
                        <p class="mt-1.5 text-xs text-ink-3">{{ $a->proposer?->name ?? 'Sistem' }} → {{ $a->approver?->name ?? '—' }}@if ($a->decision_note) · “{{ $a->decision_note }}”@endif</p>
                        @if ($a->result['message'] ?? null)<p class="mt-1 font-mono text-xs text-ink-2">{{ $a->result['message'] }}</p>@endif
                        @if ($a->expires_at)<p class="mt-1 font-mono text-xs text-ink-3">berlaku s.d. {{ waktu_wib($a->expires_at, 'd/m H:i:s') }}</p>@endif
                    </li>
                @empty
                    <li><x-ui.empty icon="terminal" title="Belum ada tindakan." /></li>
                @endforelse
            </ul>

            <div class="hidden md:block">
                <x-ui.table caption="Riwayat tindakan">
                    <x-slot:head>
                        <tr><th>#</th><th>Tindakan</th><th>Agent</th><th>Status</th><th>Pengusul / pemutus</th><th>Hasil agent</th><th>Berlaku sampai</th></tr>
                    </x-slot:head>
                    @forelse ($actions as $a)
                        <tr wire:key="act-{{ $a->id }}">
                            <td class="data text-ink-3">{{ $a->id }}</td>
                            <td>
                                <div class="font-semibold uppercase tracking-[0.05em] text-ink">{{ $a->type->label() }} <span class="data normal-case">{{ $a->params['ip'] ?? '' }}</span></div>
                                @if ($a->incident)
                                    <a href="{{ route('incidents.show', $a->incident) }}" wire:navigate class="link text-xs">Insiden #{{ $a->incident_id }}</a>
                                @endif
                            </td>
                            <td><x-ui.dogtag class="py-1"><span class="font-semibold">{{ $a->targetAgent->hostname }}</span><span class="text-ink-3">{{ $a->targetAgent->unit->name }}</span></x-ui.dogtag></td>
                            <td><x-action-status :status="$a->status" /></td>
                            <td class="text-xs text-ink-2">{{ $a->proposer?->name ?? 'Sistem' }}<br><span class="text-ink-3">{{ $a->approver?->name ?? '—' }}@if ($a->decision_note) · “{{ $a->decision_note }}”@endif</span></td>
                            <td class="text-xs text-ink-2"><span class="font-mono">{{ $a->result['message'] ?? '—' }}</span>@if ($a->executed_at)<div class="font-mono text-ink-3">{{ waktu_wib($a->executed_at, 'd/m H:i:s') }}</div>@endif</td>
                            <td class="data whitespace-nowrap text-xs text-ink-3">{{ $a->expires_at ? waktu_wib($a->expires_at, 'd/m H:i:s') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty icon="terminal" title="Belum ada tindakan." /></td></tr>
                    @endforelse
                </x-ui.table>
            </div>
        </x-ui.panel>
        {{ $actions->links() }}
    </div>
</div>
