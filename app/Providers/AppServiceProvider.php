<?php

namespace App\Providers;

use App\Repositories\Photo\PhotoRepository;
use App\Repositories\Photo\PhotoRepositoryInterface;
use App\Repositories\User\UserRepository;
use App\Repositories\User\UserRepositoryInterface;
use App\Services\Barcode\BarcodeService;
use App\Services\Barcode\BarcodeServiceInterface;
use App\Services\Photo\PhotoService;
use App\Services\Photo\PhotoServiceInterface;
use App\Services\User\UserService;
use App\Services\User\UserServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register repositories
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(PhotoRepositoryInterface::class, PhotoRepository::class);
        
        // Register services
        $this->app->bind(BarcodeServiceInterface::class, BarcodeService::class);
        $this->app->bind(UserServiceInterface::class, UserService::class);
        $this->app->bind(PhotoServiceInterface::class, PhotoService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
