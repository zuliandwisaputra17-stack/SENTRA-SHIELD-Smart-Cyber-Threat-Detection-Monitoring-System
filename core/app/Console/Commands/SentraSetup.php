<?php

namespace App\Console\Commands;

use App\Enums\AgentStatus;
use App\Enums\AssetType;
use App\Models\Agent;
use App\Models\MonitoredSite;
use App\Models\Unit;
use App\Services\AgentEnrollment;
use Illuminate\Console\Command;

/**
 * Setup Core sekali jalan dan aman diulang (PRD bagian 6: Deployability).
 * Di lab dijalankan otomatis oleh entrypoint container sentra-core.
 */
class SentraSetup extends Command
{
    protected $signature = 'sentra:setup {--no-optimize : Lewati cache konfigurasi/rute/view}';

    protected $description = 'Migrasi database, isi data master, dan siapkan agent lab (aman dijalankan ulang)';

    public function handle(AgentEnrollment $enrollment): int
    {
        $this->components->info('Migrasi database');
        $this->call('migrate', ['--force' => true]);

        $this->components->info('Data master (satuan, akun, aturan, pengaturan)');
        $this->call('db:seed', ['--force' => true]);

        $this->provisionLabAgents($enrollment);
        $this->provisionLabSites();

        if (! $this->option('no-optimize')) {
            $this->call('optimize');
        }

        $this->components->info('SENTRA Core siap.');

        return self::SUCCESS;
    }

    /** Daftarkan situs dummy lab ke Penjaga Situs (sekali; tidak menimpa yang sudah ada). */
    private function provisionLabSites(): void
    {
        $spec = trim((string) config('sentra.lab.sites'));
        if ($spec === '') {
            return;
        }
        $this->components->info('Situs lab (Penjaga Situs)');
        foreach (array_filter(array_map('trim', explode(',', $spec))) as $entry) {
            [$unitCode, $url] = array_pad(explode('=', $entry, 2), 2, '');
            $unit = Unit::where('code', $unitCode)->first();
            if ($unit === null || ! filter_var($url, FILTER_VALIDATE_URL)) {
                $this->components->warn("Lewati {$entry}: satuan atau URL tidak valid");

                continue;
            }
            $site = MonitoredSite::firstOrCreate(['url' => $url], ['unit_id' => $unit->id]);
            $this->components->twoColumnDetail($url, $site->wasRecentlyCreated ? 'didaftarkan' : 'sudah terdaftar');
        }
    }

    private function provisionLabAgents(AgentEnrollment $enrollment): void
    {
        $spec = trim((string) config('sentra.lab.agents'));
        $dir = config('sentra.lab.enrollment_dir');
        if ($spec === '' || ! $dir) {
            return;
        }

        $this->components->info('Agent lab');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach (array_filter(array_map('trim', explode(',', $spec))) as $entry) {
            [$hostname, $unitCode, $assetType] = array_pad(explode(':', $entry), 3, 'lainnya');
            $unit = Unit::where('code', $unitCode)->first();
            if ($unit === null || AssetType::tryFrom($assetType) === null) {
                $this->components->warn("Lewati {$entry}: satuan atau jenis aset tidak dikenal");

                continue;
            }

            $agent = Agent::firstOrCreate(
                ['hostname' => $hostname],
                ['unit_id' => $unit->id, 'asset_type' => $assetType, 'status' => AgentStatus::Menunggu],
            );

            $file = rtrim($dir, '/').'/'.$hostname.'.code';
            if ($agent->status === AgentStatus::Menunggu) {
                file_put_contents($file, $enrollment->issueCode($agent));
                $this->components->twoColumnDetail($hostname, 'kode pendaftaran baru ditulis');
            } else {
                @unlink($file);
                $this->components->twoColumnDetail($hostname, 'sudah terdaftar ('.$agent->status->value.')');
            }
        }
    }
}
