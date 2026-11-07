#!/usr/bin/env python3
"""Spike: membaca log nftables (NFLOG) langsung lewat netlink, pustaka standar saja.

Kenapa NFLOG, bukan `log prefix` biasa: log kernel dari dalam network namespace
container dibuang kernel (sysctl net.netfilter.nf_log_all_netns=0 secara bawaan),
sehingga tidak pernah muncul di dmesg/journal. NFLOG (`log group N`) dikirim ke
userspace per namespace, jadi tetap bisa dibaca agent di dalam container.

Keluaran: satu baris JSON per paket yang dicatat.
"""
import json
import socket
import struct
import sys
import time

NETLINK_NETFILTER = 12
NFNL_SUBSYS_ULOG = 4
NFULNL_MSG_PACKET = 0
NFULNL_MSG_CONFIG = 1
NLM_F_REQUEST = 0x1
NLM_F_ACK = 0x4
NLMSG_ERROR = 0x2
NFULA_CFG_CMD = 1
NFULA_CFG_MODE = 2
NFULNL_CFG_CMD_BIND = 1
NFULNL_CFG_CMD_PF_BIND = 3
NFULNL_COPY_PACKET = 2
NFULA_PACKET_HDR = 1
NFULA_PAYLOAD = 9
NFULA_PREFIX = 10


def _align(n):
    return (n + 3) & ~3


def _attr(atype, data):
    length = 4 + len(data)
    return struct.pack("=HH", length, atype) + data + b"\0" * (_align(length) - length)


def _config_msg(seq, family, group, attrs):
    body = struct.pack("=BBH", family, 0, socket.htons(group)) + attrs
    mtype = (NFNL_SUBSYS_ULOG << 8) | NFULNL_MSG_CONFIG
    return struct.pack("=IHHII", 16 + len(body), mtype, NLM_F_REQUEST | NLM_F_ACK, seq, 0) + body


def open_nflog(group, copy_range=128):
    sock = socket.socket(socket.AF_NETLINK, socket.SOCK_RAW, NETLINK_NETFILTER)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_RCVBUF, 4 * 1024 * 1024)
    sock.bind((0, 0))
    seq = 1
    # PF_BIND tidak wajib di kernel baru; kegagalannya diabaikan.
    for family in (socket.AF_INET, socket.AF_INET6):
        sock.send(_config_msg(seq, family, 0, _attr(NFULA_CFG_CMD, struct.pack("=B", NFULNL_CFG_CMD_PF_BIND) + b"\0\0\0")))
        seq += 1
        sock.recv(4096)
    sock.send(_config_msg(seq, socket.AF_UNSPEC, group, _attr(NFULA_CFG_CMD, struct.pack("=B", NFULNL_CFG_CMD_BIND) + b"\0\0\0")))
    seq += 1
    _expect_ack(sock, "bind group")
    mode = struct.pack(">IBB", copy_range, NFULNL_COPY_PACKET, 0) + b"\0\0"
    sock.send(_config_msg(seq, socket.AF_UNSPEC, group, _attr(NFULA_CFG_MODE, mode)))
    _expect_ack(sock, "copy mode")
    return sock


def _expect_ack(sock, what):
    data = sock.recv(4096)
    _length, mtype, _flags, _seq, _pid = struct.unpack_from("=IHHII", data, 0)
    if mtype == NLMSG_ERROR:
        (err,) = struct.unpack_from("=i", data, 16)
        if err != 0:
            raise OSError(-err, f"NFLOG {what} gagal")


def parse_ipv4(payload):
    if len(payload) < 20 or payload[0] >> 4 != 4:
        return None
    ihl = (payload[0] & 0x0F) * 4
    proto = payload[9]
    info = {
        "src_ip": socket.inet_ntoa(payload[12:16]),
        "dst_ip": socket.inet_ntoa(payload[16:20]),
        "protocol": {6: "tcp", 17: "udp"}.get(proto, str(proto)),
    }
    if proto in (6, 17) and len(payload) >= ihl + 4:
        info["src_port"], info["dst_port"] = struct.unpack_from("!HH", payload, ihl)
    return info


def read_packets(sock):
    while True:
        try:
            data = sock.recv(65536)
        except OSError as exc:  # ENOBUFS: antrean penuh, paket terlewat tapi pembacaan lanjut
            print(json.dumps({"warning": f"recv: {exc}"}), file=sys.stderr, flush=True)
            continue
        offset = 0
        while offset + 16 <= len(data):
            length, mtype, _f, _s, _p = struct.unpack_from("=IHHII", data, offset)
            if length < 16:
                break
            if mtype == (NFNL_SUBSYS_ULOG << 8) | NFULNL_MSG_PACKET:
                yield _parse_packet(data[offset + 20: offset + length])
            offset += _align(length)


def _parse_packet(attrs):
    out = {"prefix": None, "packet": None}
    pos = 0
    while pos + 4 <= len(attrs):
        alen, atype = struct.unpack_from("=HH", attrs, pos)
        if alen < 4:
            break
        value = attrs[pos + 4: pos + alen]
        atype &= 0x3FFF
        if atype == NFULA_PREFIX:
            out["prefix"] = value.rstrip(b"\0").decode(errors="replace")
        elif atype == NFULA_PAYLOAD:
            out["packet"] = parse_ipv4(value)
        pos += _align(alen)
    return out


if __name__ == "__main__":
    group = int(sys.argv[1]) if len(sys.argv) > 1 else 7
    sock = open_nflog(group)
    print(json.dumps({"status": "listening", "group": group}), flush=True)
    for pkt in read_packets(sock):
        pkt["t"] = round(time.time(), 3)
        print(json.dumps(pkt), flush=True)
