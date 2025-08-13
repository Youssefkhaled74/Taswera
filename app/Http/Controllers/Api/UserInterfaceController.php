<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Photo;
use App\Traits\ApiResponse;
use App\Models\PrintRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\PhotoResource;
use App\Http\Resources\BranchResource;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\PrintRequestResource;
use App\Services\UserInterface\UserInterfaceServiceInterface;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PhotoSelected;
use Illuminate\Support\Facades\DB;
use App\Models\PhotoSelection;

class UserInterfaceController extends Controller
{
    use ApiResponse;

    protected $userInterfaceService;

    public function __construct(UserInterfaceServiceInterface $userInterfaceService)
    {
        $this->userInterfaceService = $userInterfaceService;
    }

    /**
     * Get user photos by barcode and phone number
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserPhotos(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'barcode' => 'required|string|min:4',
        ]);

        if ($validated->fails()) {
            return $this->errorResponse($validated->errors(), Response::HTTP_BAD_REQUEST);
        }

        $barcode = $request->query('barcode');

        // ابحث عن المستخدم مع العلاقة بالفرع
        $user = User::with('branch')
            ->where('barcode', 'LIKE', $barcode . '%')
            ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }

        // احضر الصور المرتبطة بالمستخدم مع العلاقات
        $photos = Photo::with(['staff', 'branch'])
            ->where('barcode_prefix', $barcode)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Photos retrieved successfully',
            'data' => [
                'user' => [
                    'barcode' => $user->barcode,
                    'phone_number' => $user->phone_number,
                    'branch_id' => $user->branch_id,
                    'branch' => $user->branch ? new BranchResource($user->branch) : null,
                ],
                'photos' => PhotoResource::collection($photos)->resolve()
            ]
        ]);
    }
    /**
     * Get all available packages for a specific branch
     *
     * @param int $branchId
     * @return JsonResponse
     */
    public function getPackages(int $branchId): JsonResponse
    {
        $packages = $this->userInterfaceService->getPackages($branchId);
        return $this->successResponse($packages, 'Packages retrieved successfully');
    }

    /**
     * Add a new photo to user's collection
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function addUserPhoto(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string|min:4',
            'phone_number' => 'required|string',
            'photo' => 'required|image|max:10240' // Max 10MB
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        $validatedData = $validator->validated();

        $result = $this->userInterfaceService->addUserPhoto(
            $validatedData['barcode'],
            $validatedData['phone_number'],
            ['photo' => $validatedData['photo']]
        );


        if (empty($result)) {
            return $this->errorResponse('User not found', 404);
        }

        return $this->successResponse($result, 'Photo uploaded successfully');
    }

    /**
     * Select photos for printing and create print request
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function selectPhotosForPrinting(Request $request): JsonResponse
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string|min:4',
            'phone_number' => 'required|string',
            'photo_ids' => 'required|array|min:1',
            'photo_ids.*.id' => 'required|integer|exists:photos,id',
            'photo_ids.*.quantity' => 'required|integer|min:1',
            'package_id' => 'nullable|integer|exists:packages,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors(), Response::HTTP_BAD_REQUEST);
        }

        // Get validated data as an array
        $validatedData = $validator->validated();

        // Define payment method
        $paymentMethod = 'cash'; // Can be 'instaPay' or 'creditCard' as needed

        // Call service to process print request
        $result = $this->userInterfaceService->selectPhotosForPrinting(
            $validatedData['barcode'],
            $validatedData['phone_number'],
            [
                'photo_ids' => $validatedData['photo_ids'],
                'package_id' => $validatedData['package_id'] ?? null,
                'payment_method' => $paymentMethod,
            ]
        );

        // Check if result is empty
        if (empty($result)) {
            return $this->errorResponse('User not found', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse($result, 'Print request created successfully');
    }

    public function getPhotosReadyToPrint(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode_prefix' => 'required|string|min:4',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }



        try {
            $barcodePrefix = $request->query('barcode_prefix');

            // Get print requests for this barcode prefix and eager load related data
            $printRequests = PrintRequest::with(['photos.staff', 'photos.branch', 'user', 'branch', 'package'])
                ->where('barcode_prefix', $barcodePrefix)
                ->whereIn('status', ['pending', 'ready_to_print'])
                ->get();

            if ($printRequests->isEmpty()) {
                return $this->errorResponse('No photos ready for printing', 404);
            }

            // Aggregate photos only from print requests and duplicate by quantity from pivot
            $photos = $printRequests->flatMap(function ($pr) {
                return $pr->photos->flatMap(function ($photo) {
                    $qty = (int) ($photo->pivot->quantity ?? 1);
                    return collect()->pad($qty, $photo)->values();
                });
            })->values();

            if ($photos->isEmpty()) {
                return $this->errorResponse('No photos ready for printing', 404);
            }

            $firstRequest = $printRequests->first();
            $user = $firstRequest->user;

            // Build flat list mirroring photo_print_request table rows
            $printRequestPhotos = $printRequests->flatMap(function ($pr) {
                return $pr->photos->map(function ($photo) use ($pr) {
                    return [
                        'print_request_id' => $pr->id,
                        'photo_id' => $photo->id,
                        'quantity' => (int) ($photo->pivot->quantity ?? 1),
                        'unit_price' => $photo->pivot->unit_price !== null ? (float) $photo->pivot->unit_price : null,
                        'created_at' => $photo->pivot->created_at,
                        'updated_at' => $photo->pivot->updated_at,
                    ];
                });
            })->values();

            return $this->successResponse([
                'user' => [
                    'barcode' => $user?->barcode,
                    'phone_number' => $user?->phone_number,
                    'branch_id' => $firstRequest->branch_id,
                ],
                'photos' => PhotoResource::collection($photos)->resolve(),
                'print_requests' => PrintRequestResource::collection($printRequests)->resolve(),
                'print_request_photos' => $printRequestPhotos,
            ], 'Photos ready for printing retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createUserDependOnQrCode(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'barcode_prefix' => 'required|string|min:4',
            'phone_number' => 'required|string'
        ]);

        if ($validated->fails()) {
            return $this->errorResponse($validated->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        $barcodePrefix = $request->barcode_prefix;
        $phoneNumber = $request->phone_number;

        // 1. Check if barcode is already registered
        $existingUser = User::where('barcode', $barcodePrefix)->first();
        if ($existingUser) {
            return response()->json([
                'message' => 'This barcode is already registered to a user.',
                'user' => $existingUser
            ], 201);
        }

        // 2. Check if there are any photos with this barcode_prefix
        $hasPhotos = Photo::where('barcode_prefix', $barcodePrefix);

        if (!$hasPhotos) {
            return response()->json([
                'message' => 'Cannot create user. No photos found for this barcode.'
            ], 422); // Unprocessable Entity
        }

        // 3. Create the user
        $user = User::create([
            'barcode' => $barcodePrefix,
            'phone_number' => $phoneNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user
        ], 201);
    }

    public function createOrderFromSelected(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode_prefix' => 'required|string|min:4',
            'phone_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        $data = $validator->validated();

        try {
            $user = User::where('barcode', 'LIKE', $data['barcode_prefix'] . '%')
                ->where('phone_number', $data['phone_number'])
                ->first();

            if (!$user) {
                return $this->errorResponse('User not found', Response::HTTP_NOT_FOUND);
            }

            // Load all selected photos for this user and barcode prefix
            $selectedRows = PhotoSelected::where('user_id', $user->id)
                ->where('barcode_prefix', $data['barcode_prefix'])
                ->orderBy('created_at', 'asc')
                ->get();

            if ($selectedRows->isEmpty()) {
                return $this->errorResponse('No selected photos found for this barcode', Response::HTTP_NOT_FOUND);
            }

            return DB::transaction(function () use ($user, $data, $selectedRows) {
                $order = Order::create([
                    'user_id' => $user->id,
                    'package_id' => null,
                    'total_price' => 0,
                    'status' => 'pending',
                    'processed_by' => $selectedRows->first()->uploaded_by ?? null,
                    'branch_id' => $selectedRows->first()->branch_id ?? $user->branch_id,
                    'whatsapp_link' => null,
                    'link_expires_at' => null,
                    'type' => 'user_interface',
                    'phone_number' => $data['phone_number'],
                    'barcode_prefix' => $data['barcode_prefix'],
                ]);

                foreach ($selectedRows as $selected) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'photo_id' => null,
                        'selected_photo_id' => $selected->id,
                        'original_photo_id' => $selected->original_photo_id,
                        'frame' => null,
                        'filter' => null,
                        'edited_photo_path' => $selected->file_path,
                    ]);
                }

                return $this->successResponse([
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'items_count' => $selectedRows->count(),
                ], 'Order created successfully');
            });
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function selectAndClonePhotos(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string|min:4',
            'phone_number' => 'required|string',
            'photo_ids' => 'required|array|min:1',
            'photo_ids.*.id' => 'required|integer|exists:photos,id',
            'photo_ids.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        $data = $validator->validated();

        try {
            $user = User::where('barcode', 'LIKE', $data['barcode'] . '%')
                ->where('phone_number', $data['phone_number'])
                ->first();

            if (!$user) {
                return $this->errorResponse('User not found', Response::HTTP_NOT_FOUND);
            }

            $barcodePrefix = $data['barcode'];

            $photoItems = collect($data['photo_ids']);
            $photoIds = $photoItems->pluck('id')->all();

            $originalPhotos = Photo::whereIn('id', $photoIds)
                ->where('barcode_prefix', $barcodePrefix)
                ->get()
                ->keyBy('id');

            if ($originalPhotos->count() !== count($photoIds)) {
                return $this->errorResponse('Invalid photo selection', Response::HTTP_BAD_REQUEST);
            }

            $clonedPhotos = [];

            foreach ($photoItems as $item) {
                $photoId = (int) $item['id'];
                $quantity = (int) $item['quantity'];
                $original = $originalPhotos[$photoId];

                // Save selection record
                PhotoSelection::create([
                    'user_id' => $user->id,
                    'original_photo_id' => $photoId,
                    'barcode_prefix' => $barcodePrefix,
                    'quantity' => $quantity,
                    'metadata' => [ 'source' => 'user_interface' ],
                ]);

                // Clone photos according to quantity (duplicate Photo rows with same file_path)
                for ($i = 0; $i < $quantity; $i++) {
                    $cloned = Photo::create([
                        'user_id' => $user->id,
                        'barcode_prefix' => $barcodePrefix,
                        'file_path' => $original->file_path,
                        'original_filename' => $original->original_filename,
                        'uploaded_by' => $original->uploaded_by,
                        'branch_id' => $original->branch_id,
                        'is_edited' => $original->is_edited,
                        'thumbnail_path' => $original->thumbnail_path,
                        'status' => 'pending',
                        'sync_status' => 'pending',
                        'metadata' => array_merge($original->metadata ?? [], [
                            'cloned_from' => $original->id,
                            'clone_index' => $i + 1,
                        ]),
                    ]);
                    $clonedPhotos[] = $cloned;
                }
            }

            return $this->successResponse([
                'cloned_count' => count($clonedPhotos),
                'photos' => PhotoResource::collection(collect($clonedPhotos)->load(['staff', 'branch']))->resolve(),
            ], 'Photos cloned and recorded successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
