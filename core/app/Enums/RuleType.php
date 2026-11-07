<?php

namespace App\Enums;

enum RuleType: string
{
    case Pattern = 'pattern';
    case Threshold = 'threshold';

    public function label(): string
    {
        return match ($this) {
            self::Pattern => 'Pola',
            self::Threshold => 'Ambang',
        };
    }
}
