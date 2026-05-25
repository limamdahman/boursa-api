<?php

declare(strict_types=1);

namespace App\Filament\Agency\Pages;

use App\Models\Agency;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\View as ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class MyAgencyProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static \UnitEnum|string|null $navigationGroup = 'Mon agence';

    protected static ?string $navigationLabel = 'Profil agence';

    protected static ?string $title = 'Profil de mon agence';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.agency.pages.my-agency-profile';

    public ?array $data = [];

    public function mount(): void
    {
        $agency = $this->getAgency();
        if (! $agency) {
            abort(404, 'Agence introuvable');
        }

        $formData = $agency->only([
            'name', 'description', 'address', 'city_id', 'rc_number',
            'phone_whatsapp', 'phone_call', 'email', 'website', 'logo_url', 'banner_url',
        ]);

        // Extraire lat/lng depuis PostGIS Point WKB hex si présent
        if ($agency->location && is_string($agency->location) && strlen($agency->location) >= 50) {
            try {
                $bin = hex2bin($agency->location);
                $formData['lng'] = unpack('d', substr($bin, 9, 8))[1];
                $formData['lat'] = unpack('d', substr($bin, 17, 8))[1];
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->description('Le nom et la description visibles publiquement')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label("Nom de l'agence")
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Décrivez votre agence en quelques lignes...')
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        FileUpload::make('banner_url')
                            ->label('Bannière (1200×300 recommandé)')
                            ->helperText('Image panoramique max 6 MB - affichée en haut de votre profil')
                            ->image()
                            ->disk('s3')
                            ->directory('agencies/banners')
                            ->maxSize(6144)
                            ->imageEditor()
                            ->imageCropAspectRatio('4:1')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('300')
                            ->columnSpanFull(),
                        FileUpload::make('logo_url')
                            ->label('Logo')
                            ->helperText('Image carrée (1:1), max 4 MB')
                            ->image()
                            ->disk('s3')
                            ->directory('agencies/logos')
                            ->maxSize(4096)
                            ->imageEditor()
                            ->imageCropAspectRatio('1:1')
                            ->columnSpanFull(),
                    ]),

                Section::make('Contact')
                    ->description('Coordonnées affichées aux clients')
                    ->columns(2)
                    ->schema([
                        TextInput::make('phone_whatsapp')
                            ->label('WhatsApp')
                            ->tel()
                            ->placeholder('22245678901')
                            ->maxLength(30),
                        TextInput::make('phone_call')
                            ->label('Téléphone (appel)')
                            ->tel()
                            ->placeholder('22245678901')
                            ->maxLength(30),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(150),
                        TextInput::make('website')
                            ->label('Site web')
                            ->url()
                            ->placeholder('https://...')
                            ->maxLength(255),
                    ]),

                Section::make('Localisation')
                    ->description("Position de l'agence")
                    ->columns(2)
                    ->schema([
                        Select::make('city_id')
                            ->label('Ville')
                            ->relationship('city', 'name_fr')
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                        TextInput::make('address')
                            ->label('Point de repère / Adresse')
                            ->placeholder('Ex : Tevragh-Zeina, près de la mosquée Ibn Abbas')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('lat')
                            ->label('Latitude')
                            ->numeric()
                            ->step(0.000001)
                            ->placeholder('18.0858')
                            ->extraInputAttributes(['id' => 'agency-lat-field']),
                        TextInput::make('lng')
                            ->label('Longitude')
                            ->numeric()
                            ->step(0.000001)
                            ->placeholder('-15.9785')
                            ->extraInputAttributes(['id' => 'agency-lng-field']),
                        ViewField::make('map_preview')
                            ->view('filament.agency.components.map-preview')
                            ->columnSpanFull()
                            ->dehydrated(false),
                    ]),

                Section::make('Informations légales')
                    ->columns(2)
                    ->schema([
                        TextInput::make('rc_number')
                            ->label('Registre de commerce (RC)')
                            ->placeholder('Numéro RC')
                            ->maxLength(50),
                    ]),
            ])
            ->statePath('data')
            ->model($this->getAgency());
    }

    public function save(): void
    {
        $agency = $this->getAgency();
        if (! $agency) return;

        $data = $this->form->getState();

        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;
        unset($data['lat'], $data['lng'], $data['map_preview']);

        $agency->update($data);

        if ($lat !== null && $lng !== null && is_numeric($lat) && is_numeric($lng)) {
            DB::statement(
                'UPDATE agencies SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                [(float) $lng, (float) $lat, $agency->id]
            );
        }

        Notification::make()
            ->title('Profil mis à jour')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Enregistrer')
                ->color('success')
                ->submit('save'),
        ];
    }

    private function getAgency(): ?Agency
    {
        return auth()->user()?->agency;
    }
}
