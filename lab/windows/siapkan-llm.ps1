# OPSIONAL, sekali saja (butuh internet): menyiapkan LLM lokal Ollama untuk LAPSIT.
# Tanpa langkah ini LAPSIT tetap dibuat dari template.
. (Join-Path $PSScriptRoot '_umum.ps1')

$Model = 'qwen2.5:3b'
Tulis-Judul 'SENTRA SHIELD - Siapkan LLM lokal untuk LAPSIT (opsional)'
if (-not (Pastikan-Docker)) { Selesai 1 }
Tulis-Info "Mengunduh image Ollama dan model $Model (total sekitar 4-5 GB). Butuh internet, sekali saja."
Tulis-Info 'Saat menyusun LAPSIT, LLM memakai RAM tambahan sekitar 3 GB.'
Tunggu-Enter 'Tekan Enter untuk mulai (tutup jendela untuk batal)'

if (-not (Tarik-Image 'ollama/ollama:latest')) {
    Tulis-Gagal 'Image Ollama gagal diunduh.'
    Tulis-Saran 'Periksa koneksi internet lalu jalankan skrip ini lagi.'
    Selesai 1
}
Compose --profile llm up -d ollama
if ($LASTEXITCODE -ne 0) {
    Tulis-Gagal 'Gagal menyalakan container Ollama.'
    Tulis-Saran 'Periksa koneksi internet lalu jalankan skrip ini lagi.'
    Selesai 1
}

Tulis-Info "Mengunduh model $Model ..."
Compose exec -T ollama ollama pull $Model
if ($LASTEXITCODE -ne 0) {
    Tulis-Gagal 'Unduhan model gagal.'
    Tulis-Saran 'Periksa koneksi internet lalu jalankan skrip ini lagi; unduhan dilanjutkan dari bagian yang sudah ada.'
    Selesai 1
}

Tulis-Info 'Memanaskan model (jawaban pertama selalu paling lambat)...'
Compose exec -T ollama ollama run $Model 'Jawab satu kata: siap' | Out-Host

Compose exec -T ollama ollama list
Tulis-Ok 'LLM lokal siap. Mulai sekarang mulai-lab.bat ikut menyalakan Ollama.'
Tulis-Info 'Periksa dari dashboard: login admin, menu nama pengguna > Pengaturan > Periksa koneksi Ollama.'
Tulis-Info 'Bila LAPSIT tetap memakai template karena laptop lambat, naikkan batas waktu di Pengaturan (maks 120 detik).'
Selesai 0
