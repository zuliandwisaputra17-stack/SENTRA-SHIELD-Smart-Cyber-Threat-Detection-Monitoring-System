# agent/ — SENTRA Agent (Python)

Sensor ringan untuk host satuan. **Zero dependency**: satu file `sentra_agent.py`, hanya pustaka standar Python 3.10+, konfigurasi JSON.

| Sumber | Cara membaca | Event |
|---|---|---|
| `web` | tail log akses nginx/apache (format combined), tahan rotasi | IP, method, path, query, status, user agent |
| `ssh` | tail `auth.log` / log sshd | login gagal & berhasil, user, IP |
| `firewall` | nftables `log group` (NFLOG) dibaca lewat netlink; agent memasang aturan log port tertutup sendiri. **Cadangan otomatis:** bila kernel tidak mendukung NFLOG (mis. sebagian WSL2), SYN ke port tertutup dicatat lewat raw socket | IP sumber, port tujuan |
| `fim` | pemindaian berkala folder web | file baru/berubah/dihapus + SHA-256 + cuplikan isi |
| `modbus` | sensor pasif raw socket (AF_PACKET) pada TCP/502 | perintah tulis FC 5, 6, 15, 16 + IP pengirim |

Perilaku sesuai PRD:

- Event dikirim batch tiap 1 detik (maks 500), heartbeat tiap 10 detik, perintah diambil tiap 3 detik.
- Saat Core tidak terjangkau, event ditahan di antrean SQLite lokal hingga 10.000 baris, lalu dikirim ulang.
- Perintah hanya dari daftar tetap: `block_ip`, `unblock_ip`, `run_test`. Parameter divalidasi (IP lewat modul `ipaddress`, durasi 60–86.400 detik), dan `nft` dipanggil dengan argumen tetap, tanpa shell.
- Blokir memakai nftables set dengan `timeout`, sehingga blokir terbuka sendiri walau Core mati, dan tetap bertahan saat agent restart.
- Mode firewall `auto` (bawaan) memilih NFLOG atau cadangan raw socket; bisa dipaksa dengan `"mode": "nflog"` atau `"packet"`. Di lab: variabel `AGENT_FIREWALL_MODE`.
- Token disimpan di `state_dir/token` dengan izin `0600`. Komunikasi ke Core wajib HTTPS (verifikasi CA).

## Uji Kesiapan (`run_test`)

Atas perintah `run_test` dari Core, agent menjalankan skenario serangan aman **hanya ke host-nya sendiri** (R01–R07, R09–R12), di thread terpisah agar perintah blokir tidak tertahan: koneksi ke port uji 64990–65009 yang tertutup, request web ke `readiness.web_url` (wajib loopback), login SSH gagal ke akun `sentra-uji`, file uji `.sentra_uji_<token>.php`, perubahan metadata halaman utama (isi tidak diubah), tulis Modbus ke `readiness.modbus_register` di PLC lokal, dan jeda heartbeat 45 detik.

Event hasil uji ditandai `is_test` hanya bila tidak bisa dipalsukan dari luar: berasal dari loopback dan memuat token acak yang berlaku selama uji, atau isi file terbukti sama. Event uji tidak pernah menjadi insiden. Uji bisa dimatikan dengan `"readiness": {"enabled": false}`.

## Pemasangan di host nyata

```bash
sudo ./install.sh --core-url https://sentra-core.satuan.lan --code SNTR-XXXX-XXXX-XXXX-XXXX --ca /path/ca.crt
```

Kode pendaftaran dibuat Admin di halaman **Satuan & Agent** (sekali pakai, berlaku 24 jam).

## Test

```bash
python3 -m unittest discover -s agent/tests -v
```
