// Membuat resources/views/components/icon.blade.php dari ikon Lucide (lucide-static, ISC).
// Hanya ikon yang dipakai yang disalin, sebagai SVG inline (tanpa CDN, jalan offline).
// Jalankan ulang setelah menambah nama ikon: node scripts/ikon.mjs
import { readFileSync, writeFileSync } from 'node:fs';

const ikon = [
    'activity', 'arrow-right', 'ban', 'check', 'chevron-down', 'chevron-right', 'circle-alert', 'circle-check',
    'circle-x', 'clock', 'crosshair', 'download', 'external-link', 'eye', 'eye-off', 'factory', 'file-lock',
    'file-text', 'filter', 'fingerprint', 'flag', 'gauge', 'globe', 'hash', 'history', 'inbox', 'key-round',
    'layout-dashboard', 'link', 'link-2', 'list-checks', 'lock', 'log-out', 'menu', 'network', 'octagon-alert',
    'radar', 'radio', 'refresh-cw', 'scan-search', 'search', 'server', 'settings', 'shield', 'shield-alert',
    'shield-ban', 'shield-check', 'siren', 'stamp', 'target', 'terminal', 'timer', 'triangle-alert', 'unlink',
    'user', 'waypoints', 'wifi-off', 'workflow', 'x', 'zap',
    'arrow-up-right', 'building-2', 'bug', 'chart-column', 'chart-line', 'circle-dot', 'cpu', 'git-commit-horizontal',
    'layers', 'loader-circle', 'log-in', 'map-pin', 'monitor', 'plug', 'scroll-text', 'shield-x', 'signal',
    'smartphone', 'user-check', 'users', 'arrow-left', 'chevron-left',
];

const peta = ikon.map((nama) => {
    const svg = readFileSync(new URL(`../node_modules/lucide-static/icons/${nama}.svg`, import.meta.url), 'utf8');
    const isi = svg.slice(svg.indexOf('>', svg.indexOf('<svg')) + 1, svg.lastIndexOf('</svg>'))
        .replace(/\s*\n\s*/g, '').replace(/\s+\/>/g, '/>').trim();
    if (isi.includes("'")) throw new Error(`ikon ${nama} memuat tanda kutip tunggal`);
    return `        '${nama}' => '${isi}',`;
});

writeFileSync(new URL('../resources/views/components/icon.blade.php', import.meta.url), `{{--
    Ikon SVG inline dari Lucide (lucide-static, lisensi ISC). Dibuat oleh scripts/ikon.mjs; jangan diubah manual.
    Pemakaian: <x-icon name="shield-check" class="size-4" />. Dekoratif secara bawaan (aria-hidden);
    beri label="..." bila ikon berdiri sendiri tanpa teks.
--}}
@props(['name', 'label' => null, 'stroke' => 1.75])
@php
    $paths = [
${peta.join('\n')}
    ];
@endphp
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round"
    {{ $attributes->merge(['class' => 'size-4 shrink-0']) }}
    @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" focusable="false" @endif
>{!! $paths[$name] ?? '' !!}</svg>
`);
console.log(`${ikon.length} ikon ditulis ke resources/views/components/icon.blade.php`);
