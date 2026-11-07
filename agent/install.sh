#!/usr/bin/env bash
# Pasang SENTRA Agent di host satuan (Linux + systemd).
#
#   sudo ./install.sh --core-url https://sentra-core.satuan.lan --code SNTR-XXXX-XXXX-XXXX-XXXX --ca /path/ca.crt
#
# Kode pendaftaran dibuat Admin di halaman "Satuan & Agent" (sekali pakai, 24 jam).
set -euo pipefail

CORE_URL=""
CODE=""
CA=""
while [ $# -gt 0 ]; do
    case "$1" in
        --core-url) CORE_URL="$2"; shift 2 ;;
        --code) CODE="$2"; shift 2 ;;
        --ca) CA="$2"; shift 2 ;;
        *) echo "Argumen tidak dikenal: $1" >&2; exit 2 ;;
    esac
done

[ "$(id -u)" -eq 0 ] || { echo "Jalankan sebagai root (sudo)." >&2; exit 1; }
command -v python3 >/dev/null || { echo "python3 tidak ditemukan." >&2; exit 1; }
python3 -c 'import sys; sys.exit(0 if sys.version_info >= (3, 10) else 1)' || { echo "Butuh Python 3.10 atau lebih baru." >&2; exit 1; }
command -v nft >/dev/null || echo "Peringatan: nft (nftables) tidak ditemukan; log firewall dan blokir tidak akan jalan." >&2
command -v systemctl >/dev/null || { echo "systemd tidak ditemukan." >&2; exit 1; }

SRC_DIR="$(cd "$(dirname "$0")" && pwd)"
install -d -m 0755 /opt/sentra-agent
install -m 0755 "$SRC_DIR/sentra_agent.py" /opt/sentra-agent/sentra_agent.py
install -d -m 0700 /etc/sentra /var/lib/sentra-agent

if [ ! -f /etc/sentra/agent.json ]; then
    install -m 0600 "$SRC_DIR/config.example.json" /etc/sentra/agent.json
fi
if [ -n "$CA" ]; then
    install -m 0644 "$CA" /etc/sentra/ca.crt
fi
if [ -n "$CORE_URL" ] || [ -n "$CODE" ]; then
    python3 - "$CORE_URL" "$CODE" <<'PY'
import json, sys
path = "/etc/sentra/agent.json"
cfg = json.load(open(path))
if sys.argv[1]:
    cfg["core_url"] = sys.argv[1]
if sys.argv[2]:
    cfg["enrollment_code"] = sys.argv[2]
json.dump(cfg, open(path, "w"), indent=2)
PY
fi

python3 /opt/sentra-agent/sentra_agent.py --config /etc/sentra/agent.json --check
install -m 0644 "$SRC_DIR/sentra-agent.service" /etc/systemd/system/sentra-agent.service
systemctl daemon-reload
systemctl enable --now sentra-agent
echo "SENTRA Agent terpasang. Lihat log: journalctl -u sentra-agent -f"
