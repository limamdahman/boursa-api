<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Leads;

use App\Filament\Agency\Resources\Leads\Pages\ListLeads;
use App\Filament\Agency\Resources\Leads\Tables\LeadsTable;
use App\Models\Lead;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Clients';

    protected static ?string $modelLabel = 'Contact';

    protected static ?string $pluralModelLabel = 'Contacts';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $agencyId = $user?->agency?->id;
        if (! $agencyId) return null;

        $unread = Lead::where('agency_id', $agencyId)->where('is_read', false)->count();
        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return LeadsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $agencyId = $user?->agency?->id;

        $query = parent::getEloquentQuery();

        if (! $agencyId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('leads.agency_id', $agencyId);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
        ];
    }
}
