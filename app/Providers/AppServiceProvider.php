<?php

namespace App\Providers;

use App\Services\BranchManager\BranchManagerService;
use App\Services\BranchManager\BranchManagerServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind interfaces to implementations
        $this->app->bind(
            \App\Services\Photo\PhotoServiceInterface::class,
            \App\Services\Photo\PhotoService::class
        );

        $this->app->bind(
            \App\Repositories\Photo\PhotoRepositoryInterface::class,
            \App\Repositories\Photo\PhotoRepository::class
        );

        $this->app->bind(
            \App\Services\User\UserServiceInterface::class,
            \App\Services\User\UserService::class
        );

        $this->app->bind(
            \App\Services\Barcode\BarcodeServiceInterface::class,
            \App\Services\Barcode\BarcodeService::class
        );

        $this->app->bind(
            \App\Repositories\User\UserRepositoryInterface::class,
            \App\Repositories\User\UserRepository::class
        );

        $this->app->bind(
            \App\Repositories\Staff\StaffRepositoryInterface::class,
            \App\Repositories\Staff\StaffRepository::class
        );

        $this->app->bind(BranchManagerServiceInterface::class, BranchManagerService::class);
        
        // Invoice bindings
        $this->app->bind(
            \App\Repositories\Invoice\InvoiceRepositoryInterface::class,
            \App\Repositories\Invoice\InvoiceRepository::class
        );
        
        $this->app->bind(
            \App\Services\Invoice\InvoiceServiceInterface::class,
            \App\Services\Invoice\InvoiceService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
