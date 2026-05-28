<?php
declare(strict_types=1);
namespace App\Observers;

use App\Models\Agency;

class AgencyObserver
{
    public function retrieved(Agency $agency): void
    {
        // Auto-downgrade si abonnement expiré
        if (
            in_array($agency->subscription_tier, ['pro', 'business']) &&
            (!$agency->subscription_end || now()->isAfter($agency->subscription_end))
        ) {
            // Ne pas sauvegarder en DB ici pour éviter les boucles
            // La logique est gérée par ChecksSubscription trait
        }
    }
}
