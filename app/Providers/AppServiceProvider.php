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
        $this->app->bind(BranchManagerServiceInterface::class, BranchManagerService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
