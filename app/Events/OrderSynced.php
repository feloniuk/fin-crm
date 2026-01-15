<?php

namespace App\Events;

use App\Models\Shop;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderSynced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Shop $shop,
        public readonly int $ordersCount,
        public readonly int $newOrdersCount,
        public readonly int $updatedOrdersCount,
    ) {}
}
