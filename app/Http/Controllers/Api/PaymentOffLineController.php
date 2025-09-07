<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Order;
use App\Models\Photo;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\Branch;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Models\PhotoSelected;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\StaffResource;
use Illuminate\Validation\ValidationException;

class PaymentOffLineController extends Controller
{
    use ApiResponse;

    public function show(Branch $branch, Request $request)
    {
        try {
            // Validate optional shift_id parameter
            $shiftId = $request->query('shift_id', null);

            // Total Sales (sum of pay_amount from Orders for the branch)
            $salesQuery = Order::where('branch_id', $branch->id)
                ->whereNotNull('pay_amount');
            if ($shiftId) {
                $salesQuery->where('shift_id', $shiftId);
            }
            $totalSales = $salesQuery->sum('pay_amount');

            // Clients (count of unique users associated with the branch, filtered by orders if shift_id is provided)
            $clientsQuery = User::where('branch_id', $branch->id);
            if ($shiftId) {
                $clientsQuery->whereIn('id', function ($query) use ($branch, $shiftId) {
                    $query->select('user_id')
                        ->from('orders')
                        ->where('branch_id', $branch->id)
                        ->where('shift_id', $shiftId);
                });
            }
            $clients = $clientsQuery->distinct()->count();

            // Printed Photos (count of photos where status is 'printed')
            $photoQuery = Photo::where('branch_id', $branch->id)
                ->where('status', 'printed');
            if ($shiftId) {
                $photoQuery->whereIn('id', function ($query) use ($branch, $shiftId) {
                    $query->select('photo_id')
                        ->from('order_items')
                        ->join('orders', 'order_items.order_id', '=', 'orders.id')
                        ->where('orders.branch_id', $branch->id)
                        ->where('orders.shift_id', $shiftId);
                });
            }
            $printedPhotos = $photoQuery->count();

            // Selected Photos (count of photo_selected records)
            $selectedPhotoQuery = PhotoSelected::where('branch_id', $branch->id)
                ->where('status', 'sold');
            if ($shiftId) {
                $selectedPhotoQuery->whereIn('id', function ($query) use ($branch, $shiftId) {
                    $query->select('selected_photo_id')
                        ->from('order_items')
                        ->join('orders', 'order_items.order_id', '=', 'orders.id')
                        ->where('orders.branch_id', $branch->id)
                        ->where('orders.shift_id', $shiftId);
                });
            }
            $selectedPhotos = $selectedPhotoQuery->count();

            // Active Booths (count of active branches)
            $activeBooths = Branch::where('is_active', true)->count();

            // Employees (fetch staff data with photo and customer counts)
            $employees = $branch->staff()->withCount([
                'uploadedPhotos as total_photos' => function ($query) use ($shiftId) {
                    if ($shiftId) {
                        $query->whereIn('id', function ($q) use ($branch, $shiftId) {
                            $q->select('photo_id')
                                ->from('order_items')
                                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                                ->where('orders.branch_id', $branch->id)
                                ->where('orders.shift_id', $shiftId);
                        });
                    }
                },
                'registeredUsers as total_customers'
            ])->get();

            // Sales Data (monthly sales for the line graph, last 12 months)
            $salesDataQuery = Order::where('branch_id', $branch->id)
                ->where('status', 'completed')
                ->whereBetween('created_at', [now()->subYear(), now()]);
            if ($shiftId) {
                $salesDataQuery->where('shift_id', $shiftId);
            }
            $salesData = $salesDataQuery
                ->selectRaw('DATE_FORMAT(created_at, "%b") as month, SUM(pay_amount) as value')
                ->groupBy(DB::raw('DATE_FORMAT(created_at, "%b")'))
                ->orderBy(DB::raw('MIN(created_at)'))
                ->get()
                ->map(function ($item) {
                    return ['month' => $item->month, 'value' => $item->value ?? 0];
                });

            // Photo Distribution (percentages for doughnut chart)
            $photoCountsQuery = Photo::where('branch_id', $branch->id);
            if ($shiftId) {
                $photoCountsQuery->whereIn('id', function ($query) use ($branch, $shiftId) {
                    $query->select('photo_id')
                        ->from('order_items')
                        ->join('orders', 'order_items.order_id', '=', 'orders.id')
                        ->where('orders.branch_id', $branch->id)
                        ->where('orders.shift_id', $shiftId);
                });
            }
            $photoCounts = $photoCountsQuery
                ->groupBy('status')
                ->select('status', DB::raw('count(*) as count'))
                ->get()
                ->keyBy('status');

            $totalPhotos = $photoCounts->sum('count');
            $photoDistribution = [
                'sold' => $totalPhotos > 0 ? round(($photoCounts->get('sold', (object)['count' => 0])->count / $totalPhotos) * 100) : 0,
                'captured' => $totalPhotos > 0 ? round(($photoCounts->get('captured', (object)['count' => 0])->count / $totalPhotos) * 100) : 0,
                'printed' => $totalPhotos > 0 ? round(($photoCounts->get('printed', (object)['count' => 0])->count / $totalPhotos) * 100) : 0,
            ];

            // Prepare dashboard data
            $dashboardData = [
                'branch' => $branch->name,
                'total_sales' => number_format($totalSales, 2) . ' EGP',
                'clients' => $clients,
                'printed_photos' => $printedPhotos,
                'selected_photos' => $selectedPhotos,
                'active_booths' => $activeBooths,
                'employees' => StaffResource::collection($employees),
                'date' => now()->format('d-m-Y'),
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

    public function index(Branch $branch, Request $request)
    {
        try {
            // Validate optional shift_id parameter
            $shiftId = $request->query('shift_id', null);

            $clientsQuery = User::where('branch_id', $branch->id);
            if ($shiftId) {
                $clientsQuery->whereIn('id', function ($query) use ($branch, $shiftId) {
                    $query->select('user_id')
                        ->from('orders')
                        ->where('branch_id', $branch->id)
                        ->where('shift_id', $shiftId);
                });
            }

            $clients = $clientsQuery->get();
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

            $invoices = Order::where('branch_id', $branch->id)
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->get();
            $invoiceResources = InvoiceResource::collection($invoices);

            return $this->successResponse($invoiceResources, 'Invoices retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve invoices.', 500, $e->getMessage());
        }
    }

    public function photos(Branch $branch, Request $request)
    {
        try {
            // Validate optional shift_id parameter
            $shiftId = $request->query('shift_id', null);

            // Photo Statistics
            $photoQuery = Photo::where('branch_id', $branch->id);
            if ($shiftId) {
                $photoQuery->whereHas('orderItems.order', function ($query) use ($shiftId) {
                    $query->where('shift_id', $shiftId);
                });
            }
            $photoCounts = $photoQuery
                ->groupBy('status')
                ->select('status', DB::raw('count(*) as count'))
                ->get()
                ->keyBy('status');

            $totalPhotos = $photoCounts->sum('count');
            $photoStats = [
                'sold' => $photoCounts->get('sold', (object)['count' => 0])->count,
                'captured' => $photoCounts->get('captured', (object)['count' => 0])->count,
                'printed' => $photoCounts->get('printed', (object)['count' => 0])->count,
                'total' => $totalPhotos,
                'distribution' => [
                    'sold' => $totalPhotos > 0 ? round(($photoCounts->get('sold', (object)['count' => 0])->count / $totalPhotos) * 100) : 0,
                    'captured' => $totalPhotos > 0 ? round(($photoCounts->get('captured', (object)['count' => 0])->count / $totalPhotos) * 100) : 0,
                    'printed' => $totalPhotos > 0 ? round(($photoCounts->get('printed', (object)['count' => 0])->count / $totalPhotos) * 100) : 0,
                ],
            ];

            // Selected Photo Statistics
            $selectedPhotoQuery = PhotoSelected::where('branch_id', $branch->id);
            if ($shiftId) {
                $selectedPhotoQuery->whereHas('originalPhoto.orderItems.order', function ($query) use ($shiftId) {
                    $query->where('shift_id', $shiftId);
                });
            }
            $selectedPhotoCounts = $selectedPhotoQuery
                ->groupBy('status')
                ->select('status', DB::raw('count(*) as count'))
                ->get()
                ->keyBy('status');

            $totalSelectedPhotos = $selectedPhotoCounts->sum('count');
            $selectedPhotoStats = [
                'sold' => $selectedPhotoCounts->get('sold', (object)['count' => 0])->count,
                'total' => $totalSelectedPhotos,
                'distribution' => [
                    'sold' => $totalSelectedPhotos > 0 ? round(($selectedPhotoCounts->get('sold', (object)['count' => 0])->count / $totalSelectedPhotos) * 100) : 0,
                ],
            ];

            $photoData = [
                'branch' => $branch->name,
                'photos' => $photoStats,
                'selected_photos' => $selectedPhotoStats,
                'date' => now()->format('d-m-Y'),
            ];

            return $this->successResponse($photoData, 'Photo statistics retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve photo statistics.', 500, $e->getMessage());
        }
    }




    public function paymentsDashboard(Request $request)
    {
        try {
            // Validate authenticated user and retrieve branch_id
            $user = auth('branch-manager')->user();
            if (!$user || !$user->branch_id) {
                return $this->errorResponse('Unauthorized or branch not assigned.', 401);
            }
            $branch = Branch::findOrFail($user->branch_id);

            // Filters
            $shiftId = $request->query('shift_id');
            $staffId = $request->query('staff_id');
            $fromDate = $request->query('from_date');
            $toDate = $request->query('to_date');

            if ($fromDate && !$toDate) {
                // Only from_date provided: use that day only
                $startDate = \Carbon\Carbon::parse($fromDate)->startOfDay();
                $endDate = \Carbon\Carbon::parse($fromDate)->endOfDay();
            } else {
                $startDate = $fromDate ? \Carbon\Carbon::parse($fromDate)->startOfDay() : now()->subMonths(6)->startOfMonth();
                $endDate = $toDate ? \Carbon\Carbon::parse($toDate)->endOfDay() : now()->endOfMonth();
            }

            // First Section: Monthly Paid Amounts
            $query = Order::where('branch_id', $branch->id)
                ->whereNotNull('pay_amount')
                ->whereBetween('created_at', [$startDate, $endDate]);

            if ($shiftId) {
                $query->where('shift_id', $shiftId);
            }
            if ($staffId) {
                $query->where('processed_by', $staffId);
            }

            $salesData = $query
                ->selectRaw('DATE_FORMAT(created_at, "%b %Y") as month, SUM(pay_amount) as value')
                ->groupBy(DB::raw('DATE_FORMAT(created_at, "%b %Y")'))
                ->orderBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
                ->get()
                ->map(function ($item) {
                    return ['month' => $item->month, 'value' => $item->value ?? 0];
                });

            // Ensure all months are included
            $months = [];
            $current = $startDate->copy();
            while ($current <= $endDate) {
                $months[$current->format('M Y')] = 0;
                $current->addMonth();
            }

            foreach ($salesData as $data) {
                $months[$data['month']] = $data['value'];
            }

            $monthlyPayments = array_values(array_map(function ($month, $value) {
                return ['month' => $month, 'value' => $value];
            }, array_keys($months), array_values($months)));

            // Second Section: Percentage of Orders with send_type 'send' and 'print'
            $totalOrders = $query->count();
            $sendPrintOrders = (clone $query)->whereIn('send_type', ['send', 'print', 'print_and_send'])->count();
            $sendPrintPercentage = $totalOrders > 0 ? round(($sendPrintOrders / $totalOrders) * 100) : 0;

            $distribution = [
                'send_print' => $sendPrintPercentage,
                'other' => 100 - $sendPrintPercentage,
            ];

            // Third Section: Client Data from Users using barcode join (excluding package and payment method, no receipt)
            $clientsQuery = User::where('users.branch_id', $branch->id)->orwhere('users.branch_id', null);
            if ($shiftId || $staffId) {
                $clientsQuery = $clientsQuery->join('orders', 'users.barcode', '=', 'orders.barcode_prefix');
                if ($shiftId) {
                    $clientsQuery->where('orders.shift_id', $shiftId);
                }
                if ($staffId) {
                    $clientsQuery->where('orders.processed_by', $staffId);
                }
                $clientsQuery->whereBetween('orders.created_at', [$startDate, $endDate])
                    ->select('users.id', 'users.barcode', 'users.phone_number', 'users.branch_id')
                    ->distinct();
            }
            $clients = $clientsQuery->select('users.id', 'users.barcode', 'users.phone_number', 'users.branch_id')->get();

            // Add total_paid for each client
            $clients = $clients->map(function ($client) use ($startDate, $endDate, $shiftId, $staffId, $branch) {
                $orderQuery = \App\Models\Order::where('barcode_prefix', $client->barcode)
                    ->where('branch_id', $branch->id)
                    ->whereBetween('created_at', [$startDate, $endDate]);
                if ($shiftId) {
                    $orderQuery->where('shift_id', $shiftId);
                }
                if ($staffId) {
                    $orderQuery->where('processed_by', $staffId);
                }
                $totalPaid = $orderQuery->sum('pay_amount');
                $client->total_paid = $totalPaid;
                return $client;
            });

            // Fourth Section: Photo Statistics based on send_type
            $photoQuery = Photo::where('photos.branch_id', $branch->id)
                ->whereBetween('photos.created_at', [$startDate, $endDate]);
            if ($staffId) {
                $photoQuery->where('uploaded_by', $staffId);
            }
            if ($shiftId) {
                $photoQuery->whereIn('photos.id', function ($query) use ($branch, $shiftId) {
                    $query->select('order_items.photo_id')
                        ->from('order_items')
                        ->join('orders', 'order_items.order_id', '=', 'orders.id')
                        ->where('orders.branch_id', $branch->id)
                        ->where('orders.shift_id', $shiftId);
                });
            }
            $photoCountsBySendType = $photoQuery->join('order_items', 'photos.id', '=', 'order_items.photo_id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->select('orders.send_type', DB::raw('count(*) as count'))
                ->groupBy('orders.send_type')
                ->get()
                ->keyBy('send_type');

            $totalPhotos = $photoCountsBySendType->sum('count');
            $photoStats = [
                'print' => $photoCountsBySendType->get('print', (object)['count' => 0])->count,
                'send' => $photoCountsBySendType->get('send', (object)['count' => 0])->count,
                'print_and_send' => $photoCountsBySendType->get('print_and_send', (object)['count' => 0])->count,
                'total' => $totalPhotos,
                'distribution' => [
                    'print' => $totalPhotos > 0 ? round(($photoCountsBySendType->get('print', (object)['count' => 0])->count / $totalPhotos) * 100) : 0,
                    'send' => $totalPhotos > 0 ? round(($photoCountsBySendType->get('send', (object)['count' => 0])->count / $totalPhotos) * 100) : 0,
                    'print_and_send' => $totalPhotos > 0 ? round(($photoCountsBySendType->get('print_and_send', (object)['count' => 0])->count / $totalPhotos) * 100) : 0,
                ],
            ];

            $selectedPhotoQuery = PhotoSelected::where('branch_id', $branch->id)
                ->where('status', 'sold')
                ->whereBetween('created_at', [$startDate, $endDate]);
            if ($staffId) {
                $selectedPhotoQuery->where('uploaded_by', $staffId);
            }
            if ($shiftId) {
                $selectedPhotoQuery->whereIn('id', function ($query) use ($branch, $shiftId) {
                    $query->select('selected_photo_id')
                        ->from('order_items')
                        ->join('orders', 'order_items.order_id', '=', 'orders.id')
                        ->where('orders.branch_id', $branch->id)
                        ->where('orders.shift_id', $shiftId);
                });
            }
            $selectedPhotoCounts = $selectedPhotoQuery
                ->groupBy('status')
                ->select('status', DB::raw('count(*) as count'))
                ->get()
                ->keyBy('status');

            $totalSelectedPhotos = $selectedPhotoCounts->sum('count');
            $selectedPhotoStats = [
                'sold' => $selectedPhotoCounts->get('sold', (object)['count' => 0])->count,
                'total' => $totalSelectedPhotos,
                'distribution' => [
                    'sold' => $totalSelectedPhotos > 0 ? round(($selectedPhotoCounts->get('sold', (object)['count' => 0])->count / $totalSelectedPhotos) * 100) : 0,
                ],
            ];

            $dashboardData = [
                'branch' => $branch->name,
                'monthly_payments' => $monthlyPayments,
                'distribution' => $distribution,
                'clients' => $clients,
                'photo_stats' => $photoStats,
                'selected_photo_stats' => $selectedPhotoStats,
                'date' => now()->format('d-m-Y H:i'),
            ];

            return $this->successResponse($dashboardData, 'Payments dashboard data retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve payments dashboard data.', 500, $e->getMessage());
        }
    }



    public function paymentsDashboardByBranchAndShift($branchId, Request $request)
    {
        try {
            // Validate branch_id and shift_id
            $branch = Branch::findOrFail($branchId);
            $shiftId = $request->query('shift_id');

            if ($shiftId) {
                $shift = Shift::where('id', $shiftId)->where('branch_id', $branch->id)->first();
                if (!$shift) {
                    return $this->errorResponse('The specified shift does not belong to the selected branch.', 400);
                }
            } else {
                $shiftId = null; // Use null for global data within the branch
            }

            // First Section: Monthly Paid Amounts
            $startDate = now()->subMonths(6)->startOfMonth(); // February 2025
            $endDate = now()->endOfMonth(); // August 2025

            $query = Order::where('branch_id', $branch->id)
                ->whereNotNull('pay_amount')
                ->whereBetween('created_at', [$startDate, $endDate]);

            if ($shiftId) {
                $query->where('shift_id', $shiftId);
            }

            $salesData = $query
                ->selectRaw('DATE_FORMAT(created_at, "%b %Y") as month, SUM(pay_amount) as value')
                ->groupBy(DB::raw('DATE_FORMAT(created_at, "%b %Y")'))
                ->orderBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
                ->get()
                ->map(function ($item) {
                    return ['month' => $item->month, 'value' => $item->value ?? 0];
                });

            // Ensure all months are included
            $months = [];
            $current = $startDate->copy();
            while ($current <= $endDate) {
                $months[$current->format('M Y')] = 0;
                $current->addMonth();
            }

            foreach ($salesData as $data) {
                $months[$data['month']] = $data['value'];
            }

            $monthlyPayments = array_values(array_map(function ($month, $value) {
                return ['month' => $month, 'value' => $value];
            }, array_keys($months), array_values($months)));

            // Second Section: Percentage of Orders with send_type 'send' and 'print'
            $totalOrders = $query->count();
            $sendPrintOrders = $query->whereIn('send_type', ['send', 'print', 'print_and_send'])->count();
            $sendPrintPercentage = $totalOrders > 0 ? round(($sendPrintOrders / $totalOrders) * 100) : 0;

            $distribution = [
                'send_print' => $sendPrintPercentage,
                'other' => 100 - $sendPrintPercentage,
            ];

            // Third Section: Staff Data from Staff model
            $staffQuery = Staff::where('branch_id', $branch->id);
            if ($shiftId) {
                $staffQuery->whereHas('processedOrders', function ($query) use ($shiftId) {
                    $query->where('shift_id', $shiftId);
                });
            }

            $staff = $staffQuery->select('id', 'name', 'role', 'phone', 'status')->get();

            // Fourth Section: Photo Statistics based on send_type
            $photoQuery = Photo::where('photos.branch_id', $branch->id);
            if ($shiftId) {
                $photoQuery->whereIn('photos.id', function ($query) use ($branch, $shiftId) {
                    $query->select('order_items.photo_id')
                        ->from('order_items')
                        ->join('orders', 'order_items.order_id', '=', 'orders.id')
                        ->where('orders.branch_id', $branch->id)
                        ->where('orders.shift_id', $shiftId);
                });
            }
            $photoCountsBySendType = $photoQuery->join('order_items', 'photos.id', '=', 'order_items.photo_id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->select('orders.send_type', DB::raw('count(*) as count'))
                ->groupBy('orders.send_type')
                ->get()
                ->keyBy('send_type');

            $totalPhotos = $photoCountsBySendType->sum('count');
            $photoStats = [
                'print' => $photoCountsBySendType->get('print', (object)['count' => 0])->count,
                'send' => $photoCountsBySendType->get('send', (object)['count' => 0])->count,
                'print_and_send' => $photoCountsBySendType->get('print_and_send', (object)['count' => 0])->count,
                'total' => $totalPhotos,
                'distribution' => [
                    'print' => $totalPhotos > 0 ? round(($photoCountsBySendType->get('print', (object)['count' => 0])->count / $totalPhotos) * 100) : 0,
                    'send' => $totalPhotos > 0 ? round(($photoCountsBySendType->get('send', (object)['count' => 0])->count / $totalPhotos) * 100) : 0,
                    'print_and_send' => $totalPhotos > 0 ? round(($photoCountsBySendType->get('print_and_send', (object)['count' => 0])->count / $totalPhotos) * 100) : 0,
                ],
            ];

            $selectedPhotoQuery = PhotoSelected::where('branch_id', $branch->id)
                ->where('status', 'sold');
            if ($shiftId) {
                $selectedPhotoQuery->whereIn('id', function ($query) use ($branch, $shiftId) {
                    $query->select('selected_photo_id')
                        ->from('order_items')
                        ->join('orders', 'order_items.order_id', '=', 'orders.id')
                        ->where('orders.branch_id', $branch->id)
                        ->where('orders.shift_id', $shiftId);
                });
            }
            $selectedPhotoCounts = $selectedPhotoQuery
                ->groupBy('status')
                ->select('status', DB::raw('count(*) as count'))
                ->get()
                ->keyBy('status');

            $totalSelectedPhotos = $selectedPhotoCounts->sum('count');
            $selectedPhotoStats = [
                'sold' => $selectedPhotoCounts->get('sold', (object)['count' => 0])->count,
                'total' => $totalSelectedPhotos,
                'distribution' => [
                    'sold' => $totalSelectedPhotos > 0 ? round(($selectedPhotoCounts->get('sold', (object)['count' => 0])->count / $totalSelectedPhotos) * 100) : 0,
                ],
            ];

            $dashboardData = [
                'branch' => $branch->name,
                'monthly_payments' => $monthlyPayments,
                'distribution' => $distribution,
                'staff' => $staff,
                'photo_stats' => $photoStats,
                'selected_photo_stats' => $selectedPhotoStats,
                'date' => now()->format('d-m-Y H:i'),
            ];

            return $this->successResponse($dashboardData, 'Payments dashboard data retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve payments dashboard data.', 500, $e->getMessage());
        }
    }
}
