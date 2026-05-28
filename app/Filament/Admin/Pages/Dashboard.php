<?php
declare(strict_types=1);
namespace App\Filament\Admin\Pages;

use App\Models\Agency;
use App\Models\Lead;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.admin.pages.dashboard';

    public function getColumns(): array|int
    {
        return ['default' => 1, 'md' => 2, 'xl' => 4];
    }

    public function renewAgency(string $agencyId, string $tier, int $months): void
    {
        $agency = Agency::findOrFail($agencyId);
        $start  = now();
        $end    = now()->addMonths($months);

        $agency->update([
            'subscription_tier'  => $tier,
            'subscription_start' => $start,
            'subscription_end'   => $end,
        ]);

        Notification::make()
            ->title($agency->name . ' — ' . strtoupper($tier) . ' renouvelé pour ' . $months . ' mois')
            ->success()->send();
    }

    public function getStats(): array
    {
        return [
            'agencies'      => Agency::count(),
            'pro_active'    => Agency::whereIn('subscription_tier', ['pro', 'business'])->whereNotNull('subscription_end')->where('subscription_end', '>', now())->count(),
            'vehicles'      => Vehicle::count(),
            'active'        => Vehicle::where('status', 'active')->count(),
            'sold'          => Vehicle::where('status', 'sold')->count(),
            'users'         => User::where('role', 'user')->count(),
            'leads_month'   => Lead::where('created_at', '>=', now()->startOfMonth())->count(),
            'leads_total'   => Lead::count(),
        ];
    }

    public function getVehiclesByMonth(): array
    {
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $data[] = [
                'label' => $month->format('M Y'),
                'count' => Vehicle::whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count(),
            ];
        }
        return $data;
    }

    public function getLeadsByMonth(): array
    {
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $data[] = [
                'label' => $month->format('M Y'),
                'count' => Lead::whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count(),
            ];
        }
        return $data;
    }

    public function getSubscriptionsByTier(): array
    {
        return [
            'free'     => Agency::where('subscription_tier', 'free')->count(),
            'pro'      => Agency::where('subscription_tier', 'pro')->whereNotNull('subscription_end')->where('subscription_end', '>', now())->count(),
            'business' => Agency::where('subscription_tier', 'business')->whereNotNull('subscription_end')->where('subscription_end', '>', now())->count(),
        ];
    }

    public function getExpiringAgencies(): \Illuminate\Support\Collection
    {
        return Agency::whereIn('subscription_tier', ['pro', 'business'])
            ->whereNotNull('subscription_end')
            ->whereBetween('subscription_end', [now(), now()->addDays(7)])
            ->get();
    }

    public function getExpiredAgencies(): \Illuminate\Support\Collection
    {
        return Agency::whereIn('subscription_tier', ['pro', 'business'])
            ->where(function ($q) {
                $q->whereNull('subscription_end')->orWhere('subscription_end', '<', now());
            })->get();
    }
}
