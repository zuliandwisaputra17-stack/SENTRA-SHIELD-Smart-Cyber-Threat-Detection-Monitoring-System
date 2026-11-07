{{--
    KPI tile: satu angka besar terbaca dari proyektor. tone memberi warna status pada angka
    (selalu disertai teks/ikon di slot). Angka disorot sebentar saat nilainya berubah.
    :bare="true" untuk sel di dalam satu panel metrik (tanpa garis panel sendiri).
--}}
@props(['label', 'value', 'unit' => null, 'icon' => null, 'tone' => null, 'href' => null, 'bare' => false])
@php
    $warna = ['aman' => 'text-aman', 'waspada' => 'text-waspada', 'siaga' => 'text-siaga', 'awas' => 'text-awas', 'accent' => 'text-accent'][$tone] ?? 'text-ink';
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' => ($bare ? '' : 'panel ').'group relative flex min-w-0 flex-col p-4 sm:p-5 transition-colors duration-150 ease-sentra'.($href ? ($bare ? ' hover:bg-raised/50' : ' hover:border-line-strong hover:bg-raised/50') : '')]) }}>
    <div class="flex items-center justify-between gap-2">
        <span class="label-caps">{{ $label }}</span>
        @if ($icon)<x-icon :name="$icon" class="size-4 text-ink-3 transition-colors group-hover:text-ink-2" />@endif
    </div>
    <div class="mt-3 flex items-baseline gap-1.5">
        <span class="num text-[44px] sm:text-5xl xl:text-[56px] {{ $warna }} animate-sorot" wire:key="kpi-{{ \Illuminate\Support\Str::slug($label) }}-{{ $value }}">{{ $value }}</span>
        @if ($unit)<span class="font-display text-lg font-medium text-ink-3">{{ $unit }}</span>@endif
    </div>
    @if (trim($slot) !== '')
        <div class="mt-auto pt-4 text-sm text-ink-2">{{ $slot }}</div>
    @endif
    @if ($href)
        <x-icon name="arrow-right" class="absolute bottom-4 right-4 size-4 text-ink-3 opacity-0 transition-opacity duration-150 group-hover:opacity-100" />
    @endif
</{{ $tag }}>
