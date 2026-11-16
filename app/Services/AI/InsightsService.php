<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\LlmClient;
use App\Support\Money;

/** Turns the daily sales report into 3 short, actionable tips for the owner. */
class InsightsService
{
    public function __construct(private LlmClient $llm) {}

    public function summarise(array $report): array
    {
        if ($this->llm->isConfigured()) {
            $prompt = "You are a sharp F&B business advisor in Malaysia. Based on today's POS data, give exactly 3 short, "
                ."actionable bullet points for the owner (max 25 words each). Use RM. Data:\n".json_encode($report);
            $text = $this->llm->complete($prompt);
            if ($text) {
                return ['source' => $this->llm->name(), 'text' => trim($text)];
            }
        }

        return ['source' => 'rules', 'text' => $this->fallback($report)];
    }

    private function fallback(array $r): string
    {
        if ($r['orders'] === 0) {
            return '- No sales yet today. Share your table QR codes or run a breakfast promo to get the first orders in.';
        }
        $tips = [];
        $top = $r['top_items'][0] ?? null;
        if ($top) {
            $tips[] = "- {$top['name']} is today's best seller ({$top['qty']} sold). Keep it stocked and try a combo with a drink.";
        }
        $peak = collect($r['by_hour'])->sortByDesc('orders')->keys()->first();
        if ($peak !== null) {
            $tips[] = "- Peak hour is {$peak}:00. Prep ahead and roster an extra staff member around then.";
        }
        $tips[] = '- Average ticket is '.Money::format($r['avg_ticket_sen']).'. Suggest an add-on (telur, extra drink) at checkout to lift it.';

        return implode("\n", $tips);
    }
}
