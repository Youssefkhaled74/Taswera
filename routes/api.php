<?php

use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\BranchManagerController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserInterfaceController;
use App\Http\Controllers\Api\OnlineDashboard\AdminController;
use App\Http\Controllers\Api\OnlineDashboard\EmployeeController;
use App\Http\Controllers\Api\OnlineDashboard\HomePageController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

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
    Route::post('get-photos', [UserInterfaceController::class, 'getUserPhotos']);
    Route::post('add-photo', [UserInterfaceController::class, 'addUserPhoto']);
    Route::post('select-photos', [UserInterfaceController::class, 'selectPhotosForPrinting']);
    Route::get('branches/{branchId}/packages', [UserInterfaceController::class, 'getPackages']);
});

// Public Routes
Route::post('staff/login', [StaffController::class, 'login']);

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

// Branch Manager Protected Routes
Route::middleware(['auth:branch-manager'])->prefix('branch-manager')->group(function () {
    Route::post('logout', [BranchManagerController::class, 'logout']);
    Route::get('branch', [BranchManagerController::class, 'getBranchInfo']);
    Route::get('staff', [BranchManagerController::class, 'getBranchStaff']);
    Route::get('staff/{staffId}/uploaded-barcodes', [PhotoController::class, 'getStaffUploadedBarcodes']);
    Route::get('photos/barcode/{barcodePrefix}', [PhotoController::class, 'getPhotosByBarcodePrefix']);
    Route::get('photos/ready-to-print', [PhotoController::class, 'getReadyToPrintBarcodes']);
    Route::get('photos/ready-to-print/{barcodePrefix}', [PhotoController::class, 'getReadyToPrintPhotosByBarcode']);
    Route::get('photos/printed', [PhotoController::class, 'getPrintedBarcodes']);
    Route::get('photos/printed/{barcodePrefix}', [PhotoController::class, 'getPrintedPhotosByBarcode']);
    
    // Invoice routes for branch manager
    Route::post('invoices/{barcodePrefix}', [InvoiceController::class, 'store']);
    Route::get('invoices/active', [InvoiceController::class, 'index']);
    Route::get('invoices/{barcodePrefix}', [InvoiceController::class, 'show']);
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update']);
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
                Route::post('/', [EmployeeController::class, '  ']);
                Route::post('/photographer', [EmployeeController::class, 'addPhotographer']);
                Route::put('/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus']);
                Route::put('/{employee}', [EmployeeController::class, 'updateEmployee']);
                Route::put('/photographer/{photographer}', [EmployeeController::class, 'updatePhotographer']);
                Route::delete('/{employee}', [EmployeeController::class, 'destroy']);
            });
        });
    });

    // Homepage dashboard stats - protected by admin auth
    Route::get('homepage/stats', [HomePageController::class, 'getDashboardStats'])
        ->middleware(['auth:admin']);
});
