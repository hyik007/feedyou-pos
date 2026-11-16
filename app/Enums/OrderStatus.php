<?php

namespace App\Enums;

/**
 * Order lifecycle. Allowed transitions live here so every entry point
 * (POS, QR, kitchen display, API) follows the same rules.
 */
enum OrderStatus: string
{
    case Pending = 'pending';       // placed by customer (QR) – waiting for staff to accept
    case Accepted = 'accepted';     // confirmed by staff / auto-accepted from POS
    case Preparing = 'preparing';   // kitchen started
    case Ready = 'ready';           // ready to serve / pick up
    case Served = 'served';         // handed to customer
    case Completed = 'completed';   // served + paid, closed
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Accepted, self::Cancelled],
            self::Accepted => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Ready],
            self::Ready => [self::Served],
            self::Served => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** Statuses the kitchen display cares about. */
    public static function kitchen(): array
    {
        return [self::Pending, self::Accepted, self::Preparing, self::Ready];
    }
}
