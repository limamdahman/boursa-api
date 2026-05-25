<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class AgencyLeadsScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! $this->isInAgencyPanel()) {
            return;
        }

        $user = auth()->user();
        $agencyId = $user?->agency?->id;
        if (! $agencyId) {
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
