<?php

namespace App\Services\AI;

use App\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Offline fallback for natural-language orders (works with no API key).
 * Handles Manglish-style input such as:
 *   "2 kopi o, satu nasi lemak tambah telur, teh tarik x3 kurang manis"
 */
class RuleBasedOrderParser
{
    private const NUMBER_WORDS = [
        'satu' => 1, 'se' => 1, 'one' => 1, 'a' => 1, 'an' => 1,
        'dua' => 2, 'two' => 2, 'tiga' => 3, 'three' => 3, 'empat' => 4, 'four' => 4,
        'lima' => 5, 'five' => 5, 'enam' => 6, 'six' => 6,
    ];

    /** @param Collection<int, MenuItem> $menu */
    public function parse(string $text, Collection $menu): array
    {
        $chunks = preg_split('/\s*(?:,|;|\n|\band\b|\bdan\b|\bwith another\b|\+)\s*/i', Str::lower($text)) ?: [];
        $items = [];
        $unmatched = [];

        foreach (array_filter(array_map('trim', $chunks)) as $chunk) {
            [$qty, $rest] = $this->extractQuantity($chunk);
            [$name, $notes] = $this->splitNotes($rest);
            $match = $this->bestMatch($name, $menu);

            if (! $match) {
                $unmatched[] = $chunk;

                continue;
            }
            $items[] = [
                'menu_item_id' => $match->id,
                'name' => $match->name,
                'quantity' => $qty,
                'notes' => $notes,
            ];
        }

        return ['items' => $this->merge($items), 'unmatched' => $unmatched];
    }

    private function extractQuantity(string $chunk): array
    {
        // "x3" / "3x" / "× 3" anywhere
        if (preg_match('/(?:^|\s)(?:x\s*(\d+)|(\d+)\s*x)(?:\s|$)/u', $chunk, $m)) {
            return [(int) ($m[1] ?: $m[2]), trim(str_replace($m[0], ' ', $chunk))];
        }
        // leading "2 " / "dua "
        if (preg_match('/^(\d+)\s+(.*)$/u', $chunk, $m)) {
            return [(int) $m[1], $m[2]];
        }
        $first = Str::before($chunk, ' ');
        if (isset(self::NUMBER_WORDS[$first]) && str_contains($chunk, ' ')) {
            return [self::NUMBER_WORDS[$first], Str::after($chunk, ' ')];
        }

        return [1, $chunk];
    }

    /** Split "teh tarik kurang manis" into name + notes on common modifier words. */
    private function splitNotes(string $text): array
    {
        if (preg_match('/^(.*?)\s+((?:kurang|less|no|tak nak|tak|tanpa|extra|tambah|add|ikat|bungkus|panas|hot|ice|ais|peng|pedas|spicy|not spicy)\b.*)$/u', $text, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [trim($text), null];
    }

    private function bestMatch(string $needle, Collection $menu): ?MenuItem
    {
        $needle = $this->normalise($needle);
        if ($needle === '') {
            return null;
        }

        $best = null;
        $bestScore = 0.0;
        foreach ($menu as $item) {
            $candidates = array_merge([$item->name], $item->aliases ?? []);
            foreach ($candidates as $candidate) {
                $c = $this->normalise($candidate);
                if ($c === $needle) {
                    return $item;
                }
                similar_text($needle, $c, $pct);
                if (str_contains($c, $needle) || str_contains($needle, $c)) {
                    $pct = max($pct, 85.0);
                }
                if ($pct > $bestScore) {
                    $bestScore = $pct;
                    $best = $item;
                }
            }
        }

        return $bestScore >= 70 ? $best : null;
    }

    private function normalise(string $s): string
    {
        return trim(preg_replace('/[^a-z0-9 ]+/', ' ', Str::lower(Str::ascii($s))) ?? '');
    }

    private function merge(array $items): array
    {
        $out = [];
        foreach ($items as $i) {
            $k = $i['menu_item_id'].'|'.($i['notes'] ?? '');
            if (isset($out[$k])) {
                $out[$k]['quantity'] += $i['quantity'];
            } else {
                $out[$k] = $i;
            }
        }

        return array_values($out);
    }
}
