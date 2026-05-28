<x-filament-panels::page>
<div style="max-width:480px;">
    <div style="background:white; border:1px solid #E2E8F0; border-radius:12px; padding:28px;">
        <h2 style="font-size:16px; font-weight:700; color:#0F172A; margin:0 0 24px;">Changer le mot de passe</h2>

        <div style="margin-bottom:16px;">
            <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px;">Mot de passe actuel</label>
            <input type="password" wire:model="current_password" style="width:100%; padding:10px 14px; border:1px solid #D1D5DB; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box;" />
        </div>

        <div style="margin-bottom:16px;">
            <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px;">Nouveau mot de passe</label>
            <input type="password" wire:model="new_password" style="width:100%; padding:10px 14px; border:1px solid #D1D5DB; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box;" />
        </div>

        <div style="margin-bottom:24px;">
            <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px;">Confirmer le nouveau mot de passe</label>
            <input type="password" wire:model="new_password_confirmation" style="width:100%; padding:10px 14px; border:1px solid #D1D5DB; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box;" />
        </div>

        <button wire:click="save" style="background:#16A34A; color:white; border:none; border-radius:8px; padding:12px 24px; font-size:14px; font-weight:700; cursor:pointer;">
            Mettre à jour
        </button>
    </div>
</div>
</x-filament-panels::page>
