{{--
    Papan gaya OLIVE DRAB / OPS DARAT: palet, tipografi, dan semua komponen dalam satu halaman.
    Hanya untuk tinjauan desain; tidak dipasang di route aplikasi.
--}}
@php
    use App\Enums\ActionStatus;
    use App\Enums\AlertLevel;
    use App\Enums\Severity;

    $palet = [
        ['Latar olive-hitam', '#0E110A', 'bg-canvas', '—'],
        ['Pelat panel (olive drab)', '#181D10', 'bg-panel', '—'],
        ['Pelat terangkat', '#20271A', 'bg-raised', '—'],
        ['Sumur / baris aktif', '#283020', 'bg-inset', '—'],
        ['Garis', '#343C26', 'bg-line', '—'],
        ['Batas kontrol', '#8A8463', 'bg-line-control', '4,5:1 (non-teks)'],
        ['Khaki / tan (aksen)', '#C9B57A', 'bg-accent', '8,5:1'],
        ['Teks krem', '#ECE4C8', 'bg-ink', '13,5:1'],
        ['Teks sekunder', '#CEC6A6', 'bg-ink-2', '10,0:1'],
        ['Label', '#A69F80', 'bg-ink-3', '6,5:1'],
    ];
    $severity = [
        [Severity::Rendah, 'Pasir', '#D8C59C', 'bg-pasir', '10,1:1'],
        [Severity::Sedang, 'Amber', '#E5A73B', 'bg-waspada', '8,1:1'],
        [Severity::Tinggi, 'Oranye karat', '#E07B42', 'bg-siaga', '5,8:1'],
        [Severity::Kritis, 'Merah bata', '#F06E5E', 'bg-awas', '5,8:1'],
    ];
    $siaga = [
        [AlertLevel::Aman, 'Hijau sinyal', '#5FD39A', 'bg-aman', '9,2:1'],
        [AlertLevel::Waspada, 'Amber', '#E5A73B', 'bg-waspada', '8,1:1'],
        [AlertLevel::Siaga, 'Oranye karat', '#E07B42', 'bg-siaga', '5,8:1'],
        [AlertLevel::Awas, 'Merah bata', '#F06E5E', 'bg-awas', '5,8:1'],
    ];
@endphp
<x-app-layout>
    <x-slot name="title">Papan Gaya</x-slot>

    <x-ui.page-header title="Papan Gaya" icon="layers">
        Olive drab / Ops darat: palet, tipografi, dan komponen SENTRA SHIELD. Semua kontras teks lolos WCAG AA.
        <x-slot:actions><span class="font-mono text-xs text-ink-3">DTG {{ dtg(now()) }}</span></x-slot:actions>
    </x-ui.page-header>

    <div class="mx-auto max-w-[1600px] space-y-4 px-4 py-5 sm:px-6 lg:px-8">
        <div class="grid gap-4 xl:grid-cols-12">
            {{-- 01 Palet --}}
            <x-ui.panel nomor="01" kode="Palet" title="Warna dasar, severity, dan Tingkat Siaga" class="xl:col-span-7" :penggaris="true">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                    @foreach ($palet as [$nama, $hex, $kelas, $kontras])
                        <div class="overflow-hidden rounded-control border border-line">
                            <div class="h-12 {{ $kelas }}"></div>
                            <div class="bg-inset/60 px-2 py-1.5">
                                <p class="truncate text-xs font-semibold text-ink">{{ $nama }}</p>
                                <p class="font-mono text-[11px] text-ink-3">{{ $hex }} · {{ $kontras }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 grid gap-5">
                    <div>
                        <p class="label-caps">Severity (kontras teks di panel)</p>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ($severity as [$s, $nama, $hex, $kelas, $kontras])
                                <div class="rounded-control border border-line bg-inset/50 p-2">
                                    <div class="h-8 rounded-chip {{ $kelas }}"></div>
                                    <x-severity-badge :severity="$s" class="mt-2" />
                                    <p class="mt-1 font-mono text-[11px] text-ink-3">{{ $hex }}<br>{{ $nama }} · {{ $kontras }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <p class="label-caps">Tingkat Siaga (Aman berbeda jelas dari olive)</p>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ($siaga as [$lv, $nama, $hex, $kelas, $kontras])
                                <div class="rounded-control border border-line bg-inset/50 p-2">
                                    <div class="h-8 rounded-chip {{ $kelas }}"></div>
                                    <x-alert-badge :level="$lv" class="mt-2" />
                                    <p class="mt-1 font-mono text-[11px] text-ink-3">{{ $hex }}<br>{{ $nama }} · {{ $kontras }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </x-ui.panel>

            {{-- 02 Tipografi --}}
            <x-ui.panel nomor="02" kode="Tipografi" title="Stensil, condensed, monospace (semua OFL, dibundel lokal)" class="xl:col-span-5">
                <p class="label-caps">Judul · Saira Stencil One</p>
                <p class="mt-1 font-stensil text-[44px] uppercase leading-none tracking-[0.06em]">Siaga satu</p>
                <p class="mt-1 font-stensil text-[22px] uppercase tracking-[0.08em] text-accent">01 // Situasi umum</p>
                <p class="label-caps mt-5">Isi · Saira Condensed 400–700</p>
                <p class="mt-1 text-[17px] text-ink-2">Sensor di tiap satuan merangkum ratusan event menjadi satu insiden yang bisa dijelaskan, lalu respons mengikuti rantai komando.</p>
                <p class="mt-1 text-sm font-bold uppercase tracking-[0.12em] text-ink">Label kapital berjarak untuk menu dan tombol</p>
                <p class="label-caps mt-5">Data · Fira Code</p>
                <p class="mt-1 flex flex-wrap gap-1.5"><span class="chip text-ink">172.30.0.66</span><span class="chip text-ink">[EV-1183]</span><span class="chip text-ink">R07</span><span class="chip border-accent/40 bg-accent/10 text-accent">T1110.001</span><span class="chip text-ink-2">sha256 4c46…0e5a</span></p>
                <p class="label-caps mt-5">Angka · stensil</p>
                <p class="mt-1 flex items-baseline gap-6"><span class="num text-[56px] text-awas">946</span><span class="num text-[40px]">11/11</span><span class="font-mono text-sm text-ink-2">DTG {{ dtg(now()) }}</span></p>
            </x-ui.panel>
        </div>

        <div class="grid gap-4 xl:grid-cols-12">
            {{-- 03 Tombol & lencana --}}
            <x-ui.panel nomor="03" kode="Kendali" title="Tombol, lencana, cap stempel" class="xl:col-span-7">
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button icon="shield-ban">Usulkan blokir IP</x-ui.button>
                    <x-ui.button variant="secondary" icon="download">Unduh PDF</x-ui.button>
                    <x-ui.button variant="ghost" icon="x">Hapus filter</x-ui.button>
                    <x-ui.button variant="secondary" size="sm" icon="refresh-cw">Periksa sekarang</x-ui.button>
                </div>
                <div class="mt-3 grid max-w-md grid-cols-2 gap-3">
                    <x-ui.button variant="approve" size="lg" icon="check">Setujui</x-ui.button>
                    <x-ui.button variant="danger" size="lg" icon="x">Tolak</x-ui.button>
                </div>
                <div class="mt-5 flex flex-wrap items-center gap-2">
                    @foreach (ActionStatus::cases() as $st)<x-action-status :status="$st" />@endforeach
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <x-ui.badge tone="accent" icon="cpu">LLM lokal · draf</x-ui.badge>
                    <x-ui.badge tone="neutral" icon="file-text">Template</x-ui.badge>
                    <x-ui.badge tone="aman" icon="circle-check">Terdeteksi</x-ui.badge>
                    <x-ui.badge tone="awas" icon="circle-x">Lolos</x-ui.badge>
                    <x-ui.badge tone="waspada" icon="clock">Atribusi berdasarkan waktu</x-ui.badge>
                </div>
                <div class="mt-6 flex flex-wrap items-center gap-6">
                    <x-ui.stempel tone="aman" size="md">Rantai utuh</x-ui.stempel>
                    <x-ui.stempel tone="aman" size="md">Disetujui</x-ui.stempel>
                    <x-ui.stempel tone="awas" size="md">Ditolak</x-ui.stempel>
                    <x-ui.stempel tone="awas" size="lg">Awas</x-ui.stempel>
                    <x-ui.stempel tone="waspada" size="sm">Lingkungan latihan</x-ui.stempel>
                </div>
            </x-ui.panel>

            {{-- 04 Identitas --}}
            <x-ui.panel nomor="04" kode="Identitas" title="Dog-tag satuan/agent dan simbol peta (generik)" class="xl:col-span-5">
                <div class="flex flex-wrap gap-3">
                    <x-ui.dogtag><span class="font-semibold">KODIM-LAT-A</span><span class="text-ink-3">Kodim Latihan A</span></x-ui.dogtag>
                    <x-ui.dogtag><span class="font-semibold">kodim-a</span><span class="text-ink-3">172.31.0.11 · v1.0</span></x-ui.dogtag>
                    <x-ui.dogtag><span class="font-semibold">plc-sim</span><span class="text-ink-3">Aset OT (PLC/HMI)</span></x-ui.dogtag>
                </div>
                <div class="mt-5 flex flex-wrap items-end gap-5">
                    <span class="text-center"><x-ui.simbol-satuan eselon="XX" tone="awas" class="h-14 w-16" /><span class="mt-1 block font-mono text-[11px] text-ink-3">Kodam</span></span>
                    <span class="text-center"><x-ui.simbol-satuan eselon="X" tone="siaga" class="h-14 w-16" /><span class="mt-1 block font-mono text-[11px] text-ink-3">Korem</span></span>
                    <span class="text-center"><x-ui.simbol-satuan eselon="II" tone="waspada" class="h-14 w-16" /><span class="mt-1 block font-mono text-[11px] text-ink-3">Kodim</span></span>
                    <span class="text-center"><x-ui.simbol-satuan eselon="II" tone="aman" class="h-14 w-16" /><span class="mt-1 block font-mono text-[11px] text-ink-3">Aman</span></span>
                    <span class="text-center"><svg viewBox="0 0 40 40" class="h-14 w-14" aria-hidden="true"><path d="M20 3L37 20L20 37L3 20Z" stroke-width="2.2" class="fill-awas/15 stroke-awas" /><path d="M14 14l12 12M26 14l-12 12" stroke-width="2" class="stroke-awas" /></svg><span class="mt-1 block font-mono text-[11px] text-ink-3">Penyerang</span></span>
                </div>
                <p class="mt-4 text-xs text-ink-3">Simbol bingkai + tanda eselon (XX, X, II) gaya peta militer umum; bukan lambang resmi mana pun.</p>
            </x-ui.panel>
        </div>

        <div class="grid gap-4 xl:grid-cols-12">
            {{-- 05 KPI + formulir --}}
            <div class="space-y-4 xl:col-span-5">
                <x-ui.panel nomor="05" kode="Kekuatan" title="Ubin angka (KPI)" :flush="true">
                    <div class="grid divide-y divide-line sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        <x-ui.kpi :bare="true" label="Insiden terbuka" value="3" icon="siren"><x-severity-badge :severity="Severity::Kritis" /></x-ui.kpi>
                        <x-ui.kpi :bare="true" label="Agent online" value="3" unit="/ 3" icon="server"><span class="text-ink-3">Tidak ada agent buta</span></x-ui.kpi>
                        <x-ui.kpi :bare="true" label="Blokir aktif" value="2" icon="shield-ban"><span class="text-ink-2">1 IP diblokir</span></x-ui.kpi>
                    </div>
                </x-ui.panel>
                <x-ui.panel nomor="06" kode="Formulir" title="Kontrol masukan">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><x-input-label for="contoh-ip" value="IP sumber" /><x-text-input id="contoh-ip" class="mt-1.5 block h-10 w-full font-mono" placeholder="contoh: 203.0.113.7" /></div>
                        <div><x-input-label for="contoh-durasi" value="Durasi blokir" /><select id="contoh-durasi" class="mt-1.5 block h-10 w-full"><option>1 jam</option></select></div>
                    </div>
                    <label class="mt-3 inline-flex items-center gap-2.5 text-sm text-ink-2"><input type="checkbox" class="size-4" checked> Aktifkan aturan</label>
                    <x-input-error :messages="['Alasan wajib diisi bila menolak.']" class="mt-2" />
                    <div class="mt-3 flex items-start gap-2 rounded-control border border-aman/40 bg-aman/10 px-3 py-2 text-sm text-aman" role="status"><x-icon name="circle-check" class="mt-0.5 size-4" />Usulan blokir dikirim ke Antrean Persetujuan Komandan.</div>
                </x-ui.panel>
            </div>

            {{-- 07 Tabel + keadaan kosong --}}
            <div class="space-y-4 xl:col-span-7">
                <x-ui.panel nomor="07" kode="Log operasi" title="Tabel padat" :flush="true">
                    <x-ui.table caption="Contoh tabel">
                        <x-slot:head><tr><th>Waktu</th><th>Bukti</th><th>Aturan</th><th>Severity</th></tr></x-slot:head>
                        <tr><td class="data text-ink-3">10:58:34</td><td><a href="#" class="link font-mono text-xs">EV-15</a></td><td><span class="chip text-ink">R01</span> Pemindaian port</td><td><x-severity-badge :severity="Severity::Rendah" /></td></tr>
                        <tr><td class="data text-ink-3">11:00:03</td><td><a href="#" class="link font-mono text-xs">EV-1002</a></td><td><span class="chip text-ink">R08</span> Login berhasil setelah brute force</td><td><x-severity-badge :severity="Severity::Tinggi" /></td></tr>
                        <tr><td class="data text-ink-3">11:00:11</td><td><a href="#" class="link font-mono text-xs">EV-1779</a></td><td><span class="chip text-ink">R11</span> Perintah tulis Modbus tanpa izin</td><td><x-severity-badge :severity="Severity::Kritis" /></td></tr>
                    </x-ui.table>
                </x-ui.panel>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="panel"><x-ui.empty icon="inbox" title="Tidak ada usulan yang menunggu keputusan.">Usulan blokir muncul di sini untuk diputuskan Komandan.</x-ui.empty></div>
                    <x-ui.panel title="Memuat" icon="loader-circle"><x-ui.skeleton :lines="4" /></x-ui.panel>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
