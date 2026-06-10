<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Vehicle;
use App\Policies\VehiclePolicy;
use App\Services\Sms\InfobipSmsDriver;
use App\Services\Sms\LogSmsDriver;
use App\Services\Sms\SmsDriver;
use App\Services\Sms\SmsManager;
use Illuminate\Support\Facades\Gate;
use App\Observers\VehicleObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsDriver::class, function (): SmsDriver {
            return match (config('services.sms.driver', 'log')) {
                'log' => new LogSmsDriver,
                'infobip' => new InfobipSmsDriver(
                    baseUrl: (string) config('services.sms.base_url'),
                    apiKey: (string) config('services.sms.api_key'),
                    sender: (string) config('services.sms.sender_id'),
                ),
                default => throw new InvalidArgumentException('Unsupported SMS driver'),
            };
        });

        $this->app->singleton(SmsManager::class);
    }

    public function boot(): void
    {
        // Rate limiting endpoints publics API
        RateLimiter::for('api-public', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('api-search', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('api-auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
        Vehicle::observe(VehicleObserver::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
    }
}
// Note: see boot() method
