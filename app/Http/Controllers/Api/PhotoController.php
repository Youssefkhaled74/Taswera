<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhotoResource;
use App\Models\Photo;
use App\Services\Photo\PhotoServiceInterface;
use App\Services\User\UserServiceInterface;
use App\Traits\ApiResponse;
use App\Traits\HandlesMediaUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Invoice\InvoiceServiceInterface;

class PhotoController extends Controller
{
    use ApiResponse, HandlesMediaUploads;

    protected $photoService;
    protected $userService;

    public function __construct(
        PhotoServiceInterface $photoService,
        UserServiceInterface $userService,
        private readonly InvoiceServiceInterface $invoiceService
    ) {
        $this->photoService = $photoService;
        $this->userService = $userService;
    }

    /**
     * Get photos for offline dashboard
     */
    public function offlineDashboard(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        
        $query = Photo::with(['staff', 'branch'])
            ->where('sync_status', 'pending')
            ->where('branch_id', Auth::user()->branch_id)
            ->latest();

        return $this->successResponse(
            paginate($query, PhotoResource::class, $limit, $page),
            'Offline dashboard photos retrieved successfully'
        );
    }

    /**
     * Get photos taken by specific staff member
     */
    public function staffPhotos(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        
        $query = Photo::with(['staff', 'branch'])
            ->where('staff_id', Auth::id())
            ->latest();

        return $this->successResponse(
            paginate($query, PhotoResource::class, $limit, $page),
            'Staff photos retrieved successfully'
        );
    }

    /**
     * Upload new photos
     *
     * @throws ValidationException
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'photos.*' => 'required|image|max:10240', // 10MB max per photo
            'metadata' => 'nullable|json',
        ]);

        try {
            DB::beginTransaction();

            $uploadedPhotos = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $this->uploadMedia($photo, 'photos');
                    
                    $uploadedPhotos[] = Photo::create([
                        'file_path' => $path,
                        'staff_id' => Auth::id(),
                        'branch_id' => Auth::user()->branch_id,
                        'status' => 'pending',
                        'sync_status' => 'pending',
                        'metadata' => $request->input('metadata'),
                    ]);
                }
            }

            DB::commit();

            return $this->successResponse(
                PhotoResource::collection($uploadedPhotos),
                'Photos uploaded successfully',
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to upload photos: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update photo sync status
     */
    public function updateSyncStatus(Request $request, Photo $photo): JsonResponse
    {
        $request->validate([
            'sync_status' => 'required|in:pending,synced,failed',
        ]);

        $photo->update([
            'sync_status' => $request->sync_status,
        ]);

        return $this->successResponse(
            new PhotoResource($photo),
            'Photo sync status updated successfully'
        );
    }

    /**
     * Delete a photo
     */
    public function destroy(Photo $photo): JsonResponse
    {
        // Only allow deletion if photo belongs to the staff member or is in their branch
        if ($photo->staff_id !== Auth::id() && $photo->branch_id !== Auth::user()->branch_id) {
            return $this->errorResponse('Unauthorized', Response::HTTP_FORBIDDEN);
        }

        try {
            DB::beginTransaction();
            
            // Delete the physical file
            $this->deleteMedia($photo->file_path);
            
            // Delete the database record
            $photo->delete();
            
            DB::commit();
            
            return $this->successResponse(null, 'Photo deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to delete photo: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Confirm print and generate invoice
     */
    public function confirmPrint(Request $request, string $barcodePrefix): JsonResponse
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
    public function getInvoice(string $barcodePrefix): JsonResponse
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
     * Get all active invoices
     */
    public function getActiveInvoices(): JsonResponse
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
     * Update invoice status
     */
    public function updateInvoiceStatus(Request $request, Invoice $invoice): JsonResponse
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

    /**
     * Get all unique 8-digit barcodes from photos uploaded by a specific staff member
     */
    public function getStaffUploadedBarcodes(int $staffId): JsonResponse
    {
        $photos = Photo::where('uploaded_by', $staffId)->get();
        
        // Extract unique barcodes from file paths
        $barcodes = $photos->map(function ($photo) {
            return $photo->getBarcode();
        })->unique()->values();

        return $this->successResponse(
            ['barcodes' => $barcodes],
            'Staff uploaded barcodes retrieved successfully'
        );
    }

    /**
     * Get all photos associated with an 8-digit barcode prefix
     */
    public function getPhotosByBarcodePrefix(string $barcodePrefix): JsonResponse
    {
        // Validate that the input is exactly 8 digits
        if (!preg_match('/^\d{8}$/', $barcodePrefix)) {
            return $this->errorResponse('Invalid barcode prefix. Must be exactly 8 digits.', 400);
        }

        // Get all photos where the file path contains this barcode prefix
        $photos = Photo::where('file_path', 'like', "%/{$barcodePrefix}/%")
            ->with(['user', 'uploader', 'branch'])
            ->get();

        return $this->successResponse(
            PhotoResource::collection($photos),
            'Photos retrieved successfully'
        );
    }

    /**
     * Get all barcode prefixes that have photos with status 'ready_to_print'
     */
    public function getReadyToPrintBarcodes(): JsonResponse
    {
        $photos = Photo::where('status', 'ready_to_print')->get();
        
        // Extract unique barcodes from file paths
        $barcodes = $photos->map(function ($photo) {
            return $photo->getBarcode();
        })->unique()->values();

        return $this->successResponse(
            ['barcodes' => $barcodes],
            'Ready to print barcodes retrieved successfully'
        );
    }

    /**
     * Get all ready to print photos for a specific barcode prefix
     */
    public function getReadyToPrintPhotosByBarcode(string $barcodePrefix): JsonResponse
    {
        // Validate that the input is exactly 8 digits
        if (!preg_match('/^\d{8}$/', $barcodePrefix)) {
            return $this->errorResponse('Invalid barcode prefix. Must be exactly 8 digits.', 400);
        }

        // Get all ready to print photos for this barcode
        $photos = Photo::where('status', 'ready_to_print')
            ->where('file_path', 'like', "%/{$barcodePrefix}/%")
            ->with(['user', 'uploader', 'branch'])
            ->get();

        // Calculate invoice data
        $numPhotos = $photos->count();
        $pricePerPhoto = 10.00; // 10 EGP per photo
        $amount = $numPhotos * $pricePerPhoto;
        $taxRate = 0.05; // 5%
        $taxAmount = $amount * $taxRate;
        $totalAmount = $amount + $taxAmount;

        return $this->successResponse([
            'photos' => PhotoResource::collection($photos),
            'invoice_summary' => [
                'num_photos' => $numPhotos,
                'amount' => number_format($amount, 2) . ' EGP',
                'tax_rate' => '5%',
                'tax_amount' => number_format($taxAmount, 2) . ' EGP',
                'total_amount' => number_format($totalAmount, 2) . ' EGP',
            ]
        ], 'Ready to print photos retrieved successfully');
    }

    /**
     * Upload multiple photos and automatically assign them to users based on filename prefixes
     */
    public function uploadPhotos(Request $request): JsonResponse
    {
        // Debug incoming request
        Log::info('Upload request:', [
            'has_files' => $request->hasFile('photos'),
            'all_data' => $request->all()
        ]);

        // Validate request
        $validator = Validator::make($request->all(), [
            'photos' => 'required|array',
            'photos.*' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Max 2MB per photo
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        if (!$request->hasFile('photos')) {
            return $this->errorResponse('No photos provided', 422);
        }

        $results = [
            'success' => [],
            'failed' => [],
            'invalid_prefix' => []
        ];

        // Process each uploaded photo
        foreach ($request->file('photos') as $photo) {
            try {
                // Get original filename
                $filename = $photo->getClientOriginalName();
                Log::info('Processing photo:', ['filename' => $filename]);
                
                // Extract the first 8 characters as potential barcode prefix
                $potentialPrefix = substr($filename, 0, 8);
                
                // Validate that the prefix is numeric and 8 digits
                if (!preg_match('/^\d{8}$/', $potentialPrefix)) {
                    $results['invalid_prefix'][] = [
                        'filename' => $filename,
                        'reason' => 'Filename must start with 8 digits'
                    ];
                    continue;
                }

                // Find user by barcode prefix
                $user = $this->userService->findUserByBarcodePrefix($potentialPrefix);
                
                if (!$user) {
                    $results['failed'][] = [
                        'filename' => $filename,
                        'reason' => 'No user found with barcode prefix: ' . $potentialPrefix
                    ];
                    Log::info('User not found for prefix:', ['prefix' => $potentialPrefix]);
                    continue;
                }

                Log::info('Found user:', ['user_id' => $user->id, 'barcode' => $user->barcode]);

                // Upload the photo and assign to user
                $uploadedPhoto = $this->photoService->uploadPhoto(
                    $photo,
                    $user->id,
                    1, // Default staff ID since auth is commented out
                    1, // Default branch ID since auth is commented out
                    $potentialPrefix
                );

                if ($uploadedPhoto) {
                    $results['success'][] = [
                        'filename' => $filename,
                        'photo' => new PhotoResource($uploadedPhoto),
                        'assigned_to' => $potentialPrefix
                    ];
                    Log::info('Photo uploaded successfully:', ['photo_id' => $uploadedPhoto->id]);
                }
            } catch (\Exception $e) {
                Log::error('Upload failed:', ['error' => $e->getMessage()]);
                $results['failed'][] = [
                    'filename' => $filename,
                    'reason' => 'Upload failed: ' . $e->getMessage()
                ];
            }
        }

        return $this->successResponse(
            $results,
            count($results['success']) . ' photos uploaded successfully'
        );
    }

    public function getPrintedBarcodes(): JsonResponse
    {
        // Get unique barcode prefixes from file paths where status is 'printed'
        $barcodePrefixes = Photo::where('status', 'printed')
            ->get()
            ->map(function ($photo) {
                // Extract the 8-digit prefix from the file path
                preg_match('/\/(\d{8})\//', $photo->file_path, $matches);
                return $matches[1] ?? null;
            })
            ->filter()
            ->unique()
            ->values();

        return $this->successResponse(
            $barcodePrefixes,
            'Printed photo barcode prefixes retrieved successfully'
        );
    }

    public function getPrintedPhotosByBarcode(string $barcodePrefix): JsonResponse
    {
        // Validate that the input is exactly 8 digits
        if (!preg_match('/^\d{8}$/', $barcodePrefix)) {
            return $this->errorResponse('Invalid barcode prefix. Must be exactly 8 digits.', 400);
        }

        // Get all printed photos for this barcode
        $photos = Photo::where('status', 'printed')
            ->where('file_path', 'like', "%/{$barcodePrefix}/%")
            ->with(['user', 'uploader', 'branch'])
            ->get();

        // Calculate invoice data
        $numPhotos = $photos->count();
        $pricePerPhoto = 10.00; // 10 EGP per photo
        $amount = $numPhotos * $pricePerPhoto;
        $taxRate = 0.05; // 5%
        $taxAmount = $amount * $taxRate;
        $totalAmount = $amount + $taxAmount;

        return $this->successResponse([
            'photos' => PhotoResource::collection($photos),
            'invoice_summary' => [
                'num_photos' => $numPhotos,
                'amount' => number_format($amount, 2) . ' EGP',
                'tax_rate' => '5%',
                'tax_amount' => number_format($taxAmount, 2) . ' EGP',
                'total_amount' => number_format($totalAmount, 2) . ' EGP',
            ]
        ], 'Printed photos retrieved successfully');
    }
} 