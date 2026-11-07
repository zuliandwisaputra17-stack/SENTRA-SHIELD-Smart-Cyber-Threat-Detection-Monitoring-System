{{-- Keadaan kosong: menyebut apa yang akan muncul di sini dan dari mana datangnya. --}}
@props(['icon' => 'radar', 'title'])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-2 px-4 py-10 text-center']) }}>
    <span class="grid size-11 place-items-center rounded-full border border-line-strong bg-inset text-ink-3">
        <x-icon :name="$icon" class="size-5" />
    </span>
    <p class="font-medium text-ink-2">{{ $title }}</p>
    @if (trim($slot) !== '')
        <p class="max-w-sm text-sm text-ink-3">{{ $slot }}</p>
    @endif
</div>
