<?php

use App\Enums\AssetType;
use App\Models\Detection;
use App\Models\DetectionRule;
use App\Models\Incident;
use App\Models\Setting;
use Illuminate\Support\Carbon;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->seed();
    $this->web = agentLab('kodim-a');
});

test('R02 alat pemindai web dari user agent', function () {
    $d = deteksi(eventLab($this->web, 'web', ['path' => '/', 'query' => '', 'status' => 200, 'user_agent' => 'sqlmap/1.8#stable']));
    expect(kodeAturan($d))->toBe(['R02']);
});

test('R04 SQL injection walau di-URL-encode', function (string $query) {
    $d = deteksi(eventLab($this->web, 'web', ['path' => '/berita.php', 'query' => $query, 'status' => 500, 'user_agent' => 'Mozilla/5.0']));
    expect(kodeAturan($d))->toContain('R04');
})->with([
    "id=1' OR '1'='1",
    'id=1%27%20OR%20%271%27%3D%271',
    'id=-1%20UNION%20ALL%20SELECT%20NULL%2CNULL',
    'id=1%20AND%20SLEEP(5)',
    'id=1--',
]);

test('R05 XSS dan R06 path traversal', function () {
    $xss = deteksi(eventLab($this->web, 'web', ['path' => '/cari', 'query' => 'q=%3Cscript%3Ealert(1)%3C/script%3E', 'status' => 200, 'user_agent' => 'x']));
    $lfi = deteksi(eventLab($this->web, 'web', ['path' => '/index.php', 'query' => 'page=../../../../etc/passwd', 'status' => 200, 'user_agent' => 'x']));

    expect(kodeAturan($xss))->toBe(['R05'])
        ->and(kodeAturan($lfi))->toBe(['R06']);
});

test('permintaan normal tidak memicu aturan apa pun, tetapi event tetap tersimpan', function () {
    $event = eventLab($this->web, 'web', ['path' => '/berita.php', 'query' => 'id=2', 'status' => 200, 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0)']);

    expect(deteksi($event))->toBe([])
        ->and($event->fresh())->not->toBeNull();
});

test('R01 butuh 15 port berbeda dan hanya satu deteksi per jendela', function () {
    $first14 = pindai($this->web, 14);
    expect(deteksi(...$first14))->toBe([]);

    $rest = [];
    for ($i = 0; $i < 20; $i++) {
        $rest[] = eventLab($this->web, 'firewall', ['action' => 'port_tertutup'], PENYERANG, 2000 + $i, now()->subSeconds(10));
    }
    $d = deteksi(...$rest);

    expect(kodeAturan($d))->toBe(['R01'])
        ->and(count($d))->toBe(1);
});

test('R01 tidak terpicu bila port tersebar di luar jendela 60 detik', function () {
    $events = [];
    for ($i = 0; $i < 20; $i++) {
        $events[] = eventLab($this->web, 'firewall', [], PENYERANG, 3000 + $i, now()->subSeconds(400 - $i * 20));
    }
    expect(deteksi(...$events))->toBe([]);
});

test('R03 brute force direktori: 30 respons 404 dalam 60 detik', function () {
    $events = [];
    for ($i = 0; $i < 30; $i++) {
        $events[] = eventLab($this->web, 'web', ['path' => "/tidakada{$i}", 'query' => '', 'status' => 404, 'user_agent' => 'Mozilla/5.0'], PENYERANG, null, now()->subSeconds(30 - $i));
    }
    expect(kodeAturan(deteksi(...$events)))->toBe(['R03']);
});

test('R07 brute force SSH lalu R08 login berhasil dari IP yang sama', function () {
    expect(kodeAturan(deteksi(...sshGagal($this->web, 9))))->toBe([]);
    expect(kodeAturan(deteksi(...sshGagal($this->web, 1, now()->subSeconds(5)))))->toBe(['R07']);

    $success = eventLab($this->web, 'ssh', ['outcome' => 'success', 'user' => 'latihan'], PENYERANG, 22);
    expect(kodeAturan(deteksi($success)))->toBe(['R08']);
});

test('R08 tidak terpicu tanpa brute force sebelumnya', function () {
    $success = eventLab($this->web, 'ssh', ['outcome' => 'success', 'user' => 'latihan'], '10.9.9.9', 22);
    expect(deteksi($success))->toBe([]);
});

test('R09 webshell dari FIM', function () {
    $d = deteksi(eventLab($this->web, 'fim', [
        'path' => '/var/www/html/uploads/laporan.php', 'change' => 'created',
        'sample' => '<?php if (isset($_GET["c"])) { system($_GET["c"]); } ?>', 'sha256' => str_repeat('a', 64),
    ], null));

    expect(kodeAturan($d))->toBe(['R09'])
        ->and($d[0]->attribution->value)->toBe('waktu');
});

test('file PHP biasa tidak dianggap webshell', function () {
    expect(deteksi(eventLab($this->web, 'fim', ['path' => '/var/www/html/uploads/biasa.php', 'change' => 'created', 'sample' => '<?php echo "halo"; ?>'], null)))->toBe([]);
});

test('R10 defacement: halaman utama berubah di luar jadwal pembaruan', function () {
    $d = deteksi(eventLab($this->web, 'fim', ['path' => '/var/www/html/index.php', 'change' => 'modified', 'sample' => '<h1>hacked</h1>'], null));
    expect(kodeAturan($d))->toBe(['R10']);
});

test('R10 tidak terpicu di dalam jadwal pembaruan resmi', function () {
    $rule = DetectionRule::where('code', 'R10')->first();
    $rule->update(['config' => array_merge($rule->config, ['update_windows' => [['hari' => [1, 2, 3, 4, 5, 6, 7], 'mulai' => '00:00', 'selesai' => '23:59']]])]);

    expect(deteksi(eventLab($this->web, 'fim', ['path' => '/var/www/html/index.php', 'change' => 'modified'], null)))->toBe([]);
});

test('R11 perintah tulis Modbus dari IP di luar daftar HMI', function () {
    $plc = agentLab('plc-sim', 'KODIM-LAT-A', AssetType::Ot);
    $rule = DetectionRule::where('code', 'R11')->first();
    $rule->update(['config' => array_merge($rule->config, ['hmi_allowlist' => ['172.32.0.50']])]);

    $attack = eventLab($plc, 'modbus', ['function_code' => 6, 'address' => 0, 'value' => 999], PENYERANG, 502);
    $hmi = eventLab($plc, 'modbus', ['function_code' => 6, 'address' => 0, 'value' => 350], '172.32.0.50', 502);

    expect(kodeAturan(deteksi($attack)))->toBe(['R11'])
        ->and(deteksi($hmi))->toBe([]);
});

test('IP di daftar putih tidak memicu deteksi tetapi event tetap disimpan', function () {
    Setting::put('ip_whitelist', ['172.30.0.0/24']);

    $event = eventLab($this->web, 'web', ['path' => '/', 'query' => "id=1' OR '1'='1", 'status' => 200, 'user_agent' => 'sqlmap']);
    expect(deteksi($event))->toBe([])
        ->and(Detection::count())->toBe(0)
        ->and($event->fresh())->not->toBeNull();
});

test('aturan yang dinonaktifkan tidak dievaluasi', function () {
    DetectionRule::where('code', 'R02')->update(['enabled' => false]);

    expect(deteksi(eventLab($this->web, 'web', ['path' => '/', 'query' => '', 'status' => 200, 'user_agent' => 'nikto'])))->toBe([]);
});

test('ambang yang diubah dari dashboard langsung dipakai', function () {
    $rule = DetectionRule::where('code', 'R07')->first();
    $rule->update(['config' => array_merge($rule->config, ['threshold' => 3])]);

    expect(kodeAturan(deteksi(...sshGagal($this->web, 3))))->toBe(['R07']);
});

test('setiap deteksi menyimpan aturan, event bukti, dan teknik ATT&CK', function () {
    $event = eventLab($this->web, 'web', ['path' => '/', 'query' => '', 'status' => 200, 'user_agent' => 'nikto/2.5']);
    $d = deteksi($event)[0]->fresh();

    expect($d->event_id)->toBe($event->id)
        ->and($d->rule->code)->toBe('R02')
        ->and($d->attack_id)->toBe('T1595.002')
        ->and($d->weight)->toBe(15);
});

test('event Uji Kesiapan dihitung terpisah dan tidak menjadi insiden', function () {
    $d = deteksi(eventLab($this->web, 'web', ['path' => '/', 'query' => '', 'status' => 200, 'user_agent' => 'sqlmap'], '127.0.0.1', null, null, true));

    expect(kodeAturan($d))->toBe(['R02'])
        ->and($d[0]->fresh()->incident_id)->toBeNull()
        ->and(Incident::count())->toBe(0);
});

test('regresi: pemindaian cepat dalam satu detik yang sama tetap terdeteksi', function () {
    // nmap memindai ratusan port dalam < 1 detik; batas jendela harus memakai milidetik.
    $t = Carbon::parse('2026-11-12 02:14:03.100', 'UTC');
    $events = [];
    for ($i = 0; $i < 20; $i++) {
        $events[] = eventLab($this->web, 'firewall', ['action' => 'port_tertutup'], PENYERANG, 4000 + $i, $t->copy()->addMilliseconds($i * 5));
    }
    expect(kodeAturan(deteksi(...$events)))->toBe(['R01']);

    $ssh = [];
    for ($i = 0; $i < 10; $i++) {
        $ssh[] = eventLab($this->web, 'ssh', ['outcome' => 'failed', 'user' => 'root'], PENYERANG, 22, $t->copy()->addMilliseconds(600 + $i * 10));
    }
    expect(kodeAturan(deteksi(...$ssh)))->toBe(['R07']);
});
