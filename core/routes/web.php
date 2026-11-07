<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\ReportPdfController;
use App\Livewire\Actions\ActionHistory;
use App\Livewire\Actions\ApprovalQueue;
use App\Livewire\Admin\Rules;
use App\Livewire\Admin\Settings;
use App\Livewire\Admin\UnitsAgents;
use App\Livewire\Admin\Users;
use App\Livewire\Audit\AuditLogPage;
use App\Livewire\Campaigns\CampaignList;
use App\Livewire\Campaigns\CampaignShow;
use App\Livewire\Dashboard\CommandDashboard;
use App\Livewire\Incidents\IncidentList;
use App\Livewire\Incidents\IncidentShow;
use App\Livewire\Readiness\ReadinessPage;
use App\Livewire\Sites\SiteWatchPage;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/beranda');

Route::middleware('auth')->group(function () {
    Route::get('beranda', CommandDashboard::class)
        ->middleware('can:lihat-dashboard')
        ->name('dashboard');

    Route::get('insiden', IncidentList::class)
        ->middleware('can:lihat-dashboard')
        ->name('incidents.index');
    Route::get('insiden/{incident}', IncidentShow::class)
        ->name('incidents.show');
    Route::get('event/{event}', [EventController::class, 'show'])
        ->name('events.show');

    Route::get('lapsit/{report}/pdf', ReportPdfController::class)
        ->name('reports.pdf');

    Route::get('kampanye', CampaignList::class)
        ->middleware('can:lihat-kampanye')
        ->name('campaigns.index');
    Route::get('kampanye/{campaign}', CampaignShow::class)
        ->middleware('can:lihat-kampanye')
        ->name('campaigns.show');

    Route::get('uji-kesiapan', ReadinessPage::class)
        ->middleware('can:lihat-uji-kesiapan')
        ->name('readiness');

    Route::get('penjaga-situs', SiteWatchPage::class)
        ->middleware('can:lihat-penjaga-situs')
        ->name('sites');

    Route::get('audit', AuditLogPage::class)
        ->middleware('can:lihat-audit')
        ->name('audit');

    Route::get('tindakan', ActionHistory::class)
        ->middleware('can:lihat-dashboard')
        ->name('actions.index');
    Route::get('persetujuan', ApprovalQueue::class)
        ->middleware('can:putuskan-tindakan')
        ->name('approvals');

    Route::get('satuan-agent', UnitsAgents::class)
        ->middleware('can:lihat-satuan-agent')
        ->name('units-agents');
    Route::get('aturan', Rules::class)
        ->middleware('can:kelola-sistem')
        ->name('rules');
    Route::get('pengguna', Users::class)
        ->middleware('can:kelola-sistem')
        ->name('users');
    Route::get('pengaturan', Settings::class)
        ->middleware('can:kelola-sistem')
        ->name('settings');

    Route::view('profil', 'profile')
        ->name('profile');
});

require __DIR__.'/auth.php';
