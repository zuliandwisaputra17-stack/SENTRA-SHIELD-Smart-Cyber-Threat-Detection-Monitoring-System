@php
    $brokenId = $verification['broken_id'] ?? null;
    $waktuVerif = $verification ? \Illuminate\Support\Carbon::parse($verification['at']) : null;
    // Status mata rantai per baris: terverifikasi (sebelum titik putus / saat rantai utuh), titik putus, atau belum diperiksa.
    $mata = function ($log) use ($verification, $brokenId, $waktuVerif) {
        if (! $verification) {
            return 'netral';
        }
        if ($brokenId !== null) {
            return $log->id < $brokenId ? 'utuh' : ($log->id === $brokenId ? 'putus' : 'netral');
        }

        return $log->created_at <= $waktuVerif ? 'utuh' : 'netral';
    };
    $warnaMata = ['utuh' => 'bg-aman', 'putus' => 'bg-awas ring-4 ring-awas/30', 'netral' => 'bg-line-control'];
    $garisMata = ['utuh' => 'bg-aman/50', 'putus' => 'bg-awas/60', 'netral' => 'bg-line-strong'];
@endphp
<div>
    <x-ui.page-header title="Audit Log" icon="link-2">
        <span class="block max-w-3xl">Setiap baris menyimpan hash SHA-256 dari isinya ditambah hash baris sebelumnya. Mengubah atau menghapus satu baris langsung di database memutus rantai.</span>
    </x-ui.page-header>

    <div class="mx-auto max-w-[1440px] space-y-4 px-4 py-6 sm:px-6 lg:px-8">
        {{-- Verifikasi rantai: status rantai adalah informasi terpenting halaman ini. --}}
        <section @class([
            'panel relative overflow-hidden',
            'border-aman/50' => $verification && $verification['ok'],
            'border-awas/60' => $verification && ! $verification['ok'],
        ]) aria-labelledby="verifikasi-judul">
            @if ($verification)
                <div class="absolute inset-x-0 top-0 h-1 {{ $verification['ok'] ? 'bg-aman' : 'bg-awas' }}" aria-hidden="true"></div>
            @endif
            <div class="flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
                <div class="flex min-w-0 items-center gap-4">
                    <span @class([
                        'grid size-14 shrink-0 place-items-center rounded-full ring-1 ring-inset',
                        'bg-aman/10 text-aman ring-aman/40' => $verification && $verification['ok'],
                        'bg-awas/15 text-awas ring-awas/50' => $verification && ! $verification['ok'],
                        'bg-inset text-ink-3 ring-line-strong' => ! $verification,
                    ])>
                        <x-icon :name="! $verification ? 'link-2' : ($verification['ok'] ? 'shield-check' : 'unlink')" class="size-7" />
                    </span>
                    <div class="min-w-0">
                        <p class="flex items-baseline gap-2" aria-hidden="true"><span class="nomor-bagian">01 //</span><span class="font-stensil text-[19px] uppercase leading-none tracking-[0.08em] text-ink">Rantai bukti</span></p>
                        <h3 id="verifikasi-judul" class="mt-1 text-sm font-semibold text-ink-2">Verifikasi Rantai</h3>
                        <p class="mt-0.5 text-sm text-ink-3">{{ number_format($total, 0, ',', '.') }} baris tercatat. Verifikasi menghitung ulang hash seluruh baris dari yang pertama.</p>
                    </div>
                </div>
                @if ($canVerify)
                    <x-primary-button type="button" wire:click="verify" wire:loading.attr="disabled" wire:target="verify" class="h-11">
                        <x-icon name="link-2" class="size-4" wire:loading.remove wire:target="verify" />
                        <x-icon name="loader-circle" class="size-4 animate-spin" wire:loading wire:target="verify" />
                        <span wire:loading.remove wire:target="verify">Verifikasi Rantai</span>
                        <span wire:loading wire:target="verify">Memeriksa…</span>
                    </x-primary-button>
                @endif
            </div>

            @if ($verification)
                @if ($verification['ok'])
                    <div class="flex flex-wrap items-center justify-between gap-4 border-t border-aman/30 bg-aman/[0.07] px-5 py-4 sm:px-6" role="status" data-testid="hasil-verifikasi">
                        <div>
                            <p class="font-stensil text-[32px] uppercase leading-tight tracking-[0.06em] text-aman">Rantai utuh.</p>
                            <p class="mt-1 text-sm text-ink-2">{{ number_format($verification['checked'], 0, ',', '.') }} baris diperiksa, semua hash cocok.
                                <span class="data text-ink-3">({{ waktu_wib($waktuVerif, 'H:i:s') }})</span></p>
                        </div>
                        <x-ui.stempel tone="aman" size="lg" aria-hidden="true">Terverifikasi</x-ui.stempel>
                    </div>
                @else
                    <div class="border-t border-awas/40 bg-awas/[0.08] px-5 py-4 sm:px-6" role="alert" data-testid="hasil-verifikasi">
                        <x-ui.stempel tone="awas" size="lg" class="float-right ms-4" aria-hidden="true">Rantai putus</x-ui.stempel>
                        <p class="font-stensil text-[28px] uppercase leading-tight tracking-[0.04em] text-awas">Rantai RUSAK mulai baris #{{ $verification['broken_id'] }}:</p>
                        <p class="mt-1 text-sm text-ink">{{ $verification['reason'] }}.
                            {{ number_format($verification['checked'], 0, ',', '.') }} baris sebelumnya utuh.</p>
                        <x-ui.button variant="danger" size="sm" icon="eye" class="mt-3" wire:click="showRow({{ $verification['broken_id'] }})">Lihat baris #{{ $verification['broken_id'] }}</x-ui.button>
                    </div>
                @endif
            @endif
        </section>

        {{-- Filter: satu baris di atas tabel. --}}
        <div class="panel grid gap-3 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-[auto_repeat(4,minmax(0,1fr))_auto] lg:items-end">
            <p class="flex items-baseline gap-2 self-center sm:col-span-2 lg:col-span-1" aria-hidden="true"><span class="nomor-bagian">02 //</span><span class="font-stensil text-[17px] uppercase leading-none tracking-[0.08em] text-ink">Saringan</span></p>
            <div>
                <x-input-label for="f-aksi" value="Aksi" />
                <select wire:model.live="aksi" id="f-aksi" class="mt-1.5 block h-10 w-full text-sm">
                    <option value="">Semua aksi</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a }}">{{ ucfirst(str_replace('_', ' ', $a)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="f-pengguna" value="Pengguna" />
                <select wire:model.live="pengguna" id="f-pengguna" class="mt-1.5 block h-10 w-full text-sm">
                    <option value="">Semua pengguna</option>
                    <option value="{{ \App\Livewire\Audit\AuditLogPage::SYSTEM_USER }}">Sistem / agent</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="f-dari" value="Dari tanggal (WIB)" />
                <input type="date" wire:model.live="dari" id="f-dari" class="mt-1.5 block h-10 w-full text-sm">
            </div>
            <div>
                <x-input-label for="f-sampai" value="Sampai tanggal (WIB)" />
                <input type="date" wire:model.live="sampai" id="f-sampai" class="mt-1.5 block h-10 w-full text-sm">
            </div>
            <div class="flex h-10 items-center">
                @if ($aksi !== '' || $pengguna !== '' || $dari !== '' || $sampai !== '' || $baris)
                    <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Hapus filter</x-ui.button>
                @endif
            </div>
        </div>
        @if ($baris)
            <p class="text-sm text-ink-3">Menampilkan baris sekitar #{{ $baris }}.</p>
        @endif

        {{-- Layar kecil: kartu dengan garis rantai di kiri --}}
        <ul class="panel md:hidden">
            @forelse ($logs as $log)
                @php [$label, $url] = \App\Livewire\Audit\AuditLogPage::subject($log); $m = $mata($log); @endphp
                <li @class(['relative py-3 pl-9 pr-3', 'bg-awas/[0.08]' => $m === 'putus']) wire:key="audit-hp-{{ $log->id }}">
                    <span class="absolute inset-y-0 left-[1.1rem] w-0.5 {{ $garisMata[$m] }}" aria-hidden="true"></span>
                    <span class="absolute left-[1.1rem] top-4 size-2.5 -translate-x-[40%] rotate-45 {{ $warnaMata[$m] }}" aria-hidden="true"></span>
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-sm font-medium text-ink">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</span>
                        <span class="shrink-0 font-mono text-xs text-ink-3">#{{ $log->id }}</span>
                    </div>
                    <div class="mt-0.5 text-xs text-ink-3">{{ waktu_wib($log->created_at, 'd/m/Y H:i:s') }} · {{ $log->user?->name ?? 'Sistem' }} ·
                        @if ($url)<a href="{{ $url }}" wire:navigate class="link">{{ $label }}</a>@else{{ $label }}@endif
                    </div>
                    @if ($log->data)
                        <div class="mt-1 break-words text-xs text-ink-2">{{ \App\Livewire\Audit\AuditLogPage::details($log->data) }}</div>
                    @endif
                    <div class="mt-1 font-mono text-[11px] text-ink-3" title="hash {{ $log->hash }}">hash {{ substr($log->hash, 0, 16) }}…</div>
                </li>
            @empty
                <li><x-ui.empty icon="link-2" title="Tidak ada baris audit." /></li>
            @endforelse
        </ul>

        <div class="panel hidden md:block">
            <header class="panel-head">
                <p class="flex items-baseline gap-2" aria-hidden="true"><span class="nomor-bagian">03 //</span><span class="font-stensil text-[19px] uppercase leading-none tracking-[0.08em] text-ink">Buku log</span></p>
                <span class="font-mono text-xs text-ink-3">hash SHA-256 · ← hash baris sebelumnya</span>
            </header>
            <x-ui.table caption="Baris audit log berantai">
                <x-slot:head>
                    <tr>
                        <th class="w-8"><span class="sr-only">Rantai</span></th><th>#</th><th>Waktu (WIB)</th><th>Pengguna</th>
                        <th>Aksi</th><th>Objek</th><th>Rincian</th><th>Hash</th>
                    </tr>
                </x-slot:head>
                @forelse ($logs as $log)
                    @php [$label, $url] = \App\Livewire\Audit\AuditLogPage::subject($log); $m = $mata($log); @endphp
                    <tr wire:key="audit-{{ $log->id }}" @class(['bg-awas/[0.08] ring-1 ring-inset ring-awas/60' => $m === 'putus']) @if ($log->id === $brokenId) data-testid="baris-rusak" @endif>
                        <td class="relative !py-0">
                            <span class="absolute inset-y-0 left-1/2 w-0.5 -translate-x-1/2 {{ $garisMata[$m] }}" aria-hidden="true"></span>
                            <span class="absolute left-1/2 top-4 size-2.5 -translate-x-1/2 rotate-45 {{ $warnaMata[$m] }}" @if ($m === 'putus') title="Titik putus rantai" @endif aria-hidden="true"></span>
                        </td>
                        <td class="data text-ink-3">{{ $log->id }}</td>
                        <td class="whitespace-nowrap font-mono text-xs text-ink-2">{{ waktu_wib($log->created_at, 'd/m/Y H:i:s.v') }}</td>
                        <td class="whitespace-nowrap text-ink-2">{{ $log->user?->name ?? 'Sistem' }}</td>
                        <td class="whitespace-nowrap font-medium text-ink">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</td>
                        <td class="whitespace-nowrap text-xs">
                            @if ($url)<a href="{{ $url }}" wire:navigate class="link">{{ $label }}</a>@else<span class="text-ink-3">{{ $label }}</span>@endif
                        </td>
                        <td class="min-w-64 max-w-md text-xs text-ink-2"><div class="line-clamp-3 break-words" title="{{ \App\Livewire\Audit\AuditLogPage::details($log->data) }}">{{ \App\Livewire\Audit\AuditLogPage::details($log->data) ?: '—' }}</div></td>
                        <td class="whitespace-nowrap font-mono text-[11px] leading-4">
                            <div class="text-ink-2" title="hash {{ $log->hash }}">{{ substr($log->hash, 0, 12) }}…</div>
                            <div class="text-ink-3" title="hash sebelumnya {{ $log->prev_hash }}">← {{ substr($log->prev_hash, 0, 12) }}…</div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-ui.empty icon="link-2" title="Tidak ada baris audit." /></td></tr>
                @endforelse
            </x-ui.table>
        </div>
        {{ $logs->links() }}
    </div>
</div>
