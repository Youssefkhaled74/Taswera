<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;

interface InvoiceServiceInterface
{
    public function createInvoice(string $barcodePrefix, string $invoiceMethod): Invoice;
    public function getInvoiceByBarcode(string $barcodePrefix): ?Invoice;
    public function getActiveInvoices(): Collection;
    public function updateInvoiceStatus(Invoice $invoice, string $status): bool;
} 