@php $tingkat = $unit->effectiveAlertLevel(); @endphp
<li role="treeitem" @if ($unit->children->isNotEmpty()) aria-expanded="true" @endif>
    <a href="{{ route('incidents.index', ['unit' => $unit->id]) }}" wire:navigate title="Lihat insiden {{ $unit->name }}"
        class="flex min-h-11 items-center gap-3 rounded-control border border-line bg-raised/40 px-3 py-2 transition-colors duration-150 ease-sentra hover:border-line-strong hover:bg-raised">
        <span @class(['grid size-8 shrink-0 place-items-center rounded-full ring-1 ring-inset', $tingkat->badgeClass(), 'animate-denyut' => $tingkat === \App\Enums\AlertLevel::Awas])
            @if ($tingkat === \App\Enums\AlertLevel::Awas) style="--denyut: var(--awas)" @endif>
            <x-icon name="building-2" class="size-4" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-ink">{{ $unit->name }}</span>
            <span class="block truncate font-mono text-[11px] text-ink-3">{{ $unit->level->label() }} · {{ $unit->code }}</span>
        </span>
        <x-alert-badge :level="$tingkat" />
    </a>

    @if ($unit->children->isNotEmpty())
        <ul role="group">
            @foreach ($unit->children as $child)
                @include('partials.unit-node', ['unit' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @endif
</li>
