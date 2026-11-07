<?php

namespace App\Livewire\Campaigns;

use App\Models\Campaign;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Daftar Kampanye (FR-10): IP yang menyerang dua satuan atau lebih dalam 24 jam.
 */
#[Layout('layouts.app')]
#[Title('Kampanye')]
class CampaignList extends Component
{
    public function mount(): void
    {
        $this->authorize('lihat-kampanye');
    }

    #[On('sentra-refresh')]
    public function refreshLive(): void {}

    public function render()
    {
        return view('livewire.campaigns.campaign-list', [
            'campaigns' => Campaign::query()->withCount('incidents')->withMax('incidents', 'score')
                ->orderByRaw("status = 'aktif' desc")->orderByDesc('last_seen_at')->limit(50)->get(),
        ]);
    }
}
