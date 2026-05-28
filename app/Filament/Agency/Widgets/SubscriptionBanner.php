<?php
declare(strict_types=1);
namespace App\Filament\Agency\Widgets;

use App\Filament\Agency\Concerns\ChecksSubscription;
use Filament\Widgets\Widget;

class SubscriptionBanner extends Widget
{
    use ChecksSubscription;
    protected string $view = 'filament.agency.widgets.subscription-banner';
    protected static ?int $sort = -10;
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return !self::isActivePro();
    }
}
