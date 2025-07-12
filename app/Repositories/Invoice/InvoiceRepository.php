<?php

namespace App\Repositories\Invoice;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    public function create(array $data): Invoice
    {
        return Invoice::create($data);
    }

    public function findByBarcodePrefix(string $barcodePrefix): ?Invoice
    {
        return Invoice::where('barcode_prefix', $barcodePrefix)
            ->where('status', 'active')
            ->first();
    }

    public function getActiveInvoices(): Collection
    {
        return Invoice::where('status', 'active')
            ->with(['user', 'branch', 'staff'])
            ->latest()
            ->get();
    }

    public function updateStatus(Invoice $invoice, string $status): bool
    {
        return $invoice->update(['status' => $status]);
    }
} 