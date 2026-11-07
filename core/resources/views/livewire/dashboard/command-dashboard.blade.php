@php
    use App\Enums\AlertLevel;
    use App\Enums\Severity;

    // Tingkat Siaga keseluruhan = tertinggi di pohon satuan yang boleh dilihat (sama dengan aturan satuan induk).
    $tingkat = $tree->isEmpty() ? null : AlertLevel::highest(...$tree->map->effectiveAlertLevel()->all());
    $semua = collect();
    $kedalaman = [];
    $kumpulkan = function ($units, $d) use (&$kumpulkan, $semua, &$kedalaman) {
        foreach ($units as $u) {
            $semua->push($u);
            $kedalaman[$u->id] = $d;
            $kumpulkan($u->children, $d + 1);
        }
    };
    $kumpulkan($tree, 0);
    $sumber = $tingkat && $tingkat !== AlertLevel::Aman ? $semua->filter(fn ($u) => $u->alert_level === $tingkat)->pluck('name') : collect();

    $papan = [
        'aman' => ['ikon' => 'shield-check', 'teks' => 'text-aman', 'garis' => 'bg-aman', 'kotak' => 'border-aman/60 bg-aman/10 text-aman', 'nyala' => 'bg-aman text-accent-ink shadow-[0_0_24px_-4px_rgb(var(--aman)/0.75)]', 'lampu' => 'bg-aman/40'],
        'waspada' => ['ikon' => 'eye', 'teks' => 'text-waspada', 'garis' => 'bg-waspada', 'kotak' => 'border-waspada/60 bg-waspada/10 text-waspada', 'nyala' => 'bg-waspada text-accent-ink shadow-[0_0_24px_-4px_rgb(var(--waspada)/0.75)]', 'lampu' => 'bg-waspada/40'],
        'siaga' => ['ikon' => 'triangle-alert', 'teks' => 'text-siaga', 'garis' => 'bg-siaga', 'kotak' => 'border-siaga/60 bg-siaga/10 text-siaga', 'nyala' => 'bg-siaga text-accent-ink shadow-[0_0_24px_-4px_rgb(var(--siaga)/0.75)]', 'lampu' => 'bg-siaga/40'],
        'awas' => ['ikon' => 'siren', 'teks' => 'text-awas', 'garis' => 'bg-awas', 'kotak' => 'border-awas/60 bg-awas/10 text-awas', 'nyala' => 'bg-awas text-accent-ink shadow-[0_0_24px_-4px_rgb(var(--awas)/0.75)]', 'lampu' => 'bg-awas/40'],
    ];
    // Ukuran kata status mengikuti panjang kata agar muat di proyektor 1280 px dan HP 390 px.
    $ukuranKata = ['aman' => 'text-[60px] sm:text-[80px] 2xl:text-[92px]', 'awas' => 'text-[60px] sm:text-[80px] 2xl:text-[92px]', 'siaga' => 'text-[52px] sm:text-[70px] 2xl:text-[84px]', 'waspada' => 'text-[42px] sm:text-[56px] xl:text-[50px] 2xl:text-[68px]'];
    $ikonSumber = ['web' => 'globe', 'ssh' => 'terminal', 'firewall' => 'shield', 'fim' => 'file-lock', 'modbus' => 'factory', 'site' => 'scan-search'];
    $eselon = ['kodam' => 'XX', 'korem' => 'X', 'kodim' => 'II'];
    $agentLain = max(0, $agentTotal - $agentOnline - $agentBlind);
    $penyerangHp = $openIncidents->whereNotNull('src_ip')->groupBy('src_ip')->sortByDesc(fn ($g) => $g->max('score'));
@endphp
<div>
    <x-ui.page-header title="Beranda Komando" icon="layout-dashboard">
        Semua angka dihitung dari event asli. Klik angka untuk melihat datanya.
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 font-mono text-xs text-ink-3" title="Waktu data di halaman ini dihitung">
                <x-icon name="clock" class="size-3.5 text-accent" />Diperbarui {{ dtg(now()) }}
            </span>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mx-auto max-w-[1600px] space-y-4 px-4 py-5 sm:px-6 lg:px-8">
        <div class="grid gap-4 xl:grid-cols-12">
            <div class="min-w-0 space-y-4 xl:col-span-5">
                {{-- 01 // SITUASI: papan status Tingkat Siaga, elemen terbesar di layar. --}}
                <x-ui.panel nomor="01" kode="Situasi" title="Tingkat Siaga keseluruhan" heading-id="judul-siaga" data-testid="tingkat-keseluruhan" :penggaris="true">
                    @if ($tingkat)
                        @php $p = $papan[$tingkat->value]; @endphp
                        <div role="status" aria-live="polite" aria-atomic="true">
                            <div class="flex items-center gap-4">
                                <span class="grid size-14 shrink-0 place-items-center rounded-control border-2 sm:size-[4.5rem] {{ $p['kotak'] }}">
                                    <x-icon :name="$p['ikon']" stroke="1.6" class="size-8 sm:size-10" />
                                </span>
                                <p class="min-w-0 font-stensil uppercase leading-[0.9] tracking-[0.05em] {{ $ukuranKata[$tingkat->value] }} {{ $p['teks'] }}">{{ $tingkat->label() }}</p>
                            </div>
                            <p class="mt-4 text-[17px] text-ink-2">
                                @if ($sumber->isNotEmpty())
                                    Tertinggi di <span class="font-bold text-ink">{{ $sumber->join(', ') }}</span> · {{ $openCount }} insiden terbuka
                                @else
                                    Semua satuan Aman · {{ $openCount }} insiden terbuka
                                @endif
                            </p>
                            <p class="mt-1 font-mono text-xs text-ink-3">{{ $tree->pluck('name')->join(', ') }} · DTG {{ dtg(now()) }}</p>
                            {{-- Tangga empat lampu: posisi, lampu, dan teks menyampaikan tingkat, bukan warna saja. --}}
                            <ol class="mt-5 grid grid-cols-4 gap-2" aria-label="Skala Tingkat Siaga">
                                @foreach (AlertLevel::cases() as $lv)
                                    @php $nyala = $lv === $tingkat; @endphp
                                    <li @class(['rounded-control px-1 pb-2 pt-2.5 text-center', $papan[$lv->value]['nyala'] => $nyala, 'border border-line bg-inset text-ink-3' => ! $nyala]) @if ($nyala) aria-current="true" @endif>
                                        <span @class(['mx-auto block size-2.5 rounded-full', 'bg-accent-ink/80 ring-2 ring-accent-ink/25' => $nyala, $papan[$lv->value]['lampu'] => ! $nyala]) aria-hidden="true"></span>
                                        <span class="mt-1.5 block font-stensil text-[13px] uppercase tracking-[0.12em] sm:text-[15px]">{{ $lv->label() }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @else
                        <x-ui.empty icon="building-2" title="Belum ada satuan yang bisa Anda lihat">Hubungi Admin untuk menautkan akun ke satuan.</x-ui.empty>
                    @endif
                </x-ui.panel>

                {{-- 03 // KEKUATAN: metrik utama --}}
                <x-ui.panel nomor="03" kode="Kekuatan" title="Metrik utama" :flush="true">
                    <div class="grid divide-y divide-line sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        <x-ui.kpi :bare="true" label="Insiden terbuka" :value="$openCount" icon="siren" :href="route('incidents.index')" data-testid="kartu-insiden">
                            @if ($openCount > 0)
                                <div class="flex h-2 gap-0.5" aria-hidden="true">
                                    @foreach ($bySeverity as $sev => $n)
                                        @if ($n > 0)<span class="rounded-sm {{ Severity::from($sev)->fillClass() }}" style="flex-grow: {{ $n }}"></span>@endif
                                    @endforeach
                                </div>
                                <div class="mt-2.5 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                    @foreach ($bySeverity as $sev => $n)
                                        @if ($n > 0)
                                            <span class="inline-flex items-center gap-1.5"><x-severity-badge :severity="Severity::from($sev)" /><span class="font-stensil text-base text-ink">{{ $n }}</span></span>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <span class="inline-flex items-center gap-1.5 text-ink-3"><x-icon name="circle-check" class="size-4 text-aman" />Tidak ada insiden terbuka</span>
                            @endif
                        </x-ui.kpi>

                        <x-ui.kpi :bare="true" label="Agent online" :value="$agentOnline" :unit="'/ '.$agentTotal" icon="server" :href="$canSeeAgents ? route('units-agents') : null" data-testid="kartu-agent">
                            @if ($agentTotal > 0)
                                <div class="flex h-2 gap-0.5" aria-hidden="true">
                                    @if ($agentOnline > 0)<span class="rounded-sm bg-aman" style="flex-grow: {{ $agentOnline }}"></span>@endif
                                    @if ($agentBlind > 0)<span class="rounded-sm bg-awas" style="flex-grow: {{ $agentBlind }}"></span>@endif
                                    @if ($agentLain > 0)<span class="rounded-sm bg-line-strong" style="flex-grow: {{ $agentLain }}"></span>@endif
                                </div>
                            @endif
                            <p @class(['mt-2.5 flex items-center gap-1.5', 'font-bold text-awas' => $agentBlind > 0, 'text-ink-3' => $agentBlind === 0])>
                                <x-icon :name="$agentBlind > 0 ? 'wifi-off' : 'circle-check'" @class(['size-4', 'text-aman' => $agentBlind === 0]) />
                                {{ $agentBlind > 0 ? $agentBlind.' agent BUTA (sensor mati)' : 'Tidak ada agent buta' }}
                            </p>
                            @if ($sourceWarnings > 0)
                                <p class="mt-1 flex items-center gap-1.5 font-bold text-waspada"><x-icon name="triangle-alert" class="size-4" />{{ $sourceWarnings }} peringatan sumber log</p>
                            @endif
                        </x-ui.kpi>

                        <x-ui.kpi :bare="true" label="Blokir aktif" :value="$blockCount" icon="shield-ban" :href="route('actions.index', ['status' => 'aktif'])" data-testid="kartu-blokir">
                            <p class="flex items-center gap-1.5 {{ $blockCount > 0 ? 'text-ink-2' : 'text-ink-3' }}">
                                <x-icon name="{{ $blockCount > 0 ? 'ban' : 'shield' }}" class="size-4" />
                                {{ $blockCount > 0 ? $blockIps.' IP diblokir di firewall host agent' : 'Belum ada blokir aktif' }}
                            </p>
                        </x-ui.kpi>
                    </div>
                </x-ui.panel>
            </div>

            {{-- 02 // PETA OPERASI --}}
            <x-ui.panel nomor="02" kode="Peta operasi" title="Pohon Satuan & Tingkat Siaga" heading-id="pohon-satuan" class="xl:col-span-7" :flush="true" :penggaris="true"
                subtitle="Dihitung dari insiden terbuka; satuan induk mengikuti tingkat tertinggi di bawahnya.">
                @if ($tree->isEmpty())
                    <p class="p-5 text-sm text-ink-2">Belum ada satuan yang bisa Anda lihat. Hubungi Admin.</p>
                @else
                    <div class="hidden bg-canvas/40 p-2 md:block">
                        @include('livewire.dashboard.partials.peta-operasi')
                    </div>
                    {{-- HP: daftar satuan bersimbol + penyerang --}}
                    <ul class="divide-y divide-line md:hidden" role="tree" aria-labelledby="pohon-satuan">
                        @foreach ($semua as $u)
                            @php $lv = $u->effectiveAlertLevel(); @endphp
                            <li role="treeitem" style="padding-left: {{ 0.75 + ($kedalaman[$u->id] ?? 0) * 1 }}rem">
                                <a href="{{ route('incidents.index', ['unit' => $u->id]) }}" wire:navigate class="flex min-h-12 items-center gap-3 py-2 pr-4" title="Lihat insiden {{ $u->name }}">
                                    <x-ui.simbol-satuan :eselon="$eselon[$u->level->value] ?? ''" :tone="$lv->value" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-semibold text-ink">{{ $u->name }}</span>
                                        <span class="block font-mono text-[11px] text-ink-3">{{ $u->code }}</span>
                                    </span>
                                    <x-alert-badge :level="$lv" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($penyerangHp->isNotEmpty())
                        <div class="border-t border-line p-4 md:hidden">
                            <p class="label-caps text-awas">Penyerang aktif</p>
                            <ul class="mt-2 space-y-2">
                                @foreach ($penyerangHp->take(4) as $ip => $g)
                                    <li class="flex items-center gap-3">
                                        <svg viewBox="0 0 24 24" class="size-6 shrink-0" aria-hidden="true"><path d="M12 1L23 12L12 23L1 12Z" stroke-width="2" class="fill-awas/15 stroke-awas" /><path d="M8 8l8 8M16 8l-8 8" stroke-width="2" class="stroke-awas" /></svg>
                                        <span class="data font-semibold text-ink">{{ $ip }}</span>
                                        <span class="text-sm text-ink-3">{{ $g->pluck('unit_id')->unique()->count() }} satuan sasaran</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    {{-- Legenda --}}
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-line px-4 py-2.5 text-xs text-ink-3 sm:px-5">
                        <span class="inline-flex items-center gap-1.5"><svg viewBox="0 0 22 14" class="h-3.5 w-5" aria-hidden="true"><rect x="1" y="1" width="20" height="12" stroke-width="1.6" class="fill-accent/15 stroke-accent" /></svg>Satuan kawan</span>
                        <span class="inline-flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="size-3.5" aria-hidden="true"><path d="M8 1L15 8L8 15L1 8Z" stroke-width="1.6" class="fill-awas/15 stroke-awas" /></svg>Penyerang (IP sumber)</span>
                        <span class="inline-flex items-center gap-1.5"><svg viewBox="0 0 24 8" class="h-2 w-6" aria-hidden="true"><path d="M1 4H23" stroke-width="2" stroke-dasharray="5 3" class="stroke-awas" /></svg>Serangan aktif</span>
                        <span class="inline-flex items-center gap-1.5"><svg viewBox="0 0 24 8" class="h-2 w-6" aria-hidden="true"><path d="M1 4H23" stroke-width="1.6" class="stroke-accent/60" /></svg>Garis komando</span>
                    </div>
                @endif
            </x-ui.panel>
        </div>

        <div class="grid gap-4 xl:grid-cols-12 xl:items-start">
            {{-- 04 // ARUS EVENT --}}
            <x-ui.panel nomor="04" kode="Arus event" title="Event per menit" heading-id="grafik-judul" class="xl:col-span-5"
                subtitle="{{ \App\Livewire\Dashboard\CommandDashboard::CHART_MINUTES }} menit terakhir · total {{ array_sum($series['counts']) }} event · WIB">
                <div class="h-72" wire:ignore x-data="grafikEvent(@js($series))">
                    <canvas x-ref="canvas" role="img" aria-label="Grafik batang jumlah event per menit selama {{ \App\Livewire\Dashboard\CommandDashboard::CHART_MINUTES }} menit terakhir"></canvas>
                </div>
                <details class="group mt-3 text-sm text-ink-2">
                    <summary class="inline-flex cursor-pointer select-none items-center gap-1 font-semibold uppercase tracking-[0.08em] text-ink-3 hover:text-accent">
                        <x-icon name="chevron-right" class="size-4 transition-transform group-open:rotate-90" />Lihat sebagai tabel
                    </summary>
                    <table class="tbl mt-2 max-w-xs">
                        <thead><tr><th>Menit (WIB)</th><th>Event</th></tr></thead>
                        <tbody>
                            @foreach ($series['labels'] as $i => $label)
                                @if ($series['counts'][$i] > 0)
                                    <tr><td class="data text-ink-2">{{ $label }}</td><td class="data text-ink">{{ $series['counts'][$i] }}</td></tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </details>
            </x-ui.panel>

            {{-- 05 // LAPORAN MASUK: feed event live --}}
            <x-ui.panel nomor="05" kode="Laporan masuk" title="Feed event live" heading-id="feed-judul" :flush="true" class="xl:col-span-7">
                <x-slot:actions><span class="font-mono text-xs text-ink-3">12 event terakhir · waktu WIB</span></x-slot:actions>
                {{-- Baris baru (id di atas batas saat halaman dimuat) diberi animasi masuk sekali saat disisipkan.
                     wire:ignore.self menjaga x-data (batas awal) dan kelas animasi tidak ditimpa morph Livewire. --}}
                <ul class="text-sm" wire:ignore.self x-data="{ batas: {{ (int) $feed->max('id') }} }">
                    @forelse ($feed as $event)
                        @php
                            $sev = $event->detections->map(fn ($d) => $d->incident?->severity)->filter()->sortByDesc(fn ($s) => $s->rank())->first();
                        @endphp
                        <li class="relative flex flex-col gap-1 border-b border-line/70 px-4 py-2.5 last:border-b-0 sm:grid sm:grid-cols-[4rem_6.75rem_minmax(0,1fr)_auto] sm:items-center sm:gap-x-4 sm:px-5"
                            wire:key="feed-{{ $event->id }}" wire:ignore.self x-init="if ({{ $event->id }} > batas) $el.classList.add('animate-masuk')">
                            <span class="absolute inset-y-0 left-0 w-[3px] {{ $sev ? $sev->fillClass() : '' }}" aria-hidden="true"></span>
                            <span class="font-mono text-xs text-ink-3" title="{{ waktu_wib($event->occurred_at) }}">{{ $event->occurred_at->copy()->setTimezone(config('sentra.display_timezone'))->format('H:i:s') }}</span>
                            <span class="flex items-center gap-1.5">
                                <x-icon :name="$ikonSumber[$event->source->value] ?? 'activity'" class="size-4 text-ink-3" />
                                <a href="{{ route('events.show', $event) }}" class="link whitespace-nowrap font-mono text-xs">EV-{{ $event->id }}</a>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-ink" title="{{ $event->summary() }}">{{ $event->summary() }}</span>
                                <span class="block truncate text-xs text-ink-3">{{ $event->unit->name }} · {{ $event->source->label() }}</span>
                            </span>
                            <span class="flex flex-wrap items-center gap-1.5 sm:justify-end">
                                @foreach ($event->detections->pluck('rule.code')->unique() as $code)
                                    <span class="chip text-ink">{{ $code }}</span>
                                @endforeach
                                @if ($sev)<x-severity-badge :severity="$sev" />@endif
                            </span>
                        </li>
                    @empty
                        <li><x-ui.empty icon="radio" title="Belum ada event dari agent.">Event muncul di sini begitu agent di satuan mengirim telemetri.</x-ui.empty></li>
                    @endforelse
                </ul>
            </x-ui.panel>
        </div>
    </div>
</div>
