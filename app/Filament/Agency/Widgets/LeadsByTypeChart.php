<?php

declare(strict_types=1);

namespace App\Filament\Agency\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;

class LeadsByTypeChart extends ChartWidget
{
    protected ?string $heading = 'Comment vos clients vous contactent';

    protected static ?int $sort = 3;

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

        $leads = Lead::where('agency_id', $agencyId)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $labelMap = [
            'message' => 'Messages',
            'call_click' => 'Appels',
            'whatsapp_click' => 'WhatsApp',
        ];

        $colorMap = [
            'message' => '#3B82F6',
            'call_click' => '#16A34A',
            'whatsapp_click' => '#25D366',
        ];

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($labelMap as $key => $label) {
            $labels[] = $label;
            $data[] = $leads[$key] ?? 0;
            $colors[] = $colorMap[$key];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Contacts',
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
