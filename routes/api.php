<?php

use App\Http\Controllers\Api\PhotoController;
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

// Public routes
Route::post('/users/validate-access', [UserController::class, 'validateAccess']);
Route::get('/photos', [PhotoController::class, 'getPhotos']);

// Staff-only routes
Route::middleware('auth:staff')->group(function () {
    Route::post('/users/register', [UserController::class, 'register']);
    Route::post('/photos/upload', [PhotoController::class, 'upload']);
}); 