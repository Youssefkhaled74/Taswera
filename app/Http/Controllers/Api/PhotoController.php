<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Photo;
use App\Models\Invoice;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Traits\HandlesMediaUploads;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\PhotoResource;
use App\Http\Resources\InvoiceResource;
use Illuminate\Support\Facades\Validator;
use App\Services\User\UserServiceInterface;
use App\Services\Photo\PhotoServiceInterface;
use Illuminate\Validation\ValidationException;
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
        // Query unique barcode_prefix values for photos uploaded by the staff member
        $barcodes = Photo::where('uploaded_by', $staffId)
            ->distinct()
            ->pluck('barcode_prefix')
            ->filter()
            ->values();

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
        try {
            // Get all unique barcode prefixes from orders that have no payment
            $prefixes = Order::whereNotNull('barcode_prefix')
                ->where('send_type', null)
                ->select('barcode_prefix')
                ->distinct()
                ->get()

                ->pluck('barcode_prefix')
                ->toArray();

            if (empty($prefixes)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Ready to print barcodes retrieved successfully',
                    'data' => [
                        'barcodes' => []
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ready to print barcodes retrieved successfully',
                'data' => [
                    'barcodes' => $prefixes
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'barcodes' => []
                ]
            ], 500);
        }
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

        return $this->successResponse(
            PhotoResource::collection($photos),
            'Ready to print photos retrieved successfully'
        );
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

    /**
     * Get all barcode prefixes that have printed photos
     */
    public function getPrintedBarcodes(): JsonResponse
    {
        try {
            $photos = Photo::where('status', 'printed')->get();

            // Extract unique barcodes from file paths
            $barcodes = $photos->map(function ($photo) {
                return $photo->getBarcode();
            })->unique()->values()->filter();

            return $this->successResponse(
                ['barcodes' => $barcodes],
                'Printed photo barcodes retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve printed photo barcodes: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get all printed photos for a specific barcode prefix
     */
    public function getPrintedPhotosByBarcode(string $barcodePrefix): JsonResponse
    {
        // Validate that the input is exactly 8 digits
        if (!preg_match('/^\d{8}$/', $barcodePrefix)) {
            return $this->errorResponse('Invalid barcode prefix. Must be exactly 8 digits.', 400);
        }

        try {
            // Get all printed photos for this barcode
            $photos = Photo::where('status', 'printed')
                ->where('file_path', 'like', "%/{$barcodePrefix}/%")
                ->with(['user', 'uploader', 'branch'])
                ->get();

            return $this->successResponse(
                PhotoResource::collection($photos),
                'Printed photos retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve printed photos: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
    public function getOrdersBySendType(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => ['required', 'string', Rule::in(['print', 'send', 'print_and_send'])]
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
            }

            $type = $request->query('type');

            $query = Order::whereNotNull('barcode_prefix')
                ->where(function ($query) use ($type) {
                    if ($type === 'print') {
                        $query->whereIn('send_type', ['print', 'print_and_send']);
                    } elseif ($type === 'send') {
                        $query->whereIn('send_type', ['send', 'print_and_send']);
                    } else {
                        $query->where('send_type', 'print_and_send');
                    }
                })
                ->select('barcode_prefix')
                ->distinct();

            $prefixes = $query->get()->pluck('barcode_prefix')->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Orders retrieved successfully',
                'data' => [
                    'barcodes' => $prefixes ?: []
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'barcodes' => []
                ]
            ], 500);
        }
    }
    public function getSelectedPhotosByPrefix(string $prefix): JsonResponse
    {
        try {
            // Validate prefix format
            if (!preg_match('/^\d{4}$/', $prefix)) {
                return $this->errorResponse('Invalid barcode prefix. Must be exactly 8 digits.', 400);
            }

            // Get order with its related data
            $order = Order::where('barcode_prefix', $prefix)
                ->with(['orderItems.selected' => function ($query) {
                    $query->select(
                        'id',
                        'file_path',
                        'thumbnail_path',
                        'quantity',
                        'status',
                        'barcode_prefix',
                        'branch_id'
                    );
                }])
                ->first();

            if (!$order) {
                return $this->errorResponse('No order found with this prefix', 404);
            }

            // Transform the data
            $photos = $order->orderItems->map(function ($item) {
                if (!$item->selected) {
                    return null;
                }

                return [
                    'id' => $item->selected->id,
                    'file_path' => config('app.url') . $item->selected->file_path,
                    'thumbnail_path' => config('app.url') . $item->selected->thumbnail_path,
                    'quantity' => $item->selected->quantity,
                    'status' => $item->selected->status,
                    'branch_id' => $item->selected->branch_id
                ];
            })->filter()->values();

            return $this->successResponse([
                'order_id' => $order->id,
                'barcode_prefix' => $prefix,
                'send_type' => $order->send_type,
                'photos' => $photos
            ], 'Selected photos retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
