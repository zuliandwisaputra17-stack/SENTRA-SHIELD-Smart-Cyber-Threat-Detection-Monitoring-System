# lab/ — Lab demo Docker Compose

Seluruh lab berjalan di satu laptop. Penyerang berada di container dan jaringan terpisah, sehingga IP-nya berbeda dari target (PRD bagian 12).

## Menjalankan

```bash
docker compose -f lab/docker-compose.yml up -d --build
```

```bash
bash lab/cek-lab.sh
```

Dashboard: **https://localhost:8443**. Sertifikat dibuat oleh CA lab sendiri, jadi browser akan memberi peringatan; pilih lanjutkan. Akun bawaan ada di README utama.

Build pertama butuh internet (image dasar, apt, composer, npm). Setelah image ditarik sekali, lab berjalan **offline**. Tidak ada layanan cloud yang dipanggil saat runtime.

Tidak ada langkah manual lain. Entrypoint `sentra-core` membuat CA dan sertifikat TLS, menjalankan `php artisan sentra:setup` (migrasi, data master, agent lab), lalu menyalakan nginx, php-fpm, queue (deteksi), queue `lapsit`, scheduler, dan Reverb. Setiap agent lab menunggu CA dan kode pendaftaran acak dari volume `lab-pki`, lalu mendaftar sendiri.

Reset total (hapus database dan token agent; model LLM, bila ada, tidak ikut terhapus):

```bash
docker compose -f lab/docker-compose.yml --profile llm down
```

```bash
docker compose -f lab/docker-compose.yml down -v
```

## Topologi

| Service | Isi | Jaringan dan IP |
|---|---|---|
| `sentra-core` | Laravel + queue + scheduler + Reverb, nginx HTTPS | net-pusat `172.31.0.2`, port host `8443` |
| `mysql` | MySQL 8.4 | net-pusat `172.31.0.3` |
| `ollama` | LLM lokal (opsional, `--profile llm`) | net-pusat `172.31.0.4` |
| `kodim-a` | nginx + portal dummy PHP + sshd + agent | net-wan `172.30.0.11`, net-kodim-a, net-pusat |
| `kodim-b` | sama, satuan Kodim B | net-wan `172.30.0.12`, net-kodim-b, net-pusat |
| `plc-sim` | PLC Modbus TCP simulasi + agent sensor OT | net-wan `172.30.0.20`, net-kodim-a, net-pusat |
| `attacker` | nmap, hydra, sqlmap, curl, klien Modbus | **hanya net-wan `172.30.0.66`** |

`net-wan` mensimulasikan WAN/internet antar satuan (keputusan A1). Bila subnet `172.30–33.0.0/24` bentrok dengan jaringan di laptop, ubah bagian `networks:` di `docker-compose.yml`.

Portal dummy berlabel **LINGKUNGAN LATIHAN** dan sengaja rentan (SQL injection di `berita.php`, unggahan tanpa validasi di `upload.php`, akun SSH `latihan` berkata sandi lemah). **Jangan pernah dipasang di internet.**

## Skenario serangan (dari container penyerang)

```bash
docker compose -f lab/docker-compose.yml exec attacker ./skenario/01-pindai.sh 172.30.0.11
```

```bash
docker compose -f lab/docker-compose.yml exec attacker ./skenario/02-bruteforce-ssh.sh 172.30.0.11
```

```bash
docker compose -f lab/docker-compose.yml exec attacker ./skenario/03-sqlmap.sh 172.30.0.11
```

```bash
docker compose -f lab/docker-compose.yml exec attacker python3 skenario/04-modbus-tulis.py 172.30.0.20
```

```bash
docker compose -f lab/docker-compose.yml exec attacker ./skenario/05-webshell.sh 172.30.0.11
```

Injeksi judi online ber-cloaking: dropper diunggah lewat celah unggah portal dan menyisipkan kode ke halaman utama yang hanya menampilkan halaman judi untuk Googlebot dan pengunjung dari Google. FIM menangkap perubahan file (R10); Penjaga Situs menangkap versi Googlebot/referer yang berbeda (R13).

```bash
docker compose -f lab/docker-compose.yml exec attacker ./skenario/06-judol-cloaking.sh 172.30.0.11
```

Penjaga Situs memeriksa `http://kodim-a/` dan `http://kodim-b/` tiap menit (didaftarkan otomatis saat setup). Memeriksa sekarang:

```bash
docker compose -f lab/docker-compose.yml exec sentra-core php artisan sentra:penjaga-situs
```

Halaman utama portal sengaja bisa ditulis proses web (celah latihan untuk skenario 06). Reset lab mengembalikan portal ke kondisi awal.

Lalu jalankan `bash lab/cek-lab.sh` untuk melihat event yang benar-benar diterima Core per sumber.

## Spike nftables

Untuk memastikan kernel Docker di laptop mendukung log port tertutup (NFLOG) dan blokir nftables dari dalam container:

```bash
bash lab/spike/nftables/run_spike.sh
```

Hasil di sandbox ada di [spike/nftables/README.md](spike/nftables/README.md).
