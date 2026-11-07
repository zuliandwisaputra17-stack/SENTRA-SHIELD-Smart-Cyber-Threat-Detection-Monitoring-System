# Panduan Pemula: Menjalankan Lab SENTRA SHIELD di Windows 11

Panduan ini untuk yang **belum pernah** memakai Docker, WSL, atau terminal. Ikuti berurutan. Setiap langkah punya bagian **"Yang harus terlihat"**. Kalau hasilnya berbeda, lihat [Bagian 10: Mengatasi error](#10-mengatasi-error).

Perkiraan waktu: 1–2 jam untuk pertama kali (sebagian besar menunggu unduhan). Setelah itu, menyalakan lab cukup satu klik dan sekitar 30 detik.

---

## Isi

1. [Gambaran singkat](#1-gambaran-singkat)
2. [Cek apakah laptop memenuhi syarat](#2-cek-apakah-laptop-memenuhi-syarat)
3. [Cara membuka PowerShell dan menempel perintah](#3-cara-membuka-powershell-dan-menempel-perintah)
4. [Memasang WSL dan Ubuntu](#4-memasang-wsl-dan-ubuntu)
5. [Memasang Docker Desktop](#5-memasang-docker-desktop)
6. [Memasang Git dan mengambil kode](#6-memasang-git-dan-mengambil-kode)
7. [Uji kernel (wajib sekali)](#7-uji-kernel-wajib-sekali)
8. [Menjalankan lab sehari-hari](#8-menjalankan-lab-sehari-hari)
9. [Persiapan hari demo](#9-persiapan-hari-demo-24-november)
10. [Mengatasi error](#10-mengatasi-error)
11. [Kamus istilah](#11-kamus-istilah)

---

## 1. Gambaran singkat

- **Docker** menjalankan "komputer mini" (disebut *container*) di dalam laptop. Lab SENTRA SHIELD terdiri dari 6 container: pusat SENTRA, database, dua satuan Kodim, PLC simulasi, dan penyerang.
- **WSL** (Windows Subsystem for Linux) adalah fitur Windows untuk menjalankan Linux. Docker di Windows membutuhkannya.
- **Docker Desktop** adalah aplikasi Docker untuk Windows.
- Setelah semua terpasang, kamu **tidak perlu mengetik perintah**. Cukup klik dua kali file `.bat` di folder `lab\windows`.

---

## 2. Cek apakah laptop memenuhi syarat

### 2.1 Versi Windows

1. Tekan tombol **Windows + R** bersamaan.
2. Ketik `winver` lalu tekan **Enter**.

**Yang harus terlihat:** "Windows 11", versi **22H2 atau lebih baru** (misalnya 23H2 atau 24H2). Windows 11 Home maupun Pro sama-sama bisa.

### 2.2 Jenis prosesor

1. Buka **Settings** (Windows + I) → **System** → **About**.
2. Lihat baris **System type**.

**Yang harus terlihat:** "64-bit operating system, **x64-based processor**". Kalau tertulis **ARM-based processor**, catat. Lab tetap bisa jalan, tetapi saat mengunduh Docker Desktop pilih versi **ARM64**.

### 2.3 RAM (memori)

1. Tekan **Ctrl + Shift + Esc** untuk membuka **Task Manager**.
2. Klik tab **Performance** (ikon grafik), lalu **Memory**.

**Yang harus terlihat:** total RAM **minimal 8 GB**. Lab memakai sekitar 3–4 GB. Dengan 16 GB laptop akan terasa lega.

### 2.4 Ruang disk

1. Buka **File Explorer** (Windows + E) → **This PC**.
2. Lihat drive **C:**.

**Yang harus terlihat:** ruang kosong **minimal 30 GB**.

### 2.5 Virtualisasi (penting)

1. Buka **Task Manager** (Ctrl + Shift + Esc) → **Performance** → **CPU**.
2. Di kanan bawah, cari tulisan **Virtualization**.

**Yang harus terlihat:** `Virtualization: Enabled`.

Kalau tertulis **Disabled**, nyalakan di BIOS:

1. Simpan semua pekerjaan, lalu **Restart** laptop.
2. Begitu layar logo muncul, tekan tombol BIOS berulang-ulang:

   | Merek laptop | Tombol BIOS |
   |---|---|
   | ASUS | F2 |
   | Acer | F2 |
   | Lenovo | F2 atau Fn+F2 (beberapa model: tombol kecil "Novo") |
   | HP | Esc lalu F10 |
   | Dell | F2 |
   | MSI | Delete |

3. Cari pengaturan bernama **Intel Virtualization Technology**, **VT-x**, **SVM Mode** (AMD), atau **Virtualization**. Biasanya ada di menu *Advanced*, *Configuration*, atau *CPU Configuration*.
4. Ubah menjadi **Enabled**.
5. Tekan **F10** (Save and Exit), pilih **Yes**.
6. Setelah Windows menyala, cek lagi Task Manager.

### 2.6 Internet

Pemasangan pertama mengunduh sekitar 3–4 GB. Pakai Wi-Fi yang stabil. Setelah selesai, **lab bisa berjalan tanpa internet**.

---

## 3. Cara membuka PowerShell dan menempel perintah

**PowerShell** adalah jendela untuk mengetik perintah. Di panduan ini kamu hanya perlu menyalin perintah dari sini lalu menempelnya.

**Membuka PowerShell sebagai Administrator** (dibutuhkan di Bagian 4 dan 6):

1. Klik tombol **Start** (logo Windows).
2. Ketik `powershell`.
3. Pada hasil **Windows PowerShell**, klik **Run as administrator**.
4. Muncul pertanyaan "Do you want to allow this app to make changes?" → klik **Yes**.

**Membuka PowerShell biasa:** sama seperti di atas, tetapi klik **Open** biasa.

**Menempel dan menjalankan perintah:**

1. Salin perintah dari panduan ini (blok abu-abu): blok teks lalu tekan **Ctrl + C**.
2. Di jendela PowerShell, **klik kanan** sekali. Perintah akan tertempel.
3. Tekan **Enter**.
4. Tunggu sampai muncul lagi tulisan seperti `PS C:\...>` di baris paling bawah. Artinya perintah selesai.

---

## 4. Memasang WSL dan Ubuntu

1. Buka **PowerShell sebagai Administrator** (Bagian 3).
2. Tempel perintah ini lalu tekan Enter:

   ```powershell
   wsl --install
   ```

3. Tunggu sampai selesai (5–15 menit). Kalau diminta restart, **restart laptop**.
4. Setelah restart, jendela **Ubuntu** biasanya terbuka sendiri dan meminta:
   - `Enter new UNIX username:` → ketik nama pendek huruf kecil tanpa spasi, misalnya `sentra`, lalu Enter.
   - `New password:` → ketik kata sandi, lalu Enter. **Saat mengetik kata sandi, tidak ada huruf yang muncul. Itu normal.** Ketik saja lalu Enter.
   - `Retype new password:` → ketik kata sandi yang sama, lalu Enter.
   - Catat kata sandi ini.
5. Tutup jendela Ubuntu.
6. Buka **PowerShell biasa**, lalu jalankan:

   ```powershell
   wsl --update
   ```

7. Lalu jalankan:

   ```powershell
   wsl -l -v
   ```

**Yang harus terlihat:**

```
  NAME      STATE           VERSION
* Ubuntu    Stopped         2
```

Yang penting, kolom **VERSION** bernilai **2**.

---

## 5. Memasang Docker Desktop

1. Buka browser ke **https://www.docker.com/products/docker-desktop/**.
2. Klik **Download for Windows – AMD64**. Kalau di langkah 2.2 laptopmu **ARM-based**, pilih **ARM64**.
3. Buka file `Docker Desktop Installer.exe` yang terunduh (biasanya di folder **Downloads**).
4. Di layar konfigurasi, pastikan **"Use WSL 2 instead of Hyper-V"** tercentang. Klik **OK**.
5. Tunggu sampai muncul **Close and log out** atau **Close and restart**, lalu klik. Laptop akan log out atau restart.
6. Setelah masuk lagi, buka **Docker Desktop** dari menu Start.
7. Baca lalu klik **Accept** pada perjanjian layanan. Docker Desktop gratis untuk penggunaan pribadi dan pendidikan.
8. Kalau diminta **Sign in**, klik **Skip** atau *Continue without signing in*. Akun Docker **tidak diperlukan**.
9. Kalau ada survei, klik **Skip**.
10. Tunggu sampai di pojok kiri bawah Docker Desktop tertulis **Engine running** dengan titik hijau.

**Cek dari PowerShell biasa:**

```powershell
docker run hello-world
```

**Yang harus terlihat:** di antara tulisannya ada `Hello from Docker!`.

**Pengaturan yang disarankan** (ikon ⚙️ Settings di kanan atas Docker Desktop):

- **General** → centang **Start Docker Desktop when you sign in to your computer**. Docker akan menyala sendiri saat laptop dinyalakan.
- Klik **Apply & restart**.

---

## 6. Memasang Git dan mengambil kode

**Git** dipakai untuk mengambil kode SENTRA SHIELD dan memperbaruinya. Jangan memakai "Download ZIP": file skrip bisa rusak dan memperbarui kode jadi repot.

1. Buka **PowerShell sebagai Administrator**, lalu jalankan:

   ```powershell
   winget install -e --id Git.Git
   ```

   Kalau ditanya `Do you agree to all the source agreements terms?`, ketik `Y` lalu Enter.

2. **Tutup semua jendela PowerShell**, lalu buka **PowerShell biasa** yang baru.
3. Cek Git:

   ```powershell
   git --version
   ```

   **Yang harus terlihat:** `git version 2.xx...`

4. Masuk ke folder pengguna (misalnya `C:\Users\NamaKamu`):

   ```powershell
   cd $HOME
   ```

   **Jangan** menaruh kode di folder **OneDrive** (termasuk `Documents` atau `Desktop` yang ikut dicadangkan OneDrive, atau folder bawaan GitHub Desktop `Documents\GitHub`). Sinkronisasi OneDrive bisa mengunci file dan membawa atribut *read-only* ke dalam Docker. `mulai-lab.bat` akan memberi peringatan kuning bila kode ada di OneDrive.

5. Ambil kode:

   ```powershell
   git clone -b claude/pensive-galileo-eow7qv https://github.com/zuliandwisaputra17-stack/SENTRA-SHIELD-Smart-Cyber-Threat-Detection-Monitoring-System.git sentra-shield
   ```

   Kalau muncul jendela login GitHub, login dengan akun GitHub-mu.

6. Buka folder skrip Windows:

   ```powershell
   explorer sentra-shield\lab\windows
   ```

**Yang harus terlihat:** jendela File Explorer berisi file seperti `mulai-lab.bat`, `cek-lab.bat`, dan `jalankan-demo.bat`.

**Tips:** klik kanan folder `windows` di panel kiri Explorer → **Pin to Quick access**, supaya mudah dibuka lagi.

### Lokasi folder kode (bila terlanjur di OneDrive)

Kalau kode sudah ada di folder OneDrive (misalnya `C:\Users\NamaKamu\OneDrive\Documents\GitHub\...`), pindahkan dengan mengambil ulang:

1. Jalankan `matikan-lab.bat` dari folder lama.
2. Di PowerShell biasa:

   ```powershell
   cd $HOME
   ```

   ```powershell
   git clone -b claude/pensive-galileo-eow7qv https://github.com/zuliandwisaputra17-stack/SENTRA-SHIELD-Smart-Cyber-Threat-Detection-Monitoring-System.git sentra-shield
   ```

3. Mulai sekarang pakai skrip di `C:\Users\NamaKamu\sentra-shield\lab\windows`. Jalankan `mulai-lab.bat` dari folder baru itu.
4. Folder lama di OneDrive boleh dihapus.

Data lab (database, insiden, model LLM) dan image Docker **tidak hilang**, karena disimpan oleh Docker, bukan di folder kode.

> Kalau Windows memunculkan **"Windows protected your PC"** saat file `.bat` diklik: klik **More info** → **Run anyway**. Skrip ini aman dan hanya menjalankan perintah Docker untuk lab.

---

## 7. Uji kernel (wajib sekali)

Uji ini memastikan Docker di laptopmu bisa **mencatat pemindaian port** dan **memblokir IP penyerang**. Kedua hal itu inti demo.

1. Pastikan Docker Desktop menyala (**Engine running**).
2. Di folder `lab\windows`, klik dua kali **`uji-kernel.bat`**.
3. Tunggu. Pertama kali bisa 3–10 menit karena mengunduh image.

**Yang harus terlihat** di akhir, kira-kira seperti ini:

```
  [OK] ruleset blokir nftables terpasang di dalam container
  [OK] NFLOG: 198 port tertutup tercatat
  [OK] cadangan raw socket: 198 port tertutup tercatat
  [OK] pemindaian port terdeteksi (NFLOG atau cadangan raw socket)
  [OK] sebelum blokir: penyerang bisa terhubung
  [OK] koneksi yang sudah terbuka berhenti menerima data (...)
  [OK] koneksi baru dari penyerang ditolak
  [OK] setelah durasi habis penyerang bisa terhubung lagi

  Hasil: 6 lulus, 0 gagal
  [OK] SIAP DEMO: agent memakai NFLOG.
```

Kalau baris NFLOG berbunyi `[INFO] NFLOG tidak didukung kernel ini`, **itu tidak apa-apa** asalkan baris "cadangan raw socket" berstatus `[OK]` dan kesimpulannya **SIAP DEMO**. Agent otomatis memakai cara cadangan, dan deteksi pemindaian port tetap jalan.

4. **Kirim file** `lab\windows\log\hasil-uji-kernel.txt` ke pengembang, baik hasilnya lulus maupun gagal.

---

## 8. Menjalankan lab sehari-hari

Semua dilakukan dengan **klik dua kali** file di folder `lab\windows`:

| File | Gunanya | Lama |
|---|---|---|
| `mulai-lab.bat` | Memastikan image dasar ada (unduh ulang otomatis bila gagal), menyalakan seluruh lab, lalu membuka dashboard di browser | Pertama kali 5–15 menit, berikutnya ±30 detik |
| `cek-lab.bat` | Memeriksa: agent online, jumlah event, insiden, tindakan | Beberapa detik |
| `jalankan-demo.bat` | Menjalankan seluruh skenario serangan berurutan, berhenti di tiap langkah untuk penjelasan. Rekaman terminal dan log container otomatis disimpan di `docs\bukti-demo` | ±10 menit |
| `matikan-lab.bat` | Mematikan lab; data tetap tersimpan | ±10 detik |
| `reset-lab.bat` | Menghapus semua data lab lalu menyalakan dari nol (sebelum latihan demo) | ±1 menit |
| `perbarui-kode.bat` | Mengambil versi terbaru dari pengembang | Beberapa detik |
| `uji-kernel.bat` | Uji kemampuan log firewall dan blokir (Bagian 7) | 1–10 menit |
| `siapkan-llm.bat` | **Opsional, sekali saja.** Mengunduh LLM lokal (Ollama + model `qwen2.5:3b`, ±4–5 GB) untuk LAPSIT. Tanpa ini LAPSIT tetap jalan memakai template | 10–30 menit (tergantung internet) |

**Setelah `perbarui-kode.bat`, jalankan `mulai-lab.bat` lagi** agar perubahan ikut terpasang.

### LLM lokal untuk LAPSIT (opsional)

LAPSIT (Laporan Situasi) bisa disusun oleh LLM lokal yang berjalan di laptopmu sendiri; data tidak dikirim ke internet.

1. Pastikan lab sudah pernah jalan (`mulai-lab.bat`) dan internet tersambung.
2. Klik dua kali **`siapkan-llm.bat`**, tekan Enter, tunggu sampai muncul **[OK] LLM lokal siap**.
3. Mulai sekarang `mulai-lab.bat` otomatis ikut menyalakan LLM. `reset-lab.bat` tidak menghapus model, jadi tidak perlu mengunduh ulang.
4. Cek: login `admin@sentra.lab` → klik nama pengguna di kanan atas → **Pengaturan** → **Periksa koneksi Ollama**. Harus muncul *Tersambung. Model qwen2.5:3b tersedia.*

LLM butuh RAM tambahan ±3 GB. Di laptop tanpa GPU, satu LAPSIT bisa butuh 30–90 detik. Bila lebih dari batas waktu (bawaan 60 detik), LAPSIT otomatis dibuat dari template dan alasannya tertulis di LAPSIT. Batas waktu bisa dinaikkan sampai 120 detik di halaman Pengaturan.

### Membuka dashboard

1. Buka browser (Chrome atau Edge) ke **https://localhost:8443**. Perhatikan `https`, bukan `http`.
2. Muncul peringatan **"Your connection isn't private"**. Ini normal, karena sertifikat dibuat sendiri oleh lab:
   - Edge: klik **Advanced** → **Continue to localhost (unsafe)**.
   - Chrome: klik **Advanced** → **Proceed to localhost (unsafe)**.
3. Login dengan salah satu akun berikut. Kata sandi semuanya `Latihan#2026`.

| Email | Peran |
|---|---|
| `admin@sentra.lab` | Admin |
| `komandan@sentra.lab` | Komandan |
| `analis@sentra.lab` | Analis |
| `operator.a@sentra.lab` | Operator Kodim A |
| `operator.b@sentra.lab` | Operator Kodim B |
| `demo@sentra.lab` | Demo (hanya lihat) |

### Tanda dashboard live

Di kanan atas dashboard ada tanda kecil:

- **Live** (titik hijau): tersambung lewat WebSocket, data muncul seketika, normalnya kurang dari 2 detik.
- **Polling** (titik kuning): WebSocket tidak tersambung, tetapi dashboard tetap diperbarui otomatis tiap 3 detik. Demo tetap bisa jalan.

### Membuka dashboard dari HP (untuk adegan Komandan menyetujui)

1. HP dan laptop harus tersambung ke jaringan yang sama. **Di lokasi lomba, pakai hotspot HP-mu sendiri**, bukan Wi-Fi umum. Akun lab memakai kata sandi bawaan, jadi jangan sampai orang lain di Wi-Fi yang sama bisa membuka dashboard.
2. Di laptop, buka PowerShell lalu ketik `ipconfig`. Catat **IPv4 Address** Wi-Fi, misalnya `192.168.1.23`.
3. Di HP, buka **https://192.168.1.23:8443**, lalu lewati peringatan sertifikat seperti di atas.
4. Kalau tidak bisa dibuka, Windows Firewall mungkin memblokir. Saat pertama kali muncul jendela izin firewall untuk Docker, pilih **Allow**.

---

## 9. Persiapan hari demo (24 November)

**H-1:**

- [ ] Jalankan `perbarui-kode.bat` lalu `mulai-lab.bat` **saat masih ada internet**, supaya semua image sudah terunduh.
- [ ] Bila memakai LLM: `siapkan-llm.bat` sudah selesai, lalu buat satu LAPSIT percobaan dan catat berapa detik munculnya.
- [ ] Latihan penuh `reset-lab.bat` → `jalankan-demo.bat` minimal **3 kali tanpa gagal**. Setiap latihan meninggalkan dua file di `docs\bukti-demo` (rekaman terminal dan log container); kirim file dari latihan terbaik ke pengembang untuk dimasukkan ke repo sebagai bukti demo.
- [ ] Siapkan video demo cadangan di laptop dan flashdisk.

**Hari H, sebelum tampil:**

- [ ] Laptop dicas penuh dan charger dibawa.
- [ ] Windows Update dijeda: Settings → Windows Update → **Pause updates**.
- [ ] Mode sleep dimatikan: Settings → System → Power → **Screen and sleep** → *Never* (saat dicas).
- [ ] VPN dimatikan (VPN bisa bentrok dengan jaringan lab).
- [ ] Aplikasi berat ditutup (game, Zoom, browser dengan banyak tab).
- [ ] 15 menit sebelum tampil: buka Docker Desktop, tunggu **Engine running**, jalankan `reset-lab.bat`, lalu `cek-lab.bat` sampai muncul **HASIL: LULUS**.
- [ ] Login di browser laptop (analis) dan di HP (komandan).

Lab **tidak butuh internet** selama demo, asalkan image sudah terunduh.

---

## 10. Mengatasi error

Cari pesan yang paling mirip dengan yang kamu lihat.

### "Virtualization support not detected" atau "Hardware assisted virtualization and data execution protection must be enabled"

Virtualisasi belum aktif.

1. Nyalakan di BIOS (Bagian 2.5).
2. Buka **PowerShell sebagai Administrator**, jalankan:

   ```powershell
   dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart
   ```

3. Restart laptop.

### "WSL needs updating", "WSL 2 installation is incomplete", atau "WSL kernel version too low"

Buka **PowerShell sebagai Administrator**:

```powershell
wsl --update
```

Lalu restart laptop.

### Docker Desktop macet di "Starting the Docker Engine..." lebih dari 5 menit

1. Klik kanan ikon Docker (paus) di pojok kanan bawah taskbar → **Quit Docker Desktop**.
2. Buka PowerShell biasa:

   ```powershell
   wsl --shutdown
   ```

3. Buka lagi Docker Desktop.
4. Kalau masih macet, restart laptop.
5. Kalau tetap macet: Docker Desktop → ikon 🐞 (Troubleshoot) → **Restart**. Pilihan terakhir adalah **Reset to factory defaults**; ini menghapus semua image, jadi `mulai-lab.bat` harus mengunduh ulang.

### `docker` tidak dikenali, atau "error during connect ... The system cannot find the file specified"

Docker Desktop belum menyala. Buka Docker Desktop, tunggu **Engine running**, lalu ulangi. Skrip `.bat` juga mencoba membukakan Docker Desktop otomatis.

### "Bind for 0.0.0.0:8443 failed: port is already allocated"

Port 8443 sedang dipakai aplikasi lain, atau lab lama masih menyala.

1. Jalankan `matikan-lab.bat`, lalu `mulai-lab.bat` lagi.
2. Kalau masih error, cari aplikasinya. Di PowerShell biasa:

   ```powershell
   netstat -ano | findstr :8443
   ```

   Angka paling kanan adalah PID. Buka Task Manager → tab **Details**, cari PID itu, lalu tutup aplikasinya.

### "Pool overlaps with other one on this address space" atau "invalid pool request"

Jaringan lab bentrok dengan jaringan lain (sering karena VPN). Matikan VPN, lalu di PowerShell biasa:

```powershell
docker network prune -f
```

Lalu jalankan `mulai-lab.bat` lagi.

### Unduhan image lambat, "context deadline exceeded", atau "TLS handshake timeout" dari Docker Hub

`mulai-lab.bat` sudah otomatis: image dasar (ubuntu, composer, node, mysql) diunduh dulu lewat cermin Google `mirror.gcr.io` (isinya sama dengan Docker Hub, biasanya lebih cepat), lalu dari Docker Hub, masing-masing dengan percobaan ulang. Image yang sudah ada di laptop dilewati, jadi pada hari demo tidak butuh internet.

Kalau masih gagal, pasang cermin itu untuk semua unduhan Docker:

1. Docker Desktop → ikon ⚙️ **Settings** → **Docker Engine**.
2. Di kotak teks JSON, tambahkan baris `"registry-mirrors": ["https://mirror.gcr.io"],` tepat setelah tanda `{` di baris pertama.
3. Klik **Apply & restart**, tunggu **Engine running**, lalu jalankan `mulai-lab.bat` lagi.

### "container sentra-lab-sentra-core-1 is unhealthy" atau "dependency failed to start"

Core menyala tetapi tidak sehat. Penyebab yang pernah terjadi: kode diambil di folder OneDrive sehingga folder `storage` di dalam image tidak bisa ditulis (halaman `/up` menjawab HTTP 500). Sejak 7 Nov, izin itu dinormalkan otomatis saat build dan setiap kali Core menyala.

1. Jalankan `perbarui-kode.bat`, lalu `mulai-lab.bat` (image dibangun ulang).
2. Kalau masih *unhealthy*, kirim ke pengembang keluaran perintah ini (PowerShell biasa, dari folder kode):

   ```powershell
   docker compose -f lab\docker-compose.yml logs --tail 80 sentra-core
   ```

### Saat build: "TLS handshake timeout", "unexpected EOF", "failed to resolve", atau "Could not resolve host"

Internet putus atau lambat. Pastikan internet tersambung, lalu jalankan `mulai-lab.bat` lagi. Bagian yang sudah terunduh tidak diunduh ulang.

### "no space left on device"

Disk penuh.

1. Kosongkan drive C: (hapus file besar, kosongkan Recycle Bin).
2. Bersihkan sisa build Docker. Di PowerShell biasa:

   ```powershell
   docker builder prune -af
   ```

### `mulai-lab` berakhir dengan "HASIL: GAGAL - agent online 0 dari 3"

1. Tunggu 1 menit, lalu jalankan `cek-lab.bat`.
2. Kalau masih gagal: `matikan-lab.bat`, lalu `mulai-lab.bat`.
3. Kalau tetap gagal, simpan log lalu kirim ke pengembang. Di PowerShell biasa, dari folder `sentra-shield`:

   ```powershell
   docker compose -f lab\docker-compose.yml logs --tail 200 > log-lab.txt
   ```

   File `log-lab.txt` ada di folder `sentra-shield`.

### Browser: "This site can't be reached" untuk localhost:8443

- Pastikan alamatnya **https**://localhost:8443.
- Pastikan `mulai-lab.bat` sudah selesai dengan **Lab siap**.
- Jalankan `cek-lab.bat`.

### "running scripts is disabled on this system"

Kamu membuka file `.ps1` langsung. Klik dua kali file **`.bat`**-nya saja (misalnya `mulai-lab.bat`).

### Laptop sangat lambat, atau proses "Vmmem" memakai banyak memori

Batasi memori untuk WSL dan Docker:

1. Tekan **Windows + R**, ketik `notepad %UserProfile%\.wslconfig`, lalu Enter. Jawab **Yes** bila ditanya membuat file baru.
2. Isi dengan:

   ```
   [wsl2]
   memory=4GB
   ```

3. Simpan (Ctrl + S), lalu tutup Notepad.
4. Di PowerShell biasa jalankan `wsl --shutdown`, lalu buka lagi Docker Desktop.

Lab butuh sekitar 3 GB. Jangan set di bawah `3GB`.

### Error `/usr/bin/env: 'bash\r': No such file or directory`

File skrip rusak karena format akhir baris Windows. Ini terjadi kalau kode diambil lewat **Download ZIP** atau file dibuka dan disimpan di editor Windows. Hapus folder `sentra-shield`, lalu ambil ulang dengan `git clone` (Bagian 6).

### `uji-kernel` menulis "NFLOG tidak didukung kernel ini"

Normal di sebagian versi WSL2. Selama kesimpulannya **SIAP DEMO**, agent otomatis memakai cadangan raw socket. Saat lab menyala, `cek-lab.bat` akan menampilkan `firewall=ok(raw socket)` untuk agent tersebut.

### `uji-kernel` berakhir dengan "BELUM SIAP"

Ini **bukan kesalahanmu**. Kirim file `lab\windows\log\hasil-uji-kernel.txt` ke pengembang.

### Antivirus memblokir atau menghapus file di folder `lab`

Folder `lab\attacker` berisi alat uji serangan (nmap, hydra, sqlmap) dan contoh webshell untuk **lingkungan latihan**. Antivirus bisa curiga. Tambahkan folder `sentra-shield` ke pengecualian antivirus (Windows Security → Virus & threat protection → Manage settings → **Exclusions**).

---

## 11. Kamus istilah

| Istilah | Arti sederhana |
|---|---|
| **Terminal / PowerShell** | Jendela untuk mengetik perintah ke komputer |
| **Docker** | Program yang menjalankan aplikasi dalam "kotak" terpisah |
| **Image** | "Cetakan" sebuah kotak, diunduh atau dibangun sekali |
| **Container** | "Kotak" yang sedang berjalan, dibuat dari image |
| **WSL** | Fitur Windows untuk menjalankan Linux; dibutuhkan Docker |
| **Git** | Program untuk mengambil dan memperbarui kode dari GitHub |
| **Agent** | Sensor SENTRA di tiap satuan yang mengirim kejadian ke pusat |
| **localhost** | Alamat untuk laptop itu sendiri |
| **Port 8443** | "Pintu" tempat dashboard SENTRA bisa dibuka |
