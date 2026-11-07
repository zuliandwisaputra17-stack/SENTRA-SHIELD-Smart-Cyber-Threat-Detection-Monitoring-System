<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex h-10 items-center justify-center gap-2 rounded-control bg-awas px-4 text-sm font-display font-bold uppercase tracking-[0.1em] text-[#1A0505] transition-colors duration-150 ease-sentra hover:bg-awas/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-awas focus-visible:ring-offset-2 focus-visible:ring-offset-canvas']) }}>
    {{ $slot }}
</button>
