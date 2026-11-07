<?php $cfg = require __DIR__.'/config.php'; $db = new PDO('sqlite:/var/lib/portal/portal.sqlite'); ?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Portal <?= htmlspecialchars($cfg['satuan']) ?></title>
<style>body{font-family:system-ui,sans-serif;max-width:760px;margin:0 auto;padding:1rem;color:#1f2937}.latihan{background:#b91c1c;color:#fff;padding:.5rem 1rem;font-weight:700;text-align:center;border-radius:.375rem}a{color:#065f46}</style></head>
<body>
<p class="latihan">LINGKUNGAN LATIHAN — situs dummy, bukan situs resmi, sengaja rentan untuk demo SENTRA SHIELD</p>
<h1>Portal Berita <?= htmlspecialchars($cfg['satuan']) ?></h1>
<ul>
<?php foreach ($db->query('SELECT id, judul, tanggal FROM berita ORDER BY id') as $b): ?>
  <li><a href="/berita.php?id=<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['judul']) ?></a> <small>(<?= htmlspecialchars($b['tanggal']) ?>)</small></li>
<?php endforeach; ?>
</ul>
<p><a href="/upload.php">Kirim laporan warga</a></p>
</body>
</html>
