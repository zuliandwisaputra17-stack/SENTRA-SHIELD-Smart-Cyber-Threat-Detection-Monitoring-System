<div>
    <x-ui.page-header title="Antrean Persetujuan" icon="inbox" class="[&>div]:max-w-3xl">
        Two-person rule: usulan dari Operator/Analis, keputusan oleh Komandan. Setiap keputusan tercatat di audit log.
        @if ($pending->isNotEmpty())
            <x-slot:actions><x-ui.stempel tone="awas" size="sm">{{ $pending->count() }} usulan menunggu</x-ui.stempel></x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="mx-auto max-w-3xl space-y-4 px-4 py-5 sm:px-6">
        @if ($flash)
            <div class="flex items-start gap-2 rounded-control border border-aman/40 bg-aman/10 px-4 py-3 text-sm text-aman" role="status"><x-icon name="circle-check" class="mt-0.5 size-4" />{{ $flash }}</div>
        @endif

        @forelse ($pending as $group => $rows)
            @php
                $first = $rows->first();
                $inc = $first->incident;
                $tone = $inc ? $inc->severity->tone() : 'accent';
                $pita = ['pasir' => 'bg-pasir', 'waspada' => 'bg-waspada', 'siaga' => 'bg-siaga', 'awas' => 'bg-awas', 'accent' => 'bg-accent'][$tone];
            @endphp
            {{-- Lembar perintah menunggu keputusan. --}}
            <article class="panel overflow-hidden" wire:key="usulan-{{ $group }}" data-testid="usulan">
                <div class="h-1.5 {{ $pita }}" aria-hidden="true"></div>
                <div class="p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="flex items-baseline gap-2"><span class="nomor-bagian">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }} //</span>
                                <span class="font-stensil text-[15px] uppercase tracking-[0.1em] text-accent">Usulan {{ $first->type->label() }}{{ $first->campaign_id ? ' serentak · Kampanye #'.$first->campaign_id : '' }}</span></p>
                            <p class="mt-2 flex items-center gap-2.5">
                                <svg viewBox="0 0 24 24" class="size-7 shrink-0" aria-hidden="true"><path d="M12 1L23 12L12 23L1 12Z" stroke-width="2" class="fill-awas/15 stroke-awas" /><path d="M8 8l8 8M16 8l-8 8" stroke-width="2" class="stroke-awas" /></svg>
                                <span class="break-all font-mono text-[28px] font-semibold leading-tight text-ink sm:text-[34px]">{{ $first->params['ip'] ?? '—' }}</span>
                            </p>
                        </div>
                        @if ($inc)
                            <div class="flex shrink-0 flex-col items-end gap-2">
                                <span class="label-caps">Skor</span>
                                <span class="num text-[40px] {{ $inc->severity->textClass() }}">{{ $inc->score }}</span>
                                <x-severity-badge :severity="$inc->severity" />
                            </div>
                        @endif
                    </div>

                    <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 border-t border-dashed border-line-strong pt-4 text-sm sm:grid-cols-2">
                        @if ($first->campaign_id)
                            <div class="sm:col-span-2"><dt class="label-caps flex items-center gap-1.5"><x-icon name="network" class="size-3.5" />Satuan:</dt><dd class="mt-1 text-ink">{{ $rows->map(fn ($r) => $r->targetAgent->unit->name)->unique()->join(', ') }}</dd></div>
                        @elseif ($inc)
                            <div class="sm:col-span-2"><dt class="label-caps flex items-center gap-1.5"><x-icon name="siren" class="size-3.5" />Insiden:</dt><dd class="mt-1 min-w-0"><a href="{{ route('incidents.show', $inc) }}" wire:navigate class="link font-semibold">#{{ $inc->id }} {{ $inc->title }}</a></dd></div>
                        @endif
                        <div><dt class="label-caps flex items-center gap-1.5"><x-icon name="timer" class="size-3.5" />Durasi:</dt><dd class="mt-1 font-stensil text-lg uppercase tracking-[0.06em] text-ink">{{ \App\Response\ActionService::DURATIONS[$first->params['duration_s'] ?? 0] ?? (($first->params['duration_s'] ?? 0).' detik') }}</dd></div>
                        <div><dt class="label-caps flex items-center gap-1.5"><x-icon name="server" class="size-3.5" />Agent:</dt>
                            <dd class="mt-1 flex flex-wrap gap-1.5">
                                @foreach ($rows as $r)
                                    <x-ui.dogtag class="py-1"><span class="font-semibold">{{ $r->targetAgent->hostname }}</span></x-ui.dogtag>
                                @endforeach
                            </dd>
                        </div>
                        <div class="sm:col-span-2"><dt class="label-caps flex items-center gap-1.5"><x-icon name="user" class="size-3.5" />Diusulkan:</dt><dd class="mt-1 text-ink">{{ $first->proposer?->name }} ({{ $first->proposer?->role->label() }}), <span class="data">{{ waktu_wib($first->created_at, 'H:i:s') }}</span></dd></div>
                    </dl>

                    <label for="alasan-{{ $group }}" class="mt-4 block text-sm font-semibold text-ink-2">Alasan (wajib bila menolak)</label>
                    <textarea id="alasan-{{ $group }}" wire:model="note.{{ $group }}" rows="2" class="mt-1.5 block w-full text-sm"></textarea>
                    @error('keputusan.'.$group)
                        <p class="mt-1.5 flex items-center gap-1.5 text-sm text-awas" role="alert"><x-icon name="circle-alert" class="size-4" />{{ $message }}</p>
                    @enderror

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <x-ui.button variant="approve" size="lg" icon="check" wire:click="approve('{{ $group }}')" wire:loading.attr="disabled">Setujui</x-ui.button>
                        <x-ui.button variant="danger" size="lg" icon="x" wire:click="reject('{{ $group }}')" wire:loading.attr="disabled">Tolak</x-ui.button>
                    </div>
                </div>
            </article>
        @empty
            <div class="panel"><x-ui.empty icon="inbox" title="Tidak ada usulan yang menunggu keputusan.">Usulan blokir dari Operator atau Analis muncul di sini untuk diputuskan Komandan.</x-ui.empty></div>
        @endforelse

        @if ($decided->isNotEmpty())
            <x-ui.panel nomor="00" kode="Arsip" title="Keputusan terakhir" icon="history" :flush="true" class="mt-6">
                <ul class="text-sm">
                    @foreach ($decided as $rows)
                        @php $r = $rows->first(); @endphp
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-line/70 px-4 py-2.5 last:border-b-0 sm:px-5">
                            <span class="data font-semibold text-ink">{{ $r->type->value === 'unblock_ip' ? 'buka ' : '' }}{{ $r->params['ip'] ?? '' }}</span>
                            <x-action-status :status="$r->status" />
                            <span class="text-xs text-ink-3">{{ $rows->count() }} agent · {{ $r->approver?->name }} · {{ waktu_wib($r->approved_at, 'H:i') }}</span>
                            @if ($r->decision_note)
                                <span class="text-xs text-ink-2">“{{ $r->decision_note }}”</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.panel>
        @endif
    </div>
</div>
