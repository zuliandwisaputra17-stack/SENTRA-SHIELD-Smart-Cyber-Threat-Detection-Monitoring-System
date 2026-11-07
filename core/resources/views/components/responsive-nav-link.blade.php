@props(['active'])
@php
$classes = ($active ?? false)
            ? 'flex w-full items-center gap-2.5 border-l-2 border-accent bg-accent/10 py-2.5 pe-4 ps-3 text-start text-base font-medium text-ink focus:outline-none transition-colors duration-150'
            : 'flex w-full items-center gap-2.5 border-l-2 border-transparent py-2.5 pe-4 ps-3 text-start text-base font-medium text-ink-2 hover:border-line-strong hover:bg-raised hover:text-ink focus:outline-none focus:bg-raised transition-colors duration-150';
@endphp
<a {{ $attributes->merge(['class' => $classes]) }} @if ($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
