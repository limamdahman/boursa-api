{{-- Overlay Pro requis --}}
<div style="position:relative; margin-top:16px;">
    {{-- Contenu flouté --}}
    <div style="filter:blur(6px); pointer-events:none; user-select:none; opacity:0.7;">
        <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:20px;">
            @foreach(['Personnes intéressées ce mois','Clients qui vous ont contacté','Voitures en vente','Clients pour 100 visiteurs'] as $label)
            <div style="background:white; border:1px solid #E2E8F0; border-radius:12px; padding:20px;">
                <div style="font-size:13px; color:#64748B; margin-bottom:8px;">{{ $label }}</div>
                <div style="font-size:28px; font-weight:800; color:#0F172A;">{{ rand(100,999) }}</div>
                <div style="font-size:12px; color:#16A34A; margin-top:6px;">+{{ rand(1,99) }}% par rapport au mois dernier</div>
            </div>
            @endforeach
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div style="background:white; border:1px solid #E2E8F0; border-radius:12px; padding:20px; height:200px; display:flex; align-items:center; justify-content:center;">
                <div style="width:100%; height:120px; background:linear-gradient(90deg,#BBF7D0,#16A34A,#BBF7D0); border-radius:8px; opacity:0.4;"></div>
            </div>
            <div style="background:white; border:1px solid #E2E8F0; border-radius:12px; padding:20px; height:200px; display:flex; align-items:center; justify-content:center; gap:12px;">
                @foreach(['#3B82F6','#16A34A','#22C55E'] as $c)
                <div style="width:60px; background:{{ $c }}; border-radius:6px 6px 0 0; height:{{ rand(60,140) }}px; opacity:0.5;"></div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Overlay --}}
    <div style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:16px; background:rgba(255,255,255,0.5); border-radius:12px;">
        <div style="background:white; border:1px solid #E2E8F0; border-radius:16px; padding:32px 40px; text-align:center; box-shadow:0 8px 32px rgba(0,0,0,0.12); max-width:420px;">
            <div style="font-size:40px; margin-bottom:12px;">🔒</div>
            <h3 style="font-size:18px; font-weight:800; color:#0F172A; margin:0 0 8px;">Statistiques Pro</h3>
            <p style="font-size:14px; color:#475569; margin:0 0 20px; line-height:1.6;">
                Accédez à vos statistiques complètes, graphes de performance et analyse des contacts avec un abonnement Pro ou Business.
            </p>
            <a href="/agence/subscription" style="display:inline-block; background:#16A34A; color:white; padding:12px 28px; border-radius:8px; font-weight:700; font-size:14px; text-decoration:none;">
                Voir les offres
            </a>
        </div>
    </div>
</div>
