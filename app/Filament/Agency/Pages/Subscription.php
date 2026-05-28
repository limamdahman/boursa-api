<?php
declare(strict_types=1);
namespace App\Filament\Agency\Pages;

use Filament\Pages\Page;

class Subscription extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Mon abonnement';
    protected static ?int $navigationSort = 9;
    protected string $view = 'filament.agency.pages.subscription';

    public function getAgency(): ?\App\Models\Agency
    {
        return auth()->user()?->agency;
    }

    public function getDaysLeft(): int
    {
        $agency = $this->getAgency();
        if (!$agency?->subscription_end) return 0;
        return max(0, (int) now()->diffInDays($agency->subscription_end, false));
    }

    public function isExpired(): bool
    {
        $agency = $this->getAgency();
        if (!$agency?->subscription_end) return true;
        return now()->isAfter($agency->subscription_end);
    }
}
