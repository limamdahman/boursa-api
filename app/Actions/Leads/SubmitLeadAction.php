<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\Enums\LeadType;
use App\Enums\MetaEventType;
use App\Jobs\Meta\SendMetaCapiEventJob;
use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\NewLeadOnVehicleNotification;
use Filament\Notifications\Notification as FilamentNotification;
use App\Services\Meta\MetaEventBuilder;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SubmitLeadAction
{
    public function __construct(private readonly MetaEventBuilder $builder) {}

    public function execute(
        Vehicle $vehicle,
        LeadType $type,
        Request $request,
        ?User $user = null,
        ?string $message = null,
        ?string $senderName = null,
        ?string $senderPhone = null,
        ?string $eventId = null,
    ): Lead {
        if ($vehicle->status?->value !== 'active') {
            throw new DomainException('Ce véhicule n\'est plus disponible.');
        }

        return DB::transaction(function () use (
            $vehicle, $type, $request, $user, $message, $senderName, $senderPhone, $eventId
        ): Lead {
            $lead = Lead::create([
                'vehicle_id' => $vehicle->id,
                'agency_id' => $vehicle->agency_id,
                'sender_user_id' => $user?->id,
                'sender_name' => $senderName ?? $user?->name,
                'sender_phone' => $senderPhone ?? $user?->phone,
                'type' => $type,
                'message' => $message,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Vehicle::where('id', $vehicle->id)->increment('contacts_count');

            AnalyticsEvent::create([
                'user_id' => $user?->id,
                'vehicle_id' => $vehicle->id,
                'event_type' => 'lead',
                'metadata' => [
                    'lead_id' => $lead->id,
                    'lead_type' => $type->value,
                    'agency_id' => $vehicle->agency_id,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'fbclid' => $request->query('fbclid'),
                'utm_source' => $request->query('utm_source'),
                'utm_campaign' => $request->query('utm_campaign'),
            ]);

            $payload = $this->builder->build(
                eventType: MetaEventType::LEAD,
                request: $request,
                user: $user,
                customData: [
                    'content_type' => 'vehicle',
                    'content_ids' => [$vehicle->id],
                    'content_name' => trim(sprintf(
                        '%s %s %d',
                        $vehicle->brand?->name ?? '',
                        $vehicle->vehicleModel?->name ?? '',
                        $vehicle->year
                    )),
                    'content_category' => $vehicle->body_type ?? 'vehicle',
                    'currency' => $vehicle->currency,
                    'value' => $vehicle->price_mru,
                    'lead_type' => $type->value,
                    'agency_id' => $vehicle->agency_id,
                ],
                eventId: $eventId ?? 'lead_'.$lead->id,
                sourceUrl: $request->fullUrl(),
            );

            SendMetaCapiEventJob::dispatch($payload);

            // Notifier le propriétaire du véhicule
            // Priorité : agency.user (si annonce d'agence) sinon vehicle.user (si particulier)
            $owner = $vehicle->agency?->user ?? $vehicle->user;
            if ($owner && (!$user || $owner->id !== $user->id)) {
                $owner->notify(new NewLeadOnVehicleNotification($lead, $vehicle));

                // Notification Filament native pour la cloche du panel agence
                $brand = $vehicle->brand?->name ?? '';
                $model = $vehicle->vehicleModel?->name ?? '';
                $year = $vehicle->year ?? '';
                $vehicleLabel = trim($brand . ' ' . $model . ' ' . $year);

                FilamentNotification::make()
                    ->title('Nouveau contact')
                    ->body($lead->sender_name . ' est intéressé par votre ' . $vehicleLabel . ($lead->sender_phone ? ' • ' . $lead->sender_phone : ''))
                    ->icon('heroicon-o-envelope')
                    ->iconColor('warning')
                    ->actions([
                        \Filament\Actions\Action::make('view')
                            ->label('Voir le contact')
                            ->url('/agence/leads')
                            ->markAsRead(),
                    ])
                    ->sendToDatabase($owner);
            }

            return $lead->fresh();
        });
    }
}
