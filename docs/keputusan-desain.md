# Keputusan desain

Catatan keputusan yang menyimpang dari atau memperjelas PRD. Semuanya sudah disetujui pemilik proyek pada 6 Nov 2026.

## Keputusan yang menyimpang dari PRD

| No | Keputusan | Alasan |
|---|---|---|
| A1 | Lab memakai jaringan `net-wan` (simulasi WAN/internet). Penyerang hanya di `net-wan` dengan satu IP tetap. kodim-a, kodim-b, dan plc-sim juga tersambung ke `net-wan` dan `net-pusat`. | Di Docker, container yang tersambung ke dua jaringan mendapat dua IP berbeda, sehingga kampanye lintas satuan (syarat: IP sama) tidak akan terpicu. plc-sim juga perlu jalur ke Core. |
| A2 | Deteksi FIM (tanpa IP) ditempelkan ke insiden terbuka di satuan yang sama yang paling baru aktif (≤30 menit), dengan label **"Atribusi berdasarkan waktu"** di UI. Bila tidak ada, dibuat insiden baru tanpa IP. | Event FIM tidak punya `src_ip`, padahal korelasi memakai `unit_id` + `src_ip`. |
| A3 | Kolom `agents.asset_type` (ot/web/lainnya). `m_aset` insiden = nilai tertinggi dari agent yang terlibat. | Rumus skor butuh jenis aset; skema bagian 8 tidak memuatnya. |
| A4 | Tambahan skema: `agents.enrollment_code_hash`, `agents.enrollment_expires_at`, status agent `menunggu`, `agents.sources` (JSON), tabel `settings`, `monitored_sites.update_windows`. Kolom `agents.token_hash` tidak dipakai karena token disimpan ter-hash oleh Sanctum. | Dibutuhkan fitur PRD (kode pendaftaran, Sensor Buta, Pengaturan, R10). |
| A5 | Konfigurasi agent memakai JSON. Agent hanya memakai pustaka standar Python (zero dependency). | Pustaka standar tidak punya parser YAML. |
| A6 | Pencatat audit berantai dibuat di Fase 1; halaman audit dan tombol Verifikasi tetap di Fase 2. | G3 meminta persetujuan tercatat di audit log sejak Tingkat 1. |
| A7 | Lab cukup `docker compose up -d`; setup Core berjalan otomatis di entrypoint. Sistem berjalan **offline setelah image dan model ditarik sekali**. | PRD menargetkan instalasi satu perintah. |
| B9 | Status **Kedaluwarsa** hanya untuk blokir yang durasinya habis. Rollback oleh Komandan langsung dieksekusi (tetap tercatat di audit); two-person rule berlaku untuk `block_ip`. | Status di PRD ambigu. |
| B10 | Kaki halaman PDF LAPSIT memuat SHA-256 isi LAPSIT. SHA-256 file PDF final disimpan di `reports.sha256` dan audit log. | Hash file tidak bisa dicetak di dalam file itu sendiri. |
| B11 | Lisensi MIT. Nama satuan generik (Kodam Latihan I, Korem Latihan 01, Kodim Latihan A/B). | Menghindari nama satuan nyata. |

## Kolom pelengkap di skema

Kolom berikut melengkapi "kolom utama" bagian 8 agar fitur PRD bisa berjalan:

- `detection_rules.attack_name`, `detection_rules.stage`: kolom "MITRE ATT&CK" dan "Tahap" dari katalog bagian 10.
- `incidents.agent_id`, `detections.agent_id`: insiden Sensor Buta (R12) dibentuk per agent dan tidak punya IP.
- `detections.attribution`: label cara deteksi dikaitkan ke insiden (keputusan A2).
- `actions.group_uuid`: satu usulan blokir untuk beberapa agent cukup satu persetujuan. `actions.decision_note`: alasan setuju/tolak (bagian 11).
- `readiness_runs.agent_id`, `status`, `started_at`, `finished_at`, serta `readiness_results.started_at`: mengukur waktu deteksi per skenario.
- `site_checks.check_uuid`, `checked_at`: mengelompokkan tiga varian satu pemeriksaan.
- `reports.created_by`.
- Peran pengguna `demo` (read-only) untuk F15.

## Asumsi yang disetujui

- Laravel 13 (membutuhkan PHP ≥ 8.3, tetap memenuhi syarat "PHP 8.2+").
- Waktu disimpan dalam UTC dan ditampilkan dalam WIB.
- Operator melihat satuannya beserta satuan di bawahnya.
- Tingkat Siaga satuan = severity tertinggi dari insiden terbukanya; satuan induk mengikuti tingkat tertinggi dari dirinya dan anak-anaknya.
- Aturan ambang menghasilkan satu deteksi per (aturan, satuan, IP) per jendela waktu. Aturan pola menghasilkan satu deteksi per event yang cocok. Skor dihitung per aturan unik.
- Blokir dari insiden membuat satu baris `actions` per agent di satuan itu. Blokir kampanye: satu usulan dan satu persetujuan untuk N baris.
- Agent memasang blokir di nftables set dengan `timeout`, sehingga blokir terbuka sendiri walau Core mati.
- Uji Kesiapan: agent menandai event `is_test` hanya bila tidak bisa dipalsukan dari luar host: berasal dari loopback **dan** memuat token acak per uji (user agent `SENTRA-UJI/<token>`; log akses nginx tidak mencatat header lain), user SSH `sentra-uji` dari loopback, port uji 64990–65009 dari loopback, file `.sentra_uji_<token>`, atau perubahan metadata halaman utama yang isinya terbukti sama. Event uji tidak pernah menjadi insiden; hasilnya hanya masuk `readiness_results`. Ada 11 skenario (R01–R07, R09–R12); R08 tidak dipakai karena butuh akun SSH yang benar-benar bisa login.
- Sensor Modbus pasif: mengendus TCP/502 dengan raw socket dan mem-parse PDU.
- LAPSIT memakai model `qwen2.5:3b`, diproses async; sitasi `[EV-…]` yang tidak ada di bukti dibuang.
- Siaran realtime dikirim per batch, bukan per event.
- Agent memverifikasi sertifikat HTTPS dengan CA lab; verifikasi tidak pernah dimatikan.

## Keputusan teknis Fase 1a

Keputusan berikut tidak menyimpang dari PRD; isinya pilihan implementasi yang perlu diketahui juri teknis.

- **Log firewall lewat NFLOG, bukan `log prefix`.** Kernel membuang log nftables dari network namespace container (`nf_log_all_netns=0`). Agent memasang aturan `log group 7` untuk koneksi ke port tertutup, lalu membacanya langsung lewat socket netlink (pustaka standar). Laju log dibatasi 400 paket/detik. Bukti ada di `lab/spike/nftables/`.
- **Blokir memakai nftables set dengan `timeout`.** Blokir terbuka sendiri walau Core mati. Aturan blokir diletakkan sebelum `ct state established accept`, sehingga koneksi yang sudah terbuka ikut putus. Set dipertahankan saat agent restart.
- **Kode pendaftaran agent lab dibuat acak saat setup**, lalu dibagikan lewat volume `lab-pki`. Tidak ada kode atau token tetap di repo. Kunci privat CA dan server tetap di volume state Core; hanya `ca.crt` yang dibagikan.
- **Sensor Modbus pasif** (AF_PACKET) hanya mencatat perintah tulis FC 5, 6, 15, 16. ACK TCP kosong diabaikan agar perintah tulis berikutnya tidak dianggap retransmisi (regresi yang ditemukan saat uji lab 6 Nov).
- **Event disimpan satu per satu dalam satu transaksi** agar ID setiap event diketahui pasti dan bisa diteruskan ke mesin deteksi (Fase 1b).
- **Image lab memakai Ubuntu 24.04** (PHP 8.3 dan Python 3.12 bawaan, nginx 1.24) untuk semua container, sehingga hanya satu image dasar yang perlu ditarik.

## Keputusan teknis Fase 1b

- **Deteksi berjalan di job antrean** (`ProcessEvents`) yang dipicu setiap batch event dari agent. Worker antrean di lab hanya satu proses, sehingga batch diproses berurutan dan korelasi tidak saling balapan.
- **Jendela waktu aturan ambang memakai waktu kejadian event** (`occurred_at`), bukan waktu tiba di Core. Event yang tertahan di antrean agent lalu dikirim ulang tetap dihitung di jendela yang benar.
- **Aturan pola menghasilkan satu deteksi per event yang cocok** (event = bukti), tetapi skor menghitung tiap aturan sekali. Aturan ambang menghasilkan satu deteksi per (aturan, satuan, IP) per jendela.
- **Pola web dicocokkan setelah URL-decode dua kali**, agar `%27`, `%2527`, dan `+` tetap tertangkap.
- **sqlmap ikut memicu R05 (XSS) dan R06 (LFI).** Request "heuristik WAF/IPS" bawaan sqlmap memuat `<script>` dan `../../../etc/passwd`. Ini deteksi yang sah, bukan false positive, dan buktinya bisa dibuka di Detail Event.
- **Skor dibulatkan ke bilangan bulat terdekat** setelah dikali pengali, misalnya 142,5 menjadi 143. Rincian di layar menampilkan rumus lengkapnya.
- **Daftar putih IP disimpan di Pengaturan** (`ip_whitelist`) dan bisa diubah Admin di halaman Aturan Deteksi. Mendukung IP tunggal dan CIDR.

## Cadangan R01 untuk kernel tanpa NFLOG (6 Nov)

Atas permintaan pemilik proyek, karena laptop demo memakai Windows 11 + Docker Desktop (WSL2).

- Agent memakai mode firewall `auto`: coba NFLOG dulu. Bila kernel menolak aturan `log group` atau socket NFLOG tidak tersedia, ruleset nftables dimuat ulang **tanpa** aturan log, sehingga blokir tetap jalan. Port tertutup lalu dicatat lewat raw socket (AF_PACKET, pustaka standar): paket TCP SYN tanpa ACK ke port yang tidak LISTEN, dengan batas laju yang sama (400/detik, burst 800).
- Raw socket melihat paket sebelum firewall. Karena itu agent mengabaikan IP yang sedang ia blokir, padanan aturan nftables yang memeriksa blokir sebelum log.
- `uji-kernel.bat` / `run_spike.sh` menguji kedua cara dan menyimpulkan "SIAP DEMO" beserta modenya. `cek-lab` menampilkan `firewall=ok(nflog)` atau `ok(raw socket)`.
- **Hasil di laptop demo (7 Nov):** Windows 11, Docker 29.9.2, kernel WSL2 `6.18.54.1-microsoft-standard-WSL2`. Hasil 6 lulus, 0 gagal: ruleset blokir terpasang, NFLOG mencatat 198 port tertutup (cadangan raw socket juga 198), koneksi yang sudah terbuka berhenti menerima data saat diblokir, koneksi baru ditolak, dan blokir terbuka sendiri setelah durasi habis. Agent di laptop demo memakai NFLOG; cadangan raw socket tetap tersedia.

## Perbaikan presisi waktu (ditemukan saat uji cadangan di lab, 6 Nov)

- Cast `datetime:Y-m-d H:i:s.v` dibaca Laravel sebagai `custom_datetime`, sehingga milidetik terpotong saat disimpan lewat Eloquent. Semua model kini memakai cast `datetime` dengan `$dateFormat` berpresisi milidetik.
- Query builder memformat batas tanggal tanpa milidetik, sehingga event dalam detik yang sama bisa keluar dari jendela waktu aturan ambang (nmap memindai ratusan port dalam < 1 detik). Semua batas jendela deteksi dan korelasi kini diikat sebagai teks bermilidetik (`App\Support\DbTime`). Test regresi: 20 port dalam 0,1 detik tetap memicu R01, 10 login gagal dalam 0,1 detik tetap memicu R07.

## Keputusan teknis Fase 1c

- **Siaran WebSocket hanya membawa sinyal** (jenis perubahan + ID satuan), tidak pernah data insiden atau event. Setiap halaman Livewire mengambil ulang datanya sesuai hak akses pengguna, sehingga Operator tidak bisa menerima data satuan lain lewat WebSocket. Channel `private-sentra.dashboard` hanya untuk pengguna yang boleh melihat dashboard.
- **Kunci publik Reverb dibaca saat runtime dari meta tag.** Kunci dibuat acak saat lab pertama kali jalan, jadi tidak bisa ditanam saat build aset. Koneksi dicek ulang setiap navigasi SPA Livewire (login/logout tidak memuat ulang halaman). Bila WebSocket tidak tersambung, browser meminta pembaruan tiap 3 detik.
- **Kegagalan Reverb tidak boleh menggagalkan ingest event** (`LiveUpdate` menelan kegagalan siaran; diuji otomatis).
- **Status tindakan:** usulan yang ditarik pengusul berstatus **Dibatalkan**. Blokir yang dibuka lewat rollback Komandan juga berstatus **Dibatalkan** (dengan catatan perintah buka-nya) setelah agent melaporkan berhasil. **Kedaluwarsa** hanya untuk blokir yang durasinya habis (keputusan B9), ditandai scheduler tiap 10 detik.
- **Halaman Tindakan** (riwayat + filter "blokir aktif") ditambahkan sebagai tujuan klik kartu "Blokir aktif" di Beranda, sesuai prinsip "setiap angka bisa diklik ke data sumbernya".
- Durasi blokir yang bisa dipilih: 15 menit, 1 jam (bawaan), 6 jam, 24 jam.

## Keputusan teknis Fase 2

- **Uji Kesiapan (F8):** skenario dibagi ke agent yang sumber telemetrinya aktif (mis. R11 ke sensor OT). R12 dijalankan dengan agent menjeda heartbeat 45 detik setelah mengirim penanda bertoken; SensorWatch mencatat deteksi uji tanpa menyatakan agent BUTA, dan penanda dihapus begitu heartbeat normal kembali. Waktu deteksi diukur dari waktu mulai tiap skenario yang dilaporkan agent.
- **Audit Log (F11):** hasil setiap Verifikasi Rantai ikut dicatat (siapa, kapan, utuh/rusak di baris berapa). Demo bisa melihat halaman tetapi tidak menekan Verifikasi, sesuai keputusan "Demo tanpa aksi apa pun".
- **LAPSIT (F12) diproses di antrean terpisah** (`lapsit`, worker sendiri), sehingga panggilan LLM sampai 60 detik tidak menahan mesin deteksi. Diuji di lab: insiden baru tetap muncul ±3 detik saat LAPSIT sedang menunggu LLM.
- **Keluaran LLM dipaksa berbentuk JSON** (structured output Ollama: situasi, temuan, penilaian, saran) lalu divalidasi: sitasi `[EV-n]` yang tidak ada di bukti dibuang, butir temuan tanpa sitasi sah dibuang, kalimat yang menyebut IP di luar fakta insiden dibuang. Bila tidak ada temuan yang lolos, LAPSIT dibuat dari template. Jumlah kalimat yang dibuang tertulis di LAPSIT.
- **Bagian "Tindakan yang sudah diambil" selalu disusun dari data**, bukan dari LLM, agar status blokir tidak pernah dikarang.
- **Isi LAPSIT disimpan sebagai teks biasa**; SHA-256 teks itu dicetak di kaki setiap halaman PDF, dan SHA-256 file PDF disimpan di `reports.sha256` serta audit log (keputusan B10). Unduhan PDF tercatat sebagai `lapsit_diekspor`.
- **Pengaturan batas waktu LLM dibatasi 10–120 detik**; job LAPSIT berbatas 240 detik dan `retry_after` antrean 300 detik, sehingga job yang lama tidak diambil dua kali.
- **Prompt dibuat kecil untuk LLM di CPU:** JSON padat, paling banyak 24 event bukti (setiap aturan pasti terwakili), panjang jawaban dibatasi, model tetap dimuat 30 menit.
- **Penjaga Situs (F13) dijalankan Core**, bukan agent, tiap menit. Hanya situs terdaftar yang diperiksa, dan secara bawaan hanya alamat di jaringan privat (`SENTRA_SITES_ALLOW_PUBLIC=false`): di lab ini situs dummy milik sendiri. Pengalihan HTTP tidak diikuti agar pemeriksaan tidak keluar dari situs terdaftar.
- **Perbandingan isi memakai teks yang terlihat** (skrip, gaya, dan tag dibuang) dan kemiripan Jaccard himpunan kata. Cloaking (R13) = versi Googlebot/referer kemiripannya di bawah ambang aturan R13 (bawaan 0,8) **dan** memuat kata kunci judi yang tidak ada di versi browser, sehingga berita resmi tentang pemberantasan judi tidak ditandai. Kata kunci dan ambang diambil dari konfigurasi aturan R13 (bisa diubah Admin).
- **Defacement versi situs (R10)** = hash teks halaman utama versi browser berubah dari baseline di luar jadwal pembaruan situs. Perubahan di dalam jadwal menjadi baseline baru (tercatat di audit). Perubahan resmi di luar jadwal bisa diterima Analis dengan tombol "jadikan baseline" (tercatat di audit).
- **Event `site` hanya dibuat saat temuan baru**, bukan setiap menit, agar satu injeksi tidak menjadi puluhan deteksi. Deteksi situs tidak punya IP, sehingga ditautkan ke insiden terbuka di satuan yang sama berdasarkan waktu (keputusan A2); aset situs dihitung sebagai server web (m_aset 1,2).
- **Kolom pelengkap `site_checks`:** `http_status`, `keywords`, `excerpt`, `error`, untuk "hasil pemeriksaan per varian, perbandingan isi" di halaman Penjaga Situs.
- **Halaman Penjaga Situs hanya untuk Analis (dan Demo melihat)** sesuai bagian 11, sehingga Analis juga yang menambah/menghapus situs.
- **Catatan jaringan lab:** `net-kodim-a` (172.32.0.0/24) dan `net-kodim-b` (172.33.0.0/24) berada di luar rentang privat 172.16.0.0/12. Di lab hal ini tidak berpengaruh karena Core memanggil `http://kodim-a/` lewat `net-pusat` (172.31.0.0/24, privat).

## Keputusan Fase 3 dan perbaikan dari tes Windows (7 Nov)

- **F15 tanpa server publik (keputusan B8):** link karya berupa landing page statis di `site/` (GitHub Pages) dengan video dan tangkapan layar asli dari lab. Akun Demo tetap ada di aplikasi (hanya melihat). Setiap halaman aplikasi menampilkan strip **DATA LATIHAN** saat berjalan sebagai lab (`SENTRA_TRAINING_LABEL`, bawaan aktif bila agent lab dikonfigurasi) dan selalu untuk akun Demo.
- **Izin file dinormalkan di image dan saat container menyala.** Kode yang diambil di folder OneDrive membawa atribut read-only ke build Docker, sehingga `storage` tidak bisa ditulis dan Core *unhealthy* (HTTP 500 di `/up`). Ditemukan di tes Windows; direproduksi dan diverifikasi di lab.
- **Unduhan image dasar tahan gagal.** `mulai-lab` memastikan image dasar ada sebelum build: yang sudah ada dilewati (aman tanpa internet), yang belum diunduh lewat cermin `mirror.gcr.io` lalu Docker Hub dengan percobaan ulang. Ditemukan di tes Windows (Docker Hub *context deadline exceeded*).
- **Log scheduler hanya ASCII.** Penjaga Situs dijadwalkan sebagai callback; perintah artisan yang dijadwalkan membuat Laravel mencetak karakter `⇂` yang tampil sebagai `Γçé` di konsol Windows.
- **Bukti demo otomatis.** `jalankan-demo` menyimpan rekaman terminal dan log container ke `docs/bukti-demo`; `sentra:status` merangkum Uji Kesiapan terakhir, waktu eksekusi tindakan setelah disetujui, dan verifikasi rantai audit.

## Tampilan "pusat komando" (7 Nov)

Murni perubahan tampilan: logika, fitur, route, alur, dan teks penting tidak berubah. Pegangan lengkapnya ada di [design-system.md](design-system.md).

| Keputusan | Alasan |
|---|---|
| Satu skala warna untuk Tingkat Siaga dan severity (Aman/Rendah hijau, Waspada/Sedang kuning, Siaga/Tinggi oranye, Awas/Kritis merah), selalu disertai teks dan ikon atau meter batang. Hijau tidak lagi dipakai untuk tautan atau tombol; interaksi memakai aksen biru "radar". | Warna status harus punya satu arti di semua halaman dan tetap terbaca oleh pengguna buta warna (WCAG AA). |
| Huruf Fira dan ikon Lucide dibundel lokal lewat npm, tanpa CDN. | Sistem harus berjalan penuh tanpa internet. |
| Token warna latar dinamai `canvas` di Tailwind (variabel CSS tetap `--base`). | Nama `base` bentrok dengan utilitas ukuran huruf `text-base` sehingga teks ikut berwarna latar. |
| Satu angka/status terpenting per layar: Tingkat Siaga keseluruhan di Beranda, skor risiko di Detail Insiden, cakupan di Uji Kesiapan, status rantai di Audit Log. | Terbaca dari proyektor saat demo. |
| Tingkat Siaga keseluruhan di Beranda dihitung di tampilan sebagai tingkat tertinggi pohon satuan yang boleh dilihat pengguna, aturan yang sama dengan satuan induk. | Tidak menambah logika baru; hanya menampilkan nilai yang sudah ada. |
| Pratinjau LAPSIT di halaman insiden bergaya lembar dokumen (latar terang). Isi tetap dari laporan yang sama; hanya judul, baris "Kunci : nilai", dan judul bab yang diberi gaya. | Juri langsung mengenali bentuk laporan dinas. PDF tidak berubah. |
| Perbaikan bug: objek Chart.js tidak lagi disimpan di state reaktif Alpine. | Ditemukan saat uji live di lab: pembaruan grafik melempar "Maximum call stack size exceeded" dan memutus pembaruan Beranda. |
| Teks paginasi berbahasa Indonesia lewat `lang/id.json`. | Sebelumnya "Showing 1 to 15 of 52 results". |

## Perombakan tampilan Olive Drab / Ops Darat (7 Nov)

Disetujui pemilik proyek setelah meninjau papan gaya, Login, dan Beranda. Murni tampilan: logika, fitur, route, dan alur tidak berubah. Rinciannya ada di [design-system.md](design-system.md).

| Keputusan | Alasan |
|---|---|
| Palet olive-hitam/olive drab, aksen khaki, teks krem. Severity: Rendah pasir, Sedang amber, Tinggi oranye karat, Kritis merah bata. Tingkat Siaga Aman memakai hijau sinyal. | Arahan pemilik proyek. Aman harus beda jelas dari olive dasar. Semua kontras teks lolos WCAG AA. |
| Menu atas diganti sidebar kiri berkelompok (Operasi, Analisis, Sistem) dan laci di HP. Bilah atas tipis berisi DTG, Tingkat Siaga, LIVE, akun. | Menu atas yang padat membingungkan. |
| LAPSIT tetap di Detail Insiden; tidak ada menu LAPSIT terpisah. | Tidak menambah route. |
| Simbol satuan memakai konvensi peta umum (bingkai + tanda eselon + garis sinyal); penyerang belah ketupat. | Bukan lambang resmi; isi tanda tambah dihindari karena mirip simbol satuan kesehatan. |
| Beranda menampilkan IP sumber insiden terbuka di peta operasi; navigasi menghitung Tingkat Siaga untuk bilah atas. | Data yang sudah ada, hanya ditampilkan; query insiden terbuka menambah kolom `src_ip`, `unit_id`, `score`. |
| Kapital dibuat lewat CSS, teks asli di HTML tidak diubah. | Tes dan pembaca layar tetap membaca kata yang sama. |
| Papan gaya `resources/views/dev/papan-gaya.blade.php` disimpan tanpa route. | Referensi tinjauan desain; tidak menambah route aplikasi. |

