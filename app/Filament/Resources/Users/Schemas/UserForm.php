<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->maxLength(150),
                        TextInput::make('phone')
                            ->label('Téléphone')
                            ->tel()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20),
                        Select::make('language')
                            ->label('Langue')
                            ->options([
                                'fr' => 'Français',
                                'ar' => 'العربية',
                                'en' => 'English',
                            ])
                            ->default('fr')
                            ->required(),
                    ]),

                Section::make('Sécurité & rôle')
                    ->columns(2)
                    ->schema([
                        Select::make('role')
                            ->label('Rôle')
                            ->options(UserRole::class)
                            ->default(UserRole::USER)
                            ->required(),
                        TextInput::make('password')
                            ->label('Mot de passe')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->minLength(8)
                            ->helperText('Laisser vide pour ne pas modifier'),
                    ]),

                Section::make('Vérifications')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        DateTimePicker::make('email_verified_at')
                            ->label('Email vérifié le'),
                        DateTimePicker::make('phone_verified_at')
                            ->label('Téléphone vérifié le'),
                        DateTimePicker::make('last_seen_at')
                            ->label('Dernière connexion')
                            ->disabled(),
                        TextInput::make('avatar_url')
                            ->label('Avatar URL')
                            ->url()
                            ->maxLength(500),
                    ]),
            ]);
    }
}
