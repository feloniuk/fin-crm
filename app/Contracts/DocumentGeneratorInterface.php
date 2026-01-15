<?php

namespace App\Contracts;

use App\Models\Invoice;

interface DocumentGeneratorInterface
{
    /**
     * Generate a document for the invoice
     * Returns the file path where the document was saved
     */
    public function generate(Invoice $invoice): string;

    /**
     * Get the file extension for this document type
     */
    public function getExtension(): string;

    /**
     * Get the MIME type for this document type
     */
    public function getMimeType(): string;
}
