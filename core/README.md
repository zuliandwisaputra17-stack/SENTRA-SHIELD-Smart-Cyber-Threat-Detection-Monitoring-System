# core/ — SENTRA Core + Command (Laravel)

Aplikasi Laravel 13 yang berisi Core (API agent, deteksi, korelasi, scheduler) dan Command (dashboard web Livewire). Cara menjalankan dan menguji ada di [README utama](../README.md).

Peta kode penting:

- `app/Enums/` — peran, status, severity, tahap kill chain, Tingkat Siaga.
- `app/Models/` — model untuk 15 tabel inti PRD bagian 8 (`SecurityEvent` = tabel `events`, `ResponseAction` = tabel `actions`).
- `app/Support/AccessMatrix.php` — matriks hak akses PRD bagian 3 dan 11, didaftarkan sebagai Gate.
- `app/Policies/` — pembatasan per satuan dan two-person rule.
- `database/seeders/` — hanya data master: satuan, akun per peran, katalog aturan R01–R13, pengaturan.
- `tests/` — test Pest (Feature memakai MySQL `sentra_test`).
