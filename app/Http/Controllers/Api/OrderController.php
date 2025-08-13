<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            ->latest();

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
}

