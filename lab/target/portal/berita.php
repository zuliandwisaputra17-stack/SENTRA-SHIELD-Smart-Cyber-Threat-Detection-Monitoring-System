<?php
// LINGKUNGAN LATIHAN: celah SQL injection DISENGAJA (parameter id tidak disanitasi).
$cfg = require __DIR__.'/config.php';
$db = new PDO('sqlite:/var/lib/portal/portal.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$id = $_GET['id'] ?? '1';
try {
    $rows = $db->query("SELECT judul, isi, tanggal FROM berita WHERE id = $id")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Kesalahan basis data: '.htmlspecialchars($e->getMessage());
    exit;
}
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Berita</title></head>
<body>
<p style="background:#b91c1c;color:#fff;padding:.5rem;font-weight:700">LINGKUNGAN LATIHAN</p>
<?php foreach ($rows as $r): ?>
  <h1><?= htmlspecialchars((string) $r['judul']) ?></h1>
  <p><?= htmlspecialchars((string) $r['isi']) ?></p>
  <small><?= htmlspecialchars((string) $r['tanggal']) ?></small>
<?php endforeach; ?>
<p><a href="/">Kembali</a></p>
</body></html>
