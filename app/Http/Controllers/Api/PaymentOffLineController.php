<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Photo;
use App\Models\Branch;
use App\Models\Invoice;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\StaffResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class PaymentOffLineController extends Controller
{
    use ApiResponse;


    public function show(Branch $branch)
    {
        try {
            // Total Sales (sum of total_amount from Invoices for the branch)
            $totalSales = Invoice::where('branch_id', $branch->id)
                ->where('status', 'paid')
                ->sum('total_amount');

            // Clients (count of unique users associated with the branch)
            $clients = User::where('branch_id', $branch->id)
                ->distinct()
                ->count();

            // Printed Photos (count of photos where status indicates printed)
            $printedPhotos = Photo::where('branch_id', $branch->id)
                ->where('status', 'printed')
                ->count();

            // Active Booths (count of active branches)
            $activeBooths = Branch::where('is_active', true)->count();

            // Employees (fetch staff data with photo and customer counts)
            $employees = $branch->staff()->withCount(['uploadedPhotos as total_photos', 'registeredUsers as total_customers'])->get();

            // Sales Data (monthly sales for the line graph, last 12 months)
            $salesData = Invoice::where('branch_id', $branch->id)
                ->where('status', 'completed')
                ->whereBetween('created_at', [now()->subYear(), now()])
                ->selectRaw('DATE_FORMAT(created_at, "%b") as month, SUM(total_amount) as value')
                ->groupBy('month')
                ->orderBy('created_at')
                ->get()
                ->map(function ($item) {
                    return ['month' => $item->month, 'value' => $item->value ?? 0];
                });

            // Photo Distribution (percentages for doughnut chart)
            $photoCounts = Photo::where('branch_id', $branch->id)
                ->groupBy('status')
                ->select('status', DB::raw('count(*) as count'))
                ->get()
                ->keyBy('status');

            $totalPhotos = $photoCounts->sum('count');
            $photoDistribution = [
                'sold' => $totalPhotos > 0 ? round(($photoCounts->get('sold', (object)['count' => 0])->count / $totalPhotos) * 100) : 0,
                'captured' => $totalPhotos > 0 ? round(($photoCounts->get('captured', (object)['count' => 0])->count / $totalPhotos) * 100) : 0,
            ];

            // Prepare dashboard data
            $dashboardData = [
                'branch' => $branch->name,
                'total_sales' => number_format($totalSales, 2) . ' EGP',
                'clients' => $clients,
                'printed_photos' => $printedPhotos,
                'active_booths' => $activeBooths,
                'employees' => StaffResource::collection($employees), // Use collection method
                'date' => now()->format('d-m-Y'), // Current date: 27-07-2025
                'sales_data' => $salesData,
                'photo_distribution' => $photoDistribution,
            ];

            return $this->successResponse($dashboardData, 'Dashboard statistics retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve dashboard statistics.', 500, $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine());
        }
    }
    public function index(Branch $branch)
    {
        try {

            $clients = User::where('branch_id', $branch->id)->get();
            $clientResources = UserResource::collection($clients);

            return $this->successResponse($clientResources, 'Clients retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve clients.', 500, $e->getMessage());
        }
    }

    public function invoices(Branch $branch, User $user)
    {
        try {

            // Verify that the user belongs to the specified branch
            if ($user->branch_id !== $branch->id) {
                return $this->errorResponse('User does not belong to the specified branch.', 403);
            }

            $invoices = Invoice::where('branch_id', $branch->id)
                ->where('user_id', $user->id)
                ->get();
            $invoiceResources = InvoiceResource::collection($invoices);

            return $this->successResponse($invoiceResources, 'Invoices retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve invoices.', 500, $e->getMessage());
        }
    }
	
	
	
}
