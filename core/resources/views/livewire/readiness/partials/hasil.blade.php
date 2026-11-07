{{-- Lencana hasil satu skenario Uji Kesiapan: ikon + teks. --}}
@if ($r->detected)
    <x-ui.badge tone="aman" icon="circle-check">Terdeteksi</x-ui.badge>
@elseif ($running)
    <x-ui.badge tone="accent" icon="loader-circle">{{ $r->started_at ? 'Menunggu deteksi' : 'Menunggu' }}</x-ui.badge>
@else
    <x-ui.badge tone="awas" icon="circle-x">Lolos</x-ui.badge>
@endif
