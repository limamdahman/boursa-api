<?php
declare(strict_types=1);
namespace App\Filament\Admin\Actions;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class ExportCsvAction
{
    public static function make(string $filename, array $columns, Builder $query): Action
    {
        return Action::make('export_csv')
            ->label('Export CSV')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function () use ($filename, $columns, $query) {
                $headers = [
                    'Content-Type'        => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                ];

                return response()->streamDownload(function () use ($columns, $query) {
                    $handle = fopen('php://output', 'w');
                    fwrite($handle, "\xEF\xBB\xBF");
                    fputcsv($handle, array_keys($columns), ';');
                    $query->chunk(500, function ($records) use ($handle, $columns) {
                        foreach ($records as $record) {
                            $row = [];
                            foreach ($columns as $getter) {
                                $row[] = is_callable($getter) ? $getter($record) : data_get($record, $getter, '');
                            }
                            fputcsv($handle, $row, ';');
                        }
                    });
                    fclose($handle);
                }, $filename, $headers);
            });
    }
}
