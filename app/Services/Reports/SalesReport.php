<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SalesReport
{
    public function daily(Outlet $outlet, CarbonInterface $day): array
    {
        $base = Order::where('outlet_id', $outlet->id)
            ->whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->where('status', '!=', OrderStatus::Cancelled->value);

        $orders = (clone $base)->get(['id', 'channel', 'total_sen', 'paid_sen', 'created_at']);

        $top = OrderItem::query()
            ->whereIn('order_id', $orders->pluck('id'))
            ->select('name', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(line_total_sen) as revenue_sen'))
            ->groupBy('name')->orderByDesc('qty')->limit(5)->get();

        $byHour = $orders->groupBy(fn ($o) => $o->created_at->format('H'))
            ->map(fn ($g) => ['orders' => $g->count(), 'revenue_sen' => $g->sum('total_sen')])
            ->sortKeys();

        return [
            'date' => $day->toDateString(),
            'orders' => $orders->count(),
            'gross_sen' => $orders->sum('total_sen'),
            'collected_sen' => $orders->sum('paid_sen'),
            'avg_ticket_sen' => $orders->count() ? intdiv($orders->sum('total_sen'), $orders->count()) : 0,
            'by_channel' => $orders->groupBy(fn ($o) => $o->channel->value)->map->count(),
            'by_hour' => $byHour,
            'top_items' => $top,
            'open_orders' => Order::where('outlet_id', $outlet->id)
                ->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])->count(),
        ];
    }
}
