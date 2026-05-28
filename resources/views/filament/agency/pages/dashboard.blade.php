<x-filament-panels::page>

@if($this->isPro())
    {{-- Dashboard complet Pro/Business --}}
    <x-filament-widgets::widgets
        :widgets="$this->getWidgets()"
        :columns="$this->getColumns()"
    />
@else
    {{-- KPIs basiques gratuit --}}
    @php
        $agency = auth()->user()?->agency;
        $vehicleCount = $agency ? \App\Models\Vehicle::where('agency_id', $agency->id)->where('status', 'active')->count() : 0;
        $leadCount = $agency ? \App\Models\Lead::where('agency_id', $agency->id)->where('created_at', '>=', now()->subDays(30))->count() : 0;
    @endphp

    <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:16px; margin-bottom:24px;">
        <div style="background:white; border:1px solid #E2E8F0; border-radius:12px; padding:24px;">
            <div style="font-size:13px; color:#64748B; margin-bottom:8px;">Voitures en vente</div>
            <div style="font-size:32px; font-weight:800; color:#0F172A;">{{ $vehicleCount }}</div>
        </div>
        <div style="background:white; border:1px solid #E2E8F0; border-radius:12px; padding:24px;">
            <div style="font-size:13px; color:#64748B; margin-bottom:8px;">Contacts ce mois</div>
            <div style="font-size:32px; font-weight:800; color:#0F172A;">{{ $leadCount }}</div>
        </div>
    </div>

    {{-- Bloc flouté avec overlay --}}
    <div style="position:relative;">
        <div style="filter:blur(5px); pointer-events:none; user-select:none; opacity:0.6;">
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:20px;">
                @foreach(['Personnes intéressées','Taux de conversion','Vues totales','Leads qualifiés'] as $label)
                <div style="background:white; border:1px solid #E2E8F0; border-radius:12px; padding:20px;">
                    <div style="font-size:12px; color:#64748B; margin-bottom:6px;">{{ $label }}</div>
                    <div style="font-size:26px; font-weight:800; color:#0F172A;">{{ rand(100,999) }}</div>
                    <div style="font-size:12px; color:#16A34A; margin-top:4px;">+{{ rand(1,50) }}%</div>
                </div>
                @endforeach
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div style="background:white; border:1px solid #E2E8F0; border-radius:12px; padding:20px; height:180px;">
                    <div style="font-size:13px; font-weight:600; color:#0F172A; margin-bottom:12px;">Visites par jour</div>
                    <div style="display:flex; align-items:flex-end; gap:4px; height:100px;">
                        @for($i=0; $i<20; $i++)
                        <div style="flex:1; background:#BBF7D0; border-radius:3px 3px 0 0; height:{{ rand(20,90) }}px;"></div>
                        @endfor
                    </div>
                </div>
                <div style="background:white; border:1px solid #E2E8F0; border-radius:12px; padding:20px; height:180px;">
                    <div style="font-size:13px; font-weight:600; color:#0F172A; margin-bottom:12px;">Contacts par type</div>
                    <div style="display:flex; align-items:flex-end; justify-content:center; gap:16px; height:100px;">
                        @foreach(['#3B82F6','#16A34A','#22C55E'] as $c)
                        <div style="width:40px; background:{{ $c }}; border-radius:6px 6px 0 0; height:{{ rand(40,90) }}px; opacity:0.6;"></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Overlay --}}
        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;">
            <div style="background:white; border:1px solid #E2E8F0; border-radius:16px; padding:32px 40px; text-align:center; box-shadow:0 8px 32px rgba(0,0,0,0.12); max-width:400px;">
                <div style="font-size:36px; margin-bottom:12px;">🔒</div>
                <h3 style="font-size:18px; font-weight:800; color:#0F172A; margin:0 0 8px;">Statistiques Pro</h3>
                <p style="font-size:14px; color:#475569; margin:0 0 20px; line-height:1.6;">
                    Accédez aux statistiques complètes, graphes de performance et analyse des contacts avec un abonnement Pro ou Business.
                </p>
                <a href="/agence/subscription" style="display:inline-block; background:#16A34A; color:white; padding:12px 28px; border-radius:8px; font-weight:700; font-size:14px; text-decoration:none;">
                    Voir les offres Pro
                </a>
            </div>
        </div>
    </div>
@endif

</x-filament-panels::page>
