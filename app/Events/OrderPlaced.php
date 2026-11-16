<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** Hook point for printers, notifications, broadcasting (Reverb/Pusher) and analytics. */
class OrderPlaced
{
    use Dispatchable;

    public function __construct(public Order $order) {}
}
