{{--
    Panel = pelat besi bergaris dengan braket sudut. Judul stensil kapital.
    nomor + kode memberi kepala gaya briefing: "01 // SITUASI", lalu title sebagai keterangan.
    penggaris menambah tanda tick koordinat di tepi atas/kiri. flush: isi tanpa padding.
--}}
@props(['title' => null, 'subtitle' => null, 'icon' => null, 'headingId' => null, 'flush' => false, 'nomor' => null, 'kode' => null, 'penggaris' => false])
<section {{ $attributes->merge(['class' => 'panel min-w-0']) }} @if ($headingId) aria-labelledby="{{ $headingId }}" @endif>
    @if ($penggaris)<span class="penggaris-x" aria-hidden="true"></span><span class="penggaris-y" aria-hidden="true"></span>@endif
    @if ($title || $kode || isset($actions))
        <header class="panel-head">
            <div class="min-w-0">
                @if ($kode)
                    <p class="flex items-baseline gap-2" aria-hidden="true">
                        @if ($nomor)<span class="nomor-bagian">{{ $nomor }} //</span>@endif
                        <span class="font-stensil text-[19px] uppercase leading-none tracking-[0.08em] text-ink">{{ $kode }}</span>
                    </p>
                    @if ($title)
                        <h3 @if ($headingId) id="{{ $headingId }}" @endif class="mt-1 flex items-center gap-1.5 text-sm font-semibold text-ink-2">
                            @if ($icon)<x-icon :name="$icon" class="size-4 text-accent" />@endif{{ $title }}
                        </h3>
                    @endif
                @elseif ($title)
                    <h3 @if ($headingId) id="{{ $headingId }}" @endif class="panel-title flex items-center gap-2">
                        @if ($nomor)<span class="nomor-bagian normal-case">{{ $nomor }} //</span>@endif
                        @if ($icon)<x-icon :name="$icon" class="size-4 text-accent" />@endif{{ $title }}
                    </h3>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-ink-3">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div @class(['p-4 sm:p-5' => ! $flush])>{{ $slot }}</div>
</section>
