<?php

use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

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

// Public Routes
Route::post('staff/login', [StaffController::class, 'login']);

// Protected Routes
Route::middleware('staff.auth')->group(function () {
    // Staff Routes
    Route::get('staff', [StaffController::class, 'index']);
    Route::post('staff', [StaffController::class, 'store']);
    Route::get('staffShow/{staff}', [StaffController::class, 'show']);
    Route::post('staffUpdate/{staff}', [StaffController::class, 'update']);
    Route::delete('staffDelete/{staff}', [StaffController::class, 'destroy']);
    Route::post('staff/{staff}/change-password', [StaffController::class, 'changePassword']);
    Route::post('staff/logout', [StaffController::class, 'logout']);

    // Branch Routes
    Route::get('branches', [BranchController::class, 'index']);
    Route::post('branches', [BranchController::class, 'store']);
    Route::get('branches/{branch}', [BranchController::class, 'show']);
    Route::put('branches/{branch}', [BranchController::class, 'update']);
    Route::delete('branches/{branch}', [BranchController::class, 'destroy']);

    // Photo Routes
    Route::get('photos/offline-dashboard', [PhotoController::class, 'offlineDashboard']);
    Route::get('photos/staff', [PhotoController::class, 'staffPhotos']);
    Route::post('photos/upload', [PhotoController::class, 'upload']);
    Route::post('photos/{photo}/sync-status', [PhotoController::class, 'updateSyncStatus']);
    Route::delete('photos/{photo}', [PhotoController::class, 'destroy']);
});
