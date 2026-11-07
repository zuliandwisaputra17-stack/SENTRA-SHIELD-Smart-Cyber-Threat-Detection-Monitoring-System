{{--
    Cap stempel untuk status penting (tinta aus, miring). tone: aman|waspada|siaga|awas|pasir|accent.
    size: sm | md | lg. Teks tetap teks biasa (terbaca pembaca layar).
--}}
@props(['tone' => 'accent', 'size' => 'md'])
@php
    $warna = ['aman' => 'text-aman', 'waspada' => 'text-waspada', 'siaga' => 'text-siaga', 'awas' => 'text-awas', 'pasir' => 'text-pasir', 'accent' => 'text-accent'][$tone] ?? 'text-accent';
    $ukuran = ['sm' => 'text-[13px]', 'md' => 'text-lg', 'lg' => 'text-3xl'][$size] ?? 'text-lg';
@endphp
<span {{ $attributes->merge(['class' => 'stempel '.$warna.' '.$ukuran]) }}>{{ $slot }}</span>
