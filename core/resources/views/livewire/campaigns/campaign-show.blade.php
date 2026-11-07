@php
    // Garis waktu lintas satuan: satu lajur per satuan, batang = rentang insiden di satuan itu.
    $mulai = $c->first_seen_at->getTimestamp();
    $selesai = max($c->last_seen_at->getTimestamp(), $mulai + 1);
    $rentang = $selesai - $mulai;
    $posisi = fn ($t) => min(100, max(0, ($t->getTimestamp() - $mulai) / $rentang * 100));
    $eselon = ['kodam' => 'XX', 'korem' => 'X', 'kodim' => 'II'];
    $tik = collect(range(0, 4))->map(fn ($i) => \Illuminate\Support\Carbon::createFromTimestamp($mulai + $rentang * $i / 4));
@endphp
<div>
    <x-ui.page-header :back="route('campaigns.index')" back-label="Kampanye" icon="network"
        :title="new \Illuminate\Support\HtmlString('Kampanye #'.$c->id.': <span class=\'font-mono text-[0.85em]\'>'.e($c->src_ip).'</span> menyerang '.$c->unit_count.' satuan')">
        <span class="text-ink-2"><span class="data">{{ waktu_wib($c->first_seen_at, 'd/m/Y H:i:s') }} s.d. {{ waktu_wib($c->last_seen_at, 'H:i:s') }}</span> · status {{ $c->status->label() }} · skor setiap insiden tertaut dikalikan 1,3</span>
        <x-slot:actions>
            <x-ui.stempel :tone="$c->status === \App\Enums\CampaignStatus::Aktif ? 'awas' : 'pasir'" size="md">Kampanye {{ $c->status->label() }}</x-ui.stempel>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mx-auto max-w-[1600px] space-y-4 px-4 py-5 sm:px-6 lg:px-8">
        <x-ui.panel nomor="01" kode="Garis waktu operasi" :penggaris="true" title="Satuan terdampak menurut urutan waktu" icon="network" heading-id="urutan"
            subtitle="Satu lajur per satuan; batang menunjukkan rentang serangan di satuan itu, warnanya mengikuti severity insiden.">
            {{-- Sumbu waktu --}}
            <div class="hidden grid-cols-[15rem_minmax(0,1fr)] gap-x-4 sm:grid" aria-hidden="true">
                <span></span>
                <div class="relative h-5 font-mono text-[11px] text-ink-3">
                    @foreach ($tik as $i => $t)
                        <span @class(['absolute top-0 whitespace-nowrap', '-translate-x-1/2' => $i > 0 && $i < 4, '-translate-x-full' => $i === 4]) style="left: {{ $i * 25 }}%">{{ $t->setTimezone(config('sentra.display_timezone'))->format('H:i:s') }}</span>
                    @endforeach
                </div>
            </div>
            <ol class="space-y-3 sm:space-y-2">
                @foreach ($incidents as $i => $inc)
                    @php
                        $kiri = $posisi($inc->first_seen_at);
                        $lebar = max(1.2, $posisi($inc->last_seen_at) - $kiri);
                        $kiri = min($kiri, 100 - $lebar);
                    @endphp
                    <li class="grid gap-x-4 gap-y-2 sm:grid-cols-[15rem_minmax(0,1fr)] sm:items-center" wire:key="ki-{{ $inc->id }}">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="font-stensil text-lg text-accent" aria-hidden="true">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <x-ui.simbol-satuan :eselon="$eselon[$inc->unit->level->value] ?? ''" :tone="\App\Enums\AlertLevel::fromSeverity($inc->severity)->value" />
                            <div class="min-w-0">
                                <p class="truncate font-semibold uppercase tracking-[0.06em] text-ink">{{ $inc->unit->name }}</p>
                                <p class="mt-0.5 flex items-center gap-2"><x-severity-badge :severity="$inc->severity" /><span class="text-xs text-ink-2">skor {{ $inc->score }}</span></p>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <div class="grid-peta relative h-9 rounded-control bg-canvas ring-1 ring-inset ring-line" aria-hidden="true">
                                @foreach ([25, 50, 75] as $g)
                                    <span class="absolute inset-y-0 w-px bg-accent/30" style="left: {{ $g }}%"></span>
                                @endforeach
                                <span class="absolute inset-y-2 rounded-[2px] {{ $inc->severity->fillClass() }}" style="left: {{ round($kiri, 2) }}%; width: {{ round($lebar, 2) }}%"></span>
                            </div>
                            <p class="mt-1.5 text-xs text-ink-3">
                                Pertama <span class="data">{{ waktu_wib($inc->first_seen_at, 'H:i:s') }}</span> · terakhir <span class="data">{{ waktu_wib($inc->last_seen_at, 'H:i:s') }}</span> · tahap {{ $inc->stage_max?->label() ?? '—' }} ·
                                <a href="{{ route('incidents.show', $inc) }}" wire:navigate class="link">Insiden #{{ $inc->id }}</a>
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-ui.panel>

        <section class="panel relative min-w-0" aria-labelledby="serentak">
            <div class="absolute inset-x-0 top-0 h-1 bg-accent" aria-hidden="true"></div>
            <header class="panel-head">
                <div class="min-w-0">
                    <p class="flex items-baseline gap-2" aria-hidden="true"><span class="nomor-bagian">02 //</span><span class="font-stensil text-[19px] uppercase leading-none tracking-[0.08em] text-ink">Respons</span></p>
                    <h3 id="serentak" class="mt-1 flex items-center gap-1.5 text-sm font-semibold text-ink-2"><x-icon name="shield-ban" class="size-4 text-accent" />Blokir serentak</h3>
                </div>
            </header>
            <div class="p-4 sm:p-5">
                @if ($flash)
                    <div class="mb-3 flex items-start gap-2 rounded-control border border-aman/40 bg-aman/10 px-3 py-2 text-sm text-aman" role="status"><x-icon name="circle-check" class="mt-0.5 size-4" />{{ $flash }}</div>
                @endif
                @error('blokir')
                    <div class="mb-3 flex items-start gap-2 rounded-control border border-awas/50 bg-awas/10 px-3 py-2 text-sm text-awas" role="alert"><x-icon name="circle-alert" class="mt-0.5 size-4" />{{ $message }}</div>
                @enderror
                @if ($canPropose && $c->status === \App\Enums\CampaignStatus::Aktif)
                    <form wire:submit="proposeBlock" class="flex flex-wrap items-end gap-3">
                        <div class="min-w-0 flex-1 basis-72 sm:flex-none">
                            <label for="durasi" class="block text-sm text-ink-2">Blokir <span class="data font-medium text-ink">{{ $c->src_ip }}</span> di semua agent pada {{ $c->unit_count }} satuan selama</label>
                            <select id="durasi" wire:model="duration" class="mt-1.5 h-10 w-full text-sm sm:w-72">
                                @foreach (\App\Response\ActionService::DURATIONS as $seconds => $label)
                                    <option value="{{ $seconds }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-primary-button><x-icon name="shield-ban" class="size-4" />Usulkan blokir serentak</x-primary-button>
                    </form>
                @endif
                @if ($actions->isNotEmpty())
                    <ul class="mt-4 text-sm">
                        @foreach ($actions as $a)
                            <li class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-line/70 py-2.5 last:border-b-0" wire:key="ka-{{ $a->id }}">
                                <span class="font-medium text-ink">{{ $a->type->label() }} <span class="data">{{ $a->params['ip'] ?? '' }}</span></span>
                                <span class="text-ink-2"><span class="data">{{ $a->targetAgent->hostname }}</span> ({{ $a->targetAgent->unit->name }})</span>
                                <x-action-status :status="$a->status" />
                                <span class="font-mono text-xs text-ink-3">{{ $a->result['message'] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @elseif (! $canPropose)
                    <p class="text-sm text-ink-3">Belum ada tindakan untuk kampanye ini.</p>
                @endif
            </div>
        </section>
    </div>
</div>
