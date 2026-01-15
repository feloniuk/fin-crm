<?php

namespace App\Policies;

use App\Models\Counterparty;
use App\Models\User;

class CounterpartyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Counterparty $counterparty): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Counterparty $counterparty): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Counterparty $counterparty): bool
    {
        return $user->isAdmin() && $counterparty->invoices()->count() === 0;
    }

    public function restore(User $user, Counterparty $counterparty): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Counterparty $counterparty): bool
    {
        return $user->isAdmin() && $counterparty->invoices()->count() === 0;
    }
}
