#!/usr/bin/env python3
"""Spike cadangan NFLOG: catat SYN ke port tertutup lewat raw socket (AF_PACKET),
pustaka standar saja. Cara yang sama dipakai agent bila kernel tidak mendukung NFLOG."""
import json
import socket
import struct
import sys
import time

OPEN_PORTS = {22, 80, 9000}
sock = socket.socket(socket.AF_PACKET, socket.SOCK_DGRAM, socket.htons(0x0800))
print(json.dumps({"status": "listening"}), flush=True)
while True:
    pkt, addr = sock.recvfrom(65535)
    if addr[0] == "lo" or addr[2] == 4 or len(pkt) < 20 or pkt[9] != 6:
        continue
    ihl = (pkt[0] & 0x0F) * 4
    if len(pkt) < ihl + 14:
        continue
    sport, dport = struct.unpack_from("!HH", pkt, ihl)
    flags = pkt[ihl + 13]
    if flags & 0x12 == 0x02 and dport not in OPEN_PORTS:
        print(json.dumps({"src_ip": socket.inet_ntoa(pkt[12:16]), "dst_port": dport, "t": round(time.time(), 3)}), flush=True)
