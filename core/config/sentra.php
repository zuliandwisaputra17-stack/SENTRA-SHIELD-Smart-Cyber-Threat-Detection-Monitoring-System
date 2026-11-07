<?php

return [
    /*
    | Zona waktu tampilan. Data disimpan dalam UTC (app.timezone) dan
    | ditampilkan dalam WIB.
    */
    'display_timezone' => env('SENTRA_DISPLAY_TIMEZONE', 'Asia/Jakarta'),

    /*
    | Kata sandi awal akun lab yang dibuat seeder. Ganti setelah login pertama
    | bila dipakai di luar lab.
    */
    'seed_password' => env('SENTRA_SEED_PASSWORD', 'Latihan#2026'),

    /*
    | Penjaga Situs (FR-13). Bawaan: hanya situs di jaringan privat (situs
    | dummy milik sendiri di lab). Set true hanya untuk situs resmi satuan
    | yang memang menjadi tanggung jawab pemasang.
    */
    'sites' => [
        'allow_public' => (bool) env('SENTRA_SITES_ALLOW_PUBLIC', false),
    ],

    'ollama' => [
        'url' => env('SENTRA_OLLAMA_URL', 'http://ollama:11434'),
        'model' => env('SENTRA_OLLAMA_MODEL', 'qwen2.5:3b'),
    ],

    /*
    | Lab demo (docker compose). Daftar agent lab berformat
    | "hostname:KODE-SATUAN:jenis_aset" dipisah koma. Kode pendaftaran acak
    | ditulis ke enrollment_dir agar bisa dibaca container agent lab.
    */
    'lab' => [
        'agents' => env('SENTRA_LAB_AGENTS', ''),
        // Label "DATA LATIHAN" di setiap halaman (FR-15). Bawaan: aktif bila ini lab.
        'training_label' => (bool) env('SENTRA_TRAINING_LABEL', env('SENTRA_LAB_AGENTS', '') !== ''),
        // Situs dummy lab untuk Penjaga Situs: "KODE-SATUAN=url" dipisah koma.
        'sites' => env('SENTRA_LAB_SITES', ''),
        'enrollment_dir' => env('SENTRA_LAB_ENROLLMENT_DIR'),
    ],
];
