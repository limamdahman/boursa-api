<?php

declare(strict_types=1);

namespace App\Filament\Agency\Widgets;

use App\Enums\VehicleStatus;
use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AgencyStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $agencyId = $user?->agency?->id;
        if (! $agencyId) {
            return [];
        }

        $vehicleIds = Vehicle::where('agency_id', $agencyId)->pluck('id');

        // ─── 1. Vues 30 derniers jours ───
        $viewsLast30 = AnalyticsEvent::whereIn('vehicle_id', $vehicleIds)
            ->where('event_type', 'view')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $viewsPrev30 = AnalyticsEvent::whereIn('vehicle_id', $vehicleIds)
            ->where('event_type', 'view')
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->count();

        $viewsTrend = $viewsPrev30 > 0
            ? round((($viewsLast30 - $viewsPrev30) / $viewsPrev30) * 100, 1)
            : 0;

        // ─── 2. Contacts (leads) 30 derniers jours ───
        $leadsLast30 = Lead::where('agency_id', $agencyId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $leadsPrev30 = Lead::where('agency_id', $agencyId)
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->count();

        $leadsTrend = $leadsPrev30 > 0
            ? round((($leadsLast30 - $leadsPrev30) / $leadsPrev30) * 100, 1)
            : 0;

        // ─── 3. Annonces actives ───
        $activeListings = Vehicle::where('agency_id', $agencyId)
            ->where('status', VehicleStatus::ACTIVE->value)
            ->count();

        $totalListings = Vehicle::where('agency_id', $agencyId)->count();

        // ─── 4. Taux de conversion (leads/vues * 100) ───
        $conversionRate = $viewsLast30 > 0
            ? round(($leadsLast30 / $viewsLast30) * 100, 2)
            : 0;

        return [
            Stat::make('Personnes intéressées ce mois', number_format($viewsLast30, 0, ',', ' '))
                ->description($viewsTrend >= 0 ? '+' . $viewsTrend . '% par rapport au mois dernier' : $viewsTrend . '% par rapport au mois dernier')
                ->descriptionIcon($viewsTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($viewsTrend >= 0 ? 'success' : 'danger')
                ->chart($this->getDailyViews($vehicleIds, 30)),

            Stat::make('Clients qui vous ont contacté', $leadsLast30)
                ->description($leadsTrend >= 0 ? '+' . $leadsTrend . '% par rapport au mois dernier' : $leadsTrend . '% par rapport au mois dernier')
                ->descriptionIcon($leadsTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($leadsTrend >= 0 ? 'success' : 'danger')
                ->chart($this->getDailyLeads($agencyId, 30)),

            Stat::make('Voitures en vente', $activeListings)
                ->description($totalListings . ' voitures au total')
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('info'),

            Stat::make('Clients pour 100 visiteurs', $conversionRate . '%')
                ->description('Clients / Visiteurs')
                ->descriptionIcon('heroicon-m-bolt')
                ->color($conversionRate >= 2 ? 'success' : ($conversionRate >= 1 ? 'warning' : 'gray')),
        ];
    }

    /**
     * @return array<int>
     */
    private function getDailyViews($vehicleIds, int $days): array
    {
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $data[] = AnalyticsEvent::whereIn('vehicle_id', $vehicleIds)
                ->where('event_type', 'view')
                ->whereDate('created_at', now()->subDays($i)->toDateString())
                ->count();
        }
        return $data;
    }

    /**
     * @return array<int>
     */
    private function getDailyLeads(string $agencyId, int $days): array
    {
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $data[] = Lead::where('agency_id', $agencyId)
                ->whereDate('created_at', now()->subDays($i)->toDateString())
                ->count();
        }
        return $data;
    }
}
