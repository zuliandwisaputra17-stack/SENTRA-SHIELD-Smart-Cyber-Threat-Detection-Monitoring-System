<?php

namespace App\Enums;

enum SiteVariant: string
{
    case Browser = 'browser';
    case Googlebot = 'googlebot';
    case Referer = 'referer';

    public function label(): string
    {
        return match ($this) {
            self::Browser => 'Browser biasa',
            self::Googlebot => 'Googlebot',
            self::Referer => 'Referer Google',
        };
    }
}
