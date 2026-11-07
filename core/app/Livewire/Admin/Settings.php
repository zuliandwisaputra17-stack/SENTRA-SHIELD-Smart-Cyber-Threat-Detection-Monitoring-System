<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Reports\OllamaWriter;
use App\Response\ActionService;
use App\Services\AuditLogger;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pengaturan (PRD bagian 11): durasi blokir default dan LLM lokal (Ollama)
 * untuk LAPSIT. Hanya Admin. Integrasi Telegram (F14, bonus) belum dibuat.
 */
#[Layout('layouts.app')]
#[Title('Pengaturan')]
class Settings extends Component
{
    public int $blockDuration = 3600;

    public string $ollamaUrl = '';

    public string $ollamaModel = '';

    public int $ollamaTimeout = 60;

    public ?string $saved = null;

    /** @var array{ok: bool, message: string}|null */
    public ?array $ollamaStatus = null;

    public function mount(): void
    {
        $this->authorize('kelola-sistem');
        $this->blockDuration = ActionService::defaultDuration();
        $this->ollamaUrl = OllamaWriter::url();
        $this->ollamaModel = OllamaWriter::model();
        $this->ollamaTimeout = OllamaWriter::timeout();
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorize('kelola-sistem');
        $this->saved = null;
        $this->ollamaUrl = rtrim(trim($this->ollamaUrl), '/');
        $this->ollamaModel = trim($this->ollamaModel);

        $this->validate([
            'blockDuration' => ['required', 'integer', Rule::in(array_keys(ActionService::DURATIONS))],
            'ollamaUrl' => ['required', 'url:http,https', 'max:255'],
            'ollamaModel' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\/-]+$/'],
            'ollamaTimeout' => ['required', 'integer', 'between:10,'.OllamaWriter::MAX_TIMEOUT],
        ], [], [
            'blockDuration' => 'durasi blokir',
            'ollamaUrl' => 'alamat Ollama',
            'ollamaModel' => 'model',
            'ollamaTimeout' => 'batas waktu',
        ]);

        $new = [
            'block_duration_s' => $this->blockDuration,
            'ollama_url' => $this->ollamaUrl,
            'ollama_model' => $this->ollamaModel,
            'ollama_timeout_s' => $this->ollamaTimeout,
        ];
        $changes = [];
        foreach ($new as $key => $value) {
            $old = Setting::get($key);
            if ($old !== $value) {
                $changes[$key] = ['dari' => $old, 'ke' => $value];
                Setting::put($key, $value);
            }
        }
        if ($changes !== []) {
            $audit->record('pengaturan_diubah', null, $changes);
        }
        $this->ollamaStatus = null;
        $this->saved = $changes === [] ? 'Tidak ada perubahan.' : 'Pengaturan disimpan.';
    }

    public function checkOllama(): void
    {
        $this->authorize('kelola-sistem');
        $this->ollamaStatus = OllamaWriter::check();
    }

    public function render()
    {
        return view('livewire.admin.settings');
    }
}
