<?php

namespace App\Providers;

use App\Services\ChecklistService;
use App\Validators\GermanyCountryValidator;
use App\Validators\UsaCountryValidator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * To add a new country, implement CountryValidatorInterface and add it here.
     * No other files need to change.
     */
    public function register(): void
    {
        $this->app->singleton(ChecklistService::class, function () {
            return new ChecklistService([
                'USA'     => new UsaCountryValidator(),
                'Germany' => new GermanyCountryValidator(),
                // 'France' => new FranceCountryValidator(),  <-- add new countries here
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
