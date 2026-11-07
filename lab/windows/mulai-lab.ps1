# Menyalakan seluruh lab SENTRA SHIELD (build image bila perlu), menunggu
# 3 agent online, lalu membuka dashboard di browser.
. (Join-Path $PSScriptRoot '_umum.ps1')

Tulis-Judul 'SENTRA SHIELD - Menyalakan lab'
if (-not (Pastikan-Docker)) { Selesai 1 }

if (Di-OneDrive) {
    Write-Host ''
    Write-Host '  PERHATIAN: folder kode ada di dalam OneDrive:' -ForegroundColor Yellow
    Write-Host ('  ' + $SentraRoot) -ForegroundColor Yellow
    Tulis-Info 'Lab tetap bisa jalan, tetapi sinkronisasi OneDrive bisa mengunci file dan memperlambat build.'
    Tulis-Saran 'Pindahkan kode ke luar OneDrive (panduan bagian 6, "Lokasi folder kode"). Data lab tidak hilang.'
    Write-Host ''
}

$llm = LLM-Tersedia
Tulis-Info 'Memastikan image dasar tersedia (yang sudah ada dilewati)...'
$gagal = Tarik-Image-Dasar $llm
if ($gagal.Count -gt 0) {
    Tulis-Gagal ('Image berikut gagal diunduh: ' + ($gagal -join ', '))
    Tulis-Saran 'Periksa internet lalu jalankan skrip ini lagi; bagian yang sudah terunduh tidak diulang.'
    Tulis-Saran 'Bila tetap gagal, ikuti panduan bagian 10: "Unduhan image lambat atau context deadline exceeded".'
    Selesai 1
}
Tulis-Ok 'Image dasar tersedia.'

Tulis-Info 'Menyalakan container. Pertama kali bisa 5-15 menit (membangun image).'
Tulis-Info 'Berikutnya hanya sekitar 30 detik. Jangan tutup jendela ini.'
$mulai = Get-Date
if ($llm) {
    Tulis-Info 'LLM lokal (Ollama) ikut dinyalakan untuk LAPSIT.'
    Compose --profile llm up -d --build
} else {
    Compose up -d --build
}
if ($LASTEXITCODE -ne 0) {
    Tulis-Gagal 'docker compose up gagal. Baca pesan error di atas.'
    Tulis-Saran 'Lihat docs\panduan-pemula-windows.md bagian "Mengatasi error". Error internet: jalankan skrip ini lagi.'
    Selesai 1
}

Tulis-Info 'Menunggu 3 agent mendaftar dan online...'
Compose exec -T sentra-core php artisan sentra:status --tunggu-agent=3 --batas=180
$ok = ($LASTEXITCODE -eq 0)
$durasi = [int]((Get-Date) - $mulai).TotalSeconds

if ($ok) {
    Tulis-Ok ("Lab siap dalam $durasi detik.")
    Tulis-Info 'Dashboard: https://localhost:8443  (akun: admin@sentra.lab / Latihan#2026)'
    Tulis-Info 'Browser akan memberi peringatan sertifikat: klik "Advanced" lalu "Continue/Lanjutkan".'
    Start-Process 'https://localhost:8443'
    Selesai 0
}
Tulis-Gagal 'Agent belum online semua.'
Tulis-Saran 'Tunggu 1 menit lalu jalankan cek-lab.bat. Bila tetap gagal, kirim hasil cek-lab ke pengembang.'
Selesai 1
