<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Order;
use App\Models\Photo;
use App\Models\OrderItem;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Models\PhotoSelected;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
            ->where('shift_id', 0);

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
                    'shift_id' => 0,
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


    public function getOrderPhotos(Request $request, $prefix): JsonResponse
    {
        $validated = $request->validate([
            'send_type' => ['required', 'string', Rule::in(['print', 'send', 'print_and_send'])],
        ]);

        if (Order::whereNull('pay_amount')->whereNull('shift_id')) {
            return $this->errorResponse('Order not found', 404);
        }

        $order = Order::where('barcode_prefix', $prefix)
            ->with(['orderItems.selected', 'user'])
            ->first();

        if (!$order) {
            return $this->errorResponse('Order not found', 404);
        }

        // Update send type
        $order->update(['send_type' => $validated['send_type']]);

        // Generate share link if send type includes 'send'
        if (in_array($validated['send_type'], ['send', 'print_and_send'])) {
            $shareLink = $this->generateShareLink($order);
            $order->update([
                'whatsapp_link' => $shareLink, // You might want to rename this column to 'share_link'
                'link_expires_at' => now()->addHours(24)
            ]);
        }
        if (in_array($validated['send_type'], ['send', 'print_and_send'])) {
            $shareLink = $this->generateShareLink($order);
            $order->update([
                'whatsapp_link' => $shareLink,
                'link_expires_at' => now()->addDays(2) // Changed from addHours(24) to addDays(2)
            ]);
        }

        $photos = $order->orderItems->map(function ($item) {
            if (!$item->selected) {
                return null;
            }

            return [
                'id' => $item->selected->id,
                'file_path' => config('app.url') . $item->selected->file_path,
                'thumbnail_path' => $item->selected->thumbnail_path,
                'quantity' => $item->selected->quantity,
                'status' => $item->selected->status
            ];
        })->filter();

        return $this->successResponse([
            'order' => [
                'id' => $order->id,
                'share_link' => 'https://example.com/share/' . $order->barcode_prefix, // Example static link
                'link_expires_at' => $order->link_expires_at,
                'send_type' => $order->send_type,
                'photos' => $photos
            ]
        ], 'Order photos retrieved successfully');
    }

    private function generateShareLink(Order $order): string
    {
        // Generate a unique hash for the order
        $hash = base64_encode($order->id . '_' . $order->barcode_prefix);

        // Create a URL to your frontend application
        return config('app.url') . '/view-photos/' . $hash;
    }
}
