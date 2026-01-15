<?php

namespace App\Listeners;

use App\Models\Invoice;
use App\Notifications\LimitExceededNotification;
use App\Notifications\LimitWarningNotification;
use App\Services\Invoice\LimitChecker;
use Illuminate\Database\Events\QueryExecuted;

class CheckLimitOnInvoiceCreated
{
    public function __construct(
        private readonly LimitChecker $limitChecker,
    ) {}

    public function handle(object $event): void
    {
        // This listener can be called after invoice is created
        // We'll check the limit and notify admin if needed

        if (!($event instanceof Invoice)) {
            return;
        }

        $invoice = $event;
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
