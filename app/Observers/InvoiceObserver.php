<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Notifications\LimitExceededNotification;
use App\Notifications\LimitWarningNotification;
use App\Services\Invoice\LimitChecker;

class InvoiceObserver
{
    public function __construct(
        private readonly LimitChecker $limitChecker,
    ) {}

    public function created(Invoice $invoice): void
    {
        $this->checkLimits($invoice);
    }

    public function updated(Invoice $invoice): void
    {
        // Check limits only if amounts changed
        if ($invoice->isDirty(['total', 'is_paid'])) {
            $this->checkLimits($invoice);
        }
    }

    private function checkLimits(Invoice $invoice): void
    {
        $company = $invoice->ourCompany;

        if (!$company->hasLimit()) {
            return;
        }

        $check = $this->limitChecker->checkLimit($company, 0);

        if ($check['isExceeded']) {
            LimitExceededNotification::sendToDashboard(
                $company,
                abs($check['remaining'])
            );
        } elseif ($check['isWarning']) {
            LimitWarningNotification::sendToDashboard(
                $company,
                $check['percent'],
                $check['remaining']
            );
        }
    }
}
