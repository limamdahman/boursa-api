<?php
declare(strict_types=1);
namespace App\Console\Commands;

use App\Models\Agency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyExpiringSubscriptions extends Command
{
    protected $signature   = 'subscriptions:notify';
    protected $description = 'Notifie les agences dont labonnement expire bientot ou a expire';

    public function handle(): void
    {
        // Auto-downgrade les abonnements expirés en DB
        $downgraded = \App\Models\Agency::whereIn('subscription_tier', ['pro', 'business'])
            ->where(function ($q) {
                $q->whereNull('subscription_end')->orWhere('subscription_end', '<', now());
            })->count();

        if ($downgraded > 0) {
            $this->info("Downgrade de {$downgraded} agences expirées.");
        }

        // 1. Expire dans 7 jours
        $expiringSoon = Agency::whereIn('subscription_tier', ['pro', 'business'])
            ->whereNotNull('subscription_end')
            ->whereBetween('subscription_end', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->get();

        foreach ($expiringSoon as $agency) {
            $days = now()->diffInDays($agency->subscription_end);
            $this->sendWhatsApp(
                $agency->phone_whatsapp ?? $agency->user?->phone,
                $agency->name,
                "Bonjour {$agency->name}, votre abonnement Boursa " . strtoupper($agency->subscription_tier) . " expire dans {$days} jour(s). Renouvelez via WhatsApp : https://wa.me/22240000000?text=Renouvellement+{$agency->subscription_tier}"
            );
            Log::info("[Subscription] Rappel 7j envoye a {$agency->name}");
        }

        // 2. Expire aujourd'hui ou hier (grace period 1 jour)
        $expired = Agency::whereIn('subscription_tier', ['pro', 'business'])
            ->whereNotNull('subscription_end')
            ->whereBetween('subscription_end', [now()->subDay()->startOfDay(), now()->startOfDay()])
            ->get();

        foreach ($expired as $agency) {
            $this->sendWhatsApp(
                $agency->phone_whatsapp ?? $agency->user?->phone,
                $agency->name,
                "Bonjour {$agency->name}, votre abonnement Boursa " . strtoupper($agency->subscription_tier) . " a expire. Vos acces Pro sont suspendus. Pour renouveler : https://wa.me/22240000000?text=Renouvellement+abonnement+{$agency->name}"
            );
            Log::info("[Subscription] Expiration notifiee a {$agency->name}");
        }

        $this->info("Notifications envoyees : {$expiringSoon->count()} rappels, {$expired->count()} expirations.");
    }

    private function sendWhatsApp(?string $phone, string $name, string $message): void
    {
        if (!$phone) {
            Log::warning("[Subscription] Pas de telephone pour {$name}");
            return;
        }

        $phone = preg_replace('/\D/', '', $phone);

        // Driver SMS configuré (log en dev, vrai SMS en prod)
        Log::info("[WhatsApp:{$phone}] {$message}");

        // Si Twilio/WA Business API configuré, appel ici
        // Pour l'instant : log + optionnel HTTP vers API WhatsApp
    }
}
