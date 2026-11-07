<?php

use App\Livewire\Actions\Logout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }

    /** Bilah atas (Tingkat Siaga, jumlah antrean) ikut diperbarui saat ada sinyal live. */
    #[On('sentra-refresh')]
    public function segarkan(): void {}
}; ?>

@php
    $user = auth()->user();
    $pendingApprovals = $user->can('putuskan-tindakan')
        ? \App\Models\ResponseAction::query()->where('status', \App\Enums\ActionStatus::Diusulkan)->distinct()->count('group_uuid')
        : 0;
    // Menu operasi dikelompokkan. Hanya halaman yang benar-benar ada; label tidak diubah.
    $kelompok = [
        'Operasi' => [
            ['route' => 'dashboard', 'icon' => 'layout-dashboard', 'label' => 'Beranda', 'ability' => 'lihat-dashboard'],
            ['route' => 'incidents.index', 'icon' => 'siren', 'label' => 'Insiden', 'ability' => 'lihat-dashboard', 'active' => 'incidents.*'],
            ['route' => 'campaigns.index', 'icon' => 'network', 'label' => 'Kampanye', 'ability' => 'lihat-kampanye', 'active' => 'campaigns.*'],
            ['route' => 'approvals', 'icon' => 'inbox', 'label' => 'Antrean Persetujuan', 'ability' => 'putuskan-tindakan', 'badge' => $pendingApprovals],
            ['route' => 'actions.index', 'icon' => 'shield-ban', 'label' => 'Tindakan', 'ability' => 'lihat-dashboard'],
        ],
        'Analisis' => [
            ['route' => 'readiness', 'icon' => 'target', 'label' => 'Uji Kesiapan', 'ability' => 'lihat-uji-kesiapan'],
            ['route' => 'sites', 'icon' => 'globe', 'label' => 'Penjaga Situs', 'ability' => 'lihat-penjaga-situs'],
            ['route' => 'audit', 'icon' => 'link-2', 'label' => 'Audit Log', 'ability' => 'lihat-audit'],
        ],
        'Sistem' => [
            ['route' => 'units-agents', 'icon' => 'server', 'label' => 'Satuan & Agent', 'ability' => 'lihat-satuan-agent'],
            ['route' => 'rules', 'icon' => 'list-checks', 'label' => 'Aturan Deteksi', 'ability' => 'kelola-sistem'],
            ['route' => 'users', 'icon' => 'user', 'label' => 'Pengguna', 'ability' => 'kelola-sistem'],
            ['route' => 'settings', 'icon' => 'settings', 'label' => 'Pengaturan', 'ability' => 'kelola-sistem'],
        ],
    ];
    $kelompok = collect($kelompok)->map(fn ($items) => array_values(array_filter($items, fn ($i) => $user->can($i['ability']))))->filter();

    // Tingkat Siaga keseluruhan untuk bilah atas: tertinggi di pohon satuan yang boleh dilihat.
    $pohon = \App\Models\Unit::tree($user->visibleUnitIds());
    $tingkat = $pohon->isEmpty() ? null : \App\Enums\AlertLevel::highest(...$pohon->map->effectiveAlertLevel()->all());
    $ikonTingkat = ['aman' => 'shield-check', 'waspada' => 'eye', 'siaga' => 'triangle-alert', 'awas' => 'siren'];
    $isiTingkat = ['aman' => 'bg-aman text-accent-ink', 'waspada' => 'bg-waspada text-accent-ink', 'siaga' => 'bg-siaga text-accent-ink', 'awas' => 'bg-awas text-accent-ink'];
@endphp

<div x-data="{ laci: false }" x-on:keydown.escape.window="laci = false" class="contents">
    {{-- Sidebar menu operasi (layar lebar) --}}
    <aside class="hidden border-r border-line bg-panel/95 lg:sticky lg:top-0 lg:col-start-1 lg:row-span-3 lg:row-start-1 lg:flex lg:h-screen lg:flex-col lg:self-start" aria-label="Menu operasi">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 border-b border-line px-4 py-4">
            <x-application-logo class="size-10 text-accent" />
            <span class="leading-none">
                <span class="block font-stensil text-[20px] tracking-[0.08em] text-ink">SENTRA SHIELD</span>
                <span class="mt-1 block text-[11px] font-semibold uppercase tracking-[0.2em] text-ink-3">Pusat Komando Siber</span>
            </span>
        </a>
        <nav class="flex-1 overflow-y-auto pb-4">
            @include('livewire.layout.menu-operasi')
        </nav>
        <div class="border-t border-line p-4">
            <x-ui.dogtag class="w-full">
                <span class="truncate font-semibold" x-data="{{ json_encode(['name' => $user->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                <span class="truncate text-ink-3">{{ $user->role->label() }}{{ $user->unit ? ' · '.$user->unit->code : '' }}</span>
            </x-ui.dogtag>
        </div>
    </aside>

    {{-- Bilah atas tipis --}}
    <header class="sticky top-0 z-30 border-b border-line bg-panel/90 backdrop-blur supports-[backdrop-filter]:bg-panel/80 lg:col-start-2 lg:row-start-2">
        <div class="flex h-12 items-center gap-3 px-3 sm:px-5">
            <button type="button" x-on:click="laci = true" aria-label="Buka menu operasi" :aria-expanded="laci.toString()" class="-ms-1 grid size-11 place-items-center rounded-control text-ink-2 hover:bg-raised hover:text-ink lg:hidden">
                <x-icon name="menu" class="size-6" />
            </button>
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2 lg:hidden">
                <x-application-logo class="size-7 text-accent" />
                <span class="hidden whitespace-nowrap font-stensil text-[16px] tracking-[0.06em] text-ink min-[400px]:inline">SENTRA SHIELD</span>
            </a>

            <span wire:ignore class="hidden items-center gap-1.5 font-mono text-[13px] text-ink-2 sm:inline-flex" title="Date-time group (WIB)"
                x-data="{ t: @js(dtg(now())) }" x-init="setInterval(() => { const d = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' })); const b = ['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGT','SEP','OKT','NOV','DES'][d.getMonth()]; const p = (n) => String(n).padStart(2, '0'); t = p(d.getDate()) + p(d.getHours()) + p(d.getMinutes()) + ' WIB ' + b + ' ' + String(d.getFullYear()).slice(2); }, 15000)">
                <x-icon name="clock" class="size-4 text-accent" /><span class="tracking-[0.06em]" x-text="t">{{ dtg(now()) }}</span>
            </span>

            <div class="ms-auto flex items-center gap-2 sm:gap-3">
                @if ($tingkat)
                    <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-chip px-2 py-1 font-display text-[13px] font-bold uppercase tracking-[0.12em] {{ $isiTingkat[$tingkat->value] }}" title="Tingkat Siaga keseluruhan">
                        <x-icon :name="$ikonTingkat[$tingkat->value]" class="size-4" /><span class="hidden sm:inline">Siaga:</span> {{ $tingkat->label() }}
                    </a>
                @endif
                <span data-sentra-live wire:ignore class="inline-flex h-7 items-center whitespace-nowrap rounded-chip border border-line-strong bg-canvas px-2 font-display text-xs font-bold uppercase tracking-[0.14em] text-ink-2" role="status" title="WebSocket belum tersambung; diperbarui tiap 3 detik">Polling</span>
                <x-dropdown align="right" width="w-60">
                    <x-slot name="trigger">
                        <button aria-label="Menu pengguna {{ $user->name }}" class="flex items-center gap-1.5 rounded-control px-1.5 py-1 text-start transition-colors duration-150 hover:bg-raised focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                            <span class="grid size-8 place-items-center rounded-full border border-line-control bg-inset font-stensil text-sm text-accent" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                            <x-icon name="chevron-down" class="size-4 text-ink-3" />
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="px-4 pb-2 pt-2">
                            <div class="font-semibold text-ink" x-data="{{ json_encode(['name' => $user->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                            <div class="text-xs text-ink-3">{{ $user->role->label() }}@if ($user->unit) · Satuan: <span class="text-ink-2">{{ $user->unit->name }}</span>@endif</div>
                        </div>
                        <div class="mb-1 border-t border-line-strong"></div>
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            <x-icon name="user" class="size-4 text-ink-3" />Profil
                        </x-dropdown-link>
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                <x-icon name="log-out" class="size-4 text-ink-3" />Keluar
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </header>

    {{-- Laci menu (HP/tablet) --}}
    <div x-show="laci" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Menu operasi">
        <div class="absolute inset-0 bg-black/70" x-on:click="laci = false" x-show="laci" x-transition.opacity></div>
        <aside class="absolute inset-y-0 left-0 flex w-[min(20rem,86vw)] flex-col border-r border-line-strong bg-panel shadow-overlay"
            x-show="laci" x-transition:enter="transition duration-200 ease-sentra" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full">
            <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                <span class="flex items-center gap-2.5">
                    <x-application-logo class="size-9 text-accent" />
                    <span class="font-stensil text-[18px] tracking-[0.08em] text-ink">SENTRA SHIELD</span>
                </span>
                <button type="button" x-on:click="laci = false" aria-label="Tutup menu" class="grid size-11 place-items-center rounded-control text-ink-2 hover:bg-raised hover:text-ink">
                    <x-icon name="x" class="size-6" />
                </button>
            </div>
            <nav class="flex-1 overflow-y-auto pb-4" x-on:click="if ($event.target.closest('a')) laci = false">
                @include('livewire.layout.menu-operasi')
            </nav>
            <div class="space-y-3 border-t border-line p-4">
                <x-ui.dogtag class="w-full">
                    <span class="truncate font-semibold">{{ $user->name }}</span>
                    <span class="truncate text-ink-3">{{ $user->role->label() }}{{ $user->unit ? ' · '.$user->unit->name : '' }}</span>
                </x-ui.dogtag>
                <div class="flex gap-2">
                    <x-ui.button variant="secondary" size="sm" icon="user" :href="route('profile')" wire:navigate class="flex-1">Profil</x-ui.button>
                    <x-ui.button variant="secondary" size="sm" icon="log-out" wire:click="logout" class="flex-1">Keluar</x-ui.button>
                </div>
            </div>
        </aside>
    </div>
</div>
