<?php
// LINGKUNGAN LATIHAN: unggahan TANPA validasi jenis file (DISENGAJA, skenario webshell).
$pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lampiran'])) {
    $nama = basename($_FILES['lampiran']['name']);
    if (move_uploaded_file($_FILES['lampiran']['tmp_name'], __DIR__.'/uploads/'.$nama)) {
        $pesan = 'Laporan diterima: /uploads/'.htmlspecialchars($nama);
    } else {
        $pesan = 'Gagal mengunggah.';
    }
}
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Kirim laporan</title></head>
<body>
<p style="background:#b91c1c;color:#fff;padding:.5rem;font-weight:700">LINGKUNGAN LATIHAN</p>
<h1>Kirim laporan warga</h1>
<p><?= $pesan ?></p>
<form method="post" enctype="multipart/form-data">
  <input type="file" name="lampiran"> <button type="submit">Kirim</button>
</form>
</body></html>
