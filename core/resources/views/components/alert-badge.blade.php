@props(['level', 'size' => 'sm'])
{{-- Lencana Tingkat Siaga: warna + ikon + teks (tidak hanya warna). --}}
@php
    $ikon = ['aman' => 'shield-check', 'waspada' => 'eye', 'siaga' => 'triangle-alert', 'awas' => 'siren'][$level->value];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 whitespace-nowrap rounded-chip font-semibold uppercase tracking-[0.08em] ring-1 ring-inset '.($size === 'lg' ? 'px-2.5 py-1 text-sm' : 'px-1.5 py-0.5 text-xs').' '.$level->badgeClass()]) }}>
    <x-icon :name="$ikon" :class="$size === 'lg' ? 'size-4' : 'size-3.5'" />
    {{ $level->label() }}
</span>
