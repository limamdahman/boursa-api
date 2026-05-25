<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Leads\Pages;

use App\Filament\Agency\Resources\Leads\LeadResource;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    public function getTitle(): string
    {
        return 'Mes contacts';
    }

    public function getSubheading(): ?string
    {
        $user = auth()->user();
        $agencyId = $user?->agency?->id;
        if (! $agencyId) return null;

        $unread = \App\Models\Lead::where('agency_id', $agencyId)->where('is_read', false)->count();
        if ($unread === 0) return 'Tous vos contacts ont été consultés';
        return $unread . ($unread > 1 ? ' contacts non lus' : ' contact non lu');
    }
}
