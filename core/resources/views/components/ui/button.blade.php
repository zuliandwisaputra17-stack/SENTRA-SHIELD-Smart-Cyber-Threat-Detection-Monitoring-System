{{--
    Tombol. variant: primary (aksen), secondary, danger, approve (keputusan Setujui), ghost.
    size: sm | md | lg (lg = target sentuh ≥ 48px untuk HP). href menjadikannya tautan.
--}}
@props(['variant' => 'primary', 'size' => 'md', 'icon' => null, 'href' => null, 'type' => 'button'])
@php
    $varian = [
        'primary' => 'bg-accent text-accent-ink hover:bg-accent-strong',
        'secondary' => 'border border-line-control bg-raised text-ink hover:bg-inset hover:border-accent',
        'danger' => 'bg-awas text-[#1A0505] hover:bg-awas/90',
        'approve' => 'bg-aman text-[#04150D] hover:bg-aman/90',
        'ghost' => 'text-ink-2 hover:bg-raised hover:text-ink',
    ][$variant] ?? '';
    $ukuran = [
        'sm' => 'h-8 px-3 text-xs gap-1.5',
        'md' => 'h-10 px-4 text-sm gap-2',
        'lg' => 'min-h-14 px-6 text-base gap-2.5',
    ][$size] ?? '';
    $kelas = 'inline-flex select-none items-center justify-center rounded-control font-display font-bold uppercase tracking-[0.1em] transition-colors duration-150 ease-sentra focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:cursor-not-allowed disabled:opacity-50 '.$varian.' '.$ukuran;
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $kelas]) }}>
        @if ($icon)<x-icon :name="$icon" :class="$size === 'lg' ? 'size-5' : 'size-4'" />@endif{{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $kelas]) }}>
        @if ($icon)<x-icon :name="$icon" :class="$size === 'lg' ? 'size-5' : 'size-4'" />@endif{{ $slot }}
    </button>
@endif
