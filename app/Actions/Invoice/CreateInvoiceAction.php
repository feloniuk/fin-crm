<?php

namespace App\Actions\Invoice;

use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Models\Counterparty;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OurCompany;
use App\Services\Document\ExcelInvoiceGenerator;
use App\Services\Document\PdfInvoiceGenerator;
use App\Services\Invoice\InvoiceCalculator;
use App\Services\Invoice\LimitChecker;
use Illuminate\Support\Facades\DB;

class CreateInvoiceAction
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly LimitChecker $limitChecker,
    ) {}

    public function execute(
        OurCompany $company,
        Counterparty $counterparty,
        array $items,
        bool $withVat = true,
        ?Order $order = null,
        ?string $comment = null,
        DiscountType $discountType = DiscountType::NONE,
        float $discountValue = 0,
    ): Invoice {
        return DB::transaction(function () use (
            $company,
            $counterparty,
            $items,
            $withVat,
            $order,
            $comment,
            $discountType,
            $discountValue,
        ) {
            // Calculate totals
            $calculations = $this->calculator->calculateInvoice(
                $items,
                $withVat,
                $discountType === DiscountType::FIXED ? $discountValue : 0
            );

            // Check limit
            $limitCheck = $this->limitChecker->checkLimit($company, $calculations['total']);

            if ($limitCheck['isExceeded']) {
                throw new \Exception(
                    "Ліміт перевищено для компанії: {$company->name}. " .
                    "Залишок: {$limitCheck['remaining']} грн"
                );
            }

            // Auto-create counterparty if new
            if (!$counterparty->exists) {
                $counterparty->is_auto_created = true;
                $counterparty->save();
            }

            // Generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber($company);

            // Create invoice
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_date' => now()->toDateString(),
                'our_company_id' => $company->id,
                'counterparty_id' => $counterparty->id,
                'order_id' => $order?->id,
                'with_vat' => $withVat,
                'discount_type' => $discountType,
                'discount_value' => $discountType === DiscountType::PERCENT ? 0 : $discountValue,
                'comment' => $comment,
                'subtotal' => $calculations['subtotal'],
                'vat_amount' => $calculations['vat_amount'],
                'total' => $calculations['total'],
                'is_paid' => false,
            ]);

            // Create invoice items
            foreach ($items as $itemData) {
                $itemTotal = $this->calculator->calculateItemTotal(
                    (float) $itemData['quantity'],
                    (float) $itemData['unit_price'],
                    (float) ($itemData['discount'] ?? 0)
                );

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'name' => $itemData['name'],
                    'quantity' => $itemData['quantity'],
                    'unit' => $itemData['unit'] ?? 'шт.',
                    'unit_price' => $itemData['unit_price'],
                    'discount' => $itemData['discount'] ?? 0,
                    'total' => $itemTotal,
                ]);
            }

            // Refresh invoice with items
            $invoice->load('items');

            // Generate documents
            try {
                $excelGenerator = new ExcelInvoiceGenerator();
                $excelPath = $excelGenerator->generate($invoice);
                $invoice->update(['excel_path' => $excelPath]);

                $pdfGenerator = new PdfInvoiceGenerator();
                $pdfPath = $pdfGenerator->generate($invoice);
                $invoice->update(['pdf_path' => $pdfPath]);
            } catch (\Throwable $e) {
                \Log::warning('Document generation failed for invoice ' . $invoice->id . ': ' . $e->getMessage());
            }

            // Update order status if order exists
            if ($order) {
                $order->update(['status' => OrderStatus::Invoiced]);
            }

            return $invoice;
        });
    }

    private function generateInvoiceNumber(OurCompany $company): string
    {
        // Get company abbreviation from first letters
        $abbr = $this->getCompanyAbbreviation($company->name);

        $year = now()->year;

        // Get the count of invoices for this company in this year
        $count = Invoice::where('our_company_id', $company->id)
            ->whereYear('invoice_date', $year)
            ->count() + 1;

        // Format: {abbr}-{number}/{year}
        return sprintf('%s-%04d/%d', $abbr, $count, $year);
    }

    private function getCompanyAbbreviation(string $name): string
    {
        $words = explode(' ', trim($name));
        $abbr = '';

        foreach ($words as $word) {
            if (!empty($word)) {
                $abbr .= mb_strtoupper(mb_substr($word, 0, 1));
            }
        }

        return $abbr ?: 'КО';
    }
}
