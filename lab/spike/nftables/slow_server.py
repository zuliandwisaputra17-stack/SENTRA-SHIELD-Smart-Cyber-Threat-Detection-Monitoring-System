#!/usr/bin/env python3
"""Server TCP sederhana di port 9000 yang mengirim satu baris tiap 0,5 detik.
Dipakai untuk membuktikan koneksi yang SUDAH terbuka ikut terputus saat diblokir."""
import socket
import threading
import time


def handle(conn):
    n = 0
    try:
        while True:
            conn.sendall(f"tick {n}\n".encode())
            n += 1
            time.sleep(0.5)
    except OSError:
        pass


srv = socket.socket()
srv.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
srv.bind(("0.0.0.0", 9000))
srv.listen()
while True:
    c, _ = srv.accept()
    threading.Thread(target=handle, args=(c,), daemon=True).start()
