<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\User;
use App\Reports\LapsitService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Menyusun satu LAPSIT di antrean "lapsit" (FR-12). */
class GenerateLapsit implements ShouldQueue
{
    use Queueable;

    /** Di atas batas waktu LLM (maks 120 detik) + PDF; di bawah retry_after antrean (300). */
    public int $timeout = 240;

    public int $tries = 1;

    public function __construct(public int $incidentId, public ?int $userId) {}

    public function handle(LapsitService $lapsit): void
    {
        $incident = Incident::query()->find($this->incidentId);
        if ($incident === null) {
            Cache::forget(LapsitService::pendingKey($this->incidentId));

            return;
        }
        $lapsit->generate($incident, $this->userId ? User::query()->find($this->userId) : null);
    }

    public function failed(?Throwable $e): void
    {
        Cache::forget(LapsitService::pendingKey($this->incidentId));
    }
}
