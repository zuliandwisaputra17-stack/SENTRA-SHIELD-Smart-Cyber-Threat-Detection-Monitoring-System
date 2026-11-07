@php
    $nadaStatus = ['Cloaking judi' => 'awas', 'Halaman utama berubah' => 'awas', 'Tidak dapat diakses' => 'waspada', 'Aman' => 'aman', 'Belum diperiksa' => 'pasir'];
    $ikonStatus = ['Cloaking judi' => 'shield-alert', 'Halaman utama berubah' => 'triangle-alert', 'Tidak dapat diakses' => 'wifi-off', 'Aman' => 'shield-check', 'Belum diperiksa' => 'clock'];
    $ikonVarian = ['browser' => 'monitor', 'googlebot' => 'search', 'referer' => 'link'];
@endphp
<div>
    <x-ui.page-header title="Penjaga Situs" icon="globe">
        <span class="block max-w-4xl">Setiap menit Core mengambil halaman situs satuan 3 kali: sebagai browser biasa, sebagai Googlebot, dan dengan referer Google. Isi yang berbeda dan memuat kata judi menandakan injeksi judi online ber-cloaking; halaman utama yang berubah di luar jadwal pembaruan menandakan defacement. Hanya situs terdaftar yang diperiksa.</span>
    </x-ui.page-header>

    <div class="mx-auto max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8">
        @if ($flash)
            <div class="mb-4 flex items-start gap-2 rounded-control border border-aman/40 bg-aman/10 px-3 py-2 text-sm text-aman" role="status"><x-icon name="circle-check" class="mt-0.5 size-4" />{{ $flash }}</div>
        @endif

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
            {{-- Daftar situs --}}
            <div class="min-w-0 space-y-4 xl:col-span-3">
                <x-ui.panel nomor="01" kode="Sektor" title="Situs terdaftar" icon="globe" heading-id="daftar-situs">
                    <ul class="space-y-2">
                        @forelse ($sites as $s)
                            @php [$label, $color] = \App\Livewire\Sites\SiteWatchPage::status($latest[$s->id] ?? null); @endphp
                            <li wire:key="situs-{{ $s->id }}">
                                <button type="button" wire:click="select({{ $s->id }})" @if ($site?->id === $s->id) aria-current="true" @endif
                                    @class(['w-full rounded-control border px-3 py-2.5 text-left transition-colors duration-150', 'border-accent/60 bg-accent/5' => $site?->id === $s->id, 'border-line hover:border-line-strong hover:bg-raised/50' => $site?->id !== $s->id])>
                                    <span class="block truncate font-mono text-sm text-ink">{{ $s->url }}</span>
                                    <span class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
                                        <span class="inline-flex items-center gap-1 rounded-chip px-1.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $color }}"><x-icon :name="$ikonStatus[$label] ?? 'globe'" class="size-3.5" />{{ $label }}</span>
                                        <span class="text-xs text-ink-3">{{ $s->unit->name }} · diperiksa {{ ($latest[$s->id] ?? collect())->isNotEmpty() ? waktu_wib($latest[$s->id]->first()->checked_at, 'H:i:s') : '—' }}</span>
                                    </span>
                                </button>
                            </li>
                        @empty
                            <li class="text-sm text-ink-3">Belum ada situs terdaftar.</li>
                        @endforelse
                    </ul>
                </x-ui.panel>

                @if ($canManage)
                    <form wire:submit="add" class="panel space-y-3 p-4 sm:p-5" aria-labelledby="tambah-situs">
                        <p class="flex items-baseline gap-2" aria-hidden="true"><span class="nomor-bagian">02 //</span><span class="font-stensil text-[17px] uppercase leading-none tracking-[0.08em] text-ink">Pos baru</span></p>
                        <h3 id="tambah-situs" class="text-sm font-semibold text-ink-2">Tambah situs</h3>
                        <div>
                            <x-input-label for="site-unit" value="Satuan" />
                            <select wire:model="unitId" id="site-unit" class="mt-1.5 block h-10 w-full text-sm">
                                <option value="">Pilih satuan</option>
                                @foreach ($units as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('unitId')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="site-url" value="URL halaman utama" />
                            <x-text-input wire:model="url" id="site-url" class="mt-1.5 block h-10 w-full font-mono text-sm" placeholder="http://kodim-a/" />
                            <x-input-error :messages="$errors->get('url')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="site-windows" value="Jadwal pembaruan resmi (WIB, opsional)" />
                            <x-text-input wire:model="windows" id="site-windows" class="mt-1.5 block h-10 w-full font-mono text-sm" placeholder="22:00-23:00" />
                            <p class="mt-1 text-xs text-ink-3">Perubahan halaman utama di dalam jadwal ini menjadi baseline baru, bukan peringatan.</p>
                            <x-input-error :messages="$errors->get('windows')" class="mt-1" />
                        </div>
                        <x-primary-button class="w-full"><x-icon name="globe" class="size-4" />Tambah situs</x-primary-button>
                    </form>
                @endif
            </div>

            {{-- Rincian situs terpilih --}}
            <div class="min-w-0 space-y-4 xl:col-span-9">
                @if ($site)
                    @php [$label, $color] = \App\Livewire\Sites\SiteWatchPage::status($checks); @endphp
                    <section class="panel min-w-0" aria-labelledby="rincian-situs">
                        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-line p-4 sm:p-5">
                            <div class="min-w-0">
                                <p class="label-caps flex items-center gap-1.5"><x-icon name="crosshair" class="size-3.5 text-accent" />Sasaran pantau</p>
                                <h3 id="rincian-situs" class="mt-1 break-all font-mono text-xl font-semibold text-ink">{{ $site->url }}</h3>
                                <p class="mt-1 text-sm text-ink-3">{{ $site->unit->name }} · jadwal pembaruan: {{ collect($site->update_windows ?? [])->map(fn ($w) => ($w['mulai'] ?? '?').'-'.($w['selesai'] ?? '?'))->implode(', ') ?: 'tidak ada' }}</p>
                                <p class="mt-1 break-all font-mono text-[11px] text-ink-3">baseline: {{ $site->baseline_hash ?? 'belum ada (dibuat pada pemeriksaan pertama)' }}</p>
                            </div>
                            <span role="status"><x-ui.stempel :tone="$nadaStatus[$label] ?? 'pasir'" size="md" data-testid="status-situs">{{ $label }}</x-ui.stempel></span>
                        </div>
                        <div class="p-4 sm:p-5">
                            @if ($canManage)
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-primary-button type="button" wire:click="checkNow({{ $site->id }})" wire:loading.attr="disabled" wire:target="checkNow"><x-icon name="refresh-cw" class="size-4" />Periksa sekarang</x-primary-button>
                                    @if ($checks->firstWhere('variant', \App\Enums\SiteVariant::Browser)?->flagged)
                                        <x-ui.button variant="secondary" icon="stamp" class="text-waspada" wire:click="acceptBaseline({{ $site->id }})" wire:confirm="Jadikan halaman saat ini sebagai baseline? Lakukan hanya bila perubahan memang resmi.">Perubahan resmi: jadikan baseline</x-ui.button>
                                    @endif
                                    <x-ui.button variant="ghost" icon="x" class="ms-auto text-awas hover:text-awas" wire:click="remove({{ $site->id }})" wire:confirm="Hapus {{ $site->url }} dari Penjaga Situs?">Hapus situs</x-ui.button>
                                </div>
                            @endif

                            @if ($incidents->isNotEmpty())
                                <p class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-2"><x-icon name="siren" class="size-4 text-awas" />Insiden terkait:
                                    @foreach ($incidents as $inc)
                                        <a href="{{ route('incidents.show', $inc) }}" wire:navigate class="link">#{{ $inc->id }} {{ $inc->title }}</a>@if (! $loop->last),@endif
                                    @endforeach
                                </p>
                            @endif

                            {{-- Perbandingan tiga varian, berdampingan --}}
                            @if ($checks->isNotEmpty())
                                <h4 class="mt-5 flex flex-wrap items-baseline gap-2 text-sm font-semibold text-ink-2"><span class="nomor-bagian">03 //</span><span class="font-stensil text-[17px] uppercase tracking-[0.08em] text-ink">Tiga sudut pandang</span> Hasil pemeriksaan per varian · <span class="data">{{ waktu_wib($checks->first()->checked_at, 'd/m/Y H:i:s') }}</span></h4>
                                <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">
                                    @foreach ($checks as $c)
                                        <div @class(['relative min-w-0 overflow-hidden rounded-panel border', 'border-awas/60 bg-awas/[0.06]' => $c->flagged, 'border-line bg-raised/40' => ! $c->flagged]) wire:key="varian-{{ $c->id }}" data-testid="varian-{{ $c->variant->value }}">
                                            @if ($c->flagged)<div class="absolute inset-x-0 top-0 h-0.5 bg-awas" aria-hidden="true"></div>@endif
                                            <div class="flex items-center justify-between gap-2 border-b border-line px-3 py-2.5">
                                                <span class="flex items-center gap-2 text-sm font-semibold text-ink"><x-icon :name="$ikonVarian[$c->variant->value] ?? 'globe'" class="size-4 text-ink-3" />{{ $c->variant->label() }}</span>
                                                @if ($c->flagged)
                                                    <x-ui.stempel tone="awas" size="sm">Ditandai</x-ui.stempel>
                                                @endif
                                            </div>
                                            <div class="p-3">
                                                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-xs">
                                                    <dt class="text-ink-3">HTTP</dt><dd class="data text-ink">{{ $c->http_status ?? '—' }}</dd>
                                                    <dt class="text-ink-3">Kemiripan</dt>
                                                    <dd class="flex items-center gap-2">
                                                        <span class="data w-9 text-ink">{{ $c->similarity !== null ? round($c->similarity * 100).'%' : '—' }}</span>
                                                        @if ($c->similarity !== null)
                                                            <span class="h-1.5 flex-1 rounded-full bg-inset" aria-hidden="true"><span class="block h-1.5 rounded-full bg-[#9E8E58]" style="width: {{ round($c->similarity * 100) }}%"></span></span>
                                                        @endif
                                                    </dd>
                                                    <dt class="text-ink-3">Kata judi</dt><dd @class(['data', 'font-semibold text-awas' => $c->gambling_hits > 0, 'text-ink' => ! $c->gambling_hits])>{{ $c->gambling_hits }}</dd>
                                                    <dt class="text-ink-3">Hash</dt><dd class="truncate font-mono text-ink-2" title="{{ $c->hash }}">{{ $c->hash ? substr($c->hash, 0, 12).'…' : '—' }}</dd>
                                                </dl>
                                                @if ($c->keywords)
                                                    <div class="mt-2.5 flex flex-wrap gap-1">
                                                        @foreach ($c->keywords as $word => $n)
                                                            <span class="chip border-awas/50 bg-awas/15 text-awas">{{ $word }} ×{{ $n }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                                @if ($c->error)
                                                    <p class="mt-2 flex items-start gap-1.5 text-xs text-waspada"><x-icon name="triangle-alert" class="mt-px size-3.5" />{{ $c->error }}</p>
                                                @endif
                                                <div class="mt-3 max-h-60 overflow-auto rounded-control border border-line bg-canvas p-2.5 text-xs leading-relaxed text-ink-2">{!! \App\Livewire\Sites\SiteWatchPage::highlight($c->excerpt, $c->keywords) ?: '<span class="text-ink-3">(kosong)</span>' !!}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-4 text-sm text-ink-3">Belum diperiksa. Pemeriksaan berjalan otomatis tiap menit.</p>
                            @endif
                        </div>
                    </section>

                    @if ($history->isNotEmpty())
                        <x-ui.panel nomor="04" kode="Log pantau" title="Riwayat pemeriksaan" icon="history" heading-id="riwayat-situs" :flush="true">
                            <ul class="text-sm">
                                @foreach ($history as $uuid => $group)
                                    @php [$hLabel, $hColor] = \App\Livewire\Sites\SiteWatchPage::status($group); @endphp
                                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-line/70 px-4 py-2 last:border-b-0 sm:px-5" wire:key="riwayat-{{ $uuid }}">
                                        <span class="font-mono text-xs text-ink-3">{{ waktu_wib($group->first()->checked_at, 'd/m H:i:s') }}</span>
                                        <span class="inline-flex items-center gap-1 rounded-chip px-1.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $hColor }}"><x-icon :name="$ikonStatus[$hLabel] ?? 'globe'" class="size-3.5" />{{ $hLabel }}</span>
                                        <span class="text-xs text-ink-3">{{ $group->map(fn ($c) => $c->variant->label().' '.($c->similarity !== null ? round($c->similarity * 100).'%' : '—'))->implode(' · ') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </x-ui.panel>
                    @endif
                @else
                    <div class="panel"><x-ui.empty icon="globe" title="Pilih atau tambahkan situs.">Penjaga Situs memeriksa halaman utama situs satuan tiap menit dari tiga sudut pandang.</x-ui.empty></div>
                @endif
            </div>
        </div>
    </div>
</div>
