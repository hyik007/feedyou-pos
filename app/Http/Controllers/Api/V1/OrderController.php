<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Middleware\StaffAuth;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Outlet;
use App\Services\Ordering\OrderService;
use App\Services\Ordering\PriceCalculator;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    /** Staff: list orders (kitchen display, POS open tickets). ?status=pending,accepted&scope=kitchen|open|today */
    public function index(Request $request): AnonymousResourceCollection
    {
        $q = Order::with('items', 'table')->where('outlet_id', Outlet::current()->id);

        match ($request->query('scope')) {
            'kitchen' => $q->whereIn('status', array_map(fn ($s) => $s->value, OrderStatus::kitchen()))->oldest(),
            'open' => $q->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])->latest(),
            default => $q->whereDate('created_at', today())->latest(),
        };
        if ($status = $request->query('status')) {
            $q->whereIn('status', explode(',', $status));
        }

        return OrderResource::collection($q->limit(200)->get());
    }

    /** Quote a cart without saving (live totals in POS / QR cart). */
    public function quote(Request $request, PriceCalculator $pricing): JsonResponse
    {
        $data = $request->validate([
            'type' => ['sometimes', Rule::enum(OrderType::class)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.modifier_ids' => ['sometimes', 'array'],
        ]);

        return response()->json($pricing->quote(
            Outlet::current(), OrderType::from($data['type'] ?? 'dine_in'), $data['items']
        ));
    }

    /**
     * Place an order.
     * QR customers send table_token (public, forced to channel=qr).
     * Anything else is a staff order (POS / AI) and needs staff auth.
     */
    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $outlet = Outlet::current();
        $isQr = filled($data['table_token'] ?? null);

        if (! $isQr) {
            abort_unless(app(StaffAuth::class)->check($request), 401, 'Staff login required.');
            $channel = OrderChannel::from($data['channel'] ?? 'pos');
            $table = isset($data['table_code'])
                ? DiningTable::where('outlet_id', $outlet->id)->where('code', $data['table_code'])->firstOrFail()
                : null;
        } else {
            $channel = OrderChannel::Qr;
            $table = DiningTable::where('qr_token', $data['table_token'])->firstOrFail();
        }

        $type = OrderType::from($data['type'] ?? ($table ? 'dine_in' : 'takeaway'));
        $order = $this->orders->place($outlet, $data['items'], $channel, $type, $table, $data['customer_notes'] ?? null);

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load('items', 'table', 'payments'));
    }

    /** Public order tracking for QR customers: /orders/{order_no}/track?t={table_token} */
    public function track(Request $request, string $orderNo): JsonResponse
    {
        $order = Order::with('table')->where('order_no', $orderNo)->firstOrFail();
        abort_unless($order->table && hash_equals($order->table->qr_token, (string) $request->query('t')), 404);

        return response()->json([
            'order_no' => $order->order_no,
            'status' => $order->status->value,
            'total' => Money::format($order->total_sen),
        ]);
    }

    public function updateStatus(Request $request, Order $order): OrderResource
    {
        $data = $request->validate(['status' => ['required', Rule::enum(OrderStatus::class)]]);

        return new OrderResource($this->orders->transition($order, OrderStatus::from($data['status']))->load('items', 'table'));
    }

    public function pay(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'tendered_sen' => ['nullable', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);
        $order = $this->orders->pay($order, PaymentMethod::from($data['method']), $data['tendered_sen'] ?? null, $data['reference'] ?? null);
        $last = $order->payments->last();

        return response()->json([
            'order' => new OrderResource($order->load('items', 'table')),
            'change_sen' => $last?->change_sen ?? 0,
        ]);
    }
}
