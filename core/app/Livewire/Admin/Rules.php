<?php

namespace App\Livewire\Admin;

use App\Detection\IpWhitelist;
use App\Enums\RuleType;
use App\Models\DetectionRule;
use App\Models\Setting;
use App\Services\AuditLogger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Aturan Deteksi (PRD bagian 11, FR-03): aktif/nonaktif, ubah ambang dan bobot,
 * serta daftar putih IP. Hanya Admin.
 */
#[Layout('layouts.app')]
#[Title('Aturan Deteksi')]
class Rules extends Component
{
    /** @var array<int, array{enabled: bool, weight: int|string, threshold: int|string|null, window_s: int|string|null}> */
    public array $form = [];

    public string $whitelist = '';

    public ?string $saved = null;

    /** Parameter ambang yang boleh diubah per aturan. */
    private const THRESHOLD_KEYS = ['threshold', 'window_s', 'heartbeat_timeout_s'];

    public function mount(): void
    {
        $this->authorize('kelola-sistem');
        foreach (DetectionRule::query()->orderBy('code')->get() as $rule) {
            $this->form[$rule->id] = [
                'enabled' => $rule->enabled,
                'weight' => $rule->weight,
                'threshold' => $rule->config['threshold'] ?? null,
                'window_s' => $rule->config['window_s'] ?? ($rule->config['heartbeat_timeout_s'] ?? null),
            ];
        }
        $this->whitelist = implode("\n", IpWhitelist::fromSettings()->entries());
    }

    public function saveRule(int $id, AuditLogger $audit): void
    {
        $this->authorize('kelola-sistem');
        $rule = DetectionRule::findOrFail($id);

        $this->validate([
            "form.{$id}.weight" => ['required', 'integer', 'between:0,500'],
            "form.{$id}.enabled" => ['boolean'],
            "form.{$id}.threshold" => [$rule->type === RuleType::Threshold && isset($rule->config['threshold']) ? 'required' : 'nullable', 'integer', 'between:1,100000'],
            "form.{$id}.window_s" => [$rule->type === RuleType::Threshold ? 'required' : 'nullable', 'integer', 'between:5,86400'],
        ], [], [
            "form.{$id}.weight" => 'bobot',
            "form.{$id}.threshold" => 'ambang',
            "form.{$id}.window_s" => 'jendela',
        ]);

        $input = $this->form[$id];
        $config = $rule->config;
        if ($rule->type === RuleType::Threshold) {
            if (isset($config['threshold'])) {
                $config['threshold'] = (int) $input['threshold'];
            }
            if (isset($config['heartbeat_timeout_s'])) {
                $config['heartbeat_timeout_s'] = (int) $input['window_s'];
            } else {
                $config['window_s'] = (int) $input['window_s'];
            }
        }

        $before = ['enabled' => $rule->enabled, 'weight' => $rule->weight, 'config' => array_intersect_key($rule->config, array_flip(self::THRESHOLD_KEYS))];
        $rule->update(['enabled' => (bool) $input['enabled'], 'weight' => (int) $input['weight'], 'config' => $config]);
        $after = ['enabled' => $rule->enabled, 'weight' => $rule->weight, 'config' => array_intersect_key($config, array_flip(self::THRESHOLD_KEYS))];

        if ($before !== $after) {
            $audit->record('aturan_diubah', $rule, ['kode' => $rule->code, 'sebelum' => $before, 'sesudah' => $after]);
        }
        $this->saved = $rule->code.' tersimpan.';
    }

    public function saveWhitelist(AuditLogger $audit): void
    {
        $this->authorize('kelola-sistem');
        $this->resetErrorBag('whitelist');
        $entries = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $this->whitelist)))));

        foreach ($entries as $entry) {
            if (! IpWhitelist::isValidEntry($entry)) {
                $this->addError('whitelist', "\"{$entry}\" bukan IP atau CIDR yang valid.");

                return;
            }
        }

        $before = IpWhitelist::fromSettings()->entries();
        Setting::put('ip_whitelist', $entries);
        $audit->record('daftar_putih_diubah', null, ['sebelum' => $before, 'sesudah' => $entries]);
        $this->whitelist = implode("\n", $entries);
        $this->saved = 'Daftar putih IP tersimpan.';
    }

    public function render()
    {
        return view('livewire.admin.rules', [
            'rules' => DetectionRule::query()->orderBy('code')->get(),
        ]);
    }
}
