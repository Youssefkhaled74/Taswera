<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Shift;
use App\Models\Staff;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\ShiftResource;
use App\Http\Resources\StaffResource;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ShiftController extends Controller
{
    use ApiResponse;
    public function index()
    {
        $shifts = Shift::where('branch_id', auth('branch-manager')->user()->branch_id)->get();
        return $this->successResponse(ShiftResource::collection($shifts), 'Records fetched successfully');
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'branch_id' => 'required',
            'name' => 'required',
            'from' => 'required | date_format:H:i',
            'to' => 'required | date_format:H:i',
            // 'status' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }
        $shift = new Shift();
        $shift->branch_id = auth('branch-manager')->user()->branch_id;
        $shift->name = $request->input('name');
        $shift->from = $request->input('from');
        $shift->to = $request->input('to');
        $shift->save();
        return $this->successResponse(new ShiftResource($shift), 'Shift created successfully');
    }

    public function delete($id)
    {
        $shift = Shift::find($id);
        if (!$shift) {
            return $this->errorResponse('Shift not found', Response::HTTP_NOT_FOUND);
        }
        $shift->delete();
        return $this->successResponse(null, 'Shift deleted successfully');
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            // 'branch_id' => 'required',
            'name' => 'required',
            'from' => 'required | date_format:H:i',
            'to' => 'required | date_format:H:i',
            // 'status' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }
        $shift = Shift::find($id);
        if (!$shift) {
            return response()->json(['message' => 'Shift not found'], 404);
        }
        $shift->branch_id = auth('branch-manager')->user()->branch_id;
        $shift->name = $request->input('name');
        $shift->from = $request->input('from');
        $shift->to = $request->input('to');
        $shift->save();
        return $this->successResponse(new ShiftResource($shift), 'Shift updated successfully');
    }


    public function addPhotographer(Request $request)
    {
        try {
            // Define validation rules, including manager credentials
            $validator = Validator::make($request->all(), [
                'manager_email' => ['required', 'string', 'email'],
                'manager_password' => ['required', 'string'],
                'name' => ['required', 'string', 'max:255', 'unique:staff,name'],
                'phone' => ['nullable', 'string', 'max:20']
            ], [
                'manager_email.required' => 'The manager email is required.',
                'manager_password.required' => 'The manager password is required.',
                'name.required' => 'The name field is required.',
                'name.max' => 'The name cannot exceed 255 characters.',
                'phone.max' => 'The phone number cannot exceed 20 characters.'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validator->errors()
                );
            }

            // Check manager credentials
            if (
                $request->input('manager_email') !== env('MANAGER_EMAIL') ||
                $request->input('manager_password') !== env('MANAGER_PASSWORD')
            ) {
                return $this->errorResponse(
                    'Invalid manager credentials.',
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // Get validated data
            $data = $validator->validated();

            // Assign branch_id from authenticated branch manager
            $branch_id = auth('branch-manager')->user()->branch_id;

            // Verify the branch_id exists in the branches table
            if (!\App\Models\Branch::where('id', $branch_id)->exists()) {
                return $this->errorResponse(
                    'The branch associated with the authenticated user does not exist.',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            // Prepare data for photographer creation
            $data['branch_id'] = $branch_id;
            $data['role'] = 'photographer';
            $data['status'] = 'active';
            $data['phone'] = $request->input('phone', 'N/A'); // Optional phone field
            $data['email'] = strtolower(str_replace(' ', '.', $data['name'])) . '@photographer.com';
            $data['password'] = Hash::make('password123');

            // Create the photographer
            $photographer = Staff::create($data);

            return $this->successResponse(
                new StaffResource($photographer->load('branch')),
                'Photographer added successfully',
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to add photographer: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }


    public function updatePhotographer(Request $request, $id)
    {
        try {
            // Validate request data, including manager credentials
            $validator = Validator::make($request->all(), [
                'manager_email' => ['required', 'string', 'email'],
                'manager_password' => ['required', 'string'],
                'name' => ['required', 'string', 'max:255', 'unique:staff,name'],
                'phone' => ['nullable', 'string', 'max:20'],
                'status' => ['sometimes', 'string', 'in:active,inactive']
            ], [
                'manager_email.required' => 'The manager email is required.',
                'manager_password.required' => 'The manager password is required.',
                'name.max' => 'The name cannot exceed 255 characters.',
                'phone.max' => 'The phone number cannot exceed 20 characters.',
                'status.in' => 'The status must be either active or inactive.'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validator->errors()
                );
            }

            // Check manager credentials
            if (
                $request->input('manager_email') !== env('MANAGER_EMAIL') ||
                $request->input('manager_password') !== env('MANAGER_PASSWORD')
            ) {
                return $this->errorResponse(
                    'Invalid manager credentials.',
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // Find the photographer
            $branch_id = auth('branch-manager')->user()->branch_id;
            $photographer = Staff::where('id', $id)
                ->where('branch_id', $branch_id)
                ->where('role', 'photographer')
                ->first();

            if (!$photographer) {
                return $this->errorResponse(
                    'Photographer not found or does not belong to your branch.',
                    Response::HTTP_NOT_FOUND
                );
            }

            // Update only provided fields
            $data = $validator->validated();
            if (isset($data['name'])) {
                $data['email'] = strtolower(str_replace(' ', '.', $data['name'])) . '@photographer.com';
            }
            $photographer->update($data);

            return $this->successResponse(
                new StaffResource($photographer->load('branch')),
                'Photographer updated successfully',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update photographer: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Delete a photographer.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function deletePhotographer($id)
    {
        try {
            // Check manager credentials from request
            $manager_email = request()->input('manager_email');
            $manager_password = request()->input('manager_password');
            if (!$manager_email || !$manager_password) {
                return $this->errorResponse(
                    'Manager email and password are required.',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
            if (
                $manager_email !== env('MANAGER_EMAIL') ||
                $manager_password !== env('MANAGER_PASSWORD')
            ) {
                return $this->errorResponse(
                    'Invalid manager credentials.',
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // Find the photographer
            $branch_id = auth('branch-manager')->user()->branch_id;
            $photographer = Staff::where('id', $id)
                ->where('branch_id', $branch_id)
                ->where('role', 'photographer')
                ->first();

            if (!$photographer) {
                return $this->errorResponse(
                    'Photographer not found or does not belong to your branch.',
                    Response::HTTP_NOT_FOUND
                );
            }

            // Delete the photographer
            $photographer->delete();

            return $this->successResponse(
                null,
                'Photographer deleted successfully',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete photographer: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }



    public function generateBarcodes(Request $request)
    {
        try {
            $validated = Validator::make($request->all(), [
                'quantity' => 'required|integer|min:1|max:9000'
            ], [
                'quantity.required' => 'The quantity field is required.',
                'quantity.integer' => 'The quantity must be an integer.',
                'quantity.min' => 'The quantity must be at least 1.',
                'quantity.max' => 'The quantity cannot exceed 9000.'
            ]);

            if ($validated->fails()) {
                return $this->errorResponse(
                    $validated->errors()->first(),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $quantity = $request->input('quantity');
            $branchManager = auth('branch-manager')->user();
            $branchId = $branchManager->branch_id;
            $registeredBy = $branchManager->id;

            // Define the character set for alphanumeric barcodes (36 characters)
            $characters = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            $charLength = strlen($characters);
            $barcodeLength = 5;
            $maxCombinations = pow($charLength, $barcodeLength); // 36^5 = 60,466,176

            // Get existing barcodes
            $existingBarcodes = User::pluck('barcode')->toArray();

            // Check if enough unique barcodes are available
            if ($maxCombinations - count($existingBarcodes) < $quantity) {
                return $this->errorResponse(
                    'Not enough unique 4-character alphanumeric barcodes available.',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $barcodes = [];
            $users = [];

            // Generate unique barcodes
            while (count($barcodes) < $quantity) {
                $barcode = '';
                for ($i = 0; $i < $barcodeLength; $i++) {
                    $barcode .= $characters[rand(0, $charLength - 1)];
                }
                if (!in_array($barcode, $existingBarcodes) && !in_array($barcode, $barcodes)) {
                    $barcodes[] = $barcode;
                }
            }

            // Create users with generated barcodes
            foreach ($barcodes as $barcode) {
                $user = User::create([
                    'barcode' => $barcode,
                    'phone_number' => null,
                    'branch_id' => $branchId,
                    'registered_by' => $registeredBy,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $users[] = $user;
            }

            return $this->successResponse(
                [
                    'barcodes' => $barcodes,
                    // 'users' => $users, // Uncomment if user details are needed
                ],
                'Barcodes generated and users created successfully',
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to generate barcodes: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }


    public function getAllBarcodes(Request $request)
    {
        // Number per page (default 15, or pass ?per_page=20 in request)
        $perPage = $request->input('per_page', 15);

        // Initialize query
        $query = User::select('barcode', 'phone_number');

        // Apply filter if ?filter=yes is provided
        if ($request->input('filter') === 'yes') {
            $query->whereNull('phone_number');
        }

        // Paginate the results
        $users = $query->paginate($perPage);

        // Transform the data while keeping pagination meta
        $barcodes = $users->getCollection()->map(function ($user) {
            return [
                'barcode' => $user->barcode,
                'used' => !empty($user->phone_number),
            ];
        });

        // Replace the original collection with transformed data
        $users->setCollection($barcodes);

        return response()->json($users);
    }
}
