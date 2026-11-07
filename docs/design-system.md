# Sistem desain SENTRA SHIELD — Olive Drab / Ops Darat

Pegangan tampilan Core. Satu sumber token: variabel CSS di `core/resources/css/app.css`, dipetakan ke Tailwind di `core/tailwind.config.js`. Komponen Blade ada di `core/resources/views/components/` (`ui/` untuk komponen baru). Papan gaya berisi palet, tipografi, dan semua komponen: `core/resources/views/dev/papan-gaya.blade.php` (tidak dipasang di route aplikasi).

## Arah

Papan briefing operasi angkatan darat, bukan template admin. Ciri yang dijaga:

- **Gelap kehijauan.** Dasar olive-hitam, panel olive drab, garis dan aksen khaki/tan, teks krem.
- **Bahasa visual ops darat:** judul stensil, pelat besi berbracket dan berbaut, penggaris koordinat, judul bernomor ("01 // SITUASI"), waktu format DTG, dog-tag untuk satuan/agent/pengguna, cap stempel untuk status penting, simbol satuan gaya peta.
- **Satu elemen utama besar di kiri atas tiap halaman:** papan Tingkat Siaga (Beranda), pelat skor (Detail Insiden), dial cakupan (Uji Kesiapan), status situs (Penjaga Situs), status rantai (Audit Log).
- **Warna status punya arti tetap** dan tidak pernah berdiri sendiri: selalu ada teks, ikon, atau meter.
- **Data teknis tampil apa adanya** dalam monospace: IP, hash, `[EV-n]`, kode aturan, teknik ATT&CK.

Yang sengaja dihindari:

- logo, lambang, atau nama resmi TNI AD/PUSSIBERAD. Tanda perisai-radar dan simbol satuan adalah rancangan sendiri atau konvensi peta umum;
- gambar dari internet. Semua tekstur adalah SVG buatan sendiri;
- tanda klasifikasi dokumen yang meniru aslinya (misalnya "RAHASIA");
- angka hiasan: semua angka berasal dari data asli.

## Asal rekomendasi

Dicari dengan skill **ui-ux-pro-max** (MIT, `.claude/skills/ui-ux-pro-max`, dipakai saat pengembangan saja) dan **frontend-design**.

- Pencarian palet militer tidak menemukan kecocokan (hanya palet pertanian), jadi palet mengikuti arahan pemilik proyek. Kontrasnya dihitung sendiri dengan rumus WCAG.
- Pencarian font stensil merekomendasikan Saira Stencil. Dipasangkan dengan Saira Condensed (satu keluarga) untuk isi.
- Aturan UX yang dipakai: kontras 4,5:1, fokus terlihat, target sentuh ≥44 px, navigasi yang tidak menutupi isi, `prefers-reduced-motion`.

## Warna

Nilai disimpan sebagai triplet RGB agar modifier opasitas Tailwind bekerja (`bg-awas/10`). Kontras dihitung terhadap panel `#181D10`.

| Token | Hex | Kelas | Pakai untuk | Kontras |
|---|---|---|---|---|
| `--base` | `#0E110A` | `bg-canvas` | Latar olive-hitam, isi input | — |
| `--panel` | `#181D10` | `bg-panel`, `.panel` | Pelat panel olive drab | — |
| `--raised` | `#20271A` | `bg-raised` | Hover, kartu dalam panel | — |
| `--inset` | `#283020` | `bg-inset` | Sumur, chip, baris aktif | — |
| `--line` | `#343C26` | `border-line` | Garis pemisah | — |
| `--line-strong` | `#4A5134` | `border-line-strong` | Garis tegas | — |
| `--line-control` | `#8A8463` | `border-line-control` | Batas input/checkbox | 4,5:1 (non-teks) |
| `--ink` | `#ECE4C8` | `text-ink` | Teks utama (krem) | 13,5:1 |
| `--ink-2` | `#CEC6A6` | `text-ink-2` | Teks sekunder | 10,0:1 |
| `--ink-3` | `#A69F80` | `text-ink-3` | Label, keterangan | 6,5:1 |
| `--accent` | `#C9B57A` | `text-accent`, `bg-accent` | Khaki/tan: braket, tautan, tombol utama, fokus | 8,5:1 |
| `--accent-ink` | `#17140A` | `text-accent-ink` | Teks di atas khaki/warna padat | 9,1:1 |

Kelas latar memakai nama `canvas`, bukan `base`, karena `text-base` sudah dipakai Tailwind untuk ukuran huruf. Skala `gray` Tailwind dipetakan ke netral olive/krem.

### Severity dan Tingkat Siaga

| Severity | Warna | Hex | Kontras | Tingkat Siaga padanan | Ikon/pembeda |
|---|---|---|---|---|---|
| Rendah | pasir | `#D8C59C` | 10,1:1 | Aman = **hijau sinyal** `#5FD39A` (9,2:1), sengaja beda jelas dari olive | Meter 1 batang / `shield-check` |
| Sedang | amber | `#E5A73B` | 8,1:1 | Waspada | Meter 2 batang / `eye` |
| Tinggi | oranye karat | `#E07B42` | 5,8:1 | Siaga | Meter 3 batang / `triangle-alert` |
| Kritis | merah bata | `#F06E5E` | 5,8:1 | Awas | Meter 4 batang / `siren` |

- Lencana memakai tint 10 % dengan teks berwarna status (4,8–7,4:1). Kelasnya ada di enum: `Severity::badgeClass()/tone()/textClass()/fillClass()`, `AlertLevel::badgeClass()/dotClass()`.
- Warna padat (lampu Tingkat Siaga yang menyala, tombol Setujui/Tolak) memakai teks gelap `#17140A` (5,3–10,9:1).
- Merah bata semula `#E2614C` gagal AA pada tint (4,17:1), lalu dicerahkan.
- Grafik memakai khaki gelap `#9E8E58` (lolos validator palet dataviz: L 0,48–0,67, kontras ≥3:1).

## Tipografi

Dibundel lokal lewat `@fontsource` (OFL-1.1, subset latin), tanpa unduhan dari internet. Dasar `html` 17 px karena huruf condensed sempit.

| Peran | Huruf | Kelas |
|---|---|---|
| Judul halaman, judul panel, angka besar, kata status, cap | Saira Stencil One | `font-stensil`, `.panel-title`, `.num`, `.stempel` |
| Isi, label, tombol, menu | Saira Condensed 400/500/600/700 | `font-sans`, `font-display`, `.label-caps` |
| Data teknis | Fira Code 400/500/600 | `font-mono`, `.data`, `.chip` |

- Huruf kapital dibuat lewat CSS (`uppercase`). Teks asli di HTML tidak diubah, sehingga tes dan pembaca layar tetap membaca kata yang sama.
- Menu, tombol, dan label memakai kapital berjarak (`tracking-[0.08em]`–`[0.14em]`).
- Angka di kolom tabel memakai `tabular-nums`. Angka besar yang berdiri sendiri memakai stensil proporsional.

## Elemen khas

| Elemen | Implementasi |
|---|---|
| Tekstur kanvas | `body`: derau SVG (`feTurbulence`) opasitas rendah + anyaman garis 3 px |
| Pelat besi | `.panel`: braket khaki 16 px di empat sudut (`::before`) dan baut kecil (`::after`) |
| Penggaris koordinat | `.penggaris-x`, `.penggaris-y`, prop `:penggaris="true"` di `x-ui.panel` |
| Grid peta | `.grid-peta` (garis 24 px + mayor 120 px), peta operasi SVG di Beranda |
| Judul bernomor | `x-ui.panel nomor="01" kode="Situasi" title="…"` → "01 // SITUASI" + judul asli sebagai keterangan |
| DTG | helper `dtg($waktu)` → `071447 WIB NOV 26` (bulan singkat Indonesia) |
| Dog-tag | `<x-ui.dogtag>`: pelat logam berlubang, teks monospace timbul |
| Cap stempel | `<x-ui.stempel tone="awas">`: bingkai ganda, miring, tinta aus (mask derau SVG) |
| Simbol satuan | `<x-ui.simbol-satuan eselon="XX">`: bingkai + tanda eselon (XX Kodam, X Korem, II Kodim) + garis petir sinyal, tongkat markas. Penyerang = belah ketupat bersilang |
| Pita DATA LATIHAN | `.pita-latihan`: garis miring amber |

## Tata letak

- **Sidebar kiri** (`lg`+) dengan kelompok OPERASI (Beranda, Insiden, Kampanye, Antrean Persetujuan, Tindakan), ANALISIS (Uji Kesiapan, Penjaga Situs, Audit Log), dan SISTEM (Satuan & Agent, Aturan Deteksi, Pengguna, Pengaturan). Menu yang tidak boleh diakses peran tidak tampil.
- LAPSIT tidak punya halaman sendiri: ia ada di Detail Insiden (panel 06 // Laporan).
- **Laci menu** di HP dan tablet: tombol ☰ di bilah atas, tutup dengan Esc atau ketuk latar.
- **Bilah atas tipis (48 px):** DTG berjalan, Tingkat Siaga keseluruhan (diperbarui lewat sinyal live), indikator LIVE/Polling, menu akun.
- **Konten:** maksimal 1600 px, celah grid 16 px, padding panel 16–20 px.
- **HP (390 px):** satu kolom; tabel lebar diganti kartu atau digulir di wadahnya sendiri; halaman tidak pernah menggulir ke samping.

## Gerak

| Nama | Kelas | Arti |
|---|---|---|
| denyut | `animate-denyut` | Sesuatu hidup: LIVE, Core aktif, satuan Awas |
| masuk | `animate-masuk` | Baris feed baru (sekali, saat disisipkan) |
| sorot | `animate-sorot` | Angka KPI yang berubah |
| serbu | `animate-serbu` | Panah serangan di peta operasi (garis putus bergerak) |
| kilau | `.skeleton` | Memuat |

`prefers-reduced-motion: reduce` mematikan semua animasi dan transisi.

## Aksesibilitas

- Kontras teks minimal 4,5:1 (lihat tabel), batas kontrol 4,5:1, fokus keyboard berupa garis khaki 2 px.
- Status tidak pernah hanya warna: lencana punya teks dan ikon atau meter, lampu Tingkat Siaga punya teks dan posisi, stempel berisi kata.
- Peta operasi punya `<title>`/`<desc>` dan daftar alternatif di HP. Grafik punya tabel ("Lihat sebagai tabel").
- Target sentuh di HP ≥44 px. Tombol keputusan Setujui/Tolak 56 px.
- Status yang berubah memakai `role="status"`/`aria-live`. Kesalahan form memakai `role="alert"`.

## Komponen Blade

| Komponen | Props | Catatan |
|---|---|---|
| `<x-ui.page-header>` | `title`, `icon`, `back`, `backLabel`; slot keterangan, slot `actions` | Judul stensil kapital, penggaris di tepi atas |
| `<x-ui.panel>` | `title`, `subtitle`, `icon`, `headingId`, `flush`, `nomor`, `kode`, `penggaris`; slot `actions` | Pelat besi bernomor |
| `<x-ui.kpi>` | `label`, `value`, `unit`, `icon`, `tone`, `href`, `bare` | Angka stensil besar |
| `<x-ui.card>` | `tone`, `href` | Kartu berpita status di kiri |
| `<x-ui.table>` | `caption`; slot `head` | Tabel padat dalam wadah gulir |
| `<x-ui.button>` | `variant` (primary/secondary/danger/approve/ghost), `size` (sm/md/lg), `icon`, `href` | Kapital berjarak |
| `<x-ui.badge>` | `tone` (neutral/accent/pasir/aman/waspada/siaga/awas/latihan), `icon` | |
| `<x-ui.dogtag>` | slot (baris-baris) | Satuan, agent, pengguna |
| `<x-ui.stempel>` | `tone`, `size` | Status penting |
| `<x-ui.simbol-satuan>` | `eselon`, `tone` | Simbol peta generik |
| `<x-alert-badge>`, `<x-severity-badge>`, `<x-action-status>` | | Lencana status dari enum |
| `<x-ui.empty>`, `<x-ui.skeleton>`, `<x-icon>` | | Kosong, memuat, ikon Lucide inline |

### Contoh

```blade
<x-ui.panel nomor="02" kode="Respons" title="Tindakan respons" icon="shield-ban">
    <x-ui.dogtag><span>kodim-a</span><span class="text-ink-3">172.31.0.11</span></x-ui.dogtag>
    <x-ui.stempel tone="aman">Disetujui</x-ui.stempel>
</x-ui.panel>
```
