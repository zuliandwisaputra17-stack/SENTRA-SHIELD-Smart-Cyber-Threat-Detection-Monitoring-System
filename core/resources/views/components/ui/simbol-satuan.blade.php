{{--
    Simbol satuan gaya peta militer GENERIK (bingkai persegi + tanda eselon + tongkat markas).
    Bukan lambang resmi mana pun. eselon: 'XX' (Kodam), 'X' (Korem), 'II' (Kodim). tone: warna status.
--}}
@props(['eselon' => 'II', 'tone' => 'accent'])
@php
    $kelas = [
        'aman' => ['fill-aman/15 stroke-aman', 'stroke-aman/70'],
        'waspada' => ['fill-waspada/15 stroke-waspada', 'stroke-waspada/70'],
        'siaga' => ['fill-siaga/15 stroke-siaga', 'stroke-siaga/70'],
        'awas' => ['fill-awas/15 stroke-awas', 'stroke-awas/70'],
        'accent' => ['fill-accent/15 stroke-accent', 'stroke-accent/70'],
    ][$tone] ?? ['fill-accent/15 stroke-accent', 'stroke-accent/70'];
@endphp
<svg viewBox="0 0 48 44" fill="none" aria-hidden="true" focusable="false" {{ $attributes->merge(['class' => 'h-10 w-11 shrink-0']) }}>
    <text x="24" y="9" text-anchor="middle" font-family="Saira Condensed, sans-serif" font-weight="700" font-size="9" letter-spacing="1" class="fill-ink">{{ $eselon }}</text>
    <rect x="6" y="13" width="36" height="22" rx="1" stroke-width="2" class="{{ $kelas[0] }}" />
    <path d="M6 35v8" stroke-width="1.6" class="stroke-ink" />
    <path d="M11 28L19 20L27 28L35 20" stroke-width="1.8" class="{{ $kelas[1] }}" />
</svg>
