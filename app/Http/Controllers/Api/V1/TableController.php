<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Outlet;
use Illuminate\Http\JsonResponse;

class TableController extends Controller
{
    public function index(): JsonResponse
    {
        $tables = DiningTable::withCount('openOrders')
            ->withSum('openOrders as open_total_sen', 'total_sen')
            ->where('outlet_id', Outlet::current()->id)->orderBy('id')->get();

        return response()->json($tables->map(fn (DiningTable $t) => [
            'id' => $t->id,
            'code' => $t->code,
            'seats' => $t->seats,
            'occupied' => $t->open_orders_count > 0,
            'open_orders' => $t->open_orders_count,
            'open_total_sen' => (int) $t->open_total_sen,
            'qr_url' => $t->qrUrl(),
        ]));
    }
}
