<?php

namespace App\Support;

use App\Events\DashboardChanged;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kirim sinyal pembaruan dashboard lewat Reverb. Kegagalan siaran (mis.
 * Reverb mati) tidak boleh menggagalkan ingest event atau tindakan;
 * dashboard tetap diperbarui lewat cadangan polling 3 detik.
 */
final class LiveUpdate
{
    /** @param list<int|null> $unitIds */
    public static function notify(string $jenis, array $unitIds = []): void
    {
        try {
            broadcast(new DashboardChanged($jenis, array_values(array_unique(array_filter($unitIds)))));
        } catch (Throwable $e) {
            Log::warning('Siaran dashboard gagal, klien memakai polling: '.$e->getMessage());
        }
    }
}
