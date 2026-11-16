<?php

namespace App\Http\Requests;

use App\Enums\OrderChannel;
use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'channel' => ['sometimes', Rule::enum(OrderChannel::class)],
            'type' => ['sometimes', Rule::enum(OrderType::class)],
            'table_code' => ['nullable', 'string', 'max:20'],
            'table_token' => ['nullable', 'string', 'max:40'],
            'customer_notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.menu_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.modifier_ids' => ['sometimes', 'array'],
            'items.*.modifier_ids.*' => ['integer'],
            'items.*.notes' => ['nullable', 'string', 'max:200'],
        ];
    }
}
