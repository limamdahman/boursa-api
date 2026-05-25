<?php

declare(strict_types=1);

namespace App\Filament\Agency\Pages;

use App\Filament\Agency\Widgets\AgencyStatsOverview;
use App\Filament\Agency\Widgets\LeadsByTypeChart;
use App\Filament\Agency\Widgets\TopVehiclesWidget;
use App\Filament\Agency\Widgets\ViewsChart;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            AgencyStatsOverview::class,
            ViewsChart::class,
            LeadsByTypeChart::class,
            TopVehiclesWidget::class,
        ];
    }

    public function getColumns(): array|int
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 4,
        ];
    }

    public function getTitle(): string
    {
        return 'Tableau de bord';
    }

    public function getHeading(): string
    {
        return 'Tableau de bord';
    }

    public function getSubheading(): ?string
    {
        $user = auth()->user();
        $name = $user?->agency?->name ?? 'votre agence';
        return 'Performance de ' . $name . ' sur les 30 derniers jours';
    }
}
