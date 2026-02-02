<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        // Orders are only created via sync, not manually
        return false;
    }

    public function update(User $user, Order $order): bool
    {
        // Can edit order if no invoice created yet
        return !$order->invoice;
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->isAdmin() && !$order->invoice;
    }

    public function restore(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return $user->isAdmin() && !$order->invoice;
    }

    public function createInvoice(User $user, Order $order): bool
    {
        return $user->isAdmin() && $order->canCreateInvoice();
    }
}
