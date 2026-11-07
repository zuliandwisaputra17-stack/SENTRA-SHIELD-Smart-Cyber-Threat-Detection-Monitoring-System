<li wire:key="unit-{{ $unit->id }}">
    <div class="flex items-center justify-between gap-3 rounded-control px-2 py-1.5 hover:bg-raised/60" style="padding-left: {{ 0.5 + $depth * 1.25 }}rem">
        <div class="flex min-w-0 items-center gap-3">
            <x-ui.simbol-satuan :eselon="['kodam' => 'XX', 'korem' => 'X', 'kodim' => 'II'][$unit->level->value] ?? ''" :tone="$unit->alert_level->value" class="h-9 w-10" />
            <span class="min-w-0">
                <span class="block truncate font-semibold uppercase tracking-[0.05em] text-ink">{{ $unit->name }}</span>
                <span class="block font-mono text-[11px] text-ink-3">{{ $unit->level->label() }} · {{ $unit->code }}</span>
            </span>
        </div>
        @if ($canManage)
            <button wire:click="editUnit({{ $unit->id }})" class="text-xs link">Ubah</button>
        @endif
    </div>
    @if ($unit->children->isNotEmpty())
        <ul>
            @foreach ($unit->children as $child)
                @include('livewire.admin.partials.unit-row', ['unit' => $child, 'depth' => $depth + 1, 'canManage' => $canManage])
            @endforeach
        </ul>
    @endif
</li>
