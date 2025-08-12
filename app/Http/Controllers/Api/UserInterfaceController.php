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
        $validated = $request->validate([
            'barcode_prefix' => 'required|string|min:8',
            'phone_number' => 'required|string'
        ]);

        // 1. Check if barcode is already registered
        $existingUser = User::where('barcode', $validated['barcode_prefix'])->first();
        if ($existingUser) {
            return response()->json([
                'message' => 'This barcode is already registered to a user.',
                'user' => $existingUser
            ], 201);
        }

        // 2. Check if there are any photos with this barcode_prefix
        $hasPhotos = Photo::where('barcode_prefix', $validated['barcode_prefix'])->exists();

        if (!$hasPhotos) {
            return response()->json([
                'message' => 'Cannot create user. No photos found for this barcode.'
            ], 422); // Unprocessable Entity
        }

        // 3. Create the user
        $user = User::create([
            'barcode' => $validated['barcode_prefix'],
            'phone_number' => $validated['phone_number'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user
        ], 201);
    }
}
