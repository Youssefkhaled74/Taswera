<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Photo;
use App\Models\Staff;
use App\Models\Branch;
use App\Traits\ApiResponse;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\BranchManager;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\StaffResource;
use App\Http\Resources\BranchResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\BranchManagerResource;
use App\Http\Requests\BranchManager\LoginRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Services\BranchManager\BranchManagerServiceInterface;

class BranchManagerController extends Controller
{
    use ApiResponse, AuthorizesRequests;

    public function __construct(
        private readonly BranchManagerServiceInterface $branchManagerService
    ) {}

    /**
     * Handle branch manager login.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $manager = BranchManager::where('phone', $request->phone)->first();

        if (!$manager || !Hash::check($request->password, $manager->password)) {
            return $this->errorResponse('Invalid credentials', Response::HTTP_UNAUTHORIZED);
        }

        $token = $manager->createToken('branch-manager-token')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'manager' => new BranchManagerResource($manager->load('branch')),
        ], 'Login successful');
    }

    /**
     * Get branch information and statistics.
     */
    public function getBranchInfo(Request $request): JsonResponse
    {
        $manager = $request->user();
        $branch = Branch::find($manager->branch_id);

        $this->authorize('viewStats', $branch);

        $branchInfo = $this->branchManagerService->getBranchInfo($manager);

        return $this->successResponse([
            'branch' => new BranchResource($branchInfo['branch']),
            'stats' => $branchInfo['stats'],
        ], 'Branch information retrieved successfully');
    }

    /**
     * Get branch staff members with their photo and customer statistics.
     */
    public function getBranchStaff(Request $request): JsonResponse
    {
        $manager = $request->user();
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);

        // Query from service
        $staffQuery = $this->branchManagerService->getBranchStaff($manager);

        // Load uploadedPhotos relationship
        $staffQuery->with('uploadedPhotos');

        // Apply pagination
        $paginated = paginate($staffQuery, StaffResource::class, $limit, $page);

        return response()->json([
            'success' => true,
            'message' => 'Branch staff retrieved successfully',
            'data' => $paginated['data'],
            'meta' => $paginated['meta'],
            'links' => $paginated['links'],
        ]);
    }

    /**
     * Export statistics for photographers and daily payments in the branch.
     * Accepts optional from_date and to_date (Y-m-d). Defaults to today if not provided.
     */
    public function export(Request $request): JsonResponse
    {
        $manager = $request->user();
        $branchId = $manager->branch_id;
        $from = $request->input('from_date') ? Carbon::parse($request->input('from_date'))->startOfDay() : Carbon::today()->startOfDay();
        $to = $request->input('to_date') ? Carbon::parse($request->input('to_date'))->endOfDay() : Carbon::today()->endOfDay();

        // Photographers statistics
        $photographers = Staff::where('branch_id', $branchId)
            ->where('role', 'photographer')
            ->whereNull('deleted_at')
            ->get()
            ->map(function ($photographer) use ($from, $to) {
                $photosCount = Photo::where('uploaded_by', $photographer->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->count();
                $uniqueClients = Photo::where('uploaded_by', $photographer->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->distinct('user_id')
                    ->whereNotNull('user_id')
                    ->count('user_id');
                return [
                    'id' => $photographer->id,
                    'name' => $photographer->name,
                    'total_photos' => $photosCount,
                    'unique_clients' => $uniqueClients,
                ];
            });

        // Daily stats: total paid and per shift
        $orders = \App\Models\Order::where('branch_id', $branchId)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('pay_amount')
            ->where('pay_amount', '>', 0)
            ->get();

        $dailyStats = $orders->groupBy(function ($order) {
            return Carbon::parse($order->created_at)->toDateString();
        })->map(function ($ordersForDay, $date) {
            $totalPaid = $ordersForDay->sum('pay_amount');
            $shifts = $ordersForDay->groupBy('shift_id')->map(function ($ordersForShift, $shiftId) {
                return [
                    'shift_id' => $shiftId,
                    'amount_paid' => $ordersForShift->sum('pay_amount'),
                ];
            })->values();
            return [
                'date' => $date,
                'total_paid' => $totalPaid,
                'shifts' => $shifts,
            ];
        })->values();

        return $this->successResponse([
            'photographers' => $photographers,
            'daily_stats' => $dailyStats,
        ], 'Exported statistics successfully');
    }

    /**
     * Logout branch manager.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->successResponse(null, 'Logged out successfully');
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:branch_managers',
            'phone' => 'required|string|unique:branch_managers',
            'password' => 'required|string|min:8',
            'branch_id' => 'required|exists:branches,id',
        ]);

        $manager = $this->branchManagerService->register($data);
        return $this->successResponse(
            new BranchManagerResource($manager),
            'Branch manager registered successfully'
        );
    }






    public function uploadMultiplePhotos(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:staff,id',
            'barcode_prefix' => 'required|string|min:4|max:4',
            'photos' => 'required|array|min:1',
            'photos.*' => 'image',
        ]);

        if ($validated->fails()) {
            return $this->errorResponse($validated->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $barcodePrefix = $request->input('barcode_prefix');
        $user = User::where('barcode', $barcodePrefix)->first();
        if (!$user) {
            return $this->errorResponse('Barcode prefix not found', Response::HTTP_NOT_FOUND);
        }
        $employeeIds = $request->input('employee_ids');
        $staffList = \App\Models\Staff::whereIn('id', $employeeIds)
            ->whereNull('deleted_at')
            ->get();

        if ($staffList->count() !== count($employeeIds)) {
            return $this->errorResponse('One or more employee IDs are invalid or soft-deleted', Response::HTTP_NOT_FOUND);
        }
        $branchId = $staffList->first()->branch_id;
        if ($branchId == null) {
            return $this->errorResponse('Branch not found For these employees', Response::HTTP_NOT_FOUND);
        }
        $today = \Carbon\Carbon::now();
        $year = $today->year;
        $month = str_pad($today->month, 2, '0', STR_PAD_LEFT);
        $day = str_pad($today->day, 2, '0', STR_PAD_LEFT);

        $savedPhotos = [];
        $photos = $request->file('photos');
        $numEmployees = count($employeeIds);
        $employeeIndex = 0;

        foreach ($photos as $photoFile) {
            $uniqueCode = Str::random(6);
            $filename = $barcodePrefix . '_' . $uniqueCode . '.' . $photoFile->getClientOriginalExtension();
            $directory = public_path("photos/$year/$month/$day/$barcodePrefix");

            // Create directory if it doesn't exist
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            $photoFile->move($directory, $filename);

            $relativePath = "/photos/$year/$month/$day/$barcodePrefix/$filename";
            $assignedEmployeeId = $employeeIds[$employeeIndex % $numEmployees];
            $employeeIndex++;

            $photo = \App\Models\Photo::create([
                'user_id' => null,
                'barcode_prefix' => $barcodePrefix,
                'file_path' => $relativePath,
                'original_filename' => $filename,
                'uploaded_by' => $assignedEmployeeId,
                'branch_id' => $branchId,
                'status' => 'pending',
                'sync_status' => 'pending',
                'is_edited' => false,
            ]);

            $savedPhotos[] = $photo;
        }

        return $this->successResponse($savedPhotos, 'Photos uploaded successfully', 201);
    }
}
