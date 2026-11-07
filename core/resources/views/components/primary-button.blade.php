<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex h-10 items-center justify-center gap-2 rounded-control bg-accent px-4 font-display text-sm font-bold uppercase tracking-[0.1em] text-accent-ink shadow-[inset_0_1px_0_rgb(255_255_255/0.25),inset_0_-2px_0_rgb(0_0_0/0.25)] transition-colors duration-150 ease-sentra hover:bg-accent-strong focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
