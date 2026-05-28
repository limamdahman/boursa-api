<x-filament-panels::page>
@php
    $agency = auth()->user()?->agency;
    $tier = $agency?->subscription_tier ?? 'free';
    $daysLeft = 0;
    $expired = true;
    if ($agency?->subscription_end) {
        $daysLeft = max(0, (int) now()->diffInDays($agency->subscription_end, false));
        $expired = now()->isAfter($agency->subscription_end);
    }
    $tierLabels = ['free' => 'Gratuit', 'pro' => 'Pro', 'business' => 'Business'];
    $tierColors = ['free' => '#64748B', 'pro' => '#D97706', 'business' => '#16A34A'];
    $color = $tierColors[$tier] ?? '#64748B';
@endphp

<div style="max-width:900px; display:flex; flex-direction:column; gap:24px;">

    {{-- Abonnement actuel --}}
    @if($tier !== 'free')
    <div style="background:white; border:1px solid #E2E8F0; border-radius:14px; padding:28px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
            <h2 style="font-size:16px; font-weight:700; color:#0F172A; margin:0;">Abonnement actuel</h2>
            <span style="background:{{ $color }}20; color:{{ $color }}; font-weight:700; font-size:13px; padding:4px 14px; border-radius:100px; border:1px solid {{ $color }}40;">
                {{ $tierLabels[$tier] }}
            </span>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-bottom:20px;">
            <div style="background:#F8FAFC; border-radius:10px; padding:16px; text-align:center;">
                <div style="font-size:28px; font-weight:800; color:{{ $color }};">{{ $daysLeft }}</div>
                <div style="font-size:12px; color:#64748B; margin-top:4px;">Jours restants</div>
            </div>
            <div style="background:#F8FAFC; border-radius:10px; padding:16px; text-align:center;">
                <div style="font-size:14px; font-weight:700; color:#0F172A;">{{ $agency->subscription_start ? \Carbon\Carbon::parse($agency->subscription_start)->format('d/m/Y') : '—' }}</div>
                <div style="font-size:12px; color:#64748B; margin-top:4px;">Début</div>
            </div>
            <div style="background:#F8FAFC; border-radius:10px; padding:16px; text-align:center;">
                <div style="font-size:14px; font-weight:700; color:{{ $expired ? '#EF4444' : '#0F172A' }};">{{ $agency->subscription_end ? \Carbon\Carbon::parse($agency->subscription_end)->format('d/m/Y') : '—' }}</div>
                <div style="font-size:12px; color:#64748B; margin-top:4px;">Expiration</div>
            </div>
        </div>
        @if($expired)
            <div style="background:#FEF2F2; border:1px solid #FECACA; border-radius:10px; padding:14px; font-size:14px; color:#991B1B; margin-bottom:16px;">
                Votre abonnement a expiré. Contactez-nous pour le renouveler.
            </div>
        @elseif($daysLeft <= 7)
            <div style="background:#FEF9C3; border:1px solid #FDE047; border-radius:10px; padding:14px; font-size:14px; color:#713F12; margin-bottom:16px;">
                Votre abonnement expire dans {{ $daysLeft }} jours. Pensez à le renouveler.
            </div>
        @endif
    </div>
    @endif

    {{-- Grille des 3 tiers --}}
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;" class="pricing-grid">

        {{-- Gratuit --}}
        <div style="border:{{ $tier === 'free' ? '2px solid #64748B' : '1px solid #E2E8F0' }}; border-radius:16px; padding:28px; background:white; position:relative;">
            @if($tier === 'free')
                <div style="position:absolute; top:-12px; left:50%; transform:translateX(-50%); background:#64748B; color:white; padding:4px 16px; border-radius:100px; font-size:12px; font-weight:700; white-space:nowrap;">Plan actuel</div>
            @endif
            <div style="font-size:13px; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Gratuit</div>
            <div style="font-size:32px; font-weight:800; color:#0F172A; margin-bottom:4px;">0 <span style="font-size:14px; font-weight:500; color:#64748B;">MRU/mois</span></div>
            <p style="font-size:13px; color:#64748B; margin:0 0 20px; line-height:1.5;">Pour démarrer et tester la plateforme</p>
            <div style="border-top:1px solid #E2E8F0; padding-top:20px; display:flex; flex-direction:column; gap:10px; margin-bottom:28px;">
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#374151;"><span style="color:#16A34A; font-weight:700;">✓</span> Jusqu'à 10 annonces</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#374151;"><span style="color:#16A34A; font-weight:700;">✓</span> Page agence publique</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#374151;"><span style="color:#16A34A; font-weight:700;">✓</span> Contact WhatsApp</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#94A3B8;"><span style="color:#CBD5E1;">✗</span> Statistiques avancées</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#94A3B8;"><span style="color:#CBD5E1;">✗</span> Chat prospects</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#94A3B8;"><span style="color:#CBD5E1;">✗</span> Mise en avant</div>
            </div>
            <div style="background:#F1F5F9; color:#374151; border-radius:8px; padding:12px; text-align:center; font-weight:700; font-size:13px;">Plan actuel</div>
        </div>

        {{-- Pro --}}
        <div style="border:{{ $tier === 'pro' ? '2px solid #D97706' : '2px solid #16A34A' }}; border-radius:16px; padding:28px; background:white; position:relative;">
            @if($tier === 'pro')
                <div style="position:absolute; top:-12px; left:50%; transform:translateX(-50%); background:#D97706; color:white; padding:4px 16px; border-radius:100px; font-size:12px; font-weight:700; white-space:nowrap;">Plan actuel</div>
            @else
                <div style="position:absolute; top:-12px; left:50%; transform:translateX(-50%); background:#16A34A; color:white; padding:4px 16px; border-radius:100px; font-size:12px; font-weight:700; white-space:nowrap;">Le plus populaire</div>
            @endif
            <div style="font-size:13px; font-weight:700; color:#15803D; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">Pro</div>
            <div style="font-size:32px; font-weight:800; color:#0F172A; margin-bottom:4px;">Sur devis</div>
            <p style="font-size:13px; color:#64748B; margin:0 0 20px; line-height:1.5;">Pour les agences actives qui veulent croître</p>
            <div style="border-top:1px solid #E2E8F0; padding-top:20px; display:flex; flex-direction:column; gap:10px; margin-bottom:28px;">
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#374151;"><span style="color:#16A34A; font-weight:700;">✓</span> Annonces illimitées</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#374151;"><span style="color:#16A34A; font-weight:700;">✓</span> Page agence premium</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#374151;"><span style="color:#16A34A; font-weight:700;">✓</span> Statistiques avancées</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#374151;"><span style="color:#16A34A; font-weight:700;">✓</span> Chat prospects</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#94A3B8;"><span style="color:#CBD5E1;">✗</span> Mise en avant prioritaire</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#94A3B8;"><span style="color:#CBD5E1;">✗</span> Badge Partenaire officiel</div>
            </div>
            @if($tier === 'pro')
                <a target="_blank" href="https://wa.me/22240000000?text=Bonjour+Boursa,+je+souhaite+renouveler+mon+abonnement+Pro" style="display:block; text-align:center; background:#D97706; color:white; border-radius:8px; padding:12px; font-weight:700; text-decoration:none; font-size:13px;">Renouveler</a>
            @else
                <a target="_blank" href="https://wa.me/22240000000?text=Bonjour+Boursa,+je+souhaite+passer+au+plan+Pro" style="display:block; text-align:center; background:#16A34A; color:white; border-radius:8px; padding:12px; font-weight:700; text-decoration:none; font-size:13px;">Passer à Pro</a>
            @endif
        </div>

        {{-- Business --}}
        <div style="border:{{ $tier === 'business' ? '2px solid #16A34A' : '1px solid #E2E8F0' }}; border-radius:16px; padding:28px; background:#0F172A; position:relative;">
            @if($tier === 'business')
                <div style="position:absolute; top:-12px; left:50%; transform:translateX(-50%); background:#16A34A; color:white; padding:4px 16px; border-radius:100px; font-size:12px; font-weight:700; white-space:nowrap;">Plan actuel</div>
            @endif
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;"><div style="font-size:13px; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:0.05em;">Business</div><span style="background:linear-gradient(135deg,#F59E0B,#D97706); color:white; font-size:11px; font-weight:800; padding:2px 10px; border-radius:100px; letter-spacing:0.05em;">GOLD ✦</span></div>
            <div style="font-size:32px; font-weight:800; color:white; margin-bottom:4px;">Sur devis</div>
            <p style="font-size:13px; color:#64748B; margin:0 0 20px; line-height:1.5;">Pour les grands groupes et concessionnaires</p>
            <div style="border-top:1px solid #1E293B; padding-top:20px; display:flex; flex-direction:column; gap:10px; margin-bottom:28px;">
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#E2E8F0;"><span style="color:#16A34A; font-weight:700;">✓</span> Tout le plan Pro</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#E2E8F0;"><span style="color:#16A34A; font-weight:700;">✓</span> Mise en avant prioritaire</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#E2E8F0;"><span style="color:#16A34A; font-weight:700;">✓</span> Badge Partenaire officiel</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#E2E8F0;"><span style="color:#16A34A; font-weight:700;">✓</span> Account manager dédié</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#E2E8F0;"><span style="color:#16A34A; font-weight:700;">✓</span> Intégration API stock</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#E2E8F0;"><span style="color:#16A34A; font-weight:700;">✓</span> Rapports mensuels</div>
                <div style="display:flex; gap:10px; align-items:center; font-size:13px; color:#E2E8F0;"><span style="color:#16A34A; font-weight:700;">✓</span> Facebook Ads offerts 🎁</div>
            </div>
            @if($tier === 'business')
                <a target="_blank" href="https://wa.me/22240000000?text=Bonjour+Boursa,+je+souhaite+renouveler+mon+abonnement+Business" style="display:block; text-align:center; background:#16A34A; color:white; border-radius:8px; padding:12px; font-weight:700; text-decoration:none; font-size:13px;">Renouveler</a>
            @else
                <a target="_blank" href="https://wa.me/22240000000?text=Bonjour+Boursa,+je+souhaite+passer+au+plan+Business" style="display:block; text-align:center; background:#16A34A; color:white; border-radius:8px; padding:12px; font-weight:700; text-decoration:none; font-size:13px;">Nous contacter</a>
            @endif
        </div>
    </div>
</div>

<style>
@media (max-width: 768px) { .pricing-grid { grid-template-columns: 1fr !important; } }
</style>
</x-filament-panels::page>
