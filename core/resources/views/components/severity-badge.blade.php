@props(['severity', 'size' => 'sm'])
{{-- Lencana severity: meter 4 batang (tingkat terbaca tanpa warna) + teks. size: sm | lg. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center whitespace-nowrap rounded-chip font-semibold uppercase tracking-[0.08em] ring-1 ring-inset '.($size === 'lg' ? 'gap-2 px-2.5 py-1 text-sm' : 'gap-1.5 px-1.5 py-0.5 text-xs').' '.$severity->badgeClass()]) }}>
    <svg viewBox="0 0 10 10" class="{{ $size === 'lg' ? 'size-3.5' : 'size-3' }}" aria-hidden="true" focusable="false">
        @foreach ([[0, 7], [2.6, 5], [5.2, 3], [7.8, 1]] as $i => [$x, $y])
            <rect x="{{ $x }}" y="{{ $y }}" width="1.9" height="{{ 10 - $y }}" rx="0.4" fill="currentColor" fill-opacity="{{ $i <= $severity->rank() ? 1 : 0.25 }}" />
        @endforeach
    </svg>
    {{ $severity->label() }}
</span>
