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

        // Override Anthropic key from DB Settings (Settings → AI UI).
        try {
            if (\Schema::hasTable('settings')) {
                $key = \App\Models\Setting::getValue('anthropic_api_key');
                if ($key) config(['services.anthropic.api_key' => $key]);
            }
        } catch (\Throwable) {}

        // Override S3 disk config from DB Settings (Settings → Storage UI).
        // Wrapped in try because boot runs before migrations on a fresh install.
        try {
            if (\Schema::hasTable('settings')) {
                $get = fn(string $k) => \App\Models\Setting::getValue($k);
                $key      = $get('s3_key');
                $secret   = $get('s3_secret');
                $bucket   = $get('s3_bucket');
                $region   = $get('s3_region');
                $endpoint = $get('s3_endpoint');
                if ($key)      config(['filesystems.disks.s3.key'      => $key]);
                if ($secret)   config(['filesystems.disks.s3.secret'   => $secret]);
                if ($bucket)   config(['filesystems.disks.s3.bucket'   => $bucket]);
                // Region is REQUIRED by the AWS SDK even for non-AWS S3-compatible
                // services (Contabo, MinIO, etc). Default to "us-east-1" so the
                // SDK boots; the actual endpoint determines routing.
                config(['filesystems.disks.s3.region' => $region ?: 'us-east-1']);
                if ($endpoint) {
                    config(['filesystems.disks.s3.endpoint' => $endpoint]);
                    config(['filesystems.disks.s3.use_path_style_endpoint' => true]);
                }
            }
        } catch (\Throwable) { /* ignore */ }
    }
}
