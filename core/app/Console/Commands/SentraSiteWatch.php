<?php

namespace App\Console\Commands;

use App\Models\MonitoredSite;
use App\Sites\SiteWatch;
use Illuminate\Console\Command;

/**
 * Penjaga Situs (FR-13): periksa semua situs terdaftar sekarang.
 * Dijalankan scheduler tiap menit; bisa juga dipanggil manual saat demo.
 */
class SentraSiteWatch extends Command
{
    protected $signature = 'sentra:penjaga-situs';

    protected $description = 'Periksa situs satuan terdaftar (3 varian: browser, Googlebot, referer Google)';

    public function handle(SiteWatch $watch): int
    {
        foreach (MonitoredSite::query()->orderBy('id')->get() as $site) {
            $checks = $watch->check($site);
            $flags = $checks->filter->flagged->map(fn ($c) => $c->variant->label())->implode(', ');
            $this->components->twoColumnDetail($site->url, $flags !== '' ? "<fg=red>DITANDAI: {$flags}</>" : '<fg=green>aman</>');
        }

        return self::SUCCESS;
    }
}
