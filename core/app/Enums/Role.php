<?php

namespace App\Enums;

/**
 * Peran pengguna (PRD bagian 3). "demo" adalah akun read-only untuk F15.
 */
enum Role: string
{
    case Operator = 'operator';
    case Analis = 'analis';
    case Komandan = 'komandan';
    case Admin = 'admin';
    case Demo = 'demo';

    public function label(): string
    {
        return match ($this) {
            self::Operator => 'Operator',
            self::Analis => 'Analis',
            self::Komandan => 'Komandan',
            self::Admin => 'Admin',
            self::Demo => 'Demo (hanya lihat)',
        };
    }

    /** Operator hanya melihat satuannya sendiri (dan satuan di bawahnya). */
    public function seesAllUnits(): bool
    {
        return $this !== self::Operator;
    }
}
