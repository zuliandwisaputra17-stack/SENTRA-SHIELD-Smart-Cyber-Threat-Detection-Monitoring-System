<?php

namespace App\Reports;

/**
 * Penjaga keluaran LLM (FR-12): sitasi [EV-n] yang tidak ada di bukti dibuang,
 * kalimat temuan tanpa sitasi yang sah dibuang, dan kalimat yang menyebut IP
 * di luar fakta insiden dibuang (LLM tidak boleh mengarang).
 */
final class CitationGuard
{
    private const IPV4 = '/\b(?:\d{1,3}\.){3}\d{1,3}\b/';

    /** @var array<string, true> */
    private array $valid;

    /** @var array<string, true> */
    private array $ips;

    public int $dropped = 0;

    public function __construct(private IncidentFacts $facts)
    {
        $this->valid = array_fill_keys(array_map(fn ($id) => 'EV-'.$id, $facts->eventIds), true);
        $this->ips = array_fill_keys($facts->ips, true);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array{situasi: string, temuan: list<string>, penilaian: string, saran: list<string>}
     */
    public function clean(array $draft): array
    {
        $needCitation = $this->valid !== [];

        $temuan = [];
        foreach (array_slice($this->strings($draft['temuan'] ?? []), 0, 20) as $item) {
            [$text, $citations] = $this->citations($item);
            if (($needCitation && $citations === 0) || ! $this->knownIps($text)) {
                $this->dropped++;

                continue;
            }
            $temuan[] = $text;
        }

        return [
            'situasi' => $this->paragraph((string) ($draft['situasi'] ?? '')),
            'temuan' => $temuan,
            'penilaian' => $this->paragraph((string) ($draft['penilaian'] ?? '')),
            'saran' => array_values(array_filter(array_map(
                fn ($s) => $this->paragraph($s), array_slice($this->strings($draft['saran'] ?? []), 0, 8)
            ))),
        ];
    }

    /**
     * Tulis ulang sitasi: hanya ID yang ada di bukti, format [EV-n][EV-m].
     *
     * @return array{0: string, 1: int} teks bersih dan jumlah sitasi sah
     */
    private function citations(string $text): array
    {
        $count = 0;
        $text = preg_replace_callback('/\[\s*(EV-\d+(?:\s*[,;]\s*EV-\d+)*)\s*\]/i', function ($m) use (&$count) {
            $ok = collect(preg_split('/\s*[,;]\s*/', strtoupper($m[1])))->filter(fn ($id) => isset($this->valid[$id]))->unique();
            $count += $ok->count();

            return $ok->map(fn ($id) => '['.$id.']')->implode('');
        }, $text);

        return [trim(preg_replace('/\s{2,}/', ' ', $text)), $count];
    }

    /** Paragraf: sitasi dirapikan, kalimat dengan IP asing dibuang. */
    private function paragraph(string $text): string
    {
        [$text] = $this->citations($text);
        $sentences = preg_split('/(?<=[.!?])\s+(?=[^\s\[])/u', $text) ?: [];
        $kept = array_filter($sentences, function ($s) {
            if ($this->knownIps($s)) {
                return true;
            }
            $this->dropped++;

            return false;
        });

        return trim(implode(' ', $kept));
    }

    private function knownIps(string $text): bool
    {
        preg_match_all(self::IPV4, $text, $m);

        return collect($m[0])->every(fn ($ip) => isset($this->ips[$ip]));
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        return array_values(array_filter(
            array_map(fn ($v) => is_string($v) ? trim(mb_strimwidth($v, 0, 600, '…')) : '', is_array($value) ? $value : []),
            fn ($v) => $v !== ''
        ));
    }
}
