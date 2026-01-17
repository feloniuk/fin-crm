<?php

namespace App\Services\Document;

use App\Contracts\DocumentGeneratorInterface;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfInvoiceGenerator implements DocumentGeneratorInterface
{
    public function generate(Invoice $invoice): string
    {
        $html = $this->generateHtml($invoice);

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('a4');
        $pdf->setOption(['isPhpEnabled' => true, 'isHtml5ParserEnabled' => true]);

        $safeNumber = str_replace(['/', '\\'], '-', $invoice->invoice_number);
        $fileName = 'invoice_' . $safeNumber . '_' . time() . '.pdf';
        $path = storage_path('app/invoices/' . $fileName);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $pdf->save($path);

        return $path;
    }

    public function getExtension(): string
    {
        return 'pdf';
    }

    public function getMimeType(): string
    {
        return 'application/pdf';
    }

    private function generateHtml(Invoice $invoice): string
    {
        $sumInWords = NumberToWordsUkrainian::convert((int) $invoice->total);

        return view('documents.invoice-pdf', [
            'invoice' => $invoice,
            'sumInWords' => $sumInWords,
        ])->render();
    }
}
