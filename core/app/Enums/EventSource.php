<?php

namespace App\Enums;

enum EventSource: string
{
    case Web = 'web';
    case Ssh = 'ssh';
    case Firewall = 'firewall';
    case Fim = 'fim';
    case Modbus = 'modbus';
    case Site = 'site';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Log web',
            self::Ssh => 'SSH',
            self::Firewall => 'Firewall',
            self::Fim => 'Integritas file (FIM)',
            self::Modbus => 'Modbus (OT)',
            self::Site => 'Penjaga Situs',
        };
    }
}
