<?php

use App\Models\DetectionRule;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
| Katalog aturan PRD bagian 10: kode => [sumber, tipe, ATT&CK, tahap, bobot].
*/
dataset('katalog_prd', [
    'R01' => ['R01', 'firewall', 'threshold', 'T1046', 'pengintaian', 20],
    'R02' => ['R02', 'web', 'pattern', 'T1595.002', 'pengintaian', 15],
    'R03' => ['R03', 'web', 'threshold', 'T1595.003', 'pengintaian', 15],
    'R04' => ['R04', 'web', 'pattern', 'T1190', 'akses_awal', 35],
    'R05' => ['R05', 'web', 'pattern', 'T1190', 'akses_awal', 25],
    'R06' => ['R06', 'web', 'pattern', 'T1190', 'akses_awal', 35],
    'R07' => ['R07', 'ssh', 'threshold', 'T1110.001', 'akses_awal', 30],
    'R08' => ['R08', 'ssh', 'pattern', 'T1078', 'akses_awal', 50],
    'R09' => ['R09', 'fim', 'pattern', 'T1505.003', 'persistensi', 60],
    'R10' => ['R10', 'fim/site', 'pattern', 'T1491.001', 'dampak', 50],
    'R11' => ['R11', 'modbus', 'pattern', 'T0855', 'dampak', 60],
    'R12' => ['R12', 'heartbeat', 'threshold', 'T1562.001', 'pertahanan_dilumpuhkan', 80],
    'R13' => ['R13', 'site', 'pattern', 'T1491.002', 'dampak', 45],
]);

test('katalog aturan sesuai PRD bagian 10', function (string $code, string $source, string $type, string $attack, string $stage, int $weight) {
    $this->seed();
    $rule = DetectionRule::where('code', $code)->firstOrFail();

    expect($rule->source)->toBe($source)
        ->and($rule->type->value)->toBe($type)
        ->and($rule->attack_id)->toBe($attack)
        ->and($rule->stage->value)->toBe($stage)
        ->and($rule->weight)->toBe($weight)
        ->and($rule->enabled)->toBeTrue();
})->with('katalog_prd');

test('ambang awal sesuai PRD bagian 10', function () {
    $this->seed();
    $cfg = fn (string $code) => DetectionRule::where('code', $code)->value('config');

    expect($cfg('R01'))->toMatchArray(['window_s' => 60, 'threshold' => 15])
        ->and($cfg('R03'))->toMatchArray(['window_s' => 60, 'threshold' => 30])
        ->and($cfg('R07'))->toMatchArray(['window_s' => 60, 'threshold' => 10])
        ->and($cfg('R08'))->toMatchArray(['requires_rule' => 'R07', 'within_s' => 1800])
        ->and($cfg('R11')['function_codes'])->toBe([5, 6, 15, 16])
        ->and($cfg('R12'))->toMatchArray(['heartbeat_timeout_s' => 30, 'source_silence_s' => 300]);
});

test('pola regex di katalog valid', function () {
    $this->seed();

    foreach (DetectionRule::all() as $rule) {
        $patterns = array_merge(
            $rule->config['patterns'] ?? [],
            $rule->config['content_patterns'] ?? [],
            isset($rule->config['path_pattern']) ? [$rule->config['path_pattern']] : [],
        );
        foreach ($patterns as $pattern) {
            expect(@preg_match('~'.str_replace('~', '\~', $pattern).'~i', ''))
                ->not->toBeFalse("{$rule->code}: {$pattern}");
        }
    }
});

test('hierarki satuan dan akun per peran dibuat', function () {
    $this->seed();

    expect(Unit::count())->toBe(4)
        ->and(Unit::where('code', 'KODIM-LAT-A')->first()->parent->code)->toBe('KOREM-LAT-01')
        ->and(Unit::where('code', 'KOREM-LAT-01')->first()->parent->code)->toBe('KODAM-LAT-I')
        ->and(User::pluck('role')->map->value->sort()->values()->all())
        ->toBe(['admin', 'analis', 'demo', 'komandan', 'operator', 'operator'])
        ->and(Setting::get('block_duration_s'))->toBe(3600)
        ->and(Setting::get('ip_whitelist'))->toBe([]);
});

test('seeder tidak membuat data telemetri atau insiden palsu', function () {
    $this->seed();

    foreach (['events', 'detections', 'incidents', 'campaigns', 'actions', 'audit_logs', 'readiness_runs', 'site_checks', 'reports'] as $table) {
        expect(DB::table($table)->count())->toBe(0, "tabel {$table} harus kosong");
    }
});

test('seeder aman dijalankan ulang dan tidak menimpa perubahan Admin', function () {
    $this->seed();
    DetectionRule::where('code', 'R07')->update(['weight' => 99]);

    $this->seed();

    expect(Unit::count())->toBe(4)
        ->and(User::count())->toBe(6)
        ->and(DetectionRule::count())->toBe(13)
        ->and(DetectionRule::where('code', 'R07')->value('weight'))->toBe(99);
});
