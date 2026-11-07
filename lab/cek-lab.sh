#!/usr/bin/env bash
# Periksa lab SENTRA SHIELD: tunggu 3 agent online, lalu tampilkan status agent,
# event per sumber, insiden terbuka, dan tindakan (semuanya data asli dari Core).
#   bash lab/cek-lab.sh
# Versi Windows: lab\windows\cek-lab.bat
set -uo pipefail
cd "$(dirname "$0")"
docker compose ${SENTRA_ENV_FILE:+--env-file "$SENTRA_ENV_FILE"} exec -T sentra-core \
    php artisan sentra:status --tunggu-agent=3 --batas=120
