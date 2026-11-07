# Bukti demo

Bukti dari latihan skenario penuh di laptop demo (PRD bagian 12: minimal 3 kali tanpa gagal; checklist submission: log mentah, hasil Uji Kesiapan, rekaman terminal).

`lab\windows\jalankan-demo.bat` menyimpan otomatis ke folder ini setiap kali dijalankan:

| File | Isi |
|---|---|
| `demo-<tanggal-jam>.txt` | Rekaman terminal seluruh demo: keluaran alat serang (nmap, hydra, sqlmap, webshell, Modbus, judol), hasil uji blokir, dan di akhir `sentra:status`: agent, event per sumber, insiden, tindakan (berapa detik setelah disetujui), hasil Uji Kesiapan terakhir per skenario, dan verifikasi rantai audit |
| `log-container-<tanggal-jam>.txt` | Log mentah semua container lab (2000 baris terakhir per container) |

Jalankan Uji Kesiapan (langkah 6) sebelum menekan Enter terakhir agar hasilnya ikut terekam. Semua isi berasal dari lab latihan.
