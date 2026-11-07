<x-app-layout>
    <x-slot name="title">{{ $event->reference() }}</x-slot>
    <x-slot name="header">
        <p class="font-stensil text-[15px] uppercase tracking-[0.18em] text-accent" aria-hidden="true">Lembar bukti</p>
        <h2 class="mt-1 font-stensil text-[30px] uppercase leading-tight tracking-[0.06em] text-ink">Event <span class="font-mono normal-case">{{ $event->reference() }}</span></h2>
        <p class="mt-1 text-sm text-ink-3">Event telemetri asli dari agent. Event tidak bisa diubah atau dihapus dari aplikasi.</p>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-4 px-4 py-5 sm:px-6 lg:px-8">
        <section class="panel p-4 sm:p-6">
            <p class="flex items-baseline gap-2" aria-hidden="true"><span class="nomor-bagian">01 //</span><span class="font-stensil text-[19px] uppercase leading-none tracking-[0.08em] text-ink">Identitas event</span></p>
            <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="label-caps">Waktu kejadian</dt><dd class="text-ink">{{ waktu_wib($event->occurred_at, 'd/m/Y H:i:s.v') }}</dd></div>
                <div><dt class="label-caps">Diterima Core</dt><dd class="text-ink">{{ waktu_wib($event->created_at, 'd/m/Y H:i:s.v') }}</dd></div>
                <div><dt class="label-caps">Sumber</dt><dd class="text-ink">{{ $event->source->label() }}</dd></div>
                <div><dt class="label-caps">Satuan / agent</dt><dd class="text-ink">{{ $event->unit->name }} / {{ $event->agent?->hostname ?? 'Core' }}</dd></div>
                <div><dt class="label-caps">IP sumber</dt><dd class="font-mono text-ink">{{ $event->src_ip ?? '—' }}</dd></div>
                <div><dt class="label-caps">Port tujuan</dt><dd class="font-mono text-ink">{{ $event->dst_port ?? '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="label-caps">Ringkasan</dt><dd class="text-ink break-all">{{ $event->summary() }}</dd></div>
                @if ($event->is_test)
                    <div class="sm:col-span-2"><dd class="inline-block rounded bg-accent/10 px-2 py-0.5 text-xs text-accent">Event Uji Kesiapan (readiness_test)</dd></div>
                @endif
            </dl>
        </section>

        <section class="panel p-4 sm:p-6">
            <p class="flex items-baseline gap-2" aria-hidden="true"><span class="nomor-bagian">02 //</span><span class="font-stensil text-[19px] uppercase leading-none tracking-[0.08em] text-ink">Deteksi</span></p>
            <h3 class="mt-1 text-sm font-semibold text-ink-2">Deteksi dari event ini</h3>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($event->detections as $d)
                    <li class="text-ink-2">
                        <span class="chip text-ink">{{ $d->rule->code }}</span> {{ $d->rule->name }} ({{ $d->attack_id }})
                        @if ($d->incident && auth()->user()->can('view', $d->incident))
                            · <a href="{{ route('incidents.show', $d->incident) }}" class="link">Insiden #{{ $d->incident->id }}</a>
                        @endif
                    </li>
                @empty
                    <li class="text-ink-3">Tidak memicu aturan apa pun (event normal atau IP di daftar putih).</li>
                @endforelse
            </ul>
        </section>

        <section class="panel p-4 sm:p-6">
            <p class="flex items-baseline gap-2" aria-hidden="true"><span class="nomor-bagian">03 //</span><span class="font-stensil text-[19px] uppercase leading-none tracking-[0.08em] text-ink">Data mentah</span></p>
            <h3 class="mt-1 text-sm font-semibold text-ink-2">Payload (dinormalkan agent)</h3>
            <pre class="mt-3 overflow-x-auto rounded-control border border-line bg-canvas p-3 text-xs text-ink-2">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            <h3 class="mt-5 text-sm font-semibold text-ink-2">Baris mentah</h3>
            <pre class="mt-3 overflow-x-auto whitespace-pre-wrap break-all rounded-control border border-line bg-canvas p-3 text-xs text-ink-2">{{ $event->raw ?? '—' }}</pre>
        </section>
    </div>
</x-app-layout>
