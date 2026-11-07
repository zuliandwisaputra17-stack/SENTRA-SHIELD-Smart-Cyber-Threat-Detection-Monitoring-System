<?php

namespace App\Jobs;

use App\Detection\DetectionEngine;
use App\Support\LiveUpdate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Menjalankan mesin deteksi untuk satu batch event yang baru disimpan.
 * Worker antrean di lab berjalan satu proses, sehingga batch diproses berurutan.
 */
class ProcessEvents implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param list<int> $eventIds */
    public function __construct(public array $eventIds) {}

    public function handle(DetectionEngine $engine): void
    {
        $detections = $engine->processIds($this->eventIds);
        if ($detections !== []) {
            LiveUpdate::notify('insiden', array_map(fn ($d) => $d->unit_id, $detections));
        }
    }
}
