<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;

Route::get('/', function () {
    return redirect('/admin');
});

// Webhooks - public routes without authentication
Route::prefix('webhooks')->group(function () {
    Route::post('/horoshop/{shop}', [WebhookController::class, 'horoshop'])
        ->name('webhook.horoshop');
    Route::post('/prom/{shop}', [WebhookController::class, 'prom'])
        ->name('webhook.prom');
});

// Invoice downloads - authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/invoices/{invoice}/download-excel', function (\App\Models\Invoice $invoice) {
        abort_if(!$invoice->excel_path || !file_exists($invoice->excel_path), 404);
        $filename = str_replace('/', '-', $invoice->invoice_number) . '.xlsx';
        return response()->download(
            $invoice->excel_path,
            $filename
        );
    })->name('invoice.download-excel');

    Route::get('/invoices/{invoice}/download-pdf', function (\App\Models\Invoice $invoice) {
        abort_if(!$invoice->pdf_path || !file_exists($invoice->pdf_path), 404);
        $filename = str_replace('/', '-', $invoice->invoice_number) . '.pdf';
        return response()->download(
            $invoice->pdf_path,
            $filename
        );
    })->name('invoice.download-pdf');
});
