<?php

namespace App\Policies;

use App\Models\OurCompany;
use App\Models\User;

class OurCompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OurCompany $company): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, OurCompany $company): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, OurCompany $company): bool
    {
        return $user->isAdmin() && $company->invoices()->count() === 0;
    }

    public function restore(User $user, OurCompany $company): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, OurCompany $company): bool
    {
        return $user->isAdmin() && $company->invoices()->count() === 0;
    }
}
