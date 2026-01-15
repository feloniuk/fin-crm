<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;

class ShopPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Shop $shop): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Shop $shop): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Shop $shop): bool
    {
        return $user->isAdmin() && $shop->orders()->count() === 0;
    }

    public function restore(User $user, Shop $shop): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Shop $shop): bool
    {
        return $user->isAdmin() && $shop->orders()->count() === 0;
    }

    public function sync(User $user, Shop $shop): bool
    {
        return $user->isAdmin();
    }
}
