<x-filament-panels::page>
@php
    $stats   = $this->getStats();
    $vByM    = $this->getVehiclesByMonth();
    $lByM    = $this->getLeadsByMonth();
    $tiers   = $this->getSubscriptionsByTier();
    $expiring = $this->getExpiringAgencies();
    $expired  = $this->getExpiredAgencies();
    $vLabels  = collect($vByM)->pluck('label')->toJson();
    $vCounts  = collect($vByM)->pluck('count')->toJson();
    $lLabels  = collect($lByM)->pluck('label')->toJson();
    $lCounts  = collect($lByM)->pluck('count')->toJson();
@endphp

{{-- KPIs --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
    <div style="background:white;border:1px solid #E2E8F0;border-radius:12px;padding:20px;border-left:4px solid #16A34A;">
        <div style="font-size:11px;color:#64748B;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.05em;">Agences</div>
        <div style="font-size:36px;font-weight:800;color:#0F172A;">{{ $stats['agencies'] }}</div>
        <div style="font-size:12px;color:#16A34A;margin-top:4px;">{{ $stats['pro_active'] }} Pro/Business actifs</div>
    </div>
    <div style="background:white;border:1px solid #E2E8F0;border-radius:12px;padding:20px;border-left:4px solid #3B82F6;">
        <div style="font-size:11px;color:#64748B;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.05em;">Véhicules</div>
        <div style="font-size:36px;font-weight:800;color:#0F172A;">{{ $stats['vehicles'] }}</div>
        <div style="font-size:12px;color:#475569;margin-top:4px;">{{ $stats['active'] }} actifs · {{ $stats['sold'] }} vendus</div>
    </div>
    <div style="background:white;border:1px solid #E2E8F0;border-radius:12px;padding:20px;border-left:4px solid #8B5CF6;">
        <div style="font-size:11px;color:#64748B;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.05em;">Utilisateurs</div>
        <div style="font-size:36px;font-weight:800;color:#0F172A;">{{ $stats['users'] }}</div>
        <div style="font-size:12px;color:#475569;margin-top:4px;">Acheteurs inscrits</div>
    </div>
    <div style="background:white;border:1px solid #E2E8F0;border-radius:12px;padding:20px;border-left:4px solid #F59E0B;">
        <div style="font-size:11px;color:#64748B;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.05em;">Contacts ce mois</div>
        <div style="font-size:36px;font-weight:800;color:#0F172A;">{{ $stats['leads_month'] }}</div>
        <div style="font-size:12px;color:#475569;margin-top:4px;">{{ $stats['leads_total'] }} au total</div>
    </div>
</div>

{{-- Graphes --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
    <div style="background:white;border:1px solid #E2E8F0;border-radius:12px;padding:20px;">
        <h3 style="font-size:14px;font-weight:700;color:#0F172A;margin:0 0 16px;">Véhicules publiés (6 mois)</h3>
        <canvas id="vehiclesChart" height="180"></canvas>
    </div>
    <div style="background:white;border:1px solid #E2E8F0;border-radius:12px;padding:20px;">
        <h3 style="font-size:14px;font-weight:700;color:#0F172A;margin:0 0 16px;">Contacts/Leads (6 mois)</h3>
        <canvas id="leadsChart" height="180"></canvas>
    </div>
</div>

{{-- Abonnements par tier --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
    <div style="background:white;border:1px solid #E2E8F0;border-radius:12px;padding:20px;">
        <h3 style="font-size:14px;font-weight:700;color:#0F172A;margin:0 0 16px;">Répartition abonnements</h3>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:12px;height:12px;border-radius:50%;background:#94A3B8;"></div>
                    <span style="font-size:13px;color:#374151;">Gratuit</span>
                </div>
                <span style="font-size:14px;font-weight:700;">{{ $tiers['free'] }}</span>
            </div>
            <div style="background:#F1F5F9;border-radius:4px;height:6px;">
                <div style="background:#94A3B8;height:6px;border-radius:4px;width:{{ $stats['agencies'] > 0 ? round($tiers['free']/$stats['agencies']*100) : 0 }}%;"></div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:12px;height:12px;border-radius:50%;background:#3B82F6;"></div>
                    <span style="font-size:13px;color:#374151;">Pro</span>
                </div>
                <span style="font-size:14px;font-weight:700;">{{ $tiers['pro'] }}</span>
            </div>
            <div style="background:#F1F5F9;border-radius:4px;height:6px;">
                <div style="background:#3B82F6;height:6px;border-radius:4px;width:{{ $stats['agencies'] > 0 ? round($tiers['pro']/$stats['agencies']*100) : 0 }}%;"></div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:12px;height:12px;border-radius:50%;background:#F59E0B;"></div>
                    <span style="font-size:13px;color:#374151;">Business</span>
                </div>
                <span style="font-size:14px;font-weight:700;">{{ $tiers['business'] }}</span>
            </div>
            <div style="background:#F1F5F9;border-radius:4px;height:6px;">
                <div style="background:#F59E0B;height:6px;border-radius:4px;width:{{ $stats['agencies'] > 0 ? round($tiers['business']/$stats['agencies']*100) : 0 }}%;"></div>
            </div>
        </div>
    </div>

    {{-- Abonnements expirés avec action inline --}}
    <div style="background:white;border:1px solid #E2E8F0;border-radius:12px;padding:20px;">
        <h3 style="font-size:14px;font-weight:700;color:#0F172A;margin:0 0 16px;">
            Abonnements à renouveler
            @if($expired->count() > 0)
                <span style="background:#FEE2E2;color:#DC2626;font-size:11px;padding:2px 8px;border-radius:100px;margin-left:8px;">{{ $expired->count() }} expirés</span>
            @endif
            @if($expiring->count() > 0)
                <span style="background:#FEF9C3;color:#D97706;font-size:11px;padding:2px 8px;border-radius:100px;margin-left:4px;">{{ $expiring->count() }} bientôt</span>
            @endif
        </h3>
        <div style="display:flex;flex-direction:column;gap:8px;max-height:200px;overflow-y:auto;">
            @foreach($expired->merge($expiring) as $a)
            <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#F8FAFC;border-radius:8px;border:1px solid #E2E8F0;">
                <div>
                    <div style="font-size:13px;font-weight:700;color:#0F172A;">{{ $a->name }}</div>
                    <div style="font-size:11px;color:#{{ $a->subscription_end && now()->isAfter($a->subscription_end) ? 'DC2626' : 'D97706' }};">
                        {{ $a->subscription_end ? ($a->subscription_end->isPast() ? 'Expiré le '.$a->subscription_end->format('d/m/Y') : 'Expire le '.$a->subscription_end->format('d/m/Y')) : 'Sans date' }}
                    </div>
                </div>
                <button
                    onclick="openRenewModal('{{ $a->id }}', '{{ addslashes($a->name) }}', '{{ $a->subscription_tier }}')"
                    style="background:#16A34A;color:white;border:none;border-radius:6px;padding:6px 14px;font-size:12px;font-weight:700;cursor:pointer;">
                    Renouveler
                </button>
            </div>
            @endforeach
            @if($expired->count() === 0 && $expiring->count() === 0)
                <div style="text-align:center;color:#94A3B8;font-size:13px;padding:20px;">Tous les abonnements sont actifs ✓</div>
            @endif
        </div>
    </div>
</div>

{{-- Dernières activités --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <div style="background:white;border:1px solid #E2E8F0;border-radius:12px;padding:20px;">
        <h3 style="font-size:14px;font-weight:700;color:#0F172A;margin:0 0 16px;">Dernières agences</h3>
        @foreach(\App\Models\Agency::latest()->limit(6)->get() as $a)
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F1F5F9;">
            <div>
                <div style="font-size:13px;font-weight:600;color:#0F172A;">{{ $a->name }}</div>
                <div style="font-size:11px;color:#64748B;">{{ $a->created_at->format('d/m/Y') }}</div>
            </div>
            <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:100px;
                background:{{ $a->subscription_tier === 'business' ? 'linear-gradient(135deg,#F59E0B,#D97706)' : ($a->subscription_tier === 'pro' ? '#EFF6FF' : '#F1F5F9') }};
                color:{{ $a->subscription_tier === 'business' ? 'white' : ($a->subscription_tier === 'pro' ? '#2563EB' : '#94A3B8') }};">
                {{ strtoupper($a->subscription_tier) }}
            </span>
        </div>
        @endforeach
    </div>
    <div style="background:white;border:1px solid #E2E8F0;border-radius:12px;padding:20px;">
        <h3 style="font-size:14px;font-weight:700;color:#0F172A;margin:0 0 16px;">Derniers leads</h3>
        @foreach(\App\Models\Lead::with('agency')->latest()->limit(6)->get() as $l)
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F1F5F9;">
            <div>
                <div style="font-size:13px;font-weight:600;color:#0F172A;">{{ $l->agency?->name ?? 'Particulier' }}</div>
                <div style="font-size:11px;color:#64748B;">{{ $l->type ?? 'message' }} · {{ $l->created_at->format('d/m/Y H:i') }}</div>
            </div>
            <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:100px;background:#F0FDF4;color:#16A34A;">{{ $l->type ?? 'lead' }}</span>
        </div>
        @endforeach
    </div>
</div>

{{-- Modal Renouveler --}}
<div id="renew-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:16px;padding:32px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <h3 style="font-size:18px;font-weight:800;color:#0F172A;margin:0 0 6px;" id="modal-agency-name"></h3>
        <p style="font-size:13px;color:#64748B;margin:0 0 24px;">Choisir le nouveau plan et la durée</p>

        <div style="margin-bottom:16px;">
            <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Plan</label>
            <select id="renew-tier" style="width:100%;padding:10px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:14px;outline:none;background:white;">
                <option value="free">Gratuit</option>
                <option value="pro" selected>Pro</option>
                <option value="business">Business (GOLD)</option>
            </select>
        </div>

        <div style="margin-bottom:24px;">
            <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Durée</label>
            <select id="renew-months" style="width:100%;padding:10px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:14px;outline:none;background:white;">
                <option value="1">1 mois</option>
                <option value="3" selected>3 mois</option>
                <option value="6">6 mois</option>
                <option value="12">12 mois</option>
                <option value="24">24 mois</option>
            </select>
        </div>

        <div id="renew-preview" style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;padding:12px;margin-bottom:20px;font-size:13px;color:#15803D;"></div>

        <div style="display:flex;gap:10px;">
            <button onclick="confirmRenew()" style="flex:1;background:#16A34A;color:white;border:none;border-radius:8px;padding:12px;font-size:14px;font-weight:700;cursor:pointer;">
                Confirmer le renouvellement
            </button>
            <button onclick="document.getElementById('renew-modal').style.display='none'" style="background:#F1F5F9;color:#374151;border:none;border-radius:8px;padding:12px 20px;font-size:14px;font-weight:600;cursor:pointer;">
                Annuler
            </button>
        </div>
    </div>
</div>

{{-- Scripts Chart.js + Modal --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
let currentAgencyId = null;

// Véhicules chart
new Chart(document.getElementById('vehiclesChart'), {
    type: 'bar',
    data: {
        labels: {!! $vLabels !!},
        datasets: [{
            label: 'Véhicules',
            data: {!! $vCounts !!},
            backgroundColor: 'rgba(22,163,74,0.7)',
            borderRadius: 6,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Leads chart
new Chart(document.getElementById('leadsChart'), {
    type: 'line',
    data: {
        labels: {!! $lLabels !!},
        datasets: [{
            label: 'Leads',
            data: {!! $lCounts !!},
            borderColor: '#F59E0B',
            backgroundColor: 'rgba(245,158,11,0.1)',
            tension: 0.4,
            fill: true,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

function openRenewModal(id, name, currentTier) {
    currentAgencyId = id;
    document.getElementById('modal-agency-name').textContent = name;
    document.getElementById('renew-tier').value = currentTier === 'free' ? 'pro' : currentTier;
    updatePreview();
    document.getElementById('renew-modal').style.display = 'flex';
}

function updatePreview() {
    const tier = document.getElementById('renew-tier').value;
    const months = parseInt(document.getElementById('renew-months').value);
    const end = new Date();
    end.setMonth(end.getMonth() + months);
    const tierLabel = tier === 'business' ? 'Business GOLD' : tier === 'pro' ? 'Pro' : 'Gratuit';
    document.getElementById('renew-preview').textContent =
        tierLabel + ' · ' + months + ' mois · Expire le ' + end.toLocaleDateString('fr-FR');
}

document.getElementById('renew-tier').addEventListener('change', updatePreview);
document.getElementById('renew-months').addEventListener('change', updatePreview);

async function confirmRenew() {
    const tier = document.getElementById('renew-tier').value;
    const months = parseInt(document.getElementById('renew-months').value);
    const btn = document.querySelector('#renew-modal button');
    btn.disabled = true;
    btn.textContent = 'Traitement...';

    await window.Livewire.dispatch('renewAgency', { agencyId: currentAgencyId, tier: tier, months: months });

    document.getElementById('renew-modal').style.display = 'none';
    btn.disabled = false;
    btn.textContent = 'Confirmer le renouvellement';
    setTimeout(() => location.reload(), 1000);
}

// Fermer modal en cliquant dehors
document.getElementById('renew-modal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>
</x-filament-panels::page>
