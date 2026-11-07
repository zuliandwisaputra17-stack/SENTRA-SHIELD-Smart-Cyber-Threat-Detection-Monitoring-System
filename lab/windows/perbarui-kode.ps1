# Mengambil versi kode terbaru dari GitHub (git pull).
. (Join-Path $PSScriptRoot '_umum.ps1')

Tulis-Judul 'SENTRA SHIELD - Perbarui kode'
if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    Tulis-Gagal 'Git belum terpasang.'
    Tulis-Saran 'Ikuti docs\panduan-pemula-windows.md bagian "Memasang Git".'
    Selesai 1
}
git -C $SentraRoot pull --ff-only
if ($LASTEXITCODE -ne 0) {
    Tulis-Gagal 'Gagal mengambil kode terbaru.'
    Tulis-Saran 'Pastikan internet tersambung. Bila ada pesan "local changes", kirim tangkapan layar ke pengembang.'
    Selesai 1
}
Tulis-Ok 'Kode sudah terbaru.'
Tulis-Info 'Bila lab sedang menyala, jalankan mulai-lab.bat lagi agar perubahan ikut terpasang.'
Selesai 0
