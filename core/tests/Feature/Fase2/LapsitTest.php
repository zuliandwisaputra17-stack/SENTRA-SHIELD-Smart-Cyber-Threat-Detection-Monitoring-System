<?php

use App\Enums\ReportGenerator;
use App\Livewire\Admin\Settings;
use App\Livewire\Incidents\IncidentShow;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;
use App\Reports\IncidentFacts;
use App\Reports\LapsitService;
use App\Response\ActionService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

require_once __DIR__.'/../Fase1b/helpers.php';

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
    $this->web = agentLab('kodim-a');
    deteksi(...pindai($this->web, 20));
    deteksi(...sshGagal($this->web, 10));
    $this->incident = Incident::sole();
    $this->analis = User::where('email', 'analis@sentra.lab')->first();
    $this->evIds = IncidentFacts::for($this->incident)->eventIds;
});

function jawabanLlm(array $draft): array
{
    return ['*/api/chat' => Http::response(['model' => 'qwen2.5:3b', 'message' => ['role' => 'assistant', 'content' => json_encode($draft)], 'done' => true])];
}

test('Ollama tidak tersedia: LAPSIT dari template dengan sitasi bukti, PDF ber-hash, audit', function () {
    Http::fake(['*' => Http::failedConnection()]);

    Livewire::actingAs($this->analis)->test(IncidentShow::class, ['incident' => $this->incident])
        ->call('createReport')
        ->assertHasNoErrors()
        ->assertSee('LAPSIT/INS-'.$this->incident->id.'/1')
        ->assertSee('Template')
        ->assertSee('Unduh PDF');

    $report = Report::sole();
    $pdf = Storage::disk('local')->get($report->pdf_path);
    expect($report->generator)->toBe(ReportGenerator::Template)
        ->and($report->created_by)->toBe($this->analis->id)
        ->and($report->content)->toContain('LAPORAN SITUASI (LAPSIT) SIBER')
        ->and($report->content)->toContain('Template otomatis SENTRA SHIELD (LLM tidak dipakai: Ollama tidak dapat dihubungi')
        ->and($report->content)->toContain('1. SITUASI')
        ->and($report->content)->toContain('IP 172.30.0.66 menyerang Kodim Latihan A')
        ->and($report->content)->toContain('R01 Pemindaian port (T1046')
        ->and($report->content)->toContain('R07 Brute force SSH (T1110.001')
        ->and($report->content)->toContain('[EV-'.$this->evIds[0].']')
        ->and($report->content)->toContain('Usulkan blokir IP 172.30.0.66')
        ->and($report->content)->toContain('4. TINDAKAN YANG SUDAH DIAMBIL'."\n".'a. Belum ada tindakan respons.')
        ->and(str_starts_with($pdf, '%PDF-'))->toBeTrue()
        ->and($report->sha256)->toBe(hash('sha256', $pdf))
        ->and(Cache::has(LapsitService::pendingKey($this->incident->id)))->toBeFalse();

    $log = AuditLog::where('action', 'lapsit_dibuat')->sole();
    expect($log->subject_id)->toBe($this->incident->id)
        ->and($log->user_id)->toBe($this->analis->id)
        ->and($log->data)->toMatchArray(['penyusun' => 'template', 'sha256_pdf' => $report->sha256, 'sha256_isi' => hash('sha256', $report->content)]);

    // Semua sitasi di template merujuk event bukti insiden ini.
    preg_match_all('/\[EV-(\d+)\]/', $report->content, $m);
    expect(array_diff(array_map('intval', $m[1]), $this->evIds))->toBe([]);
});

test('LLM lokal: prompt hanya fakta JSON, sitasi palsu dan IP karangan dibuang', function () {
    [$a, $b] = [$this->evIds[0], end($this->evIds)];
    Http::fake(jawabanLlm([
        'situasi' => 'IP 172.30.0.66 menyerang Kodim Latihan A. Server 10.9.9.9 juga terlibat.',
        'temuan' => [
            "Pemindaian port dari 172.30.0.66 terdeteksi [EV-{$a}].",
            "Brute force SSH berlangsung [EV-{$b}, EV-999999].",
            'Penyerang memasang ransomware [EV-999999].',
            'Kalimat tanpa sitasi sama sekali.',
            "Koneksi dari 10.9.9.9 ikut tercatat [EV-{$a}].",
        ],
        'penilaian' => 'Serangan mencapai tahap Akses Awal.',
        'saran' => ['Usulkan blokir IP 172.30.0.66.', 'Blokir juga 192.0.2.1.'],
    ]));

    app(LapsitService::class)->generate($this->incident, $this->analis);

    $report = Report::sole();
    expect($report->generator)->toBe(ReportGenerator::Llm)
        ->and($report->content)->toContain('LLM lokal qwen2.5:3b melalui Ollama. DRAF: wajib diperiksa Analis')
        ->and($report->content)->toContain("a. Pemindaian port dari 172.30.0.66 terdeteksi [EV-{$a}].")
        ->and($report->content)->toContain("b. Brute force SSH berlangsung [EV-{$b}].")
        ->and($report->content)->not->toContain('EV-999999')
        ->and($report->content)->not->toContain('ransomware')
        ->and($report->content)->not->toContain('tanpa sitasi')
        ->and($report->content)->not->toContain('10.9.9.9')
        ->and($report->content)->not->toContain('192.0.2.1')
        ->and($report->content)->toContain('Validasi : 5 kalimat tanpa bukti yang sah dibuang')
        ->and($report->content)->toContain('Rumus skor: ');

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $facts = json_decode(substr($body['messages'][1]['content'], strlen("FAKTA INSIDEN (JSON):\n")), true);

        return $request->url() === 'http://ollama:11434/api/chat'
            && $body['model'] === 'qwen2.5:3b'
            && $body['stream'] === false
            && $body['format']['required'] === ['situasi', 'temuan', 'penilaian', 'saran']
            && $body['messages'][0]['role'] === 'system'
            && array_keys($facts) === ['insiden', 'rincian_skor', 'kampanye', 'deteksi', 'event', 'tindakan']
            && $facts['insiden']['ip_sumber'] === '172.30.0.66'
            && count($facts['event']) <= IncidentFacts::MAX_EVENTS;
    });
});

test('LLM melewati batas waktu: template dipakai dengan alasan yang jelas', function () {
    Setting::put('ollama_timeout_s', 60);
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 60001 milliseconds'));

    app(LapsitService::class)->generate($this->incident, $this->analis);

    expect(Report::sole()->generator)->toBe(ReportGenerator::Template)
        ->and(Report::sole()->content)->toContain('LLM tidak dipakai: Ollama tidak menjawab dalam 60 detik');
});

test('keluaran LLM tanpa temuan bersitasi sah diganti template', function () {
    Http::fake(jawabanLlm(['situasi' => 'Ada serangan.', 'temuan' => ['Serangan besar [EV-999999].'], 'penilaian' => '-', 'saran' => []]));
    app(LapsitService::class)->generate($this->incident, $this->analis);

    expect(Report::sole()->generator)->toBe(ReportGenerator::Template)
        ->and(Report::sole()->content)->toContain('LLM tidak dipakai: Keluaran LLM tidak lolos validasi sitasi bukti');
});

test('model belum diunduh: template dengan pesan Ollama; nomor LAPSIT berurutan', function () {
    Http::fake(['*/api/chat' => Http::response(['error' => 'model "qwen2.5:3b" not found, try pulling it first'], 404)]);
    app(LapsitService::class)->generate($this->incident, $this->analis);
    app(LapsitService::class)->generate($this->incident, $this->analis);
    expect(Report::latest('id')->first()->content)->toContain('Ollama menolak permintaan: model "qwen2.5:3b" not found')
        ->and(Report::latest('id')->first()->number())->toBe('LAPSIT/INS-'.$this->incident->id.'/2');
});

test('ekspor PDF tercatat di audit; hanya Analis dan Komandan', function () {
    Http::fake(['*' => Http::failedConnection()]);
    $report = app(LapsitService::class)->generate($this->incident, $this->analis);

    foreach (['analis@sentra.lab', 'komandan@sentra.lab'] as $email) {
        $this->actingAs(User::where('email', $email)->first())->get(route('reports.pdf', $report))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('LAPSIT-INS-'.$this->incident->id.'-1.pdf');
    }
    expect(AuditLog::where('action', 'lapsit_diekspor')->count())->toBe(2)
        ->and(AuditLog::where('action', 'lapsit_diekspor')->first()->data['sha256_pdf'])->toBe($report->sha256);

    foreach (['operator.a@sentra.lab', 'demo@sentra.lab', 'admin@sentra.lab'] as $email) {
        $this->actingAs(User::where('email', $email)->first())->get(route('reports.pdf', $report))->assertForbidden();
    }
});

test('tombol Buat LAPSIT: Analis dan Komandan; Operator dan Demo hanya membaca', function () {
    Http::fake(['*' => Http::failedConnection()]);
    $komandan = User::where('email', 'komandan@sentra.lab')->first();
    Livewire::actingAs($komandan)->test(IncidentShow::class, ['incident' => $this->incident])
        ->assertSee('Buat LAPSIT')
        ->call('createReport')
        ->assertSee('Baca LAPSIT');

    foreach (['operator.a@sentra.lab', 'demo@sentra.lab'] as $email) {
        Livewire::actingAs(User::where('email', $email)->first())->test(IncidentShow::class, ['incident' => $this->incident])
            ->assertDontSee('Unduh PDF')
            ->assertSee('Baca LAPSIT')
            ->call('createReport')
            ->assertForbidden();
    }
    expect(Report::count())->toBe(1);
});

test('permintaan kedua saat LAPSIT masih disusun ditolak', function () {
    Cache::put(LapsitService::pendingKey($this->incident->id), $this->analis->id, 120);

    Livewire::actingAs($this->analis)->test(IncidentShow::class, ['incident' => $this->incident])
        ->assertSee('Menyusun LAPSIT')
        ->call('createReport')
        ->assertHasErrors('lapsit')
        ->assertSee('masih disusun');
    expect(Report::count())->toBe(0);
});

test('LAPSIT tercatat di garis waktu insiden; sitasi bukti bisa diklik', function () {
    Http::fake(['*' => Http::failedConnection()]);
    app(LapsitService::class)->generate($this->incident, $this->analis);

    $this->actingAs($this->analis)->get(route('incidents.show', $this->incident))
        ->assertSee('LAPSIT LAPSIT/INS-'.$this->incident->id.'/1 terbit (template), disusun untuk Analis Siber.')
        ->assertSee('<a href="'.route('events.show', $this->evIds[0]).'" class="ev-ref">[EV-'.$this->evIds[0].']</a>', false);
});

test('Pengaturan: Admin mengubah durasi blokir dan Ollama; perubahan tercatat', function () {
    $admin = User::where('email', 'admin@sentra.lab')->first();

    Livewire::actingAs($admin)->test(Settings::class)
        ->assertSet('ollamaUrl', 'http://ollama:11434')
        ->assertSet('ollamaTimeout', 60)
        ->set('blockDuration', 21600)
        ->set('ollamaUrl', 'http://172.31.0.4:11434/')
        ->set('ollamaModel', 'llama3.2:3b')
        ->set('ollamaTimeout', 45)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Pengaturan disimpan.');

    expect(ActionService::defaultDuration())->toBe(21600)
        ->and(Setting::get('ollama_url'))->toBe('http://172.31.0.4:11434')
        ->and(Setting::get('ollama_model'))->toBe('llama3.2:3b')
        ->and(AuditLog::where('action', 'pengaturan_diubah')->sole()->data['block_duration_s'])->toEqualCanonicalizing(['dari' => 3600, 'ke' => 21600]);

    Livewire::actingAs($admin)->test(Settings::class)
        ->set('ollamaUrl', 'file:///etc/passwd')
        ->set('ollamaTimeout', 500)
        ->set('blockDuration', 5)
        ->set('ollamaModel', 'model; rm -rf /')
        ->call('save')
        ->assertHasErrors(['ollamaUrl', 'ollamaTimeout', 'blockDuration', 'ollamaModel']);

    foreach (['analis@sentra.lab', 'komandan@sentra.lab', 'demo@sentra.lab'] as $email) {
        $this->actingAs(User::where('email', $email)->first())->get(route('settings'))->assertForbidden();
    }
});

test('Pengaturan: periksa koneksi Ollama', function (array $fake, string $message) {
    Http::fake($fake);
    Livewire::actingAs(User::where('email', 'admin@sentra.lab')->first())->test(Settings::class)
        ->call('checkOllama')
        ->assertSee($message);
})->with([
    'model tersedia' => [fn () => ['*/api/tags' => Http::response(['models' => [['name' => 'qwen2.5:3b']]])], 'Tersambung. Model qwen2.5:3b tersedia.'],
    'model belum diunduh' => [fn () => ['*/api/tags' => Http::response(['models' => []])], 'belum diunduh'],
    'tidak tersambung' => [fn () => ['*' => Http::failedConnection()], 'Tidak tersambung ke http://ollama:11434'],
]);

test('fakta LAPSIT: setiap aturan terwakili walau event bukti dibatasi', function () {
    // 40 aturan pola R02 di awal tidak boleh menggeser bukti aturan yang terjadi belakangan.
    for ($i = 0; $i < 40; $i++) {
        deteksi(eventLab($this->web, 'web', ['method' => 'GET', 'path' => '/p'.$i, 'status' => 404, 'user_agent' => 'sqlmap/1.8'], PENYERANG, 80, now()->subMinutes(5)->addSeconds($i)));
    }
    deteksi(eventLab($this->web, 'fim', ['path' => '/var/www/html/uploads/x.php', 'change' => 'created', 'sample' => '<?php eval(base64_decode($_POST["c"])); ?>'], null));

    $facts = IncidentFacts::for($this->incident->fresh());
    $rules = collect($facts->data['deteksi'])->pluck('aturan')->map(fn ($a) => strtok($a, ' '));
    expect(count($facts->data['event']))->toBeLessThanOrEqual(IncidentFacts::MAX_EVENTS)
        ->and($rules->all())->toContain('R09')
        ->and(collect($facts->data['deteksi'])->every(fn ($d) => $d['event_bukti'] !== []))->toBeTrue();
});
