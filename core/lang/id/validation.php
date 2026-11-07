<?php

/*
| Pesan validasi bahasa Indonesia untuk aturan yang dipakai aplikasi.
| Aturan lain jatuh ke bahasa cadangan (en).
*/
return [
    'accepted' => ':attribute harus diterima.',
    'array' => ':attribute harus berupa daftar.',
    'between' => [
        'numeric' => ':attribute harus antara :min dan :max.',
        'string' => ':attribute harus antara :min dan :max karakter.',
        'array' => ':attribute harus berisi antara :min dan :max item.',
    ],
    'boolean' => ':attribute harus benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'current_password' => 'Kata sandi salah.',
    'date' => ':attribute bukan tanggal yang valid.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'ip' => ':attribute harus berupa alamat IP yang valid.',
    'json' => ':attribute harus berupa JSON yang valid.',
    'lowercase' => ':attribute harus huruf kecil.',
    'max' => [
        'numeric' => ':attribute tidak boleh lebih dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
        'array' => ':attribute tidak boleh lebih dari :max item.',
    ],
    'min' => [
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
        'array' => ':attribute minimal berisi :min item.',
    ],
    'numeric' => ':attribute harus berupa angka.',
    'password' => [
        'letters' => ':attribute harus memuat minimal satu huruf.',
        'mixed' => ':attribute harus memuat huruf besar dan huruf kecil.',
        'numbers' => ':attribute harus memuat minimal satu angka.',
        'symbols' => ':attribute harus memuat minimal satu simbol.',
        'uncompromised' => ':attribute ini pernah bocor. Gunakan :attribute lain.',
    ],
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah dipakai.',
    'uuid' => ':attribute harus berupa UUID yang valid.',

    'attributes' => [
        'name' => 'nama',
        'email' => 'email',
        'password' => 'kata sandi',
        'current_password' => 'kata sandi saat ini',
        'form.email' => 'email',
        'form.password' => 'kata sandi',
    ],
];
