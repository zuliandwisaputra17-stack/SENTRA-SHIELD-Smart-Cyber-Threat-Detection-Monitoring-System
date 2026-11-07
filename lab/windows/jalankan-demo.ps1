# Menjalankan seluruh skenario demo berurutan (naskah video PRD bagian 12).
# Setiap langkah berhenti menunggu Enter agar ada waktu menjelaskan di dashboard.
# Rekaman terminal dan log container disimpan ke docs\bukti-demo (bukti demo PRD).
. (Join-Path $PSScriptRoot '_umum.ps1')

$KodimA = '172.30.0.11'
$KodimB = '172.30.0.12'
$Plc = '172.30.0.20'

function Serang([string]$Judul, [string[]]$Perintah) {
    Tulis-Info ('Menjalankan: ' + $Judul)
    # 2>&1 + teks biasa: pesan stderr alat serang tidak tampil sebagai error merah di rekaman.
    Compose exec -T attacker @Perintah 2>&1 | ForEach-Object { "$_" }
}

Tulis-Judul 'SENTRA SHIELD - Demo lengkap'
if (-not (Pastikan-Docker)) { Selesai 1 }
$stempel = Get-Date -Format 'yyyyMMdd-HHmmss'
$buktiDir = Join-Path $SentraRoot 'docs\bukti-demo'
New-Item -ItemType Directory -Force -Path $buktiDir | Out-Null
$rekaman = Join-Path $buktiDir "demo-$stempel.txt"
Start-Transcript -Path $rekaman -Force | Out-Null
Tulis-Info "Rekaman terminal: $rekaman"
if (-not (Lab-Menyala)) {
    Tulis-Gagal 'Lab belum menyala. Jalankan mulai-lab.bat terlebih dahulu.'
    Selesai 1
}
Compose exec -T sentra-core php artisan sentra:status --tunggu-agent=3 --batas=60
if ($LASTEXITCODE -ne 0) {
    Tulis-Gagal 'Belum semua agent online. Demo dihentikan.'
    Tulis-Saran 'Tunggu 1 menit lalu jalankan cek-lab.bat.'
    Selesai 1
}
Tulis-Info 'Saran: jalankan reset-lab.bat sebelum latihan agar dashboard bersih.'
Tulis-Info 'Buka https://localhost:8443 di browser dan login sebagai analis@sentra.lab.'
Start-Process 'https://localhost:8443'
Tunggu-Enter 'Siap? Tekan Enter untuk memulai serangan'

# 1:00 Serangan ke Kodim A
Tulis-Judul '1. Serangan ke Kodim A: pemindaian port lalu brute force SSH'
Tulis-Info "Penyerang 172.30.0.66 berada di mesin dan jaringan terpisah dari target $KodimA."
Serang 'nmap (pemindaian port)' @('./skenario/01-pindai.sh', $KodimA)
Serang 'hydra (brute force SSH, sekitar 1 menit)' @('./skenario/02-bruteforce-ssh.sh', $KodimA)
Tulis-Info 'Lihat dashboard: muncul SATU insiden untuk IP 172.30.0.66 di Kodim Latihan A,'
Tulis-Info 'tahap Pengintaian lalu Akses Awal, dan skor naik ke Tinggi.'
Tunggu-Enter

# 1:50 Serangan ke Kodim B
Tulis-Judul '2. IP yang sama memindai Kodim B (serangan terkoordinasi)'
Serang 'nmap ke Kodim B' @('./skenario/01-pindai.sh', $KodimB)
Tulis-Info 'Lihat dashboard: insiden di Kodim Latihan B dari IP yang sama -> Kampanye lintas satuan.'
Tunggu-Enter

# 2:20 Eksploitasi dan OT
Tulis-Judul '3. Eksploitasi portal dan serangan OT'
Serang 'sqlmap (SQL injection ke portal)' @('./skenario/03-sqlmap.sh', $KodimA)
Serang 'unggah webshell (tertangkap FIM)' @('./skenario/05-webshell.sh', $KodimA)
Serang 'perintah tulis Modbus ke PLC' @('python3', 'skenario/04-modbus-tulis.py', $Plc)
Serang 'injeksi judi online ber-cloaking ke halaman utama portal' @('./skenario/06-judol-cloaking.sh', $KodimA)
Tulis-Info 'Penjaga Situs memeriksa portal sekarang (otomatis juga tiap menit)...'
Compose exec -T sentra-core php artisan sentra:penjaga-situs
Tulis-Info 'Lihat Detail Insiden: tahap Eksekusi/Persistensi dan Dampak muncul, skor menjadi Kritis.'
Tulis-Info 'Menu Penjaga Situs (analis): versi Googlebot berisi judi, versi browser tetap normal.'
Tunggu-Enter

# 3:00 Respons
Tulis-Judul '4. Respons berjenjang: Operator mengusulkan, Komandan menyetujui'
Tulis-Info '1) Login sebagai operator.a@sentra.lab. Menu Insiden > klik insiden dari 172.30.0.66.'
Tulis-Info '   Di bagian "Tindakan respons" pilih durasi lalu klik "Usulkan blokir IP".'
Tulis-Info '2) Login sebagai komandan@sentra.lab (bisa dari HP). Menu Antrean Persetujuan > klik Setujui.'
Tunggu-Enter 'Setelah Komandan menyetujui, tekan Enter untuk menguji koneksi penyerang'
$kode = ('' + (Compose exec -T attacker curl -s -m 4 -o /dev/null -w '%{http_code}' "http://$KodimA/")).Trim()
if ($kode -eq '200') {
    Tulis-Gagal "Penyerang masih bisa membuka portal Kodim A (HTTP $kode). Blokir belum aktif."
    Tulis-Saran 'Pastikan tindakan berstatus Dieksekusi di dashboard, lalu ulangi langkah ini.'
} else {
    Tulis-Ok "Koneksi penyerang ke Kodim A TERPUTUS (kode $kode). Blokir nftables bekerja."
}
Tulis-Info '3) LAPSIT: login sebagai analis@sentra.lab, buka insiden yang sama, klik "Buat LAPSIT".'
Tulis-Info '   LAPSIT terbit (LLM lokal bila sudah disiapkan, selain itu template); klik "Unduh PDF".'
Tulis-Info '   Setiap temuan mengutip event bukti [EV-n]; hash SHA-256 tercetak di kaki halaman PDF.'
Tunggu-Enter

# 3:40 Sensor Buta
Tulis-Judul '5. Penyerang mematikan sensor di Kodim B (Sensor Buta)'
Compose exec -T kodim-b supervisorctl stop sentra-agent
Tulis-Info 'Agent Kodim B dimatikan. Menunggu 40 detik (batas heartbeat 30 detik)...'
for ($i = 40; $i -gt 0; $i -= 5) { Write-Host ("    sisa $i detik") ; Start-Sleep -Seconds 5 }
Tulis-Info 'Lihat dashboard: agent Kodim B berstatus BUTA dan insiden Sensor Buta (Tinggi) muncul.'
Tunggu-Enter 'Tekan Enter untuk menghidupkan kembali agent Kodim B'
Compose exec -T kodim-b supervisorctl start sentra-agent
Tulis-Ok 'Agent Kodim B hidup kembali (tercatat di garis waktu insiden).'
Tunggu-Enter

# 4:10 Uji Kesiapan
Tulis-Judul '6. Uji Kesiapan'
Tulis-Info 'Login sebagai analis, buka menu Uji Kesiapan, pilih Kodim Latihan A, klik Jalankan Uji Kesiapan.'
Tulis-Info 'Uji berjalan sekitar 1,5 menit (termasuk jeda heartbeat 45 detik untuk skenario Sensor Buta).'
Tulis-Info 'Matriks 11 skenario terisi; cakupan dan rata-rata waktu deteksi tampil. Tidak ada insiden baru.'
Tunggu-Enter

Tulis-Judul 'Demo selesai'
Compose exec -T sentra-core php artisan sentra:status
$logFile = Join-Path $buktiDir "log-container-$stempel.txt"
Tulis-Info 'Menyimpan log container (2000 baris terakhir per container)...'
cmd /c "docker compose -f ""$SentraCompose"" logs --no-color --timestamps --tail 2000 > ""$logFile"" 2>nul"
Stop-Transcript | Out-Null
Tulis-Ok 'Bukti demo tersimpan di folder docs\bukti-demo:'
Tulis-Info ("  " + (Split-Path $rekaman -Leaf) + '  (rekaman terminal, termasuk hasil Uji Kesiapan dan verifikasi audit)')
Tulis-Info ("  " + (Split-Path $logFile -Leaf) + '  (log mentah container)')
Tulis-Info 'Untuk mengulang dari awal: reset-lab.bat'
Selesai 0
