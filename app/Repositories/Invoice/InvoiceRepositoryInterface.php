<?php

namespace App\Repositories\Invoice;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;

interface InvoiceRepositoryInterface
{
    public function create(array $data): Invoice;
    public function findByBarcodePrefix(string $barcodePrefix): ?Invoice;
    public function getActiveInvoices(): Collection;
    public function updateStatus(Invoice $invoice, string $status): bool;
} 