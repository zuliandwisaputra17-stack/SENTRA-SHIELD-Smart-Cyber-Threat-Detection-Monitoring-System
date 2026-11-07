<?php

namespace App\Livewire\Campaigns;

use App\Models\Campaign;
use App\Models\ResponseAction;
use App\Response\ActionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Detail Kampanye (FR-10): satuan terdampak, urutan waktu, usul blokir serentak.
 */
#[Layout('layouts.app')]
class CampaignShow extends Component
{
    public Campaign $campaign;

    public int $duration = 3600;

    public ?string $flash = null;

    public function mount(Campaign $campaign): void
    {
        $this->authorize('lihat-kampanye');
        $this->campaign = $campaign;
        $this->duration = ActionService::defaultDuration();
    }

    #[On('sentra-refresh')]
    public function refreshLive(): void {}

    public function proposeBlock(ActionService $actions): void
    {
        $this->resetErrorBag();
        try {
            $actions->proposeCampaignBlock($this->campaign, auth()->user(), (int) $this->duration);
        } catch (ValidationException $e) {
            $this->addError('blokir', collect($e->errors())->flatten()->first());

            return;
        } catch (AuthorizationException $e) {
            $this->addError('blokir', $e->getMessage());

            return;
        }
        $this->flash = 'Usulan blokir serentak dikirim ke Antrean Persetujuan Komandan.';
    }

    public function render()
    {
        $campaign = $this->campaign->fresh();

        return view('livewire.campaigns.campaign-show', [
            'c' => $campaign,
            'incidents' => $campaign->incidents()->with('unit')->orderBy('first_seen_at')->get(),
            'actions' => ResponseAction::query()->with(['targetAgent.unit'])->where('campaign_id', $campaign->id)->orderByDesc('id')->get(),
            'canPropose' => auth()->user()->can('usul-tindakan'),
        ])->title('Kampanye #'.$campaign->id);
    }
}
