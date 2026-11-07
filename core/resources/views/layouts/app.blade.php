<!DOCTYPE html>
<html lang="id" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="dark">
        {{-- Kunci publik Reverb dibaca saat runtime (dibuat acak saat lab pertama kali jalan). --}}
        <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
        <meta name="sentra-user" content="{{ auth()->id() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'SENTRA SHIELD') }}</title>

        {{-- Tanpa font/CDN eksternal: aplikasi harus berjalan penuh tanpa internet. --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-canvas text-ink">
        <div class="min-h-screen lg:grid lg:grid-cols-[15.5rem_minmax(0,1fr)] lg:grid-rows-[auto_auto_1fr]">
            {{-- FR-15: data lab ditandai DATA LATIHAN; akun Demo hanya melihat. --}}
            @if (config('sentra.lab.training_label') || auth()->user()?->role === \App\Enums\Role::Demo)
                <div class="pita-latihan border-b border-waspada/30 px-4 py-1 text-center font-stensil text-[12px] uppercase tracking-[0.18em] text-waspada lg:col-start-2 lg:row-start-1" data-testid="label-latihan">
                    Data latihan · telemetri asli dari serangan di lab, bukan data operasional
                    @if (auth()->user()?->role === \App\Enums\Role::Demo)
                        <span>· mode demo, hanya lihat</span>
                    @endif
                </div>
            @endif
            <livewire:layout.navigation />

            <div class="min-w-0 lg:col-start-2 lg:row-start-3">
                @if (isset($header))
                    <header class="border-b border-line">
                        <div class="mx-auto max-w-[1600px] px-4 py-5 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
