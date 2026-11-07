<?php

use App\Enums\ActionStatus;
use App\Enums\UnitLevel;
use App\Livewire\Campaigns\CampaignShow;
use App\Livewire\Incidents\IncidentShow;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Incident;
use App\Models\ResponseAction;
use App\Models\Unit;
use App\Models\User;
use App\Response\ActionService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

require_once __DIR__.'/../Fase1b/helpers.php';

function personel(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

beforeEach(function () {
    $this->seed();
    $this->a = agentLab('kodim-a', 'KODIM-LAT-A');
    $this->b = agentLab('kodim-b', 'KODIM-LAT-B');
});

test('IP yang sama di dua satuan dalam 24 jam membentuk kampanye dengan skor x1,3', function () {
    deteksi(...pindai($this->a, 15, now()->subMinutes(10)));
    expect(Campaign::count())->toBe(0)
        ->and(Incident::sole()->score)->toBe(24);

    deteksi(...pindai($this->b, 15, now()->subMinutes(2)));

    $campaign = Campaign::sole();
    $incidents = Incident::orderBy('id')->get();
    expect($campaign->src_ip)->toBe(PENYERANG)
        ->and($campaign->unit_count)->toBe(2)
        ->and($incidents->pluck('campaign_id')->unique()->all())->toBe([$campaign->id])
        // 20 x 1,2 x 1,3 = 31,2 -> 31
        ->and($incidents->pluck('score')->all())->toBe([31, 31])
        ->and($incidents[0]->score_breakdown['pengali_kampanye'])->toEqualCanonicalizing(['nilai' => 1.3, 'aktif' => true])
        ->and(AuditLog::where('action', 'kampanye_dibuat')->count())->toBe(1);
});

test('insiden yang terpaut lebih dari 24 jam tidak menjadi kampanye', function () {
    deteksi(...pindai($this->a, 15, now()->subHours(30)));
    deteksi(...pindai($this->b, 15, now()->subMinutes(2)));

    expect(Campaign::count())->toBe(0)
        ->and(Incident::whereNotNull('campaign_id')->count())->toBe(0);
});

test('IP berbeda tidak ditautkan', function () {
    deteksi(...pindai($this->a, 15, now()->subMinutes(10)));
    deteksi(...pindai($this->b, 15, now()->subMinutes(2), '172.30.0.77'));

    expect(Campaign::count())->toBe(0);
});

test('satuan ketiga bergabung ke kampanye yang sama', function () {
    $korem = Unit::where('code', 'KOREM-LAT-01')->first();
    $c = Unit::create(['name' => 'Kodim Latihan C', 'code' => 'KODIM-LAT-C', 'level' => UnitLevel::Kodim, 'parent_id' => $korem->id]);
    $agentC = agentLab('kodim-c', 'KODIM-LAT-C');

    deteksi(...pindai($this->a, 15, now()->subMinutes(10)));
    deteksi(...pindai($this->b, 15, now()->subMinutes(6)));
    deteksi(...pindai($agentC, 15, now()->subMinutes(2)));

    expect(Campaign::sole()->unit_count)->toBe(3)
        ->and(Incident::whereNotNull('campaign_id')->count())->toBe(3);
});

test('halaman kampanye menampilkan satuan terdampak menurut urutan waktu', function () {
    deteksi(...pindai($this->b, 15, now()->subMinutes(10)));
    deteksi(...pindai($this->a, 15, now()->subMinutes(2)));
    $campaign = Campaign::sole();

    $this->actingAs(personel('analis@sentra.lab'))->get(route('campaigns.show', $campaign))
        ->assertOk()
        ->assertSeeInOrder(['Kodim Latihan B', 'Kodim Latihan A'])
        ->assertSee('menyerang 2 satuan');
    $this->actingAs(personel('analis@sentra.lab'))->get(route('incidents.show', Incident::first()))
        ->assertSee('Bagian dari Kampanye #'.$campaign->id);
});

test('akses halaman kampanye sesuai PRD bagian 11', function (string $email, int $status) {
    $this->actingAs(personel($email))->get('/kampanye')->assertStatus($status);
})->with([
    ['analis@sentra.lab', 200],
    ['komandan@sentra.lab', 200],
    ['demo@sentra.lab', 200],
    ['operator.a@sentra.lab', 403],
    ['admin@sentra.lab', 403],
]);

test('analis mengusulkan blokir serentak, komandan menyetujui sekali untuk semua satuan', function () {
    deteksi(...pindai($this->a, 15, now()->subMinutes(10)));
    deteksi(...pindai($this->b, 15, now()->subMinutes(2)));
    $campaign = Campaign::sole();

    Livewire::actingAs(personel('analis@sentra.lab'))->test(CampaignShow::class, ['campaign' => $campaign])
        ->call('proposeBlock')
        ->assertSee('Usulan blokir serentak dikirim');

    $rows = ResponseAction::where('campaign_id', $campaign->id)->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('group_uuid')->unique())->toHaveCount(1)
        ->and($rows->pluck('incident_id')->filter())->toHaveCount(2);

    $this->actingAs(personel('komandan@sentra.lab'))->get('/persetujuan')
        ->assertSee('serentak · Kampanye #'.$campaign->id, false)
        ->assertSee('Kodim Latihan A, Kodim Latihan B');

    app(ActionService::class)->decide($rows->first()->group_uuid, personel('komandan@sentra.lab'), true);
    expect(ResponseAction::where('campaign_id', $campaign->id)->pluck('status')->unique()->all())->toEqual([ActionStatus::Disetujui]);
});

test('operator tidak bisa mengusulkan blokir serentak', function () {
    deteksi(...pindai($this->a, 15, now()->subMinutes(10)));
    deteksi(...pindai($this->b, 15, now()->subMinutes(2)));

    app(ActionService::class)->proposeCampaignBlock(Campaign::sole(), personel('operator.a@sentra.lab'), 3600);
})->throws(AuthorizationException::class);

test('kampanye selesai bila semua insidennya selesai', function () {
    deteksi(...pindai($this->a, 15, now()->subMinutes(10)));
    deteksi(...pindai($this->b, 15, now()->subMinutes(2)));
    [$ia, $ib] = Incident::orderBy('id')->get()->all();

    Livewire::actingAs(personel('komandan@sentra.lab'))->test(IncidentShow::class, ['incident' => $ia])->call('setStatus', 'selesai');
    expect(Campaign::sole()->status->value)->toBe('aktif');
    Livewire::actingAs(personel('komandan@sentra.lab'))->test(IncidentShow::class, ['incident' => $ib])->call('setStatus', 'selesai');
    expect(Campaign::sole()->status->value)->toBe('selesai');
});
