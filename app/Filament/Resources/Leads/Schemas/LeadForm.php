<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\LeadType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('vehicle_id')
                    ->relationship('vehicle', 'id')
                    ->required(),
                Select::make('agency_id')
                    ->relationship('agency', 'name'),
                TextInput::make('sender_user_id'),
                TextInput::make('sender_name'),
                TextInput::make('sender_phone')
                    ->tel(),
                Select::make('type')
                    ->options(LeadType::class)
                    ->required(),
                Textarea::make('message')
                    ->columnSpanFull(),
                Toggle::make('is_read')
                    ->required(),
                DateTimePicker::make('read_at'),
                TextInput::make('ip_address'),
                TextInput::make('user_agent'),
            ]);
    }
}
