<?php

namespace App\Sites;

/**
 * Teks yang terlihat dari sebuah halaman HTML, untuk dibandingkan antar varian
 * (FR-13). Skrip, gaya, dan tag dibuang; huruf kecil; spasi dirapikan.
 */
final class PageText
{
    public readonly string $text;

    /** @var array<string, true> */
    private array $words;

    public function __construct(string $html)
    {
        $html = preg_replace('~<(script|style|noscript|template)\b[^>]*>.*?</\1\s*>~is', ' ', $html) ?? '';
        $html = preg_replace('~<!--.*?-->~s', ' ', $html) ?? '';
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->text = trim(preg_replace('/\s+/u', ' ', mb_strtolower($text)) ?? '');
        preg_match_all('/[\p{L}\p{N}]{2,}/u', $this->text, $m);
        $this->words = array_fill_keys($m[0], true);
    }

    public function hash(): string
    {
        return hash('sha256', $this->text);
    }

    /** Kemiripan Jaccard himpunan kata (0..1). Dua halaman kosong dianggap sama. */
    public function similarity(self $other): float
    {
        if ($this->words === [] && $other->words === []) {
            return 1.0;
        }
        $intersection = count(array_intersect_key($this->words, $other->words));
        $union = count($this->words + $other->words);

        return round($intersection / max(1, $union), 4);
    }

    /**
     * Kata kunci judi yang muncul (utuh sebagai kata) beserta jumlahnya.
     *
     * @param  list<string>  $keywords
     * @return array<string, int>
     */
    public function keywordHits(array $keywords): array
    {
        $hits = [];
        foreach ($keywords as $keyword) {
            $count = preg_match_all('/(?<![\p{L}\p{N}])'.preg_quote(mb_strtolower($keyword), '/').'(?![\p{L}\p{N}])/u', $this->text);
            if ($count > 0) {
                $hits[$keyword] = $count;
            }
        }

        return $hits;
    }

    public function excerpt(int $length = 1500): string
    {
        return mb_strimwidth($this->text, 0, $length, '…');
    }
}
