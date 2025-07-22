<?php

namespace App\Http\Controllers\Api\OnlineDashboard;

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
use Illuminate\Validation\ValidationException;


class PaymentController extends Controller
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

            // Printed Photos (count of photos where status indicates printed, assuming 'status' is used)
            $printedPhotos = Photo::where('branch_id', $branch->id)
                ->where('status', 'printed') // Adjust 'status' condition based on your logic
                ->count();

            // Active Booths (count of active branches, assuming a booth is a branch)
            $activeBooths = Branch::where('is_active', true)->count();

            // Employees (fetch staff data using StaffResource)
            $employees = $branch->staff()->get()
                ->map(function ($staff) {
                    return [
                        'id' => $staff->id,
                        'name' => $staff->name,
                        'email' => $staff->email,
                        'phone' => $staff->phone,
                        'role' => $staff->role,
                        'status' => $staff->status,
                    ];
                });

            // Prepare dashboard data
            $dashboardData = [
                'branch' => $branch->name,
                'total_sales' => number_format($totalSales, 2) . ' EGP',
                'clients' => $clients,
                'printed_photos' => $printedPhotos,
                'active_booths' => $activeBooths,
                'employees' => $employees,
                'date' => now()->format('d-m-Y'), // Current date
            ];

            return $this->successResponse($dashboardData, 'Dashboard statistics retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve dashboard statistics.', 500, $e->getMessage());
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
