<?php
declare(strict_types=1);
namespace App\Filament\Agency\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;

class ChangePassword extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-lock-closed';
    protected static ?string $navigationLabel = 'Mot de passe';
    protected static ?int $navigationSort = 10;
    protected string $view = 'filament.agency.pages.change-password';

    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';

    public function save(): void
    {
        $user = auth()->user();

        if ($user->password && !Hash::check($this->current_password, $user->password)) {
            Notification::make()->title('Mot de passe actuel incorrect')->danger()->send();
            return;
        }

        if (strlen($this->new_password) < 8) {
            Notification::make()->title('Le mot de passe doit contenir au moins 8 caractères')->danger()->send();
            return;
        }

        if ($this->new_password !== $this->new_password_confirmation) {
            Notification::make()->title('Les mots de passe ne correspondent pas')->danger()->send();
            return;
        }

        $user->update(['password' => Hash::make($this->new_password)]);

        $this->current_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';

        Notification::make()->title('Mot de passe mis à jour avec succès')->success()->send();
    }
}
