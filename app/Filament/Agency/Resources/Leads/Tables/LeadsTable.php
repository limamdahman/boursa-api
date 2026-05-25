<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Leads\Tables;

use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_read')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-s-bell-alert')
                    ->trueColor('gray')
                    ->falseColor('danger')
                    ->width('20px'),
                TextColumn::make('created_at')
                    ->label('Reçu le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->weight(fn ($record) => $record->is_read ? null : 'bold'),
                TextColumn::make('sender_name')
                    ->label('Contact')
                    ->searchable()
                    ->weight(fn ($record) => $record->is_read ? null : 'bold'),
                TextColumn::make('sender_phone')
                    ->label('Téléphone')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Numéro copié')
                    ->copyMessageDuration(1500)
                    ->fontFamily('mono'),
                TextColumn::make('type')
                    ->label('Canal')
                    ->badge()
                    ->color(function ($state): string {
                        $value = $state instanceof \BackedEnum ? $state->value : (string) $state;
                        return match ($value) {
                            'message' => 'info',
                            'call_click' => 'success',
                            'whatsapp_click' => 'warning',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function ($state): string {
                        $value = $state instanceof \BackedEnum ? $state->value : (string) $state;
                        return match ($value) {
                            'message' => 'Message',
                            'call_click' => 'Appel',
                            'whatsapp_click' => 'WhatsApp',
                            default => $value,
                        };
                    }),
                TextColumn::make('vehicle.brand.name')
                    ->label('Véhicule')
                    ->formatStateUsing(function ($record): string {
                        $v = $record->vehicle;
                        if (! $v) return '—';
                        $brand = $v->brand?->name ?? '';
                        $model = $v->vehicleModel?->name ?? '';
                        return trim($brand . ' ' . $model . ' ' . ($v->year ?? ''));
                    })
                    ->searchable(),
                TextColumn::make('message')
                    ->label('Message')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->message)
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Canal')
                    ->options([
                        'message' => 'Message',
                        'call_click' => 'Appel',
                        'whatsapp_click' => 'WhatsApp',
                    ]),
                SelectFilter::make('is_read')
                    ->label('Lu')
                    ->options([
                        '0' => 'Non lus',
                        '1' => 'Lus',
                    ]),
            ])
            ->recordActions([
                Action::make('view_vehicle')
                    ->label('Véhicule')
                    ->icon('heroicon-o-truck')
                    ->color('gray')
                    ->url(fn ($record) => $record->vehicle ? "/agence/vehicles/{$record->vehicle_id}/edit" : null)
                    ->visible(fn ($record) => $record->vehicle !== null),
                Action::make('call')
                    ->label('Appeler')
                    ->icon('heroicon-o-phone')
                    ->color('success')
                    ->url(fn ($record) => 'tel:' . $record->sender_phone)
                    ->openUrlInNewTab(false)
                    ->visible(fn ($record) => ! empty($record->sender_phone)),
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-bottom-center')
                    ->color('success')
                    ->url(fn ($record) => 'https://wa.me/' . preg_replace('/\D/', '', $record->sender_phone ?? ''))
                    ->openUrlInNewTab(true)
                    ->visible(fn ($record) => ! empty($record->sender_phone)),
                Action::make('toggle_read')
                    ->label(fn ($record) => $record->is_read ? 'Marquer non lu' : 'Marquer lu')
                    ->icon('heroicon-o-check-circle')
                    ->color('gray')
                    ->action(function ($record) {
                        $record->update([
                            'is_read' => ! $record->is_read,
                            'read_at' => $record->is_read ? null : now(),
                        ]);
                    }),
            ]);
    }
}
