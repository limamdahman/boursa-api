<?php
declare(strict_types=1);
namespace App\Filament\Agency\Concerns;

trait ChecksSubscription
{
    public static function getActiveTier(): string
    {
        $agency = auth()->user()?->agency;
        if (!$agency) return 'free';
        $tier = $agency->subscription_tier ?? 'free';
        if ($tier === 'free') return 'free';
        if (!$agency->subscription_end || now()->isAfter($agency->subscription_end)) return 'free';
        return $tier;
    }

    public static function isActivePro(): bool
    {
        return in_array(self::getActiveTier(), ['pro', 'business']);
    }
}
