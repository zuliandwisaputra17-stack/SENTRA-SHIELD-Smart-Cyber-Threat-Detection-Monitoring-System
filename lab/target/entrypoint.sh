#!/usr/bin/env bash
set -euo pipefail

# Nama satuan untuk portal dummy.
printf "<?php return ['satuan' => %s];\n" "$(php -r 'echo var_export(getenv("SATUAN_NAMA") ?: "Kodim Latihan", true);')" > /var/www/html/config.php

# Basis data portal (di luar webroot).
mkdir -p /var/lib/portal /var/www/html/uploads
[ -f /var/lib/portal/portal.sqlite ] || php /opt/portal-seed.php /var/lib/portal/portal.sqlite
chown -R www-data:www-data /var/lib/portal /var/www/html/uploads
chmod -R ug+rwX /var/lib/portal /var/www/html/uploads
# Celah DISENGAJA (lingkungan latihan): halaman utama bisa ditulis proses web, sehingga
# file PHP yang diunggah bisa menyisipkan judi online ber-cloaking (skenario 06).
chown www-data:www-data /var/www/html/index.php
chmod u+rw /var/www/html/index.php

# Akun SSH lemah yang SENGAJA ada di wordlist penyerang (lingkungan latihan saja).
id latihan >/dev/null 2>&1 || useradd -m -s /bin/bash latihan
echo "latihan:${TARGET_SSH_PASSWORD:-latihan123}" | chpasswd
mkdir -p /run/sshd /run/php
touch /var/log/auth.log

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
