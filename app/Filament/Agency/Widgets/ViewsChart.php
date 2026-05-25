<?php

declare(strict_types=1);

namespace App\Filament\Agency\Widgets;

use App\Models\AnalyticsEvent;
use App\Models\Vehicle;
use Filament\Widgets\ChartWidget;

class ViewsChart extends ChartWidget
{
    protected ?string $heading = 'Visites par jour';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 2,
        'xl' => 2,
    ];

    protected function getData(): array
    {
        $user = auth()->user();
        $agencyId = $user?->agency?->id;
        if (! $agencyId) {
            return ['datasets' => [], 'labels' => []];
        }

        $vehicleIds = Vehicle::where('agency_id', $agencyId)->pluck('id');

        $labels = [];
        $views = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('d/m');
            $views[] = AnalyticsEvent::whereIn('vehicle_id', $vehicleIds)
                ->where('event_type', 'view')
                ->whereDate('created_at', $date->toDateString())
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Vues',
                    'data' => $views,
                    'borderColor' => '#16A34A',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
