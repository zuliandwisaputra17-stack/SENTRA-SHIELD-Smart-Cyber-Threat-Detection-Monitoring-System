<?php

use App\Detection\SensorWatch;
use App\Readiness\ReadinessService;
use App\Response\ActionService;
use App\Sites\SiteWatch;
use Illuminate\Support\Facades\Schedule;

// Blokir yang durasinya habis -> Kedaluwarsa (agent sudah membukanya lewat timeout nftables).
Schedule::call(fn () => app(ActionService::class)->expireBlocks())
    ->name('sentra:kedaluwarsa-blokir')
    ->everyTenSeconds()
    ->withoutOverlapping();

// Sensor Buta (FR-09): agent tanpa heartbeat > 30 detik -> BUTA + insiden Tinggi.
Schedule::call(fn () => app(SensorWatch::class)->checkHeartbeats())
    ->name('sentra:sensor-buta')
    ->everyTenSeconds()
    ->withoutOverlapping();

// Uji Kesiapan (FR-08): cocokkan deteksi uji dan selesaikan run yang sudah lengkap.
Schedule::call(fn () => app(ReadinessService::class)->tick())
    ->name('sentra:uji-kesiapan')
    ->everyTenSeconds()
    ->withoutOverlapping();

// Penjaga Situs (FR-13): situs satuan terdaftar diperiksa tiap menit. Dipanggil
// langsung (bukan Schedule::command) agar log scheduler hanya berisi ASCII; untuk
// demo tersedia juga `php artisan sentra:penjaga-situs`.
Schedule::call(fn () => app(SiteWatch::class)->checkAll())
    ->name('sentra:penjaga-situs')
    ->everyMinute()
    ->withoutOverlapping();
