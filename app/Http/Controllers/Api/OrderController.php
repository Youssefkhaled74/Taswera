<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\PhotoSelected;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode_prefix' => 'sometimes|string|min:4',
            'phone_number' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        $query = Order::query()
            ->with(['orderItems.selected', 'user', 'branch'])
            ->withCount('orderItems')
            ->latest()
            ->whereNull('pay_amount')
            ->where('shift_id' , 0);

        if ($request->filled('barcode_prefix')) {
            $query->where('barcode_prefix', $request->query('barcode_prefix'));
        }
        if ($request->filled('phone_number')) {
            $query->where('phone_number', $request->query('phone_number'));
        }

        $orders = $query->paginate($request->query('limit', 15));

        return $this->successResponse([
            'data' => OrderResource::collection($orders->items())->resolve(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ], 'Orders retrieved successfully');
    }

    public function uploadPhotosAndCreate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode_prefix' => 'required|string|min:4',
            'phone_number' => 'required|string',
            'employee_id' => 'required|integer|exists:staff,id',
            'photos' => 'required|array|min:1',
            'photos.*' => 'required|image',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        $data = $validator->validated();

        try {
            return DB::transaction(function () use ($data, $request) {
                // Ensure user exists or create
                $user = User::firstOrCreate(
                    ['barcode' => $data['barcode_prefix'], 'phone_number' => $data['phone_number']],
                    ['created_at' => now(), 'updated_at' => now()]
                );

                $year = date('Y');
                $month = date('m');
                $day = date('d');
                $dir = "photos/{$year}/{$month}/{$day}/{$data['barcode_prefix']}";
                File::ensureDirectoryExists(public_path($dir));

                $createdSelected = collect();

                foreach ($request->file('photos') as $file) {
                    $ext = $file->getClientOriginalExtension();
                    $originalName = $file->getClientOriginalName();
                    $unique = uniqid();
                    $filename = $data['barcode_prefix'] . '_' . $unique . '.' . $ext;
                    $absPath = public_path($dir . '/' . $filename);
                    $file->move(dirname($absPath), basename($absPath));
                    $relative = '/' . trim($dir . '/' . $filename, '/');

                    // Create Photo row
                    $photo = Photo::create([
                        'user_id' => $user->id,
                        'barcode_prefix' => $data['barcode_prefix'],
                        'file_path' => $relative,
                        'original_filename' => $originalName,
                        'uploaded_by' => $data['employee_id'],
                        'branch_id' => 1,
                        'is_edited' => false,
                        'thumbnail_path' => null,
                        'status' => 'pending',
                        'sync_status' => 'pending',
                        'metadata' => ['created_from' => 'upload_order']
                    ]);

                    // Mirror to PhotoSelected with same file path
                    $createdSelected->push(PhotoSelected::create([
                        'user_id' => $user->id,
                        'original_photo_id' => $photo->id,
                        'quantity' => 1,
                        'barcode_prefix' => $data['barcode_prefix'],
                        'file_path' => $photo->file_path,
                        'original_filename' => $photo->original_filename,
                        'uploaded_by' => $photo->uploaded_by,
                        'branch_id' => $photo->branch_id,
                        'is_edited' => $photo->is_edited,
                        'thumbnail_path' => $photo->thumbnail_path,
                        'status' => 'pending',
                        'sync_status' => 'pending',
                        'metadata' => ['created_from' => 'upload_order']
                    ]));
                }

                // Create order
                $order = Order::create([
                    'user_id' => $user->id,
                    'package_id' => null,
                    'total_price' => 0,
                    'status' => 'pending',
                    'processed_by' => $createdSelected->first()->uploaded_by ?? null,
                    'branch_id' => 1,
                    'whatsapp_link' => null,
                    'link_expires_at' => null,
                    'phone_number' => $data['phone_number'],
                    'barcode_prefix' => $data['barcode_prefix'],
                    'type' => 'manual',
                ]);

                // Add items from selected
                foreach ($createdSelected as $sel) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'photo_id' => $sel->original_photo_id,
                        'selected_photo_id' => $sel->id,
                        'original_photo_id' => $sel->original_photo_id,
                        'edited_photo_path' => $sel->file_path,
                        'frame' => null,
                        'filter' => null,
                    ]);
                }

                return $this->successResponse(new OrderResource($order->load(['orderItems.selected'])), 'Order created successfully');
            });
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    public function submitOrder(Request $request, $orderId)
    {
        $order = Order::find($orderId);

        if (!$order) {
            return $this->errorResponse('Order not found', Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'shift_id' => 'required|exists:shifts,id',
            'pay_amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        $order->shift_id = $request->input('shift_id');
        $order->pay_amount = $request->input('pay_amount');
        $order->save();

        return new OrderResource($order);
    }
}
