# Mematikan lab. Data (database, token agent) TETAP disimpan untuk dinyalakan lagi.
. (Join-Path $PSScriptRoot '_umum.ps1')

Tulis-Judul 'SENTRA SHIELD - Mematikan lab'
if (-not (Pastikan-Docker)) { Selesai 1 }
# --profile llm: Ollama (bila ada) ikut dimatikan. Model tetap tersimpan.
Compose --profile llm down
if ($LASTEXITCODE -eq 0) {
    Tulis-Ok 'Lab dimatikan. Data tetap tersimpan. Nyalakan lagi dengan mulai-lab.bat.'
    Selesai 0
}
Tulis-Gagal 'Gagal mematikan lab. Baca pesan di atas.'
Selesai 1
