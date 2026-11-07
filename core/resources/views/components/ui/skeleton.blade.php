{{-- Kerangka pemuatan: baris abu berkilau, menahan ruang agar tata letak tidak bergeser. --}}
@props(['lines' => 3])
<div {{ $attributes->merge(['class' => 'space-y-2']) }} aria-hidden="true">
    @for ($i = 0; $i < $lines; $i++)
        <div class="skeleton h-3" style="width: {{ [92, 78, 64, 85, 70][$i % 5] }}%"></div>
    @endfor
</div>
