<?php

namespace App\Reports;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * LAPSIT lewat LLM lokal Ollama (FR-12). Prompt hanya berisi fakta insiden
 * dalam JSON; keluaran dipaksa berbentuk JSON (structured output) lalu
 * divalidasi CitationGuard. Data tidak keluar dari jaringan lab.
 */
final class OllamaWriter
{
    /** Batas atas pengaturan; job LAPSIT (240 dtk) dan retry_after antrean (300 dtk) di atasnya. */
    public const MAX_TIMEOUT = 120;

    public const SYSTEM_PROMPT = <<<'TXT'
Anda adalah Analis siber yang menyusun Laporan Situasi (LAPSIT) untuk Komandan.
Aturan:
- Gunakan HANYA fakta pada JSON dari pengguna. Jangan menambah IP, nama host, waktu, angka, akun, atau kejadian yang tidak ada di JSON.
- Bahasa Indonesia baku, singkat, lugas, gaya laporan dinas.
- "situasi": 2-3 kalimat ringkasan (satuan, IP sumber, severity, skor, status).
- "temuan": daftar kalimat kronologis, satu kalimat per butir. SETIAP butir wajib diakhiri ID event buktinya persis seperti di data, contoh [EV-1042]. Hanya pakai ID dari daftar "event".
- "penilaian": 1-3 kalimat tentang tahap serangan, teknik ATT&CK, dan arti skor.
- "saran": 2-4 langkah tindak lanjut yang sesuai fakta.
Jawab hanya dengan JSON sesuai skema.
TXT;

    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'situasi' => ['type' => 'string'],
            'temuan' => ['type' => 'array', 'items' => ['type' => 'string']],
            'penilaian' => ['type' => 'string'],
            'saran' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['situasi', 'temuan', 'penilaian', 'saran'],
    ];

    public static function url(): string
    {
        return rtrim((string) Setting::get('ollama_url', config('sentra.ollama.url')), '/');
    }

    public static function model(): string
    {
        return (string) Setting::get('ollama_model', config('sentra.ollama.model'));
    }

    public static function timeout(): int
    {
        return max(10, min(self::MAX_TIMEOUT, (int) Setting::get('ollama_timeout_s', 60)));
    }

    /** @return array<string, mixed> draf mentah (belum divalidasi) */
    public function write(IncidentFacts $facts): array
    {
        try {
            $response = Http::connectTimeout(3)
                ->timeout(self::timeout())
                ->acceptJson()
                ->post(self::url().'/api/chat', [
                    'model' => self::model(),
                    'stream' => false,
                    'format' => self::SCHEMA,
                    // Jawaban dibatasi dan model tetap dimuat 30 menit agar LAPSIT berikutnya cepat.
                    'options' => ['temperature' => 0.2, 'num_predict' => 900],
                    'keep_alive' => '30m',
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => "FAKTA INSIDEN (JSON):\n".$facts->json()],
                    ],
                ]);
        } catch (ConnectionException $e) {
            $timedOut = str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'Timeout');
            throw new LlmUnavailable($timedOut
                ? 'Ollama tidak menjawab dalam '.self::timeout().' detik'
                : 'Ollama tidak dapat dihubungi di '.self::url());
        }

        if (! $response->successful()) {
            $error = (string) ($response->json('error') ?? 'HTTP '.$response->status());
            throw new LlmUnavailable('Ollama menolak permintaan: '.mb_strimwidth($error, 0, 120, '…'));
        }

        $draft = json_decode((string) $response->json('message.content'), true);
        if (! is_array($draft)) {
            throw new LlmUnavailable('Keluaran LLM bukan JSON yang valid');
        }

        return $draft;
    }

    /**
     * Pemeriksaan untuk halaman Pengaturan: Ollama tersambung dan model tersedia?
     *
     * @return array{ok: bool, message: string}
     */
    public static function check(): array
    {
        try {
            $response = Http::connectTimeout(3)->timeout(5)->get(self::url().'/api/tags');
        } catch (ConnectionException) {
            return ['ok' => false, 'message' => 'Tidak tersambung ke '.self::url().'. LAPSIT akan dibuat dari template.'];
        }
        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'Ollama menjawab HTTP '.$response->status().'. LAPSIT akan dibuat dari template.'];
        }
        $models = collect($response->json('models') ?? [])->pluck('name');
        $model = self::model();
        $found = $models->contains(fn ($name) => $name === $model || $name === $model.':latest');

        return $found
            ? ['ok' => true, 'message' => "Tersambung. Model {$model} tersedia."]
            : ['ok' => false, 'message' => "Tersambung, tetapi model {$model} belum diunduh. LAPSIT akan dibuat dari template sampai model tersedia."];
    }
}
