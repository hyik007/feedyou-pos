<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Outlet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /** Public menu (used by POS and QR ordering). */
    public function index(Request $request): JsonResponse
    {
        $outlet = Outlet::current();
        $categories = $outlet->categories()->with(['items.modifierGroups.modifiers'])->get();

        return response()->json([
            'outlet' => [
                'name' => $outlet->name,
                'currency' => $outlet->currency,
                'tax_rate_bp' => $outlet->tax_rate_bp,
                'service_charge_bp' => $outlet->service_charge_bp,
            ],
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'items' => $c->items->map(fn (MenuItem $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'description' => $i->description,
                    'price_sen' => $i->price_sen,
                    'image_url' => $i->image_url,
                    'is_available' => $i->is_available,
                    'modifier_groups' => $i->modifierGroups->map(fn ($g) => [
                        'id' => $g->id, 'name' => $g->name,
                        'min_select' => $g->min_select, 'max_select' => $g->max_select,
                        'modifiers' => $g->modifiers->map(fn ($m) => [
                            'id' => $m->id, 'name' => $m->name, 'price_delta_sen' => $m->price_delta_sen,
                        ]),
                    ]),
                ]),
            ]),
        ]);
    }

    /** Staff: mark sold out / back in stock. */
    public function availability(Request $request, MenuItem $item): JsonResponse
    {
        $data = $request->validate(['is_available' => ['required', 'boolean']]);
        $item->update($data);

        return response()->json(['id' => $item->id, 'is_available' => $item->is_available]);
    }

    /** Staff: create / update a menu item. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $item = Outlet::current()->menuItems()->create($data);

        return response()->json($item, 201);
    }

    public function update(Request $request, MenuItem $item): JsonResponse
    {
        $item->update($this->validated($request, partial: true));

        return response()->json($item);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$req, 'string', 'max:120'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'price_sen' => [$req, 'integer', 'min:0'],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['string', 'max:60'],
            'is_available' => ['sometimes', 'boolean'],
        ]);
    }
}
