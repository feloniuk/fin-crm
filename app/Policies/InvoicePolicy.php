<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin();
    }

    public function markAsPaid(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin();
    }

    public function downloadExcel(User $user, Invoice $invoice): bool
    {
        return true;
    }

    public function downloadPdf(User $user, Invoice $invoice): bool
    {
        return true;
    }

    public function regenerateDocuments(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin();
    }
}
