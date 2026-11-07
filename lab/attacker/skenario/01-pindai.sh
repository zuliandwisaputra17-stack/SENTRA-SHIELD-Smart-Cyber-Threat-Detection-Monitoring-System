#!/usr/bin/env bash
# Pengintaian: pindai port target (memicu R01 Pemindaian port).
TARGET="${1:-172.30.0.11}"
nmap -sS -Pn -n -p 1-1000 "$TARGET"
