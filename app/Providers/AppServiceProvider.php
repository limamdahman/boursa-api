<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Vehicle;
use App\Policies\VehiclePolicy;
use App\Services\Sms\LogSmsDriver;
use App\Services\Sms\SmsDriver;
use App\Services\Sms\SmsManager;
use Illuminate\Support\Facades\Gate;
use App\Observers\VehicleObserver;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsDriver::class, function (): SmsDriver {
            return match (config('services.sms.driver', 'log')) {
                'log' => new LogSmsDriver,
                default => throw new InvalidArgumentException('Unsupported SMS driver'),
            };
        });

        $this->app->singleton(SmsManager::class);
    }

    public function boot(): void
    {
        Vehicle::observe(VehicleObserver::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
    }
}
