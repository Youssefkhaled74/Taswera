<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Invoice\InvoiceServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly InvoiceServiceInterface $invoiceService
    ) {}

    /**
     * Get all active invoices
     */
    public function index(): JsonResponse
    {
        try {
            $invoices = $this->invoiceService->getActiveInvoices();
            
            return $this->successResponse(
                InvoiceResource::collection($invoices),
                'Active invoices retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve invoices: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create new invoice from print confirmation
     */
    public function store(Request $request, string $barcodePrefix): JsonResponse
    {
        $request->validate([
            'invoice_method' => 'required|in:whatsapp,print,both'
        ]);

        try {
            $invoice = $this->invoiceService->createInvoice($barcodePrefix, $request->invoice_method);
            
            return $this->successResponse(
                new InvoiceResource($invoice->load(['user', 'branch', 'staff'])),
                'Print confirmation successful and invoice generated'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process print confirmation: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get invoice by barcode prefix
     */
    public function show(string $barcodePrefix): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->getInvoiceByBarcode($barcodePrefix);
            
            if (!$invoice) {
                return $this->errorResponse('Invoice not found', Response::HTTP_NOT_FOUND);
            }

            return $this->successResponse(
                new InvoiceResource($invoice->load(['user', 'branch', 'staff'])),
                'Invoice retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve invoice: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update invoice status
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,cancelled,completed'
        ]);

        try {
            $updated = $this->invoiceService->updateInvoiceStatus($invoice, $request->status);
            
            if (!$updated) {
                return $this->errorResponse('Failed to update invoice status', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->successResponse(
                new InvoiceResource($invoice->fresh()->load(['user', 'branch', 'staff'])),
                'Invoice status updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update invoice status: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
} 