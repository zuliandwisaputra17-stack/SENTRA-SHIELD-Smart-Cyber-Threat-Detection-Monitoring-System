# Memeriksa lab: status agent, event per sumber, insiden terbuka, tindakan.
. (Join-Path $PSScriptRoot '_umum.ps1')

Tulis-Judul 'SENTRA SHIELD - Cek lab'
if (-not (Pastikan-Docker)) { Selesai 1 }
if (-not (Lab-Menyala)) {
    Tulis-Gagal 'Lab belum menyala.'
    Tulis-Saran 'Jalankan mulai-lab.bat terlebih dahulu.'
    Selesai 1
}

Compose ps --format 'table {{.Service}}\t{{.Status}}'
Write-Host ''
Compose exec -T sentra-core php artisan sentra:status --tunggu-agent=3 --batas=60
Selesai $LASTEXITCODE
