<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $verificationUrl = $this->buildVerificationUrl($notifiable);
        $lang = $notifiable->language ?? 'fr';

        return (new MailMessage)
            ->subject($lang === 'ar' ? 'تأكيد البريد الإلكتروني' : 'Vérifiez votre adresse email')
            ->greeting($lang === 'ar' ? 'مرحباً ' . $notifiable->name : 'Bonjour ' . $notifiable->name . ',')
            ->line($lang === 'ar'
                ? 'انقر على الزر أدناه لتأكيد بريدك الإلكتروني.'
                : 'Cliquez sur le bouton ci-dessous pour vérifier votre adresse email.')
            ->action(
                $lang === 'ar' ? 'تأكيد البريد' : 'Vérifier mon email',
                $verificationUrl
            )
            ->line($lang === 'ar'
                ? 'هذا الرابط ينتهي خلال 60 دقيقة.'
                : 'Ce lien expire dans 60 minutes.')
            ->salutation($lang === 'ar' ? 'فريق بورصة' : 'L\'équipe Boursa');
    }

    protected function buildVerificationUrl(mixed $notifiable): string
    {
        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // Remplacer le domaine API par le domaine frontend
        $apiUrl      = config('app.url');
        $frontendUrl = config('app.frontend_url', 'http://localhost:4321');
        $lang        = $notifiable->language ?? 'fr';

        return str_replace(
            $apiUrl . '/api/v1/auth/email/verify',
            $frontendUrl . '/' . $lang . '/verifier-email',
            $signedUrl
        );
    }
}
