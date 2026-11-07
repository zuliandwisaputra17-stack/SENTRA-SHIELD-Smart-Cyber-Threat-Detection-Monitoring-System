# Spike nftables (Fase 1a, 6 Nov 2026)

Risiko terbesar PRD bagian 14: "Blokir nftables di container tidak jalan" dan "Pemindaian dari container tidak tercatat". Spike ini membuktikan keduanya bisa dari dalam container Docker.

## Hasil (sandbox cloud, kernel Linux 6.18, Docker 29)

| Uji | Hasil |
|---|---|
| Ruleset nftables terpasang di dalam container (`cap_add: NET_ADMIN`) | LULUS |
| `nmap -sS -p 1-200` dari container penyerang → 198 paket port tertutup tercatat, semuanya dengan IP penyerang | LULUS |
| Sebelum blokir: penyerang bisa terhubung | LULUS |
| Setelah `block_ip`: koneksi yang sudah terbuka berhenti menerima data (< 0,5 detik) | LULUS |
| Setelah `block_ip`: koneksi baru ditolak | LULUS |
| Setelah durasi habis: elemen blokir hilang sendiri, penyerang bisa terhubung lagi | LULUS |

## Temuan penting

- **Log nftables biasa (`log prefix`) tidak bisa dipakai di container.** Kernel membuang log dari network namespace selain host (`net.netfilter.nf_log_all_netns = 0` secara bawaan), sehingga log tidak pernah muncul di dmesg/journal.
- **Solusi: NFLOG (`log group 7`).** Paket dikirim ke userspace per namespace dan dibaca agent langsung lewat socket netlink. `nflog_reader.py` hanya memakai pustaka standar Python (sesuai keputusan A5, zero dependency), tanpa ulogd.
- **Blokir memakai nftables set dengan `timeout`.** Blokir terbuka sendiri walau Core mati. Aturan blokir diletakkan sebelum `ct state established accept`, sehingga koneksi yang sudah terbuka ikut putus.
- **Log port tertutup dibatasi lajunya** (400 paket/detik, burst 800) supaya pemindaian 65.535 port tidak membanjiri pipeline. R01 hanya butuh 15 port dalam 60 detik.

## Mengulang spike di laptop

```bash
bash lab/spike/nftables/run_spike.sh
```

Butuh Docker dengan akses internet untuk build image pertama kali. Bila subnet `172.30.250.0/24` bentrok, set `SPIKE_SUBNET=10.99.0.0/24`.
