@props(['status'])
@php
    $ikon = ['diusulkan' => 'inbox', 'disetujui' => 'stamp', 'dieksekusi' => 'check', 'gagal' => 'circle-x', 'ditolak' => 'ban', 'kedaluwarsa' => 'clock', 'dibatalkan' => 'x'][$status->value] ?? 'circle-alert';
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 whitespace-nowrap rounded-chip px-1.5 py-0.5 text-xs font-semibold uppercase tracking-[0.08em] ring-1 ring-inset '.$status->badgeClass()]) }}>
    <x-icon :name="$ikon" class="size-3.5" />{{ $status->label() }}
</span>
