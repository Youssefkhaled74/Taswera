<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Models\Photo;
use App\Models\User;
use App\Repositories\Invoice\InvoiceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceService implements InvoiceServiceInterface
{
    private const PRICE_PER_PHOTO = 10.00;
    private const TAX_RATE = 0.05;

    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository
    ) {}

    public function createInvoice(string $barcodePrefix, string $invoiceMethod): Invoice
    {
        // Start transaction
        return DB::transaction(function () use ($barcodePrefix, $invoiceMethod) {
            // Find user by barcode prefix
            $user = User::where('barcode', $barcodePrefix)->first();
            
            if (!$user) {
                throw new \Exception('No user found with this barcode');
            }

            // Get ready to print photos for this user
            $photos = Photo::where('user_id', $user->id)
                         ->where('status', 'ready_to_print')
                         ->get();

            if ($photos->isEmpty()) {
                throw new \Exception('No photos found ready to print for this user');
            }

            // Calculate totals
            $numPhotos = $photos->count();
            $amount = $numPhotos * self::PRICE_PER_PHOTO;
            $taxAmount = $amount * self::TAX_RATE;
            $totalAmount = $amount + $taxAmount;

            // Create invoice
            $invoice = $this->invoiceRepository->create([
                'barcode_prefix' => $barcodePrefix,
                'user_id' => $user->id,
                'branch_id' => $user->branch_id,
                'staff_id' => Auth::id(),
                'num_photos' => $numPhotos,
                'amount' => $amount,
                'tax_rate' => self::TAX_RATE,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'invoice_method' => $invoiceMethod,
                'status' => 'active',
                'metadata' => [
                    'photo_ids' => $photos->pluck('id')->toArray()
                ]
            ]);

            // Update photos status to 'printed'
            $photos->each(function ($photo) {
                $photo->update(['status' => 'printed']);
            });

            return $invoice;
        });
    }

    public function getInvoiceByBarcode(string $barcodePrefix): ?Invoice
    {
        return $this->invoiceRepository->findByBarcodePrefix($barcodePrefix);
    }

    public function getActiveInvoices(): Collection
    {
        return $this->invoiceRepository->getActiveInvoices();
    }

    public function updateInvoiceStatus(Invoice $invoice, string $status): bool
    {
        return $this->invoiceRepository->updateStatus($invoice, $status);
    }
} 