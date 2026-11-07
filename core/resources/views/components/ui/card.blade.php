{{--
    Kartu: satu objek dalam daftar (insiden, usulan tindakan, agent). Lebih ringan dari panel.
    tone memberi garis status di tepi kiri (aman|waspada|siaga|awas|accent); href menjadikannya tautan.
--}}
@props(['tone' => null, 'href' => null])
@php
    $tepi = ['aman' => 'border-l-aman', 'waspada' => 'border-l-waspada', 'siaga' => 'border-l-siaga', 'awas' => 'border-l-awas', 'accent' => 'border-l-accent', 'pasir' => 'border-l-pasir'][$tone] ?? null;
    $kelas = 'block min-w-0 rounded-panel border border-line bg-raised/40 p-4'.($tepi ? ' border-l-[3px] '.$tepi : '').($href ? ' transition-colors duration-150 ease-sentra hover:border-line-strong hover:bg-raised' : '');
@endphp
@if ($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => $kelas]) }}>{{ $slot }}</a>
@else
    <article {{ $attributes->merge(['class' => $kelas]) }}>{{ $slot }}</article>
@endif
