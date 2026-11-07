@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-control border-line-control bg-canvas text-ink placeholder:text-ink-3 focus:border-accent focus:ring-accent/40 disabled:opacity-60']) }}>
