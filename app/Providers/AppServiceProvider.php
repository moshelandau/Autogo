<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\RentalPayment::observe(\App\Observers\PaymentBalanceObserver::class);
        \App\Models\Reservation::observe(\App\Observers\ReservationObserver::class);
    }
}
