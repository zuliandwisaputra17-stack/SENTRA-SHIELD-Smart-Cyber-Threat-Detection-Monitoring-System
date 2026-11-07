{{-- Daftar menu operasi berkelompok; dipakai sidebar dan laci HP. Butuh $kelompok. --}}
@foreach ($kelompok as $judul => $items)
    <div class="mt-4 first:mt-3">
        <p class="flex items-center gap-2 px-4 pb-1.5 font-stensil text-[13px] uppercase tracking-[0.2em] text-accent" aria-hidden="true">
            <span>{{ $judul }}</span><span class="h-px flex-1 bg-accent/25"></span>
        </p>
        <ul>
            @foreach ($items as $item)
                @php $aktif = request()->routeIs($item['active'] ?? $item['route']); @endphp
                <li>
                    <a href="{{ route($item['route']) }}" wire:navigate @if ($aktif) aria-current="page" @endif
                        @class([
                            'relative flex min-h-10 items-center gap-3 px-4 py-2 text-[14px] font-semibold uppercase tracking-[0.09em] transition-colors duration-150',
                            'bg-accent/10 text-ink' => $aktif,
                            'text-ink-2 hover:bg-raised hover:text-ink' => ! $aktif,
                        ])>
                        @if ($aktif)<span class="absolute inset-y-1 left-0 w-1 rounded-r-sm bg-accent" aria-hidden="true"></span>@endif
                        <x-icon :name="$item['icon']" @class(['size-[18px]', 'text-accent' => $aktif, 'text-ink-3' => ! $aktif]) />
                        <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                        @if (! empty($item['badge']))
                            <span class="grid min-w-6 place-items-center rounded-chip bg-awas px-1.5 font-mono text-xs font-bold text-accent-ink" aria-label="{{ $item['badge'] }} menunggu">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endforeach
