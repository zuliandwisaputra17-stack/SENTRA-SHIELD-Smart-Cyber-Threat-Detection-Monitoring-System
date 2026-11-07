@php
    $sevTeks = $inc->severity->textClass();
    $sevGaris = $inc->severity->fillClass();
    $ikonTahap = ['pengintaian' => 'scan-search', 'akses_awal' => 'key-round', 'persistensi' => 'terminal', 'dampak' => 'zap', 'pertahanan_dilumpuhkan' => 'eye-off'];
    $garisFase = ['ALFA', 'BRAVO', 'CHARLIE', 'DELTA', 'ECHO'];
    $angka = fn ($v) => str_replace('.', ',', (string) $v);
    // Naskah LAPSIT untuk pratinjau: judul, baris "Kunci : nilai", dan judul bab diberi gaya dokumen;
    // [EV-n] menjadi tautan ke event bukti. Isi tetap di-escape dulu.
    $naskah = function (string $isi): string {
        $h = e($isi);
        $h = preg_replace('/\A([^\n]+)/', '<span class="judul">$1</span>', $h);
        $h = preg_replace('/^([A-Z][a-z]+) +: /m', '<span class="kunci">$1</span>: ', $h);
        $h = preg_replace('/^(\d+\. [A-Z][A-Z ,\/()-]+)$/m', '<span class="bab">$1</span>', $h);

        return preg_replace_callback('/\[EV-(\d+)\]/', fn ($m) => '<a href="'.route('events.show', (int) $m[1]).'" class="ev-ref">[EV-'.$m[1].']</a>', $h);
    };
@endphp
<div>
    {{-- Kepala berkas insiden --}}
    <header class="relative border-b border-line bg-gradient-to-b from-panel/80 to-transparent">
        <div class="absolute inset-x-0 top-0 h-1 {{ $sevGaris }}" aria-hidden="true"></div>
        <span class="penggaris-x top-1 opacity-60" aria-hidden="true"></span>
        <div class="mx-auto flex max-w-[1600px] flex-wrap items-start justify-between gap-x-8 gap-y-4 px-4 pb-5 pt-6 sm:px-6 lg:px-8">
            <div class="min-w-0 flex-1 basis-[30rem]">
                <a href="{{ route('incidents.index') }}" wire:navigate class="mb-2 inline-flex min-h-6 items-center gap-1 text-sm font-semibold uppercase tracking-[0.1em] text-ink-3 transition-colors hover:text-accent">
                    <x-icon name="chevron-left" class="size-4" />Daftar Insiden
                </a>
                <p class="font-stensil text-[15px] uppercase tracking-[0.18em] text-accent" aria-hidden="true">Berkas insiden #{{ $inc->id }}</p>
                <h2 class="mt-1 break-words font-display text-[26px] font-bold uppercase leading-tight tracking-[0.04em] text-ink sm:text-[30px]"><span class="font-mono text-[0.75em] font-medium normal-case text-ink-3">#{{ $inc->id }}</span> {{ $inc->title }}</h2>
                <div class="mt-3 flex flex-wrap items-center gap-2.5">
                    <x-ui.dogtag><span class="font-semibold">{{ $inc->unit->code }}</span><span class="text-ink-3">{{ $inc->unit->name }}</span></x-ui.dogtag>
                    @if ($inc->src_ip)
                        <span class="inline-flex items-center gap-1.5 rounded-control border border-awas/40 bg-awas/10 px-2.5 py-1.5 text-sm text-ink-2"><x-icon name="crosshair" class="size-4 text-awas" />IP sumber <span class="data font-semibold text-ink">{{ $inc->src_ip }}</span></span>
                    @endif
                    <span class="inline-flex items-center gap-1.5 rounded-control border border-line bg-inset/60 px-2.5 py-1.5 text-sm text-ink-2"><x-icon name="clock" class="size-4 text-accent" /><span class="data" title="{{ waktu_wib($inc->first_seen_at, 'd/m/Y H:i:s') }} s.d. {{ waktu_wib($inc->last_seen_at, 'H:i:s') }}">{{ dtg($inc->first_seen_at) }} → {{ waktu_wib($inc->last_seen_at, 'H:i:s') }}</span></span>
                </div>
                @if ($inc->campaign)
                    <p class="mt-3">
                        @can('lihat-kampanye')
                            <a href="{{ route('campaigns.show', $inc->campaign) }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-chip bg-awas/10 px-2 py-1 text-xs font-bold uppercase tracking-[0.08em] text-awas ring-1 ring-inset ring-awas/50 transition-colors hover:bg-awas/20"><x-icon name="network" class="size-3.5" />Bagian dari Kampanye #{{ $inc->campaign->id }} · {{ $inc->campaign->unit_count }} satuan · skor ×1,3</a>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-chip bg-awas/10 px-2 py-1 text-xs font-bold uppercase tracking-[0.08em] text-awas ring-1 ring-inset ring-awas/50"><x-icon name="network" class="size-3.5" />Bagian dari serangan lintas satuan · skor ×1,3</span>
                        @endcan
                    </p>
                @endif
            </div>
            <div class="flex flex-wrap items-stretch gap-3">
                {{-- Angka terpenting halaman ini: skor risiko, di atas pelat. --}}
                <div class="panel flex items-center gap-5 px-5 py-4">
                    <div>
                        <p class="label-caps">Skor risiko</p>
                        <p class="num mt-1.5 text-[60px] {{ $sevTeks }}" data-testid="skor">{{ $inc->score }}</p>
                    </div>
                    <div class="flex flex-col items-start gap-3">
                        <x-ui.stempel :tone="$inc->severity->tone()" size="md">{{ $inc->severity->label() }}</x-ui.stempel>
                        <x-severity-badge :severity="$inc->severity" />
                    </div>
                </div>
                <div class="panel flex flex-col justify-center px-4 py-3">
                    <label for="status" class="label-caps">Status</label>
                    @if ($canUpdateStatus)
                        <select id="status" wire:change="setStatus($event.target.value)" class="mt-2 h-10 min-w-40 text-sm">
                            @foreach (\App\Enums\IncidentStatus::cases() as $s)
                                <option value="{{ $s->value }}" @selected($inc->status === $s)>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    @else
                        <div class="mt-2 font-stensil text-lg uppercase tracking-[0.08em] text-ink">{{ $inc->status->label() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </header>

    <div class="mx-auto max-w-[1600px] px-4 py-5 sm:px-6 lg:px-8">
        {{-- Layar lebar: alur operasi selebar penuh; di bawahnya kiri (bukti, kronologi, LAPSIT) dan
             kanan (respons, perhitungan skor). HP: pembungkus memakai display: contents agar panel bisa
             diurutkan (respons, alur operasi, skor, bukti, kronologi, LAPSIT). --}}
        <div class="grid gap-4 xl:grid-cols-12 xl:items-start">
                {{-- 01 // ALUR OPERASI: Attack Story sebagai fase bertahap dengan garis fase (PL). --}}
                <x-ui.panel nomor="01" kode="Alur operasi" title="Attack Story" icon="workflow" heading-id="attack-story" class="order-2 xl:order-none xl:col-span-12" :penggaris="true"
                    subtitle="Alur serangan menurut tahap kill chain, dengan teknik MITRE ATT&CK dan event buktinya.">
                    @php $daftarTahap = $stages->values(); @endphp
                    <ol class="grid gap-3 lg:grid-cols-[repeat(var(--n),minmax(0,1fr))] lg:gap-2" style="--n: {{ $daftarTahap->count() }}">
                        @foreach ($daftarTahap as $i => $item)
                            @php $reached = $item['rules']->isNotEmpty(); @endphp
                            <li class="min-w-0">
                                <p class="mb-1 flex items-center gap-1.5 font-mono text-[11px] text-ink-3" aria-hidden="true">
                                    <span class="h-3 w-0.5 bg-accent/70"></span>PL {{ $garisFase[$i] ?? '' }}
                                </p>
                                {{-- Chevron fase --}}
                                <div @class([
                                    'flex min-h-14 items-center gap-2.5 py-2 pe-6 ps-3',
                                    'bg-accent text-accent-ink' => $reached,
                                    'bg-inset text-ink-3' => ! $reached,
                                ]) style="clip-path: polygon(0 0, calc(100% - 16px) 0, 100% 50%, calc(100% - 16px) 100%, 0 100%{{ $i > 0 ? ', 12px 50%' : '' }}); {{ $i > 0 ? 'padding-left: 1.4rem;' : '' }}">
                                    <x-icon :name="$ikonTahap[$item['stage']->value] ?? 'target'" class="size-5 shrink-0" />
                                    <span class="min-w-0">
                                        <span class="block text-[11px] font-bold uppercase tracking-[0.14em] opacity-80">Tahap {{ $item['stage']->order() }}</span>
                                        <h4 class="font-stensil text-[15px] uppercase leading-tight tracking-[0.06em] [overflow-wrap:anywhere]">{{ $item['stage']->label() }}</h4>
                                    </span>
                                </div>
                                <div class="mt-2.5 space-y-2">
                                    @if (! $reached)
                                        <p class="rounded-control border border-dashed border-line-strong px-3 py-2.5 text-center text-sm font-semibold uppercase tracking-[0.1em] text-ink-3">Belum tercapai</p>
                                    @endif
                                    @foreach ($item['rules'] as $r)
                                        <div class="relative rounded-control border border-line bg-raised/60 p-3 pl-3.5">
                                            <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-control bg-accent/70" aria-hidden="true"></span>
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="chip text-ink">{{ $r->rule->code }}</span>
                                                <span class="font-mono text-xs text-ink-3">{{ waktu_wib(\Illuminate\Support\Carbon::parse($r->pertama), 'H:i:s') }}</span>
                                            </div>
                                            <p class="mt-2 font-semibold leading-snug text-ink">{{ $r->rule->name }}</p>
                                            @if ($r->rule->attack_id)
                                                <p class="mt-1.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-ink-2">
                                                    <span class="chip border-accent/40 bg-accent/10 text-accent"><x-icon name="crosshair" class="size-3" />{{ $r->rule->attack_id }}</span>
                                                    <span class="break-words">{{ $r->rule->attack_name }}</span>
                                                </p>
                                            @endif
                                            <p class="mt-2 break-words text-xs text-ink-3">
                                                <button wire:click="filterRule('{{ $r->rule->code }}')" class="link font-semibold">{{ $r->jumlah }} deteksi</button> ·
                                                @if ($r->event_pertama)
                                                    bukti pertama <a href="{{ route('events.show', $r->event_pertama) }}" class="link whitespace-nowrap font-mono">EV-{{ $r->event_pertama }}</a>
                                                @else
                                                    dideteksi Core dari heartbeat yang hilang
                                                @endif
                                            </p>
                                            @if ($r->atribusi === 'waktu' && $inc->src_ip)
                                                <p class="mt-2"><x-ui.badge tone="waspada" icon="clock" class="!whitespace-normal" title="Event FIM tidak memuat IP; dikaitkan ke insiden yang aktif pada waktu yang sama">Atribusi berdasarkan waktu</x-ui.badge></p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </x-ui.panel>

            <div class="contents xl:col-span-8 xl:block xl:min-w-0 xl:space-y-4">
                {{-- 04 // BUKTI --}}
                <x-ui.panel nomor="04" kode="Bukti" title="Event bukti" icon="fingerprint" heading-id="bukti" :flush="true" class="order-4 xl:order-none">
                    @if ($ruleFilter !== '')
                        <x-slot:actions>
                            <button wire:click="filterRule('{{ $ruleFilter }}')" class="inline-flex items-center gap-1.5 rounded-chip bg-accent/10 px-2 py-1 text-xs font-bold uppercase tracking-[0.08em] text-accent ring-1 ring-inset ring-accent/40 hover:bg-accent/20"><x-icon name="filter" class="size-3.5" />Hanya {{ $ruleFilter }} · tampilkan semua</button>
                        </x-slot:actions>
                    @endif
                    <x-ui.table caption="Event bukti insiden">
                        <x-slot:head>
                            <tr><th>Waktu</th><th>Bukti</th><th>Aturan</th><th>Sumber</th><th>Ringkasan</th></tr>
                        </x-slot:head>
                        @foreach ($evidence as $d)
                            <tr wire:key="det-{{ $d->id }}">
                                <td class="whitespace-nowrap font-mono text-xs text-ink-3">{{ waktu_wib($d->event?->occurred_at ?? $d->detected_at, 'H:i:s') }}</td>
                                <td class="whitespace-nowrap">@if ($d->event_id)<a href="{{ route('events.show', $d->event_id) }}" class="link font-mono text-xs">EV-{{ $d->event_id }}</a>@else<span class="text-xs text-ink-3">Core</span>@endif</td>
                                <td class="whitespace-nowrap text-ink-2"><span class="chip text-ink">{{ $d->rule->code }}</span> {{ $d->rule->name }}</td>
                                <td class="whitespace-nowrap text-ink-3">{{ $d->event?->source->label() ?? 'Heartbeat' }}</td>
                                <td class="min-w-[16rem] text-ink-2"><span class="line-clamp-2 break-all font-mono text-xs" title="{{ $d->event?->summary() }}">{{ $d->event?->summary() ?? 'Heartbeat agent '.($d->agent?->hostname ?? '').' hilang lebih dari ambang' }}</span></td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                    @if ($evidence->hasPages())
                        <div class="border-t border-line px-4 py-3 sm:px-5">{{ $evidence->links() }}</div>
                    @endif
                </x-ui.panel>

                {{-- 05 // KRONOLOGI (audit log insiden) --}}
                @if ($timeline->isNotEmpty())
                    <x-ui.panel nomor="05" kode="Kronologi" title="Garis waktu" icon="history" heading-id="garis-waktu" class="order-5 xl:order-none">
                        <ol class="relative space-y-3 text-sm before:absolute before:inset-y-2 before:left-[6.75rem] before:w-px before:bg-accent/40">
                            @foreach ($timeline as $log)
                                <li class="grid grid-cols-[6.25rem_minmax(0,1fr)] gap-x-4" wire:key="tl-{{ $log->id }}">
                                    <span class="whitespace-nowrap pt-0.5 font-mono text-xs text-ink-3">{{ waktu_wib($log->created_at, 'H:i:s') }}</span>
                                    <span class="relative text-ink-2"><span class="absolute -left-[0.75rem] top-[0.45rem] size-2.5 -translate-x-1/2 rotate-45 bg-accent ring-2 ring-panel" aria-hidden="true"></span>{{ \App\Support\TimelineText::describe($log) }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </x-ui.panel>
                @endif

                {{-- 06 // LAPSIT (FR-12) --}}
                <section class="panel order-6 min-w-0 xl:order-none" aria-labelledby="lapsit" @if ($lapsitPending) wire:poll.3s @endif>
                    <header class="panel-head">
                        <div class="min-w-0">
                            <p class="flex items-baseline gap-2" aria-hidden="true"><span class="nomor-bagian">06 //</span><span class="font-stensil text-[19px] uppercase leading-none tracking-[0.08em] text-ink">Laporan</span></p>
                            <h3 id="lapsit" class="mt-1 flex items-center gap-1.5 text-sm font-semibold text-ink-2"><x-icon name="scroll-text" class="size-4 text-accent" />LAPSIT</h3>
                            <p class="mt-0.5 max-w-2xl text-xs text-ink-3">Laporan Situasi disusun LLM lokal dari fakta insiden (setiap temuan mengutip event bukti), atau dari template bila LLM tidak tersedia.</p>
                        </div>
                        @if ($canLapsit)
                            <x-primary-button type="button" wire:click="createReport" wire:loading.attr="disabled" wire:target="createReport" :disabled="$lapsitPending"><x-icon name="file-text" class="size-4" />Buat LAPSIT</x-primary-button>
                        @endif
                    </header>
                    <div class="p-4 sm:p-5">
                        @if ($lapsitPending)
                            <div class="mb-4 flex items-center gap-2 rounded-control border border-accent/40 bg-accent/10 px-3 py-2 text-sm text-accent" role="status" data-testid="lapsit-disusun">
                                <x-icon name="loader-circle" class="size-4 animate-spin" />
                                Menyusun LAPSIT… paling lama {{ \App\Reports\OllamaWriter::timeout() }} detik; bila LLM tidak menjawab, template dipakai.
                            </div>
                        @endif
                        @error('lapsit')
                            <div class="mb-4 flex items-start gap-2 rounded-control border border-awas/50 bg-awas/10 px-3 py-2 text-sm text-awas" role="alert"><x-icon name="circle-alert" class="mt-0.5 size-4" />{{ $message }}</div>
                        @enderror

                        @if ($reports->isEmpty())
                            @unless ($lapsitPending)
                                <p class="text-sm text-ink-3">Belum ada LAPSIT untuk insiden ini.</p>
                            @endunless
                        @else
                            <ul class="space-y-5">
                                @foreach ($reports as $r)
                                    @php $llm = $r->generator === \App\Enums\ReportGenerator::Llm; @endphp
                                    <li wire:key="lapsit-{{ $r->id }}">
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                            <span class="font-mono text-sm font-semibold text-ink">{{ $r->number() }}</span>
                                            @if ($llm)
                                                <x-ui.badge tone="accent" icon="cpu">LLM lokal · draf</x-ui.badge>
                                            @else
                                                <x-ui.badge tone="neutral" icon="file-text">Template</x-ui.badge>
                                            @endif
                                            <span class="text-xs text-ink-3">{{ waktu_wib($r->created_at, 'd/m/Y H:i:s') }} · {{ $r->creator?->name ?? 'Sistem' }}</span>
                                            @if ($canLapsit && $r->pdf_path)
                                                <x-ui.button variant="secondary" size="sm" icon="download" :href="route('reports.pdf', $r)" class="ms-auto">Unduh PDF</x-ui.button>
                                            @endif
                                        </div>
                                        <div class="mt-1 break-all font-mono text-[11px] text-ink-3">SHA-256 PDF: {{ $r->sha256 ?? '—' }}</div>
                                        <details class="group mt-2" @if ($loop->first) open @endif>
                                            <summary class="inline-flex cursor-pointer select-none items-center gap-1 text-sm font-semibold uppercase tracking-[0.08em] text-ink-3 hover:text-accent">
                                                <x-icon name="chevron-right" class="size-4 transition-transform group-open:rotate-90" />Baca LAPSIT
                                            </summary>
                                            {{-- Pratinjau bergaya dokumen operasi: kop, garis ganda, cap jenis penyusun. --}}
                                            <div class="dokumen relative mt-3 max-w-3xl rounded-[2px] shadow-overlay">
                                                <div class="border-b-4 border-double border-[#555E3B] px-5 pb-3 pt-4 sm:px-8">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <span class="flex items-center gap-2.5">
                                                            <x-application-logo class="size-9 text-[#555E3B]" />
                                                            <span class="leading-tight">
                                                                <span class="block font-stensil text-[15px] tracking-[0.12em] text-[#1A1E12]">SENTRA SHIELD</span>
                                                                <span class="block text-[11px] font-semibold uppercase tracking-[0.18em] text-[#555E3B]">Pusat Komando Siber · Laporan Situasi</span>
                                                            </span>
                                                        </span>
                                                        <span class="text-right font-mono text-[11px] leading-snug text-[#555E3B]">{{ $r->number() }}<br>{{ dtg($r->created_at) }}</span>
                                                    </div>
                                                </div>
                                                <span class="stempel pointer-events-none absolute right-6 top-[9.5rem] text-[15px] text-[#8A3B2B] opacity-90 sm:right-10" aria-hidden="true">{{ $llm ? 'Draf LLM' : 'Template' }}</span>
                                                <pre class="max-h-[36rem] overflow-auto whitespace-pre-wrap break-words px-5 py-5 font-sans text-[15px] leading-relaxed sm:px-8">{!! $naskah($r->content) !!}</pre>
                                                <div class="break-all border-t border-[#555E3B]/40 px-5 py-2.5 font-mono text-[11px] text-[#555E3B] sm:px-8">SHA-256 isi (tercetak di kaki halaman PDF): {{ $r->contentHash() }}</div>
                                            </div>
                                        </details>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </section>
            </div>
            <div class="contents xl:col-span-4 xl:block xl:space-y-4">
                {{-- 02 // RESPONS: tindakan berjenjang --}}
                <section class="panel relative order-1 min-w-0 xl:order-none" aria-labelledby="tindakan">
                    <div class="absolute inset-x-0 top-0 h-1 bg-accent" aria-hidden="true"></div>
                    <header class="panel-head">
                        <div class="min-w-0">
                            <p class="flex items-baseline gap-2" aria-hidden="true"><span class="nomor-bagian">02 //</span><span class="font-stensil text-[19px] uppercase leading-none tracking-[0.08em] text-ink">Respons</span></p>
                            <h3 id="tindakan" class="mt-1 flex items-center gap-1.5 text-sm font-semibold text-ink-2"><x-icon name="shield-ban" class="size-4 text-accent" />Tindakan respons</h3>
                        </div>
                    </header>
                    <div class="p-4 sm:p-5">
                        <p class="text-sm text-ink-3">Operator/Analis mengusulkan, Komandan menyetujui, agent mengeksekusi di firewall host.</p>

                        @if ($flash)
                            <div class="mt-3 flex items-start gap-2 rounded-control border border-aman/40 bg-aman/10 px-3 py-2 text-sm text-aman" role="status"><x-icon name="circle-check" class="mt-0.5 size-4" />{{ $flash }}</div>
                        @endif
                        @error('blokir')
                            <div class="mt-3 flex items-start gap-2 rounded-control border border-awas/50 bg-awas/10 px-3 py-2 text-sm text-awas" role="alert"><x-icon name="circle-alert" class="mt-0.5 size-4" />{{ $message }}</div>
                        @enderror

                        @if ($canPropose)
                            <form wire:submit="proposeBlock" class="mt-4 space-y-3">
                                <div>
                                    <label for="durasi" class="block text-sm text-ink-2">Blokir IP <span class="data font-semibold text-ink">{{ $inc->src_ip }}</span> di semua agent {{ $inc->unit->name }} selama</label>
                                    <select id="durasi" wire:model="duration" class="mt-1.5 h-11 w-full text-sm">
                                        @foreach (\App\Response\ActionService::DURATIONS as $seconds => $label)
                                            <option value="{{ $seconds }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <x-primary-button class="h-12 w-full text-base"><x-icon name="shield-ban" class="size-5" />Usulkan blokir IP</x-primary-button>
                            </form>
                        @endif

                        @if ($actions->isNotEmpty())
                            <ul class="mt-4 space-y-2">
                                @foreach ($actions as $a)
                                    <li wire:key="tindakan-{{ $a->id }}" class="rounded-control border border-line bg-raised/50 p-3 text-sm">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="font-semibold uppercase tracking-[0.06em] text-ink">{{ $a->type->label() }} <span class="data normal-case">{{ $a->params['ip'] ?? '' }}</span></span>
                                            <x-action-status :status="$a->status" />
                                        </div>
                                        <p class="mt-2 flex flex-wrap items-center gap-2 text-ink-2">
                                            <x-ui.dogtag class="py-1"><span class="font-semibold">{{ $a->targetAgent->hostname }}</span></x-ui.dogtag>
                                            @if ($a->expires_at && $a->status === \App\Enums\ActionStatus::Dieksekusi)
                                                <span class="font-mono text-xs text-ink-3">s.d. {{ waktu_wib($a->expires_at, 'H:i:s') }}</span>
                                            @endif
                                        </p>
                                        <p class="mt-1.5 text-xs text-ink-3">{{ $a->proposer?->name ?? 'Sistem' }} → {{ $a->approver?->name ?? '—' }}@if ($a->decision_note) · “{{ $a->decision_note }}”@endif</p>
                                        @if ($a->result['message'] ?? null)
                                            <p class="mt-1 font-mono text-xs text-ink-2">{{ $a->result['message'] }}</p>
                                        @endif
                                        @canany(['cancel', 'rollback'], $a)
                                            <div class="mt-2.5 flex flex-wrap gap-2">
                                                @can('cancel', $a)
                                                    <x-ui.button variant="secondary" size="sm" icon="x" wire:click="cancelProposal('{{ $a->group_uuid }}')">Batalkan usulan</x-ui.button>
                                                @endcan
                                                @can('rollback', $a)
                                                    <x-ui.button variant="secondary" size="sm" icon="unlink" class="text-waspada" wire:click="rollback({{ $a->id }})" wire:confirm="Buka blokir {{ $a->params['ip'] ?? '' }} di {{ $a->targetAgent->hostname }}?">Buka blokir</x-ui.button>
                                                @endcan
                                            </div>
                                        @endcanany
                                    </li>
                                @endforeach
                            </ul>
                            @can('putuskan-tindakan')
                                @if ($actions->contains(fn ($a) => $a->status === \App\Enums\ActionStatus::Diusulkan))
                                    <x-ui.button variant="secondary" icon="inbox" :href="route('approvals')" wire:navigate class="mt-3 w-full">Putuskan di Antrean Persetujuan</x-ui.button>
                                @endif
                            @endcan
                        @elseif (! $canPropose)
                            <p class="mt-3 text-sm text-ink-3">Belum ada tindakan untuk insiden ini.</p>
                        @endif
                    </div>
                </section>

                {{-- 03 // PERHITUNGAN: kontribusi tiap komponen sebagai batang, lalu pengali. --}}
                <x-ui.panel nomor="03" kode="Perhitungan" title="Rincian skor" icon="chart-column" heading-id="rincian-skor" class="order-3 xl:order-none">
                    <p class="font-mono text-xs text-ink-3">S = (Σ bobot aturan unik + 20·(T−1)) × m<sub>aset</sub> × m<sub>kampanye</sub></p>
                    @php
                        $komponen = collect($breakdown['aturan'] ?? [])->map(fn ($a) => ['kode' => $a['kode'], 'label' => $a['label'], 'poin' => (int) $a['poin']]);
                        if (($breakdown['tahap']['jumlah'] ?? 0) > 0) {
                            $komponen->push(['kode' => null, 'label' => $breakdown['tahap']['jumlah'].' tahap kill chain tercapai (20 × '.max(0, $breakdown['tahap']['jumlah'] - 1).')', 'poin' => (int) $breakdown['tahap']['poin']]);
                        }
                        $poinMaks = max(1, (int) $komponen->max('poin'));
                    @endphp
                    <ul class="mt-4 space-y-2.5 text-sm">
                        @foreach ($komponen as $k)
                            <li>
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="min-w-0 text-ink-2">@if ($k['kode'])<span class="font-mono text-xs text-ink-3">{{ $k['kode'] }}</span> @endif{{ $k['label'] }}</span>
                                    <span class="data shrink-0 font-semibold text-ink">+{{ $k['poin'] }}</span>
                                </div>
                                <div class="mt-1 h-1.5 rounded-sm bg-inset" aria-hidden="true">
                                    <div class="h-1.5 rounded-sm bg-[#9E8E58]" style="width: {{ round($k['poin'] / $poinMaks * 100, 1) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <dl class="mt-4 space-y-1.5 border-t border-line pt-3 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-ink-3">Subtotal</dt><dd class="data text-ink">{{ $breakdown['subtotal'] ?? 0 }}</dd></div>
                        @isset($breakdown['pengali_aset'])
                            <div class="flex justify-between gap-3"><dt class="text-ink-2">Aset: {{ $breakdown['pengali_aset']['label'] }}{{ $breakdown['pengali_aset']['host'] ? ' ('.$breakdown['pengali_aset']['host'].')' : '' }}</dt><dd class="data text-ink">× {{ $angka($breakdown['pengali_aset']['nilai']) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-2">Kampanye lintas satuan{{ $breakdown['pengali_kampanye']['aktif'] ? '' : ' (tidak ada)' }}</dt><dd class="data text-ink">× {{ $angka($breakdown['pengali_kampanye']['nilai']) }}</dd></div>
                        @endisset
                        <div class="flex items-baseline justify-between gap-3 border-t border-accent/40 pt-2"><dt class="font-bold uppercase tracking-[0.1em] text-ink">Total</dt><dd class="flex items-center gap-2"><span class="num text-3xl {{ $sevTeks }}">{{ $inc->score }}</span><span class="text-sm font-bold uppercase tracking-[0.08em] text-ink">· {{ $inc->severity->label() }}</span></dd></div>
                    </dl>
                    @isset($breakdown['rumus'])
                        <p class="mt-3 break-words rounded-control border border-line bg-canvas px-3 py-2 font-mono text-xs text-ink-2">{{ $breakdown['rumus'] }}</p>
                    @endisset
                    {{-- Skala severity dengan penanda posisi skor. --}}
                    @php
                        // Empat pita sama lebar (Rendah, Sedang, Tinggi, Kritis); posisi skor diinterpolasi di dalam pitanya.
                        $pita = [[0, 40, 'bg-pasir'], [40, 80, 'bg-waspada'], [80, 150, 'bg-siaga'], [150, 300, 'bg-awas']];
                        $ke = $inc->score >= 150 ? 3 : ($inc->score >= 80 ? 2 : ($inc->score >= 40 ? 1 : 0));
                        [$dari, $sampai] = $pita[$ke];
                        $posisi = min(98.5, max(1.5, ($ke + min(1, max(0, ($inc->score - $dari) / ($sampai - $dari)))) * 25));
                    @endphp
                    <div class="relative mt-4 pt-3" aria-hidden="true">
                        <div class="flex h-2 gap-0.5">
                            @foreach ($pita as $i => [, , $warna])
                                <span @class(['flex-1 rounded-sm', $warna, 'opacity-30' => $i !== $ke])></span>
                            @endforeach
                        </div>
                        <span class="absolute top-0 h-5 w-0.5 -translate-x-1/2 bg-ink" style="left: {{ round($posisi, 1) }}%"></span>
                    </div>
                    <p class="mt-2 grid grid-cols-4 text-xs text-ink-3"><span>Rendah 0–39</span><span>· Sedang 40–79</span><span>· Tinggi 80–149</span><span>· Kritis 150+</span></p>
                </x-ui.panel>
            </div>
        </div>
    </div>
</div>
