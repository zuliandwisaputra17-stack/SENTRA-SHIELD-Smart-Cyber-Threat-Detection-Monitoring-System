<?php

namespace App\Enums;

enum AgentStatus: string
{
    /** Dibuat Admin, menunggu agent menukar kode pendaftaran. */
    case Menunggu = 'menunggu';
    case Online = 'online';
    case Buta = 'buta';
    case Dicabut = 'dicabut';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu pendaftaran',
            self::Online => 'Online',
            self::Buta => 'BUTA',
            self::Dicabut => 'Dicabut',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Menunggu => 'bg-inset text-ink-2 ring-line-strong',
            self::Online => 'bg-aman/10 text-aman ring-aman/40',
            self::Buta => 'bg-awas/15 text-awas ring-awas/50',
            self::Dicabut => 'bg-inset text-ink-3 ring-line-strong',
        };
    }
}
