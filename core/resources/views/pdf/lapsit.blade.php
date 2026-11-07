<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $number }}</title>
    <style>
        @page { margin: 22mm 18mm 26mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.45; color: #111; }
        h1 { font-size: 14pt; text-align: center; margin: 0 0 4mm 0; letter-spacing: 0.5pt; }
        .meta { font-family: "DejaVu Sans Mono", monospace; font-size: 8.8pt; border-top: 1px solid #444; border-bottom: 1px solid #444; padding: 2mm 0; margin-bottom: 4mm; }
        .meta div { white-space: pre-wrap; }
        h2 { font-size: 11pt; margin: 4mm 0 1.5mm 0; }
        p { margin: 0 0 1.5mm 0; text-align: justify; }
        p.item { padding-left: 6mm; text-indent: -6mm; }
        p.ref { margin-top: 5mm; font-size: 8.5pt; color: #444; }
        .footer { position: fixed; bottom: -18mm; left: 0; right: 0; font-size: 7.5pt; color: #333; border-top: 1px solid #999; padding-top: 1.5mm; }
        .footer .hash { font-family: "DejaVu Sans Mono", monospace; }
    </style>
</head>
<body>
    <div class="footer">
        <div>SHA-256 isi LAPSIT: <span class="hash">{{ $contentHash }}</span></div>
        <div>{{ $number }} · SENTRA SHIELD</div>
    </div>

    @php
        $title = array_shift($lines);
        $meta = [];
        while ($lines !== [] && $lines[0] !== '') {
            $meta[] = array_shift($lines);
        }
    @endphp

    <h1>{{ $title }}</h1>
    <div class="meta">
        @foreach ($meta as $line)
            <div>{{ $line }}</div>
        @endforeach
    </div>

    @foreach ($lines as $line)
        @if ($line === '')
            @continue
        @elseif (preg_match('/^\d+\. [A-Z]/', $line))
            <h2>{{ $line }}</h2>
        @elseif (preg_match('/^[a-z]{1,2}\. /', $line))
            <p class="item">{{ $line }}</p>
        @elseif (str_starts_with($line, 'Rujukan bukti:'))
            <p class="ref">{{ $line }}</p>
        @else
            <p>{{ $line }}</p>
        @endif
    @endforeach
</body>
</html>
