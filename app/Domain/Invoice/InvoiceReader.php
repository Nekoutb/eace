<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

/**
 * Port: reads a stored invoice file (PDF or image) into structured InvoiceData.
 * The fake implementation backs the demo and tests; the Azure Document
 * Intelligence reader implements the same interface for real extraction, so the
 * recording flow and the conformity checker never change when it is enabled.
 */
interface InvoiceReader
{
    public function read(string $diskPath, string $disk = 'local'): InvoiceData;
}
