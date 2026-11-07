<?php

namespace App\Enums;

/**
 * Daftar tetap perintah ke agent (PRD bagian 6 & 9). Tidak ada eksekusi bebas.
 */
enum ActionType: string
{
    case BlockIp = 'block_ip';
    case UnblockIp = 'unblock_ip';
    case RunTest = 'run_test';

    public function label(): string
    {
        return match ($this) {
            self::BlockIp => 'Blokir IP',
            self::UnblockIp => 'Buka blokir IP',
            self::RunTest => 'Jalankan uji',
        };
    }
}
