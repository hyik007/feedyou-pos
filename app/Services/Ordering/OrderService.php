<?php

namespace App\Services\Ordering;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Exceptions\DomainException;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

/**
 * The single write-path for orders. Controllers (POS, QR, KDS, API) stay thin
 * and call into here, so business rules live in one place.
 */
class OrderService
{
    public function __construct(private PriceCalculator $pricing) {}

    public function place(
        Outlet $outlet,
        array $cart,
        OrderChannel $channel,
        OrderType $type = OrderType::DineIn,
        ?DiningTable $table = null,
        ?string $notes = null,
    ): Order {
        if ($type === OrderType::DineIn && ! $table && $channel === OrderChannel::Qr) {
            throw new DomainException('QR orders must belong to a table.');
        }

        $quote = $this->pricing->quote($outlet, $type, $cart);

        $order = DB::transaction(function () use ($outlet, $quote, $channel, $type, $table, $notes) {
            $order = Order::create([
                'outlet_id' => $outlet->id,
                'dining_table_id' => $table?->id,
                'order_no' => $this->nextOrderNo($outlet),
                'channel' => $channel,
                'type' => $type,
                // Staff-entered orders skip the "pending" approval step.
                'status' => $channel === OrderChannel::Qr ? OrderStatus::Pending : OrderStatus::Accepted,
                'payment_status' => PaymentStatus::Unpaid,
                'subtotal_sen' => $quote['subtotal_sen'],
                'service_charge_sen' => $quote['service_charge_sen'],
                'tax_sen' => $quote['tax_sen'],
                'rounding_sen' => $quote['rounding_sen'],
                'total_sen' => $quote['total_sen'],
                'customer_notes' => $notes,
            ]);
            $order->items()->createMany($quote['lines']);

            return $order;
        });

        OrderPlaced::dispatch($order);

        return $order->load('items', 'table');
    }

    public function transition(Order $order, OrderStatus $to): Order
    {
        $from = $order->status;
        if (! $from->canTransitionTo($to)) {
            throw new DomainException("Cannot move order from {$from->value} to {$to->value}.");
        }
        if ($to === OrderStatus::Completed && $order->payment_status !== PaymentStatus::Paid) {
            throw new DomainException('Order must be fully paid before completing.');
        }

        $order->status = $to;
        if ($to === OrderStatus::Completed) {
            $order->completed_at = now();
        }
        $order->save();

        OrderStatusChanged::dispatch($order, $from);

        return $order;
    }

    /**
     * Record a payment. Cash may be over-tendered (change is returned);
     * non-cash methods are capped at the outstanding balance.
     */
    public function pay(Order $order, PaymentMethod $method, ?int $tenderedSen = null, ?string $reference = null): Order
    {
        if ($order->status === OrderStatus::Cancelled) {
            throw new DomainException('Cannot pay a cancelled order.');
        }
        $balance = $order->balanceSen();
        if ($balance === 0) {
            throw new DomainException('Order is already paid.');
        }

        $tendered = $tenderedSen ?? $balance;
        if ($tendered <= 0) {
            throw new DomainException('Payment amount must be positive.');
        }
        $amount = min($tendered, $balance);
        $change = $method === PaymentMethod::Cash ? max(0, $tendered - $balance) : 0;

        DB::transaction(function () use ($order, $method, $amount, $tendered, $change, $reference) {
            $order->payments()->create([
                'method' => $method,
                'amount_sen' => $amount,
                'tendered_sen' => $tendered,
                'change_sen' => $change,
                'reference' => $reference,
            ]);
            $order->paid_sen += $amount;
            $order->payment_status = $order->paid_sen >= $order->total_sen ? PaymentStatus::Paid : PaymentStatus::Partial;
            $order->save();
        });

        // Paid + already served => close it automatically.
        if ($order->payment_status === PaymentStatus::Paid && $order->status === OrderStatus::Served) {
            $this->transition($order, OrderStatus::Completed);
        }

        return $order->load('payments');
    }

    private function nextOrderNo(Outlet $outlet): string
    {
        $prefix = now()->format('Ymd');
        $count = Order::where('outlet_id', $outlet->id)->where('order_no', 'like', "$prefix-%")->count();

        return sprintf('%s-%04d', $prefix, $count + 1);
    }
}
