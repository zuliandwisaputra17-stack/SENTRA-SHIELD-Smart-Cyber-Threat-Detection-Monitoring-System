{{-- Kelas warna penanda panah (agar ikut dibangun Tailwind): fill-pasir fill-waspada fill-siaga fill-awas --}}
{{--
    Peta operasi (SVG, layar sedang ke atas): satuan kawan dengan simbol generik gaya peta militer,
    garis komando induk-anak, penyerang (IP sumber insiden terbuka) sebagai belah ketupat di zona luar,
    dan panah serangan ke satuan sasaran. Semua dari data asli: $tree, $openIncidents.
--}}
@php
    $W = 760; $H = 640; $batasX = 560;
    $eselon = ['kodam' => 'XX', 'korem' => 'X', 'kodim' => 'II'];
    $warna = [
        'aman' => ['fill-aman/15 stroke-aman', 'fill-aman', 'stroke-aman'],
        'waspada' => ['fill-waspada/15 stroke-waspada', 'fill-waspada', 'stroke-waspada'],
        'siaga' => ['fill-siaga/15 stroke-siaga', 'fill-siaga', 'stroke-siaga'],
        'awas' => ['fill-awas/15 stroke-awas', 'fill-awas', 'stroke-awas'],
    ];
    $panahSeverity = ['rendah' => 'stroke-pasir', 'sedang' => 'stroke-waspada', 'tinggi' => 'stroke-siaga', 'kritis' => 'stroke-awas'];

    // Susun satuan per kedalaman pohon.
    $lapisan = [];
    $induk = [];
    $telusur = function ($units, $d, $parent) use (&$telusur, &$lapisan, &$induk) {
        foreach ($units as $u) {
            $lapisan[$d][] = $u;
            $induk[$u->id] = $parent;
            $telusur($u->children, $d + 1, $u->id);
        }
    };
    $telusur($tree, 0, null);
    $jumlahLapis = max(1, count($lapisan));
    $langkahY = $jumlahLapis > 1 ? min(190, 420 / ($jumlahLapis - 1)) : 0;
    $pos = [];
    foreach ($lapisan as $d => $units) {
        $n = count($units);
        foreach ($units as $i => $u) {
            $pos[$u->id] = ['x' => 30 + ($batasX - 60) / $n * ($i + 0.5), 'y' => 110 + $d * $langkahY, 'u' => $u];
        }
    }

    // Penyerang: kelompokkan insiden terbuka per IP sumber, urut skor tertinggi.
    $penyerang = $openIncidents->whereNotNull('src_ip')->groupBy('src_ip')
        ->map(fn ($g, $ip) => ['ip' => $ip, 'insiden' => $g->filter(fn ($i) => isset($pos[$i->unit_id])), 'skor' => $g->max('score')])
        ->filter(fn ($p) => $p['insiden']->isNotEmpty())
        ->sortByDesc('skor')->values();
    $lainnya = max(0, $penyerang->count() - 4);
    $penyerang = $penyerang->take(4);
    $kolom = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
@endphp
<svg viewBox="0 0 {{ $W }} {{ $H }}" class="h-auto w-full" role="img" aria-labelledby="peta-judul peta-isi">
    <title id="peta-judul">Peta operasi satuan</title>
    <desc id="peta-isi">{{ count($pos) }} satuan kawan; {{ $penyerang->count() }} IP penyerang aktif{{ $penyerang->isNotEmpty() ? ': '.$penyerang->pluck('ip')->join(', ') : '' }}.</desc>
    <defs>
        <pattern id="arsir-luar" width="10" height="10" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
            <path d="M0 0v10" class="stroke-awas/15" stroke-width="3" />
        </pattern>
        @foreach (['pasir', 'waspada', 'siaga', 'awas'] as $m)
            <marker id="panah-{{ $m }}" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                <path d="M0 0L10 5L0 10z" class="fill-{{ $m }}" />
            </marker>
        @endforeach
    </defs>

    {{-- Grid koordinat --}}
    <g aria-hidden="true">
        @for ($x = 0; $x <= $W; $x += 20)
            <path d="M{{ $x }} 0V{{ $H }}" class="{{ $x % 80 === 0 ? 'stroke-accent/[0.14]' : 'stroke-accent/[0.05]' }}" stroke-width="1" />
        @endfor
        @for ($y = 0; $y <= $H; $y += 20)
            <path d="M0 {{ $y }}H{{ $W }}" class="{{ $y % 80 === 0 ? 'stroke-accent/[0.14]' : 'stroke-accent/[0.05]' }}" stroke-width="1" />
        @endfor
        @for ($k = 0; $k < 10; $k++)
            <text x="{{ $k * 80 + 40 }}" y="13" text-anchor="middle" font-family="Fira Code, monospace" font-size="10" class="fill-accent/70">{{ $kolom[$k] }}</text>
        @endfor
        @for ($k = 0; $k < 8; $k++)
            <text x="6" y="{{ $k * 80 + 44 }}" font-family="Fira Code, monospace" font-size="10" class="fill-accent/70">{{ $k + 1 }}</text>
        @endfor
        {{-- Garis kontur (medan) --}}
        <g fill="none" class="stroke-accent/[0.13]" stroke-width="1.2">
            <path d="M-10 575C80 540 150 600 250 565S420 510 560 540" />
            <path d="M-10 600C90 575 170 625 270 595S430 545 560 575" />
            <path d="M-10 625C100 605 180 650 290 622S440 580 560 605" />
            <path d="M90 200C150 150 260 160 285 215S220 300 150 285 60 245 90 200Z" />
            <path d="M118 210C160 178 238 184 255 222S210 275 160 266 98 238 118 210Z" />
            <path d="M380 330C440 295 530 315 532 365S460 420 410 405 340 365 380 330Z" />
        </g>
        {{-- Zona luar (WAN) --}}
        <rect x="{{ $batasX + 14 }}" y="20" width="{{ $W - $batasX - 20 }}" height="{{ $H - 30 }}" fill="url(#arsir-luar)" />
        <path d="M{{ $batasX + 14 }} 20V{{ $H - 10 }}" class="stroke-awas/60" stroke-width="1.5" stroke-dasharray="8 6" />
        <text x="{{ $batasX + 26 }}" y="40" font-family="Saira Stencil One, sans-serif" font-size="14" letter-spacing="2" class="fill-awas">ZONA LUAR · WAN</text>
        {{-- Mata angin --}}
        <g transform="translate(36 52)">
            <path d="M0 -16L6 6L0 1L-6 6Z" class="fill-accent" />
            <text x="0" y="22" text-anchor="middle" font-family="Saira Condensed, sans-serif" font-weight="700" font-size="11" class="fill-accent">U</text>
        </g>
    </g>

    {{-- Garis komando induk ke anak --}}
    <g fill="none" class="stroke-accent/50" stroke-width="1.5" aria-hidden="true">
        @foreach ($pos as $id => $p)
            @if (($induk[$id] ?? null) && isset($pos[$induk[$id]]))
                @php $q = $pos[$induk[$id]]; @endphp
                @php $tengah = ($q['y'] + 92 + $p['y'] - 40) / 2; @endphp
                <path d="M{{ round($q['x']) }} {{ round($q['y'] + 92) }}V{{ round($tengah) }}H{{ round($p['x']) }}V{{ round($p['y'] - 40) }}" />
            @endif
        @endforeach
    </g>

    {{-- Panah serangan --}}
    <g fill="none" stroke-width="2" stroke-dasharray="7 5" aria-hidden="true">
        @foreach ($penyerang as $i => $pr)
            @php $ay = 120 + $i * 125; $ax = 668; @endphp
            {{-- Satu panah per satuan sasaran, berwarna severity tertinggi insiden IP itu di satuan tersebut. --}}
            @foreach ($pr['insiden']->groupBy('unit_id')->map(fn ($g) => $g->sortByDesc(fn ($i) => $i->severity->rank())->first()) as $inc)
                @php
                    $t = $pos[$inc->unit_id];
                    $mk = ['rendah' => 'pasir', 'sedang' => 'waspada', 'tinggi' => 'siaga', 'kritis' => 'awas'][$inc->severity->value];
                @endphp
                <path d="M{{ $ax - 28 }} {{ $ay }}C{{ $ax - 140 }} {{ $ay }} {{ round($t['x'] + 150) }} {{ round($t['y']) }} {{ round($t['x'] + 46) }} {{ round($t['y']) }}"
                    class="{{ $panahSeverity[$inc->severity->value] }} animate-serbu" marker-end="url(#panah-{{ $mk }})" />
            @endforeach
        @endforeach
    </g>

    {{-- Satuan kawan --}}
    @foreach ($pos as $id => $p)
        @php $u = $p['u']; $lv = $u->effectiveAlertLevel(); $w = $warna[$lv->value]; $x = round($p['x']); $y = round($p['y']); @endphp
        <a href="{{ route('incidents.index', ['unit' => $u->id]) }}" aria-label="{{ $u->name }}: Tingkat Siaga {{ $lv->label() }}">
            <text x="{{ $x }}" y="{{ $y - 28 }}" text-anchor="middle" font-family="Saira Condensed, sans-serif" font-weight="700" font-size="12" letter-spacing="2" class="fill-ink">{{ $eselon[$u->level->value] ?? '' }}</text>
            <rect x="{{ $x - 38 }}" y="{{ $y - 22 }}" width="76" height="44" rx="1" stroke-width="2.2" class="{{ $w[0] }}" />
            <path d="M{{ $x - 38 }} {{ $y + 22 }}v18" stroke-width="1.8" class="stroke-ink" />
            <path d="M{{ $x - 24 }} {{ $y + 8 }}L{{ $x - 8 }} {{ $y - 8 }}L{{ $x + 8 }} {{ $y + 8 }}L{{ $x + 24 }} {{ $y - 8 }}" fill="none" stroke-width="1.8" class="{{ $w[2] }}" stroke-opacity=".8" />
            @if ($lv === \App\Enums\AlertLevel::Awas)
                <rect x="{{ $x - 44 }}" y="{{ $y - 28 }}" width="88" height="56" rx="2" fill="none" stroke-width="1.5" class="{{ $w[2] }} animate-pulse" stroke-opacity=".55" />
            @endif
            <text x="{{ $x }}" y="{{ $y + 52 }}" text-anchor="middle" font-family="Saira Condensed, sans-serif" font-weight="600" font-size="15" class="fill-ink">{{ $u->name }}</text>
            <text x="{{ $x }}" y="{{ $y + 67 }}" text-anchor="middle" font-family="Fira Code, monospace" font-size="10" class="fill-ink-3">{{ $u->code }}</text>
            <text x="{{ $x }}" y="{{ $y + 84 }}" text-anchor="middle" font-family="Saira Stencil One, sans-serif" font-size="13" letter-spacing="2" class="{{ $w[1] }}">{{ mb_strtoupper($lv->label()) }}</text>
        </a>
    @endforeach

    {{-- Penyerang (belah ketupat = pihak musuh) --}}
    @forelse ($penyerang as $i => $pr)
        @php $ay = 120 + $i * 125; $ax = 668; @endphp
        <g>
            <path d="M{{ $ax }} {{ $ay - 26 }}L{{ $ax + 26 }} {{ $ay }}L{{ $ax }} {{ $ay + 26 }}L{{ $ax - 26 }} {{ $ay }}Z" stroke-width="2.2" class="fill-awas/15 stroke-awas" />
            <path d="M{{ $ax - 9 }} {{ $ay - 9 }}l18 18M{{ $ax + 9 }} {{ $ay - 9 }}l-18 18" stroke-width="2" class="stroke-awas" />
            <text x="{{ $ax }}" y="{{ $ay + 42 }}" text-anchor="middle" font-family="Fira Code, monospace" font-size="11" font-weight="600" class="fill-ink">{{ $pr['ip'] }}</text>
            <text x="{{ $ax }}" y="{{ $ay + 56 }}" text-anchor="middle" font-family="Saira Condensed, sans-serif" font-size="11" class="fill-ink-3">{{ $pr['insiden']->pluck('unit_id')->unique()->count() }} satuan sasaran</text>
        </g>
    @empty
        <text x="{{ ($batasX + $W) / 2 + 6 }}" y="{{ $H / 2 }}" text-anchor="middle" font-family="Saira Condensed, sans-serif" font-size="13" class="fill-ink-3">Tidak ada penyerang aktif</text>
    @endforelse
    @if ($lainnya > 0)
        <text x="668" y="{{ $H - 16 }}" text-anchor="middle" font-family="Saira Condensed, sans-serif" font-size="12" class="fill-ink-2">+{{ $lainnya }} IP lain</text>
    @endif
</svg>
