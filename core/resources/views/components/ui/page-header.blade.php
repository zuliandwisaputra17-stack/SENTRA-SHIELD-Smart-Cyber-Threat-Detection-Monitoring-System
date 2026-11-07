{{--
    Kepala halaman gaya briefing: judul stensil kapital (teks asli; kapital lewat CSS), keterangan (slot),
    aksi di kanan (slot "actions"). back + backLabel: tautan kembali di atas judul (halaman detail).
--}}
@props(['title', 'icon' => null, 'back' => null, 'backLabel' => null])
<header {{ $attributes->merge(['class' => 'relative border-b border-line bg-gradient-to-b from-panel/70 to-transparent']) }}>
    <span class="penggaris-x opacity-60" aria-hidden="true"></span>
    <div class="mx-auto max-w-[1600px] px-4 pb-5 pt-6 sm:px-6 lg:px-8">
        @if ($back)
            <a href="{{ $back }}" wire:navigate class="mb-2 inline-flex min-h-6 items-center gap-1 text-sm font-semibold uppercase tracking-[0.1em] text-ink-3 transition-colors hover:text-accent">
                <x-icon name="chevron-left" class="size-4" />{{ $backLabel }}
            </a>
        @endif
        <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
            <div class="min-w-0">
                <h2 class="flex items-center gap-3 font-stensil text-[30px] uppercase leading-tight tracking-[0.06em] text-ink sm:text-[34px]">
                    @if ($icon)<x-icon :name="$icon" class="size-7 shrink-0 text-accent" />@endif<span class="min-w-0 break-words">{{ $title }}</span>
                </h2>
                @if (trim($slot) !== '')
                    <div class="mt-1.5 text-[15px] text-ink-3">{{ $slot }}</div>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    </div>
</header>
