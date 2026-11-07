@props(['active'])
@php
$classes = ($active ?? false)
            ? 'relative inline-flex h-full items-center gap-1.5 whitespace-nowrap px-1 text-sm font-medium text-ink after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:bg-accent focus-visible:outline-none focus-visible:text-accent transition-colors duration-150'
            : 'relative inline-flex h-full items-center gap-1.5 whitespace-nowrap px-1 text-sm font-medium text-ink-3 hover:text-ink focus-visible:outline-none focus-visible:text-accent transition-colors duration-150';
@endphp
<a {{ $attributes->merge(['class' => $classes]) }} @if ($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
