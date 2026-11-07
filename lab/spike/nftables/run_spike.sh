#!/usr/bin/env bash
# Spike nftables SENTRA SHIELD: memastikan dari dalam container Docker
#   1) ruleset blokir nftables bisa dipasang,
#   2) koneksi ke port tertutup tercatat (bahan R01): lewat NFLOG, atau cadangan
#      raw socket bila kernel (mis. WSL2) tidak mendukung NFLOG,
#   3) blokir IP memutus koneksi baru DAN koneksi yang sudah terbuka,
#   4) blokir terbuka sendiri saat durasinya habis.
# Pakai: bash lab/spike/nftables/run_spike.sh   (Windows: lab\windows\uji-kernel.bat)
# Variabel opsional: BASE_IMAGE (default ubuntu:24.04), SPIKE_SUBNET (default 172.30.250.0/24),
#                    DOCKER_BUILD_ARGS (argumen tambahan docker build).
set -uo pipefail
cd "$(dirname "$0")"

BASE_IMAGE="${BASE_IMAGE:-ubuntu:24.04}"
SUBNET="${SPIKE_SUBNET:-172.30.250.0/24}"
PREFIX="${SUBNET%.*}"
TARGET_IP="${PREFIX}.10"
ATTACKER_IP="${PREFIX}.66"
NET=sentra-spike-wan
PASS=0
FAIL=0
NFLOG_OK=0
RAW_OK=0

hasil() { # $1=nama uji, $2=0 lulus
    if [ "$2" -eq 0 ]; then echo "  [LULUS] $1"; PASS=$((PASS + 1)); else echo "  [GAGAL] $1"; FAIL=$((FAIL + 1)); fi
}

bersihkan() {
    docker rm -f sentra-spike-target sentra-spike-attacker >/dev/null 2>&1
    docker network rm "$NET" >/dev/null 2>&1
}
trap bersihkan EXIT
bersihkan

echo "== Membangun image spike (base: $BASE_IMAGE)"
docker version --format 'Docker {{.Server.Version}} / kernel {{.Server.KernelVersion}}' 2>/dev/null
docker build -q ${DOCKER_BUILD_ARGS:-} --build-arg BASE_IMAGE="$BASE_IMAGE" -f Dockerfile.target -t sentra-spike/target . >/dev/null || { echo "Build target gagal"; exit 2; }
docker build -q ${DOCKER_BUILD_ARGS:-} --build-arg BASE_IMAGE="$BASE_IMAGE" -f Dockerfile.attacker -t sentra-spike/attacker . >/dev/null || { echo "Build attacker gagal"; exit 2; }

docker network create --subnet "$SUBNET" "$NET" >/dev/null || { echo "Gagal membuat jaringan $SUBNET (bentrok? set SPIKE_SUBNET)"; exit 2; }
docker run -d --name sentra-spike-target --network "$NET" --ip "$TARGET_IP" --cap-add NET_ADMIN sentra-spike/target >/dev/null
docker run -d --name sentra-spike-attacker --network "$NET" --ip "$ATTACKER_IP" sentra-spike/attacker >/dev/null

echo "== Uji 1: ruleset blokir nftables"
docker exec sentra-spike-target nft -f /spike/sentra.nft
hasil "ruleset blokir nftables terpasang di dalam container" $?

echo "== Uji 2: koneksi ke port tertutup tercatat (bahan R01)"
docker exec -d sentra-spike-target sh -c 'python3 /spike/slow_server.py'
docker exec -d sentra-spike-target sh -c 'python3 /spike/packet_reader.py > /spike/raw.jsonl 2> /spike/raw.err'
NFLOG_RULE=1
if docker exec sentra-spike-target nft -f /spike/sentra-log.nft 2>/tmp/sentra-nflog.err; then
    NFLOG_RULE=0
    docker exec -d sentra-spike-target sh -c 'python3 /spike/nflog_reader.py 7 > /spike/nflog.jsonl 2> /spike/nflog.err'
fi
sleep 1
docker exec sentra-spike-attacker nmap -sS -Pn -n -p 1-200 "$TARGET_IP" >/dev/null 2>&1
sleep 1
N_NFLOG=0
if [ "$NFLOG_RULE" -eq 0 ]; then
    N_NFLOG=$(docker exec sentra-spike-target sh -c "grep -c '$ATTACKER_IP' /spike/nflog.jsonl" 2>/dev/null || echo 0)
fi
N_RAW=$(docker exec sentra-spike-target sh -c "grep -c '$ATTACKER_IP' /spike/raw.jsonl" 2>/dev/null || echo 0)
if [ "${N_NFLOG:-0}" -ge 15 ]; then
    NFLOG_OK=1
    echo "  [LULUS] NFLOG: $N_NFLOG port tertutup tercatat"
else
    echo "  [INFO]  NFLOG tidak didukung kernel ini (tercatat ${N_NFLOG:-0}); $(head -c 200 /tmp/sentra-nflog.err 2>/dev/null)"
fi
if [ "${N_RAW:-0}" -ge 15 ]; then
    RAW_OK=1
    echo "  [LULUS] cadangan raw socket: $N_RAW port tertutup tercatat"
else
    echo "  [INFO]  cadangan raw socket hanya mencatat ${N_RAW:-0} port"
fi
[ "$NFLOG_OK" -eq 1 ] || [ "$RAW_OK" -eq 1 ]
hasil "pemindaian port terdeteksi (NFLOG atau cadangan raw socket)" $?

echo "== Uji 3: blokir memutus koneksi"
docker exec -d sentra-spike-attacker sh -c "nc $TARGET_IP 9000 > /tmp/stream.log 2>&1"
sleep 2
docker exec sentra-spike-attacker sh -c "nc -w 2 -z $TARGET_IP 9000" >/dev/null 2>&1
hasil "sebelum blokir: penyerang bisa terhubung" $?
docker exec sentra-spike-target nft add element inet sentra blocklist4 "{ $ATTACKER_IP timeout 15s }"
sleep 1
SEBELUM=$(docker exec sentra-spike-attacker sh -c 'wc -l < /tmp/stream.log')
sleep 3
SESUDAH=$(docker exec sentra-spike-attacker sh -c 'wc -l < /tmp/stream.log')
[ "$SEBELUM" -gt 0 ] && [ "$SEBELUM" -eq "$SESUDAH" ]
hasil "koneksi yang sudah terbuka berhenti menerima data ($SEBELUM -> $SESUDAH baris)" $?
! docker exec sentra-spike-attacker sh -c "nc -w 2 -z $TARGET_IP 9000" >/dev/null 2>&1
hasil "koneksi baru dari penyerang ditolak" $?

echo "== Uji 4: blokir terbuka sendiri saat durasi habis (tunggu 15 detik)"
sleep 12
docker exec sentra-spike-attacker sh -c "nc -w 2 -z $TARGET_IP 9000" >/dev/null 2>&1
hasil "setelah durasi habis penyerang bisa terhubung lagi" $?

echo
echo "Hasil: $PASS lulus, $FAIL gagal"
if [ "$FAIL" -eq 0 ]; then
    if [ "$NFLOG_OK" -eq 1 ]; then echo "SIAP DEMO: agent memakai NFLOG."; else echo "SIAP DEMO: agent otomatis memakai cadangan raw socket (NFLOG tidak didukung)."; fi
    exit 0
fi
echo "BELUM SIAP: kirim keluaran ini ke pengembang."
exit 1
