<!DOCTYPE html>
<html lang="id" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="dark">

        <title>Masuk · {{ config('app.name', 'SENTRA SHIELD') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-canvas font-sans text-ink antialiased">
        <div class="relative flex min-h-screen flex-col overflow-hidden">
            {{-- Latar: grid peta + garis kontur + penggaris koordinat. Dekoratif, tanpa informasi. --}}
            <div class="latar-grid pointer-events-none absolute inset-0 [mask-image:radial-gradient(ellipse_at_35%_45%,black_25%,transparent_80%)]" aria-hidden="true"></div>
            <svg class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" fill="none" aria-hidden="true">
                <g class="stroke-accent/[0.12]" stroke-width="1.4">
                    <path d="M-20 620C160 560 300 660 470 600S760 500 960 560 1180 640 1240 600" />
                    <path d="M-20 660C170 610 310 700 480 645S770 545 965 605 1185 680 1240 650" />
                    <path d="M-20 700C180 660 320 740 490 690S780 590 970 650 1190 720 1240 700" />
                    <path d="M240 210C320 150 470 160 500 230S420 340 320 320 180 270 240 210Z" />
                    <path d="M270 220C330 180 440 186 462 236S405 310 330 296 230 262 270 220Z" />
                    <path d="M300 232C340 208 410 212 424 242S386 285 340 276 280 256 300 232Z" />
                </g>
            </svg>
            <span class="penggaris-x" aria-hidden="true"></span>
            <span class="penggaris-y" aria-hidden="true"></span>

            {{-- Strip status sistem: hanya fakta dari server ini. --}}
            <div class="relative border-b border-line bg-panel/85">
                <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-5 gap-y-1 px-4 py-2 font-mono text-xs text-ink-3 sm:px-6">
                    <span class="inline-flex items-center gap-1.5 text-aman"><span class="size-2 rounded-full bg-aman animate-denyut" style="--denyut: var(--aman)"></span>Core aktif</span>
                    <span class="inline-flex items-center gap-1.5"><x-icon name="clock" class="size-3.5 text-accent" />{{ dtg(now()) }}</span>
                    @if (request()->secure())
                        <span class="inline-flex items-center gap-1.5"><x-icon name="lock" class="size-3.5 text-accent" />Saluran terenkripsi TLS</span>
                    @endif
                    @if (config('sentra.lab.training_label'))
                        <span class="inline-flex items-center gap-1.5 text-waspada"><x-icon name="flag" class="size-3.5" />Lingkungan latihan · data latihan</span>
                    @endif
                </div>
            </div>

            <main class="relative mx-auto grid w-full max-w-6xl flex-1 items-center gap-10 px-4 py-10 sm:px-6 lg:grid-cols-[1.15fr_1fr] lg:gap-16">
                <section class="min-w-0">
                    <div class="flex items-center gap-4">
                        <x-application-logo class="size-16 text-accent sm:size-20" />
                        <div class="min-w-0">
                            <h1 class="font-stensil text-[40px] leading-none tracking-[0.08em] sm:text-[56px]">SENTRA SHIELD</h1>
                            <p class="mt-2 text-[13px] font-semibold uppercase tracking-[0.22em] text-accent">Pusat Komando Deteksi &amp; Respons Ancaman Siber</p>
                        </div>
                    </div>
                    <p class="mt-8 max-w-xl text-xl leading-relaxed text-ink-2">Sensor di tiap satuan, satu insiden yang bisa dijelaskan, dan respons yang mengikuti rantai komando.</p>
                    @if (config('sentra.lab.training_label'))
                        <x-ui.stempel tone="waspada" size="md" class="mt-6">Lingkungan latihan</x-ui.stempel>
                    @endif
                    <dl class="mt-8 hidden max-w-xl grid-cols-3 gap-4 sm:grid">
                        <div class="border-t-2 border-accent/50 pt-3"><dt class="font-stensil text-[17px] uppercase tracking-[0.1em] text-ink">Nyata</dt><dd class="mt-1 text-[15px] text-ink-2">Deteksi dari telemetri asli</dd></div>
                        <div class="border-t-2 border-accent/50 pt-3"><dt class="font-stensil text-[17px] uppercase tracking-[0.1em] text-ink">Terukur</dt><dd class="mt-1 text-[15px] text-ink-2">Uji Kesiapan dalam angka</dd></div>
                        <div class="border-t-2 border-accent/50 pt-3"><dt class="font-stensil text-[17px] uppercase tracking-[0.1em] text-ink">Siap pakai</dt><dd class="mt-1 text-[15px] text-ink-2">Satu perintah, tanpa internet</dd></div>
                    </dl>
                </section>

                <section class="panel w-full min-w-0 p-6 sm:p-8 lg:max-w-md lg:justify-self-end" aria-label="Masuk">
                    {{ $slot }}
                </section>
            </main>
        </div>
    </body>
</html>
