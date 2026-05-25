<?php

declare(strict_types=1);

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Enums\VehicleStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Véhicule')
                    ->columns(2)
                    ->schema([
                        Select::make('brand_id')
                            ->label('Marque')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Select::make('vehicle_model_id')
                            ->label('Modèle')
                            ->relationship(
                                'vehicleModel',
                                'name',
                                fn ($query, callable $get) => $query->where('brand_id', $get('brand_id'))
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('year')
                            ->label('Année')
                            ->required()
                            ->numeric()
                            ->minValue(1980)
                            ->maxValue((int) date('Y') + 1),
                        TextInput::make('mileage_km')
                            ->label('Kilométrage')
                            ->suffix('km')
                            ->numeric()
                            ->minValue(0),
                        Select::make('fuel')
                            ->label('Carburant')
                            ->options([
                                'gasoline' => 'Essence',
                                'diesel' => 'Diesel',
                                'hybrid' => 'Hybride',
                                'electric' => 'Électrique',
                                'gpl' => 'GPL',
                            ]),
                        Select::make('transmission')
                            ->label('Boîte de vitesses')
                            ->options([
                                'manual' => 'Manuelle',
                                'automatic' => 'Automatique',
                            ]),
                        Select::make('body_type')
                            ->label('Carrosserie')
                            ->options([
                                'sedan' => 'Berline',
                                'suv' => 'SUV',
                                'hatchback' => 'Compacte',
                                'pickup' => 'Pickup',
                                'van' => 'Utilitaire',
                                'coupe' => 'Coupé',
                                'wagon' => 'Break',
                                'convertible' => 'Cabriolet',
                            ]),
                        TextInput::make('color')
                            ->label('Couleur')
                            ->maxLength(30),
                        Select::make('condition')
                            ->label('État')
                            ->options([
                                'new' => 'Neuf',
                                'used' => 'Occasion',
                                'damaged' => 'Accidenté',
                            ])
                            ->default('used')
                            ->required(),
                    ]),

                Section::make('Prix & négociation')
                    ->columns(3)
                    ->schema([
                        TextInput::make('price_mru')
                            ->label('Prix')
                            ->suffix('MRU')
                            ->required()
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('currency')
                            ->label('Devise')
                            ->default('MRU')
                            ->required()
                            ->maxLength(3),
                        Toggle::make('price_negotiable')
                            ->label('Prix négociable')
                            ->default(false),
                    ]),

                Section::make('Localisation & propriétaire')
                    ->columns(2)
                    ->schema([
                        Select::make('city_id')
                            ->label('Ville')
                            ->relationship('city', 'name_fr')
                            ->searchable()
                            ->preload(),
                        Select::make('agency_id')
                            ->label('Agence')
                            ->relationship('agency', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('user_id')
                            ->label('Vendeur particulier')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Descriptions')
                    ->columns(1)
                    ->schema([
                        Textarea::make('description_fr')
                            ->label('Description (FR)')
                            ->rows(4),
                        Textarea::make('description_ar')
                            ->label('Description (AR)')
                            ->rows(4),
                    ]),

                Section::make('Statut & publication')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->label('Statut')
                            ->options(VehicleStatus::class)
                            ->default(VehicleStatus::DRAFT)
                            ->required(),
                        DateTimePicker::make('published_at')
                            ->label('Publié le'),
                        DateTimePicker::make('expires_at')
                            ->label('Expire le'),
                        Textarea::make('moderation_notes')
                            ->label('Notes de modération')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
