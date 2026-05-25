<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Vehicles\Schemas;

use Filament\Forms\Components\FileUpload;
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
                    ->description('Informations principales du véhicule')
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
                            ->numeric()
                            ->minValue(1980)
                            ->maxValue((int) date('Y') + 1)
                            ->required(),
                        Select::make('condition')
                            ->label('État')
                            ->options([
                                'new' => 'Neuf',
                                'used' => 'Occasion',
                                'imported' => 'Importé',
                            ])
                            ->default('used')
                            ->required(),
                        TextInput::make('mileage_km')
                            ->label('Kilométrage (km)')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('color')
                            ->label('Couleur')
                            ->maxLength(50),
                    ]),

                Section::make('Caractéristiques techniques')
                    ->columns(2)
                    ->schema([
                        Select::make('fuel')
                            ->label('Carburant')
                            ->options([
                                'gasoline' => 'Essence',
                                'diesel' => 'Diesel',
                                'hybrid' => 'Hybride',
                                'electric' => 'Électrique',
                                'lpg' => 'GPL',
                            ]),
                        Select::make('transmission')
                            ->label('Boîte')
                            ->options([
                                'manual' => 'Manuelle',
                                'automatic' => 'Automatique',
                            ]),
                        Select::make('body_type')
                            ->label('Carrosserie')
                            ->options([
                                'sedan' => 'Berline',
                                'suv' => 'SUV',
                                'pickup' => 'Pickup',
                                'hatchback' => 'Compacte',
                                'van' => 'Utilitaire',
                                'coupe' => 'Coupé',
                            ]),
                        Select::make('city_id')
                            ->label('Ville')
                            ->relationship('city', 'name_fr')
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Prix')
                    ->columns(2)
                    ->schema([
                        TextInput::make('price_mru')
                            ->label('Prix (MRU)')
                            ->numeric()
                            ->minValue(50000)
                            ->required()
                            ->suffix('MRU'),
                        Toggle::make('price_negotiable')
                            ->label('Prix négociable')
                            ->default(true),
                    ]),

                Section::make('Photos')
                    ->description('Ajoutez au moins une photo. La première sera utilisée comme couverture.')
                    ->schema([
                        FileUpload::make('photos')
                            ->label('Photos du véhicule')
                            ->image()
                            ->multiple()
                            ->maxFiles(30)
                            ->maxSize(10240)
                            ->disk('local')
                            ->directory('tmp-uploads')
                            ->reorderable()
                            ->appendFiles()
                            ->panelLayout('grid')
                            ->imagePreviewHeight('120')
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->helperText('Formats acceptés : JPG, PNG, WEBP. Max 10 MB par photo.'),
                    ]),

                Section::make('Description')
                    ->columns(1)
                    ->schema([
                        Textarea::make('description_fr')
                            ->label('Description (français)')
                            ->rows(4)
                            ->maxLength(5000),
                        Textarea::make('description_ar')
                            ->label('Description (arabe)')
                            ->rows(4)
                            ->maxLength(5000)
                            ->extraInputAttributes(['dir' => 'rtl']),
                    ]),
            ]);
    }
}
