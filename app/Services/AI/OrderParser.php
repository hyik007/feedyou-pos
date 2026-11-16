<?php

namespace App\Services\AI;

use App\Models\MenuItem;
use App\Models\Outlet;
use App\Services\AI\Contracts\LlmClient;

/**
 * Natural-language → cart.
 * 1. Try the LLM with the live menu as grounding (returns menu_item_ids only).
 * 2. Validate every id against the menu (never trust model output).
 * 3. Fall back to the rule-based parser if the LLM is missing / fails.
 */
class OrderParser
{
    public function __construct(private LlmClient $llm, private RuleBasedOrderParser $rules) {}

    /** @return array{items: array, unmatched: array, source: string} */
    public function parse(Outlet $outlet, string $text): array
    {
        $menu = MenuItem::available()->where('outlet_id', $outlet->id)->get();

        if ($this->llm->isConfigured()) {
            $result = $this->viaLlm($text, $menu);
            if ($result !== null) {
                return $result + ['source' => $this->llm->name()];
            }
        }

        return $this->rules->parse($text, $menu) + ['source' => 'rules'];
    }

    private function viaLlm(string $text, $menu): ?array
    {
        $menuLines = $menu->map(fn (MenuItem $m) => sprintf(
            '%d | %s | RM%.2f%s', $m->id, $m->name, $m->price_sen / 100,
            $m->aliases ? ' | aka: '.implode(', ', $m->aliases) : ''
        ))->implode("\n");

        $prompt = <<<PROMPT
You are the order-taking assistant for a Malaysian restaurant POS. Customers may write in English, Malay, Manglish or Chinese.
Map the customer's request to menu item ids from the MENU below. Never invent items.
Put preferences (kurang manis, no ice, tambah telur, bungkus...) in "notes".
Anything you cannot map goes into "unmatched" as the original phrase.

MENU (id | name | price | aliases):
{$menuLines}

CUSTOMER: "{$text}"

Reply with JSON only:
{"items":[{"menu_item_id":1,"quantity":2,"notes":null}],"unmatched":[]}
PROMPT;

        $raw = $this->llm->complete($prompt, json: true);
        $data = $raw ? json_decode(trim($raw, " \n\r\t`json"), true) : null;
        if (! is_array($data) || ! isset($data['items']) || ! is_array($data['items'])) {
            return null;
        }

        $byId = $menu->keyBy('id');
        $items = [];
        foreach ($data['items'] as $row) {
            $item = $byId->get((int) ($row['menu_item_id'] ?? 0));
            if (! $item) {
                continue;   // hallucinated id – drop it
            }
            $items[] = [
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'quantity' => max(1, min(99, (int) ($row['quantity'] ?? 1))),
                'notes' => isset($row['notes']) && $row['notes'] !== '' ? (string) $row['notes'] : null,
            ];
        }

        return ['items' => $items, 'unmatched' => array_values(array_map('strval', $data['unmatched'] ?? []))];
    }
}
