<div>
    <x-ui.page-header title="Kampanye" icon="network">
        Serangan terkoordinasi: IP yang sama memicu insiden di dua satuan atau lebih dalam 24 jam. Skor tiap insiden tertaut dikalikan 1,3.
    </x-ui.page-header>
    <div class="mx-auto max-w-[1600px] px-4 py-5 sm:px-6 lg:px-8">
        <x-ui.panel nomor="01" kode="Operasi musuh" title="Kampanye lintas satuan" icon="network" :flush="true">
            <x-ui.table caption="Daftar kampanye lintas satuan">
                <x-slot:head>
                    <tr><th>Kampanye</th><th>Satuan terdampak</th><th>Insiden</th><th>Skor tertinggi</th><th>Status</th><th>Rentang waktu</th></tr>
                </x-slot:head>
                @forelse ($campaigns as $c)
                    <tr wire:key="kmp-{{ $c->id }}">
                        <td><a href="{{ route('campaigns.show', $c) }}" wire:navigate class="link inline-flex items-center gap-2 font-semibold"><svg viewBox="0 0 24 24" class="size-5 shrink-0" aria-hidden="true"><path d="M12 1L23 12L12 23L1 12Z" stroke-width="2" class="fill-awas/15 stroke-awas" /><path d="M8 8l8 8M16 8l-8 8" stroke-width="2" class="stroke-awas" /></svg>#{{ $c->id }} <span class="font-mono">{{ $c->src_ip }}</span></a></td>
                        <td class="font-stensil text-lg text-ink">{{ $c->unit_count }} <span class="font-sans text-sm text-ink-2">satuan</span></td>
                        <td class="data text-ink-2">{{ $c->incidents_count }}</td>
                        <td>
                            @if ($c->incidents_max_score !== null)
                                <span class="inline-flex items-center gap-2"><span class="data font-semibold text-ink">{{ $c->incidents_max_score }}</span> <x-severity-badge :severity="\App\Enums\Severity::fromScore($c->incidents_max_score)" /></span>
                            @endif
                        </td>
                        <td><x-ui.badge :tone="$c->status === \App\Enums\CampaignStatus::Aktif ? 'awas' : 'pasir'">{{ $c->status->label() }}</x-ui.badge></td>
                        <td class="data whitespace-nowrap text-ink-3">{{ waktu_wib($c->first_seen_at, 'd/m H:i') }} – {{ waktu_wib($c->last_seen_at, 'H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty icon="network" title="Belum ada kampanye lintas satuan.">Kampanye terbentuk otomatis saat satu IP memicu insiden di dua satuan atau lebih.</x-ui.empty></td></tr>
                @endforelse
            </x-ui.table>
        </x-ui.panel>
    </div>
</div>
