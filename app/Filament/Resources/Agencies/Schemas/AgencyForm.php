<?php

namespace App\Filament\Resources\Agencies\Schemas;

use App\Enums\AgencyStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AgencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('logo_url')
                    ->url(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('address'),
                Select::make('city_id')
                    ->relationship('city', 'id'),
                TextInput::make('rc_number'),
                TextInput::make('phone_whatsapp')
                    ->tel(),
                TextInput::make('phone_call')
                    ->tel(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('website')
                    ->url(),
                Select::make('status')
                    ->options(AgencyStatus::class)
                    ->default('pending')
                    ->required(),
                DateTimePicker::make('verified_at'),
                TextInput::make('subscription_tier')
                    ->required()
                    ->default('free'),
                TextInput::make('quota_active_listings')
                    ->required()
                    ->numeric()
                    ->default(10),
                TextInput::make('location'),
            ]);
    }
}
