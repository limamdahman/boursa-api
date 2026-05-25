<?php

declare(strict_types=1);

namespace App\Filament\Resources\Vehicles\RelationManagers;

use App\Models\Vehicle;
use App\Models\VehicleMedia;
use App\Services\Media\MediaUploadService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Photos';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('is_cover')
                ->label('Photo de couverture'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->columns([
                ImageColumn::make('url_thumb')
                    ->label('Aperçu')
                    ->getStateUsing(fn (VehicleMedia $r) => $r->url_thumb ?? $r->url_original)
                    ->square()
                    ->size(80),
                IconColumn::make('is_cover')
                    ->label('Couverture')
                    ->boolean(),
                IconColumn::make('watermarked')
                    ->label('Watermark')
                    ->boolean(),
                TextColumn::make('width')
                    ->label('Dimensions')
                    ->formatStateUsing(fn ($state, VehicleMedia $r) => $r->width ? "{$r->width}×{$r->height}" : '—'),
                TextColumn::make('size_bytes')
                    ->label('Taille')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024, 0) . ' KB' : '—'),
                TextColumn::make('created_at')
                    ->label('Ajoutée le')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Action::make('upload')
                    ->label('Ajouter des photos')
                    ->icon('heroicon-o-photo')
                    ->color('primary')
                    ->schema([
                        FileUpload::make('photos')
                            ->label('Photos')
                            ->image()
                            ->multiple()
                            ->maxFiles(30)
                            ->maxSize(10240)
                            ->disk('local')
                            ->directory('tmp-uploads')
                            ->required(),
                        Toggle::make('set_first_as_cover')
                            ->label('Définir la première comme couverture')
                            ->default(false)
                            ->visible(fn ($livewire) => $livewire->getOwnerRecord()->media()->count() === 0),
                    ])
                    ->action(function (array $data, $livewire) {
                        /** @var Vehicle $vehicle */
                        $vehicle = $livewire->getOwnerRecord();
                        $service = app(MediaUploadService::class);

                        $count = 0;
                        $errors = [];
                        $files = $data['photos'] ?? [];
                        $setCover = $data['set_first_as_cover'] ?? false;

                        foreach ($files as $index => $tmpPath) {
                            try {
                                $fullPath = Storage::disk('local')->path($tmpPath);
                                if (! file_exists($fullPath)) {
                                    $errors[] = "Fichier introuvable : {$tmpPath}";
                                    continue;
                                }

                                $uploadedFile = new UploadedFile(
                                    $fullPath,
                                    basename($tmpPath),
                                    mime_content_type($fullPath) ?: 'image/jpeg',
                                    null,
                                    true
                                );

                                $shouldBeCover = $setCover && $index === 0;
                                $service->store($vehicle, $uploadedFile, $shouldBeCover);
                                $count++;

                                Storage::disk('local')->delete($tmpPath);
                            } catch (Throwable $e) {
                                $errors[] = $e->getMessage();
                            }
                        }

                        if ($count > 0) {
                            Notification::make()
                                ->title("{$count} photo(s) téléchargée(s)")
                                ->body('Le watermark et les thumbnails se génèrent en arrière-plan.')
                                ->success()
                                ->send();
                        }

                        if (! empty($errors)) {
                            Notification::make()
                                ->title('Certaines photos ont échoué')
                                ->body(implode("\n", array_slice($errors, 0, 3)))
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('setCover')
                    ->label('Définir comme couverture')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (VehicleMedia $r) => ! $r->is_cover)
                    ->requiresConfirmation()
                    ->action(function (VehicleMedia $record, $livewire) {
                        $vehicle = $livewire->getOwnerRecord();
                        $vehicle->media()->update(['is_cover' => false]);
                        $record->update(['is_cover' => true]);

                        Notification::make()
                            ->title('Couverture mise à jour')
                            ->success()
                            ->send();
                    }),
                Action::make('delete')
                    ->label('Supprimer')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (VehicleMedia $record) {
                        app(MediaUploadService::class)->delete($record);

                        Notification::make()
                            ->title('Photo supprimée')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
