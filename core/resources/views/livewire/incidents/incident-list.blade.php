<div>
    <x-ui.page-header title="Daftar Insiden" icon="siren">
        Register insiden dari mesin korelasi; klik insiden untuk membuka berkasnya.
    </x-ui.page-header>

    <div class="mx-auto max-w-[1600px] space-y-4 px-4 py-5 sm:px-6 lg:px-8">
        {{-- 01 // SARINGAN --}}
        <div class="panel grid gap-3 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-[auto_repeat(4,minmax(0,1fr))] lg:items-end">
            <p class="flex items-baseline gap-2 self-center sm:col-span-2 lg:col-span-1" aria-hidden="true"><span class="nomor-bagian">01 //</span><span class="font-stensil text-[17px] uppercase leading-none tracking-[0.08em] text-ink">Saringan</span></p>
            <div>
                <x-input-label for="f-severity" value="Severity" />
                <select wire:model.live="severity" id="f-severity" class="mt-1.5 block h-10 w-full text-sm">
                    <option value="">Semua</option>
                    @foreach (\App\Enums\Severity::cases() as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="f-status" value="Status" />
                <select wire:model.live="status" id="f-status" class="mt-1.5 block h-10 w-full text-sm">
                    <option value="terbuka">Terbuka (belum selesai)</option>
                    <option value="">Semua</option>
                    @foreach (\App\Enums\IncidentStatus::cases() as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="f-unit" value="Satuan" />
                <select wire:model.live="unit" id="f-unit" class="mt-1.5 block h-10 w-full text-sm">
                    <option value="">Semua</option>
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="f-ip" value="IP sumber" />
                <x-text-input wire:model.live.debounce.400ms="ip" id="f-ip" class="mt-1.5 block h-10 w-full font-mono" placeholder="contoh: 203.0.113.7" />
            </div>
        </div>

        {{-- 02 // REGISTER INSIDEN --}}
        <x-ui.panel nomor="02" kode="Register insiden" title="Insiden" icon="siren" :flush="true">
            {{-- HP: kartu berpita severity --}}
            <ul class="md:hidden">
                @forelse ($incidents as $incident)
                    <li class="relative border-b border-line/70 py-3 pl-5 pr-4 last:border-b-0" wire:key="inc-hp-{{ $incident->id }}">
                        <span class="absolute inset-y-0 left-0 w-1 {{ $incident->severity->fillClass() }}" aria-hidden="true"></span>
                        <div class="flex items-start justify-between gap-3">
                            <a href="{{ route('incidents.show', $incident) }}" wire:navigate class="link min-w-0 font-semibold">#{{ $incident->id }} {{ $incident->title }}</a>
                            <span class="num shrink-0 text-2xl {{ $incident->severity->textClass() }}">{{ $incident->score }}</span>
                        </div>
                        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-3">
                            <x-severity-badge :severity="$incident->severity" />
                            <span>{{ $incident->unit->name }}</span>
                            <span>{{ $incident->stage_max?->label() ?? '—' }}</span>
                            <span>{{ $incident->status->label() }}</span>
                            <span class="font-mono">{{ waktu_wib($incident->last_seen_at, 'd/m H:i:s') }}</span>
                        </div>
                    </li>
                @empty
                    <li><x-ui.empty icon="siren" title="Tidak ada insiden yang cocok dengan filter." /></li>
                @endforelse
            </ul>

            <div class="hidden md:block">
                <x-ui.table caption="Daftar insiden">
                    <x-slot:head>
                        <tr><th class="w-1 !px-0"><span class="sr-only">Severity</span></th><th>Insiden</th><th>Satuan</th><th>IP sumber</th><th>Tahap tertinggi</th><th>Skor</th><th>Status</th><th>Terakhir terlihat</th></tr>
                    </x-slot:head>
                    @forelse ($incidents as $incident)
                        <tr wire:key="inc-{{ $incident->id }}">
                            <td class="relative !p-0"><span class="absolute inset-y-0 left-0 w-1 {{ $incident->severity->fillClass() }}" aria-hidden="true"></span></td>
                            <td>
                                <a href="{{ route('incidents.show', $incident) }}" wire:navigate class="link font-semibold">#{{ $incident->id }} {{ $incident->title }}</a>
                                <div class="text-xs text-ink-3">{{ $incident->detections_count }} deteksi</div>
                            </td>
                            <td class="text-ink-2">{{ $incident->unit->name }}</td>
                            <td class="data text-ink-2">{{ $incident->src_ip ?? '—' }}</td>
                            <td class="text-ink-2">{{ $incident->stage_max?->label() ?? '—' }}</td>
                            <td class="whitespace-nowrap"><span class="num me-2 text-xl {{ $incident->severity->textClass() }}">{{ $incident->score }}</span><x-severity-badge :severity="$incident->severity" /></td>
                            <td class="text-ink-2">{{ $incident->status->label() }}</td>
                            <td class="data whitespace-nowrap text-ink-3">{{ waktu_wib($incident->last_seen_at, 'd/m H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-ui.empty icon="siren" title="Tidak ada insiden yang cocok dengan filter." /></td></tr>
                    @endforelse
                </x-ui.table>
            </div>
        </x-ui.panel>
        {{ $incidents->links() }}
    </div>
</div>
