#!/usr/bin/env bash
# Entrypoint sentra-core: sertifikat TLS lab, .env dari environment, tunggu MySQL,
# lalu `php artisan sentra:setup` (aman diulang) sebelum layanan dinyalakan.
set -euo pipefail

STATE=/var/lib/sentra
PKI_PUBLIC=${SENTRA_PKI_PUBLIC_DIR:-/pki}
export DB_HOST=${DB_HOST:-mysql} DB_PORT=${DB_PORT:-3306} DB_DATABASE=${DB_DATABASE:-sentra} \
    DB_USERNAME=${DB_USERNAME:-sentra} DB_PASSWORD=${DB_PASSWORD:-sentra}
APP=/var/www/core
mkdir -p "$STATE/tls" "$PKI_PUBLIC"
chmod 700 "$STATE" "$STATE/tls"

# 1. CA lab + sertifikat server. Kunci privat tetap di volume state Core; hanya
#    ca.crt yang dibagikan ke agent lewat volume pki.
if [ ! -f "$STATE/tls/server.crt" ]; then
    echo "[sentra] membuat CA lab dan sertifikat server"
    openssl req -x509 -newkey rsa:2048 -nodes -days 825 -subj "/CN=SENTRA SHIELD Lab CA" \
        -keyout "$STATE/tls/ca.key" -out "$STATE/tls/ca.crt" 2>/dev/null
    openssl req -newkey rsa:2048 -nodes -subj "/CN=sentra-core" \
        -keyout "$STATE/tls/server.key" -out /tmp/server.csr 2>/dev/null
    cat > /tmp/server.ext <<EXT
basicConstraints=critical,CA:FALSE
keyUsage=critical,digitalSignature,keyEncipherment
extendedKeyUsage=serverAuth
subjectKeyIdentifier=hash
authorityKeyIdentifier=keyid,issuer
subjectAltName=DNS:sentra-core,DNS:localhost,IP:127.0.0.1
EXT
    openssl x509 -req -in /tmp/server.csr -CA "$STATE/tls/ca.crt" -CAkey "$STATE/tls/ca.key" \
        -CAcreateserial -days 825 -extfile /tmp/server.ext -out "$STATE/tls/server.crt" 2>/dev/null
    rm -f /tmp/server.csr /tmp/server.ext
fi
cp "$STATE/tls/ca.crt" "$PKI_PUBLIC/ca.crt"
chmod 644 "$PKI_PUBLIC/ca.crt"

# 2. Rahasia aplikasi dibuat sekali lalu disimpan di volume state.
[ -f "$STATE/app.key" ] || echo "base64:$(openssl rand -base64 32)" > "$STATE/app.key"
[ -f "$STATE/reverb.env" ] || printf 'REVERB_APP_ID=%s\nREVERB_APP_KEY=%s\nREVERB_APP_SECRET=%s\n' \
    "$(openssl rand -hex 4)" "$(openssl rand -hex 16)" "$(openssl rand -hex 24)" > "$STATE/reverb.env"
chmod 600 "$STATE/app.key" "$STATE/reverb.env"

# 3. .env dibuat dari environment container (php-fpm tidak meneruskan env).
{
    echo "APP_NAME=\"SENTRA SHIELD\""
    echo "APP_ENV=${APP_ENV:-production}"
    echo "APP_KEY=$(cat "$STATE/app.key")"
    echo "APP_DEBUG=${APP_DEBUG:-false}"
    echo "APP_URL=${APP_URL:-https://localhost:8443}"
    echo "APP_LOCALE=id"
    echo "APP_FALLBACK_LOCALE=en"
    echo "LOG_CHANNEL=single"
    echo "LOG_LEVEL=${LOG_LEVEL:-info}"
    echo "DB_CONNECTION=mysql"
    echo "DB_HOST=${DB_HOST:-mysql}"
    echo "DB_PORT=${DB_PORT:-3306}"
    echo "DB_DATABASE=${DB_DATABASE:-sentra}"
    echo "DB_USERNAME=${DB_USERNAME:-sentra}"
    echo "DB_PASSWORD=${DB_PASSWORD:-sentra}"
    echo "SESSION_DRIVER=database"
    echo "SESSION_SECURE_COOKIE=true"
    echo "CACHE_STORE=database"
    echo "QUEUE_CONNECTION=database"
    # Di atas batas job LAPSIT (240 dtk) agar job yang lama tidak terambil dua kali.
    echo "DB_QUEUE_RETRY_AFTER=300"
    echo "BROADCAST_CONNECTION=reverb"
    cat "$STATE/reverb.env"
    echo "REVERB_HOST=127.0.0.1"
    echo "REVERB_PORT=8080"
    echo "REVERB_SCHEME=http"
    echo "SENTRA_DISPLAY_TIMEZONE=${SENTRA_DISPLAY_TIMEZONE:-Asia/Jakarta}"
    echo "SENTRA_SEED_PASSWORD=\"${SENTRA_SEED_PASSWORD:-Latihan#2026}\""
    echo "SENTRA_OLLAMA_URL=${SENTRA_OLLAMA_URL:-http://ollama:11434}"
    echo "SENTRA_OLLAMA_MODEL=${SENTRA_OLLAMA_MODEL:-qwen2.5:3b}"
    echo "SENTRA_LAB_AGENTS=${SENTRA_LAB_AGENTS:-}"
    echo "SENTRA_LAB_SITES=${SENTRA_LAB_SITES:-}"
    echo "SENTRA_LAB_ENROLLMENT_DIR=${SENTRA_LAB_AGENTS:+$PKI_PUBLIC/enroll}"
} > "$APP/.env"
chown root:www-data "$APP/.env"
chmod 640 "$APP/.env"

# 4. Tunggu MySQL lalu setup (migrasi, data master, agent lab, cache).
echo "[sentra] menunggu MySQL di ${DB_HOST:-mysql}:${DB_PORT:-3306}"
for i in $(seq 1 90); do
    if php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0);} catch (Throwable $e) { exit(1);}' 2>/dev/null; then
        break
    fi
    sleep 2
done
cd "$APP"
# storage/app ada di volume: volume yang dibuat dari image lama bisa membawa izin
# read-only (build context Windows/OneDrive). Dinormalkan setiap start.
mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/private
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
php artisan sentra:setup
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

echo "[sentra] layanan dinyalakan: https://localhost:8443"
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
