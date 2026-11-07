#!/usr/bin/env bash
# Menjalankan SENTRA Agent di container lab: tunggu CA dan kode pendaftaran dari
# sentra-core (volume pki), susun konfigurasi JSON, lalu jalankan agent.
set -euo pipefail

HOST="${AGENT_HOSTNAME:?AGENT_HOSTNAME wajib diisi}"
STATE=/var/lib/sentra-agent
mkdir -p "$STATE" /etc/sentra
chmod 700 "$STATE"

until [ -f /pki/ca.crt ]; do echo "[agent] menunggu CA lab dari sentra-core"; sleep 2; done

CODE=""
if [ ! -f "$STATE/token" ]; then
    until [ -f "/pki/enroll/$HOST.code" ]; do echo "[agent] menunggu kode pendaftaran $HOST"; sleep 2; done
    CODE="$(cat "/pki/enroll/$HOST.code")"
fi

python3 - "$CODE" > /etc/sentra/agent.json <<'PY'
import json, os, sys
enabled = set(filter(None, os.environ.get("AGENT_SOURCES", "").split(",")))
cfg = {
    "core_url": os.environ.get("CORE_URL", "https://sentra-core"),
    "ca_file": "/pki/ca.crt",
    "enrollment_code": sys.argv[1] or None,
    "state_dir": "/var/lib/sentra-agent",
    "hostname": os.environ["AGENT_HOSTNAME"],
    "sources": {
        "web": {"enabled": "web" in enabled, "path": "/var/log/nginx/access.log"},
        "ssh": {"enabled": "ssh" in enabled, "path": "/var/log/auth.log"},
        "firewall": {"enabled": "firewall" in enabled, "mode": os.environ.get("AGENT_FIREWALL_MODE", "auto"), "nflog_group": 7, "manage_nftables": True},
        "fim": {"enabled": "fim" in enabled, "paths": ["/var/www/html"], "interval_s": 2},
        "modbus": {"enabled": "modbus" in enabled, "port": 502},
    },
}
json.dump(cfg, sys.stdout, indent=2)
PY
chmod 600 /etc/sentra/agent.json

exec python3 /opt/sentra-agent/sentra_agent.py --config /etc/sentra/agent.json
