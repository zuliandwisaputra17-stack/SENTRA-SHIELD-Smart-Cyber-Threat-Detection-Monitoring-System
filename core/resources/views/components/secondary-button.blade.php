<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex h-10 items-center justify-center gap-2 rounded-control border border-line-control bg-raised px-4 text-sm font-display font-bold uppercase tracking-[0.1em] text-ink transition-colors duration-150 ease-sentra hover:border-ink-3 hover:bg-inset focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:opacity-50']) }}>
    {{ $slot }}
</button>
