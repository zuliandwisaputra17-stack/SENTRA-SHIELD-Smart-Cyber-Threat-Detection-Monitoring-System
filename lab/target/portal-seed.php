<?php
// Mengisi basis data portal dummy. Isi fiktif, khusus lingkungan latihan.
$db = new PDO('sqlite:'.$argv[1]);
$db->exec('CREATE TABLE berita (id INTEGER PRIMARY KEY, judul TEXT, isi TEXT, tanggal TEXT)');
$db->exec('CREATE TABLE pengguna (id INTEGER PRIMARY KEY, nama TEXT, kata_sandi TEXT)');
$stmt = $db->prepare('INSERT INTO berita (judul, isi, tanggal) VALUES (?, ?, ?)');
foreach ([
    ['Latihan kesiapsiagaan bencana', 'Satuan melaksanakan latihan kesiapsiagaan bersama warga.', '2026-11-01'],
    ['Karya bakti di wilayah binaan', 'Kegiatan karya bakti membersihkan saluran air.', '2026-11-03'],
    ['Penyuluhan keamanan siber', 'Penyuluhan kepada warga tentang modus penipuan daring.', '2026-11-05'],
] as $row) {
    $stmt->execute($row);
}
$db->exec("INSERT INTO pengguna (nama, kata_sandi) VALUES ('admin', 'hash-latihan-bukan-asli')");
