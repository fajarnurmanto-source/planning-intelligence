<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Pi\Development\SemanticCandidateProvider;
use App\Services\Pi\Development\CloudSemanticCandidateProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SemanticCandidateProvider::class, CloudSemanticCandidateProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
