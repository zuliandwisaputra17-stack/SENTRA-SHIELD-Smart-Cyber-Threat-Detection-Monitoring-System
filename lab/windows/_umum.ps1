# Fungsi bersama skrip Windows SENTRA SHIELD (Windows PowerShell 5.1).
# File ini dipanggil oleh skrip lain; jangan dijalankan langsung.

$ErrorActionPreference = 'Continue'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch { }

$Global:SentraRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$Global:SentraCompose = Join-Path $SentraRoot 'lab\docker-compose.yml'

function Tulis-Judul([string]$Teks) {
    Write-Host ''
    Write-Host ('=' * 70) -ForegroundColor DarkGreen
    Write-Host ('  ' + $Teks) -ForegroundColor Green
    Write-Host ('=' * 70) -ForegroundColor DarkGreen
}

function Tulis-Info([string]$Teks) { Write-Host ('  ' + $Teks) }
function Tulis-Ok([string]$Teks) { Write-Host ('  [OK] ' + $Teks) -ForegroundColor Green }
function Tulis-Gagal([string]$Teks) { Write-Host ('  [GAGAL] ' + $Teks) -ForegroundColor Red }
function Tulis-Saran([string]$Teks) { Write-Host ('  Saran: ' + $Teks) -ForegroundColor Yellow }

function Tunggu-Enter([string]$Pesan = 'Tekan Enter untuk lanjut') {
    Write-Host ''
    [void](Read-Host ('  >> ' + $Pesan))
}

# Pastikan Docker terpasang dan mesinnya menyala. Bila Docker Desktop belum
# dibuka, skrip mencoba membukanya lalu menunggu sampai 3 menit.
function Pastikan-Docker {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        Tulis-Gagal 'Perintah docker tidak ditemukan. Docker Desktop belum terpasang.'
        Tulis-Saran 'Ikuti docs\panduan-pemula-windows.md bagian "Memasang Docker Desktop".'
        return $false
    }
    docker info *> $null
    if ($LASTEXITCODE -eq 0) { return $true }

    $exe = Join-Path $env:ProgramFiles 'Docker\Docker\Docker Desktop.exe'
    if (Test-Path $exe) {
        Tulis-Info 'Docker Desktop belum menyala. Membuka Docker Desktop...'
        Start-Process $exe
    }
    Tulis-Info 'Menunggu mesin Docker siap (maks 3 menit)...'
    for ($i = 0; $i -lt 90; $i++) {
        Start-Sleep -Seconds 2
        docker info *> $null
        if ($LASTEXITCODE -eq 0) { Tulis-Ok 'Docker siap.'; return $true }
    }
    Tulis-Gagal 'Docker tidak kunjung siap.'
    Tulis-Saran 'Buka Docker Desktop, tunggu tulisan "Engine running", lalu jalankan skrip ini lagi.'
    return $false
}

# Jalankan docker compose untuk lab. Argumen diteruskan apa adanya.
function Compose {
    docker compose -f $SentraCompose @args
}

# Image dasar dari Docker Hub yang dipakai lab (bahan build dan database).
$Global:SentraImageDasar = @('ubuntu:24.04', 'composer:2', 'node:22-bookworm-slim', 'mysql:8.4')

# Jalankan perintah docker tanpa menampilkan keluarannya; kode keluar di $LASTEXITCODE.
# Lewat cmd agar stderr normal tidak menjadi error merah di Windows PowerShell 5.1.
function Docker-Diam([string]$Perintah) {
    cmd /c "docker $Perintah >nul 2>&1"
}

function Image-Ada([string]$Image) {
    Docker-Diam "image inspect $Image"
    return ($LASTEXITCODE -eq 0)
}

# Unduh satu image dengan percobaan ulang. Image resmi Docker Hub dicoba lebih dulu
# dari cermin Google (mirror.gcr.io, isinya sama) lalu diberi nama aslinya; bila
# gagal, dari Docker Hub. Image yang sudah ada di laptop dilewati, sehingga lab
# tetap bisa dinyalakan tanpa internet.
function Tarik-Image([string]$Image) {
    if (Image-Ada $Image) { return $true }
    if ($Image -notmatch '/') {
        $cermin = "mirror.gcr.io/library/$Image"
        for ($i = 1; $i -le 2; $i++) {
            Tulis-Info "Mengunduh $Image lewat cermin mirror.gcr.io (percobaan $i dari 2)..."
            docker pull $cermin
            if ($LASTEXITCODE -eq 0) {
                docker tag $cermin $Image
                if ($LASTEXITCODE -eq 0) { return $true }
            }
            Start-Sleep -Seconds 5
        }
    }
    for ($i = 1; $i -le 3; $i++) {
        Tulis-Info "Mengunduh $Image dari Docker Hub (percobaan $i dari 3)..."
        docker pull $Image
        if ($LASTEXITCODE -eq 0) { return $true }
        Start-Sleep -Seconds (5 * $i)
    }
    return $false
}

# @return daftar image yang gagal diunduh (kosong = semua siap)
function Tarik-Image-Dasar([bool]$DenganLlm = $false) {
    $daftar = @($SentraImageDasar)
    if ($DenganLlm) { $daftar += 'ollama/ollama:latest' }
    $gagal = @()
    foreach ($img in $daftar) {
        if (-not (Tarik-Image $img)) { $gagal += $img }
    }
    return ,$gagal
}

# Folder kode di dalam OneDrive? Sinkronisasi OneDrive bisa mengunci file dan
# membawa atribut read-only ke build Docker.
function Di-OneDrive {
    $akar = $SentraRoot.ToLower()
    foreach ($od in @($env:OneDrive, $env:OneDriveConsumer, $env:OneDriveCommercial)) {
        if ($od -and $akar.StartsWith($od.ToLower().TrimEnd('\') + '\')) { return $true }
    }
    return ($akar -match '\\onedrive( - [^\\]+)?\\')
}

# Model LLM sudah pernah diunduh (siapkan-llm.bat)? Bila ya, Ollama ikut dinyalakan.
function LLM-Tersedia {
    $vol = docker volume ls -q --filter 'name=sentra-lab_ollama-data' 2>$null
    return -not [string]::IsNullOrWhiteSpace($vol)
}

function Lab-Menyala {
    $id = docker compose -f $SentraCompose ps -q sentra-core 2>$null
    return -not [string]::IsNullOrWhiteSpace($id)
}

function Selesai([int]$Kode = 0) {
    Write-Host ''
    exit $Kode
}
