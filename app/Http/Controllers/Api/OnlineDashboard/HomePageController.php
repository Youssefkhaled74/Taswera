<?php

namespace App\Http\Controllers\Api\OnlineDashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardStatsResource;
use App\Models\Order;
use App\Models\Photo;
use App\Models\Staff;
use App\Models\User;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomePageController extends Controller
{
    use ApiResponse;

    /**
     * Get dashboard statistics and data
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getDashboardStats(Request $request): JsonResponse
    {
        try {
            // Get date range (default to current month if not provided)
            $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
            $endDate = $request->input('end_date', Carbon::now()->endOfMonth());

            // Get total sales
            $totalSales = Order::whereBetween('created_at', [$startDate, $endDate])
                ->sum('total_price') ?? 0;

            // Get total clients (users)
            $totalClients = User::count() ?? 0;

            // Get total printed photos
            $totalPrintedPhotos = Photo::where('status', 'printed')
                ->count() ?? 0;

            // Get active booths (assuming active staff members)
            $activeBooths = Staff::where('role', 'staff')
                ->where('updated_at', '>=', Carbon::now()->subDay())
                ->count() ?? 0;

            // Get monthly sales data for graph
            $monthlySales = Order::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(total_price) as total')
            )
                ->where('created_at', '>=', Carbon::now()->subMonths(6))
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'month' => Carbon::createFromFormat('Y-m', $item->month)->format('M'),
                        'total' => (float)$item->total
                    ];
                });

            // Get staff performance data
            $staffPerformance = Staff::select('staff.id', 'staff.name')
                ->selectRaw('COUNT(DISTINCT users.id) as customer_count')
                ->selectRaw('COUNT(DISTINCT photos.id) as photo_count')
                ->leftJoin('users', 'staff.id', '=', 'users.registered_by')
                ->leftJoin('photos', 'staff.id', '=', 'photos.uploaded_by')
                ->groupBy('staff.id', 'staff.name')
                ->limit(7)
                ->get()
                ->map(function ($staff) {
                    return [
                        'name' => $staff->name,
                        'customers' => $staff->customer_count ?? 0,
                        'photos' => $staff->photo_count ?? 0
                    ];
                });

            // Calculate sold vs captured ratio
            $totalPhotos = Photo::count() ?? 0;
            $soldPhotos = Photo::where('status', 'printed')->count() ?? 0;
            $soldRatio = $totalPhotos > 0 ? round(($soldPhotos / $totalPhotos) * 100) : 0;

            $response = [
                'summary' => [
                    'total_sales' => number_format($totalSales, 2),
                    'total_clients' => $totalClients,
                    'printed_photos' => $totalPrintedPhotos,
                    'active_booths' => $activeBooths
                ],
                'sales_chart' => [
                    'labels' => $monthlySales->pluck('month'),
                    'data' => $monthlySales->pluck('total')
                ],
                'staff_performance' => $staffPerformance,
                'photo_stats' => [
                    'sold_percentage' => $soldRatio,
                    'sold_count' => $soldPhotos,
                    'captured_count' => $totalPhotos - $soldPhotos
                ]
            ];

            return $this->successResponse($response, 'Dashboard statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve dashboard statistics: ' . $e->getMessage(),
                500
            );
        }
    }
} 