{{--
    Tabel padat. Slot "head" berisi <tr><th>…; isi slot utama berisi baris <tr>.
    Menggulir horizontal di dalam wadahnya sendiri, tidak melebarkan halaman.
--}}
@props(['caption' => null])
<div {{ $attributes->merge(['class' => 'min-w-0 overflow-x-auto']) }}>
    <table class="tbl">
        @if ($caption)<caption class="sr-only">{{ $caption }}</caption>@endif
        @isset($head)<thead>{{ $head }}</thead>@endisset
        <tbody>{{ $slot }}</tbody>
    </table>
</div>
