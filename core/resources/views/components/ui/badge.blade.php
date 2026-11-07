{{-- Lencana umum. Untuk status pakai x-alert-badge / x-severity-badge / x-action-status. --}}
@props(['tone' => 'neutral', 'icon' => null])
@php
    $kelas = [
        'neutral' => 'bg-inset text-ink-2 ring-line-strong',
        'accent' => 'bg-accent/10 text-accent ring-accent/40',
        'aman' => 'bg-aman/10 text-aman ring-aman/40',
        'waspada' => 'bg-waspada/10 text-waspada ring-waspada/40',
        'siaga' => 'bg-siaga/10 text-siaga ring-siaga/40',
        'awas' => 'bg-awas/15 text-awas ring-awas/50',
        'latihan' => 'bg-waspada/15 text-waspada ring-waspada/40',
        'pasir' => 'bg-pasir/10 text-pasir ring-pasir/40',
    ][$tone] ?? 'bg-inset text-ink-2 ring-line-strong';
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 whitespace-nowrap rounded-chip px-1.5 py-0.5 text-xs font-semibold uppercase tracking-[0.08em] ring-1 ring-inset '.$kelas]) }}>
    @if ($icon)<x-icon :name="$icon" class="size-3.5" />@endif{{ $slot }}
</span>
