<?php

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\FrameController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\StickerController;
// use App\Http\Controllers\Api\PaymentController as PaymentOffLineController; 

use App\Http\Controllers\Api\PhotoLookupController;
use App\Http\Controllers\Api\BranchManagerController;
use App\Http\Controllers\Api\UserInterfaceController;
use App\Http\Controllers\Api\PaymentOffLineController;
use App\Http\Controllers\Api\OnlineDashboard\AdminController;
use App\Http\Controllers\Api\OnlineDashboard\PaymentController;
use App\Http\Controllers\Api\OnlineDashboard\EmployeeController;
use App\Http\Controllers\Api\OnlineDashboard\HomePageController;
use App\Http\Controllers\Api\OrderController as OfflineDashboardOrderController;
use App\Http\Controllers\Api\OnlineDashboard\BranchController as OnlineDashboardBranchController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('user-interface')->group(function () {
    // Route::get('get-photos', [UserInterfaceController::class, 'getUserPhotos']);
    Route::post('add-photo', [UserInterfaceController::class, 'addUserPhoto']);
    Route::post('select-and-clone-photos', [PhotoLookupController::class, 'selectAndClonePhotos']);
    Route::post('selected-photos/{selected}/update', [PhotoLookupController::class, 'updateSelectedPhoto']);
    Route::get('selected-photos/by-barcode', [PhotoLookupController::class, 'getSelectedPhotosByBarcode']);
    Route::post('orders/create-from-selected', [UserInterfaceController::class, 'createOrderFromSelected']);
    Route::get('branches/{branchId}/packages', [UserInterfaceController::class, 'getPackages']);
    Route::get('photos/ready-to-print', [UserInterfaceController::class, 'getPhotosReadyToPrint'])->name('user.photos.ready');
    Route::post('assign/user-qr', [UserInterfaceController::class, 'createUserDependOnQrCode']);
    Route::get('get-photos', [PhotoLookupController::class, 'getPhotosByBarcodePrefix']);
    Route::post('reset-barcodes', [UserInterfaceController::class, 'resetBarCodes']);
    Route::get('/frames', [FrameController::class, 'index']);
    Route::get('/stickers', [StickerController::class, 'index']);
});

// Public Routes
Route::post('staff/login', [StaffController::class, 'login']);
Route::post('check-barcode', [UserController::class, 'checkUserByBarcode']);

// Branch Manager Public Routes
Route::prefix('branch-manager')->group(function () {
    Route::post('login', [BranchManagerController::class, 'login']);
    Route::post('register', [BranchManagerController::class, 'register']);
});

// Protected Routes 
Route::prefix('staff')->group(function () {
    // Staff Routes
    Route::get('/', [StaffController::class, 'index']);
    Route::post('/', [StaffController::class, 'store']);
    Route::get('staffShow/{staff}', [StaffController::class, 'show']);
    Route::post('staffUpdate/{staff}', [StaffController::class, 'update']);
    Route::delete('staffDelete/{staff}', [StaffController::class, 'destroy']);
    Route::post('/{staff}/change-password', [StaffController::class, 'changePassword']);
    Route::post('/logout', [StaffController::class, 'logout']);
    
    // Branch Routes
    Route::get('branches', [BranchController::class, 'index']);
    Route::post('branches', [BranchController::class, 'store']);
    Route::get('branches/{branch}', [BranchController::class, 'show']);
    Route::put('branches/{branch}', [BranchController::class, 'update']);
    Route::get('/branches/phone-numbers', [UserInterfaceController::class, 'getAllPhoneNumbers']);
    Route::delete('branches/{branch}', [BranchController::class, 'destroy']);
    
    // Photo Routes
    Route::get('photos/offline-dashboard', [PhotoController::class, 'offlineDashboard']);
    Route::get('photos/staff', [PhotoController::class, 'staffPhotos']);
    Route::post('photos/upload', [PhotoController::class, 'uploadPhotos']);
    Route::post('photos/{photo}/sync-status', [PhotoController::class, 'updateSyncStatus']);
    Route::delete('photos/{photo}', [PhotoController::class, 'destroy']);
    Route::get('photos/ready-to-print', [PhotoController::class, 'getReadyToPrintBarcodes']);
    Route::get('photos/ready-to-print/{barcodePrefix}', [PhotoController::class, 'getReadyToPrintPhotosByBarcode']);
    
    // Invoice routes
    Route::post('invoices/{barcodePrefix}', [InvoiceController::class, 'store']);
    Route::get('invoices/active', [InvoiceController::class, 'index']);
    Route::get('invoices/{barcodePrefix}', [InvoiceController::class, 'show']);
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update']);
});

Route::prefix('branch-manager')->group(function () {
    Route::post('/photos/upload', [BranchManagerController::class, 'uploadMultiplePhotos']);

    // Invoice routes for branch manager
    Route::post('invoices/{barcodePrefix}', [InvoiceController::class, 'store']);
    Route::post('/invoices/update-total/{barcodePrefix}', [InvoiceController::class, 'updateTotalAmount']);
});
// Branch Manager Protected Routes
Route::middleware(['auth:branch-manager'])->prefix('branch-manager')->group(function () {
    Route::post('logout', [BranchManagerController::class, 'logout']);
    Route::get('branch', [BranchManagerController::class, 'getBranchInfo']);
    Route::get('staff', [BranchManagerController::class, 'getBranchStaff']);
    Route::get('staff/uploaded-barcodes', [PhotoController::class, 'getStaffUploadedBarcodes']);
    Route::get('photos/barcode/{barcodePrefix}', [PhotoController::class, 'getPhotosByBarcodePrefix']);
    Route::get('photos/ready-to-print', [PhotoController::class, 'getReadyToPrintBarcodes']);
    Route::get('photos/ready-to-print/{barcodePrefix}', [PhotoController::class, 'getReadyToPrintPhotosByBarcode']);
    Route::get('photos/printed', [PhotoController::class, 'getPrintedBarcodes']);
    Route::get('photos/printed/{barcodePrefix}', [PhotoController::class, 'getPrintedPhotosByBarcode']);
    Route::get('photos/selected/{prefix}', [PhotoController::class, 'getSelectedPhotosByPrefix']);


    Route::get('orders/by-send-type', [PhotoController::class, 'getOrdersBySendType']);

    // Invoice routes for branch manager
    //Route::post('invoices/{barcodePrefix}', [InvoiceController::class, 'store']);
    Route::get('invoices/active', [InvoiceController::class, 'index']);
    Route::get('invoices/{barcodePrefix}', [InvoiceController::class, 'show']);
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update']);

    Route::prefix('payments')->group(function () {
        // Route::get('/{branch}', [PaymentOffLineController::class, 'show']);
        Route::get('/clients/{branch}', [PaymentOffLineController::class, 'index']);
        Route::get('/invoices/{branch}/{user}', [PaymentOffLineController::class, 'invoices']);

        Route::get('/dashboard', [PaymentOffLineController::class, 'paymentsDashboard']);
    });



    // Orders overview for offline dashboard
    Route::get('orders', [OfflineDashboardOrderController::class, 'index']);
    Route::post('orders/upload-and-create', [OfflineDashboardOrderController::class, 'uploadPhotosAndCreate']);
    Route::post('/orders_submit/{orderId}', [OfflineDashboardOrderController::class, 'submitOrder']);
    Route::get('orders/{prefix}/photos', [OfflineDashboardOrderController::class, 'getOrderPhotos']);
    //Shift Logic

    Route::get('shifts', [ShiftController::class, 'index']);
    Route::post('shifts', [ShiftController::class, 'create']);
    // Route::get('shifts/{shift}', [ShiftController::class, 'show']);
    Route::put('shifts/{shift}', [ShiftController::class, 'update']);
    Route::delete('shifts/{shift}', [ShiftController::class, 'delete']);
    Route::post('photographers', [ShiftController::class, 'addPhotographer']);
    Route::post('photographers/{id}', [ShiftController::class, 'updatePhotographer']);
    Route::delete('photographers/{id}', [ShiftController::class, 'deletePhotographer']);
    Route::post('/users/generate-barcodes', [ShiftController::class, 'generateBarcodes']);
    Route::get('/users/barcodes', [ShiftController::class, 'getAllBarcodes']);

    // Endpoint to get last sync time
    Route::get('/sync/last', [ShiftController::class, 'getLastSyncTime']);

    // Endpoint to filter sync jobs
    Route::get('/sync/filter', [ShiftController::class, 'filterSyncJobs']);


    // Frames
    Route::post('/frames', [FrameController::class, 'store']);
    Route::get('/frames', [FrameController::class, 'index']);
    Route::get('/frames/{id}', [FrameController::class, 'show']);
    
    // Bulk delete frames and stickers
    Route::post('/frames/delete-many', [FrameController::class, 'destroyMany']);
    Route::post('/stickers/delete-many', [StickerController::class, 'destroyMany']);
    
    // Stickers
    Route::post('/stickers', [StickerController::class, 'store']);
    Route::get('/stickers/{id}', [StickerController::class, 'show']);
});


/*
|--------------------------------------------------------------------------
| Online Dashboard Routes
|--------------------------------------------------------------------------
*/
Route::prefix('onlinedashboard')->group(function () {
    Route::prefix('admin')->group(function () {
        // Public routes
        Route::post('register', [AdminController::class, 'register']);
        Route::post('login', [AdminController::class, 'login']);

        // Protected routes
        Route::middleware(['auth:admin'])->group(function () {
            Route::post('logout', [AdminController::class, 'logout']);

            // Employee management routes
            Route::prefix('employees')->group(function () {
                Route::get('/', [EmployeeController::class, 'getEmployees']);
                Route::get('/photographers', [EmployeeController::class, 'getPhotographers']);
                Route::post('/', [EmployeeController::class, 'addEmployee']);
                Route::post('/photographer', [EmployeeController::class, 'addPhotographer']);
                Route::put('/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus']);
                Route::put('/{employee}', [EmployeeController::class, 'updateEmployee']);
                Route::put('/photographer/{photographer}', [EmployeeController::class, 'updatePhotographer']);
                Route::delete('/{employee}', [EmployeeController::class, 'destroy']);
            });
        });
        // Branchs managment routes 
        Route::prefix('branches')->group(function () {
            Route::get('/', [OnlineDashboardBranchController::class, 'index']);
            Route::post('/', [OnlineDashboardBranchController::class, 'store']);
            Route::get('/{branch}', [OnlineDashboardBranchController::class, 'show']);
            Route::put('/{branch}', [OnlineDashboardBranchController::class, 'update']);
            Route::delete('/{branch}', [OnlineDashboardBranchController::class, 'destroy']);
            Route::get('/unassigned-employees', [OnlineDashboardBranchController::class, 'getUnassignedEmployees']);
            Route::get('/unassigned-photographers', [OnlineDashboardBranchController::class, 'getUnassignedPhotographers']);
        });
        Route::prefix('payments')->group(function () {
            Route::get('/{branch}', [PaymentController::class, 'show']);
            Route::get('/clients/{branch}', [PaymentController::class, 'index']);
            Route::get('/invoices/{branch}/{user}', [PaymentController::class, 'invoices']);
        });
    });

    // Homepage dashboard stats - protected by admin auth
    Route::get('homepage/stats', [HomePageController::class, 'getDashboardStats'])
        ->middleware(['auth:admin']);
    Route::prefix('payments')->group(function () {
        Route::get('/dashboard/{branchId}', [PaymentOffLineController::class, 'paymentsDashboardByBranchAndShift']);
    });
});

Route::middleware(['auth:branch-manager'])->group(function () {
    Route::get('branch-manager/export', [BranchManagerController::class, 'export']);
});
