<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Scope qui filtre automatiquement les véhicules par agency_id du user connecté.
 * S'applique UNIQUEMENT dans le panel Filament 'agency'.
 * Aucun effet sur les requêtes API publiques ou admin.
 */
class AgencyVehiclesScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Ne s'applique QUE si on est dans le panel Filament 'agency'
        if (! Filament::hasPlugin('') && ! $this->isInAgencyPanel()) {
            return;
        }

        $user = auth()->user();
        if (! $user || ! $user->relationLoaded('agency')) {
            // Force chargement de la relation
            $user?->load('agency');
        }

        $agencyId = $user?->agency?->id;
        if (! $agencyId) {
            // Pas d'agence → ne retourne RIEN (sécurité)
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where($model->getTable() . '.agency_id', $agencyId);
    }

    private function isInAgencyPanel(): bool
    {
        try {
            return Filament::getCurrentPanel()?->getId() === 'agency';
        } catch (\Throwable $e) {
            return false;
        }
    }
}
