<?php

use App\Detection\RiskScorer;
use App\Enums\AssetType;
use App\Enums\IncidentStatus;
use App\Models\Detection;
use App\Models\Incident;
use App\Models\Unit;
use App\Services\AgentEnrollment;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->seed();
    $this->web = agentLab('kodim-a');
});

test('contoh perhitungan PRD: R01+R07+R08+R09, 3 tahap, server web = 240 Kritis', function () {
    deteksi(...pindai($this->web, 15, now()->subMinutes(5)));
    deteksi(...sshGagal($this->web, 10, now()->subMinutes(4)));
    deteksi(eventLab($this->web, 'ssh', ['outcome' => 'success', 'user' => 'latihan'], PENYERANG, 22, now()->subMinutes(3)));
    deteksi(eventLab($this->web, 'fim', ['path' => '/var/www/html/uploads/x.php', 'change' => 'created', 'sample' => '<?php eval(base64_decode($_POST["x"])); ?>'], null, null, now()->subMinutes(2)));

    $incident = Incident::sole();
    $b = $incident->score_breakdown;

    expect($incident->score)->toBe(240)
        ->and($incident->severity->value)->toBe('kritis')
        ->and($incident->src_ip)->toBe(PENYERANG)
        ->and(array_column($b['aturan'], 'poin', 'kode'))->toBe(['R01' => 20, 'R07' => 30, 'R08' => 50, 'R09' => 60])
        ->and($b['tahap']['jumlah'])->toBe(3)
        ->and($b['tahap']['poin'])->toBe(40)
        ->and($b['subtotal'])->toBe(200)
        ->and($b['pengali_aset']['nilai'])->toBe(1.2)
        ->and($b['rumus'])->toBe('(160 + 40) x 1.2 x 1 = 240')
        ->and($incident->stage_max->value)->toBe('persistensi');

    $webshell = Detection::whereHas('rule', fn ($q) => $q->where('code', 'R09'))->sole();
    expect($webshell->incident_id)->toBe($incident->id)
        ->and($webshell->attribution->value)->toBe('waktu');
});

test('satu rangkaian pemindaian, brute force, dan eksploitasi dari satu IP menjadi 1 insiden', function () {
    deteksi(...pindai($this->web, 30));
    deteksi(...sshGagal($this->web, 15));
    for ($i = 0; $i < 25; $i++) {
        deteksi(eventLab($this->web, 'web', ['path' => '/berita.php', 'query' => "id=1' UNION SELECT {$i}--", 'status' => 500, 'user_agent' => 'sqlmap/1.8']));
    }

    expect(Incident::count())->toBe(1)
        ->and(Detection::count())->toBeGreaterThan(40)
        ->and(Incident::sole()->score_breakdown['tahap']['daftar'])->toBe(['pengintaian', 'akses_awal']);
});

test('aturan yang berulang dihitung sekali dalam skor', function () {
    for ($i = 0; $i < 5; $i++) {
        deteksi(eventLab($this->web, 'web', ['path' => '/', 'query' => '', 'status' => 200, 'user_agent' => 'nikto']));
    }
    $incident = Incident::sole();

    // R02 = 15, 1 tahap (tanpa bonus), server web 1,2 -> 18.
    expect($incident->score)->toBe(18)
        ->and($incident->score_breakdown['aturan'][0]['jumlah_deteksi'])->toBe(5)
        ->and($incident->severity->value)->toBe('rendah');
});

test('IP berbeda atau satuan berbeda menghasilkan insiden terpisah', function () {
    $kodimB = agentLab('kodim-b', 'KODIM-LAT-B');

    deteksi(eventLab($this->web, 'web', ['user_agent' => 'nmap', 'path' => '/'], PENYERANG));
    deteksi(eventLab($this->web, 'web', ['user_agent' => 'nmap', 'path' => '/'], '172.30.0.77'));
    deteksi(eventLab($kodimB, 'web', ['user_agent' => 'nmap', 'path' => '/'], PENYERANG));

    expect(Incident::count())->toBe(3);
});

test('deteksi setelah lebih dari 30 menit membuat insiden baru', function () {
    deteksi(eventLab($this->web, 'web', ['user_agent' => 'nikto', 'path' => '/'], PENYERANG, null, now()->subMinutes(45)));
    deteksi(eventLab($this->web, 'web', ['user_agent' => 'nikto', 'path' => '/'], PENYERANG, null, now()->subMinutes(10)));

    expect(Incident::count())->toBe(2);
});

test('insiden yang sudah selesai tidak dilanjutkan', function () {
    deteksi(eventLab($this->web, 'web', ['user_agent' => 'nikto', 'path' => '/']));
    Incident::sole()->update(['status' => IncidentStatus::Selesai]);
    deteksi(eventLab($this->web, 'web', ['user_agent' => 'nikto', 'path' => '/']));

    expect(Incident::count())->toBe(2);
});

test('FIM tanpa insiden IP membentuk insiden tanpa IP di satuannya', function () {
    deteksi(eventLab($this->web, 'fim', ['path' => '/var/www/html/index.php', 'change' => 'modified'], null));

    $incident = Incident::sole();
    expect($incident->src_ip)->toBeNull()
        ->and($incident->title)->toContain('Kodim Latihan A');
});

test('aset OT memakai pengali 1,5', function () {
    $plc = agentLab('plc-sim', 'KODIM-LAT-A', AssetType::Ot);
    deteksi(eventLab($this->web, 'web', ['user_agent' => 'nmap', 'path' => '/']));
    deteksi(eventLab($plc, 'modbus', ['function_code' => 6], PENYERANG, 502));

    $incident = Incident::sole();
    // R02 15 + R11 60 + bonus 1 tahap tambahan (20) = 95 x 1,5 = 142,5 -> 143 Tinggi.
    expect($incident->score_breakdown['pengali_aset']['nilai'])->toBe(1.5)
        ->and($incident->score_breakdown['pengali_aset']['host'])->toBe('plc-sim')
        ->and($incident->score)->toBe(143)
        ->and($incident->severity->value)->toBe('tinggi');
});

test('skor diperbarui otomatis saat deteksi baru masuk', function () {
    deteksi(eventLab($this->web, 'web', ['user_agent' => 'nikto', 'path' => '/']));
    $before = Incident::sole()->score;
    deteksi(eventLab($this->web, 'web', ['user_agent' => 'x', 'path' => '/berita.php', 'query' => "id=1' OR '1'='1"]));

    expect(Incident::sole()->score)->toBeGreaterThan($before);
});

test('Tingkat Siaga satuan mengikuti insiden terbuka dan kembali Aman setelah selesai', function () {
    deteksi(...pindai($this->web, 15, now()->subMinutes(5)));
    deteksi(...sshGagal($this->web, 10, now()->subMinutes(4)));
    deteksi(eventLab($this->web, 'ssh', ['outcome' => 'success', 'user' => 'latihan'], PENYERANG, 22, now()->subMinutes(3)));

    $unit = Unit::where('code', 'KODIM-LAT-A')->first();
    // 20 + 30 + 50 + 20 = 120 x 1,2 = 144 -> Tinggi -> Siaga.
    expect($unit->fresh()->alert_level->value)->toBe('siaga');

    $incident = Incident::sole();
    $incident->update(['status' => IncidentStatus::Selesai]);
    app(RiskScorer::class)->refreshUnitAlert($unit->id);
    expect($unit->fresh()->alert_level->value)->toBe('aman');
});

test('event dari API agent langsung diproses mesin deteksi', function () {
    [$agent, $code] = [$this->web, app(AgentEnrollment::class)->issueCode($this->web)];
    $token = $this->postJson('/api/v1/agent/register', ['enrollment_code' => $code, 'hostname' => 'kodim-a', 'version' => '0.1.0'])->json('token');

    $events = [];
    for ($i = 0; $i < 12; $i++) {
        $events[] = ['source' => 'ssh', 'occurred_at' => now()->subSeconds(20 - $i)->toIso8601String(), 'src_ip' => PENYERANG, 'dst_port' => 22, 'payload' => ['outcome' => 'failed', 'user' => 'root'], 'is_test' => false];
    }
    $this->withToken($token)->postJson('/api/v1/agent/events', ['events' => $events])->assertOk();

    expect(Incident::sole()->score_breakdown['aturan'][0]['kode'])->toBe('R07');
});
