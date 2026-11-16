<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_no' => $this->order_no,
            'channel' => $this->channel->value,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'next_statuses' => array_map(fn ($s) => $s->value, $this->status->next()),
            'payment_status' => $this->payment_status->value,
            'table' => $this->whenLoaded('table', fn () => $this->table?->code),
            'subtotal_sen' => $this->subtotal_sen,
            'service_charge_sen' => $this->service_charge_sen,
            'tax_sen' => $this->tax_sen,
            'rounding_sen' => $this->rounding_sen,
            'total_sen' => $this->total_sen,
            'paid_sen' => $this->paid_sen,
            'balance_sen' => $this->balanceSen(),
            'customer_notes' => $this->customer_notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'name' => $i->name,
                'quantity' => $i->quantity,
                'unit_price_sen' => $i->unit_price_sen,
                'line_total_sen' => $i->line_total_sen,
                'modifiers' => collect($i->modifiers ?? [])->pluck('name')->all(),
                'notes' => $i->notes,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'method' => $p->method->value, 'amount_sen' => $p->amount_sen, 'change_sen' => $p->change_sen,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'age_minutes' => (int) floor($this->created_at?->diffInMinutes(now(), true) ?? 0),
        ];
    }
}
