<?php

namespace App\Services\Ordering;

use App\Enums\OrderType;
use App\Exceptions\DomainException;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\Outlet;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Pure pricing logic: turns a cart into priced line snapshots + totals.
 * Never trusts prices sent by the client.
 *
 * Cart line shape: ['menu_item_id' => int, 'quantity' => int, 'modifier_ids' => int[], 'notes' => ?string]
 */
class PriceCalculator
{
    /**
     * @param  array<int, array>  $cart
     * @return array{lines: array<int, array>, subtotal_sen:int, service_charge_sen:int, tax_sen:int, rounding_sen:int, total_sen:int}
     */
    public function quote(Outlet $outlet, OrderType $type, array $cart): array
    {
        if ($cart === []) {
            throw new DomainException('Cart is empty.');
        }

        $items = MenuItem::with('modifierGroups.modifiers')
            ->where('outlet_id', $outlet->id)
            ->whereIn('id', array_column($cart, 'menu_item_id'))
            ->get()->keyBy('id');

        $lines = [];
        foreach ($cart as $line) {
            /** @var MenuItem|null $item */
            $item = $items->get($line['menu_item_id']);
            if (! $item) {
                throw new DomainException("Menu item #{$line['menu_item_id']} not found.");
            }
            if (! $item->is_available) {
                throw new DomainException("{$item->name} is sold out.");
            }

            $qty = max(1, (int) ($line['quantity'] ?? 1));
            $mods = $this->resolveModifiers($item, $line['modifier_ids'] ?? []);
            $unit = $item->price_sen + $mods->sum('price_delta_sen');

            $lines[] = [
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'unit_price_sen' => $unit,
                'quantity' => $qty,
                'modifiers' => $mods->map(fn (Modifier $m) => [
                    'id' => $m->id, 'name' => $m->name, 'price_delta_sen' => $m->price_delta_sen,
                ])->values()->all(),
                'notes' => $line['notes'] ?? null,
                'line_total_sen' => $unit * $qty,
            ];
        }

        $subtotal = array_sum(array_column($lines, 'line_total_sen'));
        $service = $type === OrderType::DineIn
            ? (int) round($subtotal * $outlet->service_charge_bp / 10000) : 0;
        $tax = (int) round(($subtotal + $service) * $outlet->tax_rate_bp / 10000);
        $raw = $subtotal + $service + $tax;
        $total = Money::roundTo5Sen($raw);

        return [
            'lines' => $lines,
            'subtotal_sen' => $subtotal,
            'service_charge_sen' => $service,
            'tax_sen' => $tax,
            'rounding_sen' => $total - $raw,
            'total_sen' => $total,
        ];
    }

    /** Only modifiers that belong to the item's groups are accepted; group max is enforced. */
    private function resolveModifiers(MenuItem $item, array $ids): Collection
    {
        $chosen = collect();
        foreach ($item->modifierGroups as $group) {
            $picked = $group->modifiers->whereIn('id', $ids);
            if ($picked->count() > $group->max_select) {
                throw new DomainException("{$item->name}: pick at most {$group->max_select} for {$group->name}.");
            }
            if ($picked->count() < $group->min_select) {
                throw new DomainException("{$item->name}: pick at least {$group->min_select} for {$group->name}.");
            }
            $chosen = $chosen->merge($picked);
        }

        return $chosen;
    }
}
