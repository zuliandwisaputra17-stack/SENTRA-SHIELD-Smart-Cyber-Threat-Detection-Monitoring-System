<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sinyal "ada perubahan" untuk dashboard live (FR-07).
 *
 * Sengaja TIDAK membawa data insiden/event: setiap halaman mengambil ulang
 * datanya lewat Livewire sesuai hak akses penggunanya, sehingga data satuan
 * lain tidak pernah terkirim lewat WebSocket.
 */
class DashboardChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param list<int> $unitIds */
    public function __construct(public string $jenis, public array $unitIds = []) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('sentra.dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'perubahan';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['jenis' => $this->jenis, 'unit_ids' => array_values($this->unitIds)];
    }
}
