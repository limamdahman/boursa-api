<x-filament-panels::page>
@php
    $conversations = $this->getConversations();
    $messages = $this->getMessages();
@endphp

<div style="display:grid;grid-template-columns:300px 1fr;gap:0;height:calc(100vh - 200px);background:white;border:1px solid #E2E8F0;border-radius:12px;overflow:hidden;">
    <div style="border-inline-end:1px solid #E2E8F0;overflow-y:auto;">
        <div style="padding:16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:700;color:#0F172A;">
            Conversations ({{ $conversations->count() }})
        </div>
        @foreach($conversations as $conv)
        @php
            $name = $conv->agency?->name ?? $conv->user?->name ?? 'Inconnu';
            $last = $conv->lastMessage?->body ?? '';
            $isActive = $conv->id === $this->activeConvId;
        @endphp
        <div wire:click="selectConv('{{ $conv->id }}')"
             style="padding:12px 16px;cursor:pointer;border-bottom:1px solid #F1F5F9;background:{{ $isActive ? '#F0FDF4' : 'white' }};border-left:{{ $isActive ? '3px solid #16A34A' : '3px solid transparent' }};">
            <div style="font-size:13px;font-weight:700;color:#0F172A;">{{ $name }}</div>
            @if($conv->user && $conv->agency)
                <div style="font-size:10px;color:#16A34A;margin-bottom:2px;">{{ $conv->user->name }} ↔ {{ $conv->agency->name }}</div>
            @elseif($conv->user && !$conv->agency)
                <div style="font-size:10px;color:#7C3AED;margin-bottom:2px;">Support: {{ $conv->user->name }}</div>
            @endif
            <div style="font-size:11px;color:#64748B;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ Str::limit($last, 40) }}</div>
            @if($conv->last_message_at)
                <div style="font-size:10px;color:#94A3B8;margin-top:2px;">{{ $conv->last_message_at->diffForHumans() }}</div>
            @endif
        </div>
        @endforeach
    </div>

    <div style="display:flex;flex-direction:column;height:calc(100vh - 200px);">
        @if($this->activeConvId)
        @php $activeConv = $conversations->firstWhere('id', $this->activeConvId); @endphp
        <div style="padding:16px;border-bottom:1px solid #E2E8F0;font-weight:700;font-size:14px;color:#0F172A;flex-shrink:0;">
            {{ $activeConv?->agency?->name ?? ($activeConv?->user?->name ?? 'Conversation') }}
            @if($activeConv?->user && $activeConv?->agency)
                <span style="font-size:12px;color:#64748B;font-weight:400;"> — {{ $activeConv->user->name }}</span>
            @endif
        </div>

        <div style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;" id="msg-container">
            @foreach($messages as $msg)
            @php
                $isFromAgencyOrAdmin = $msg->sender_type === 'agency';
                $senderName = $isFromAgencyOrAdmin ? 'Admin/Agence' : ($activeConv?->user?->name ?? 'User');
            @endphp
            <div style="display:flex;flex-direction:column;align-items:{{ $isFromAgencyOrAdmin ? 'flex-end' : 'flex-start' }};">
                <div style="font-size:10px;color:#94A3B8;margin-bottom:2px;">{{ $senderName }}</div>
                <div style="max-width:70%;padding:10px 14px;font-size:13px;line-height:1.5;
                    border-radius:{{ $isFromAgencyOrAdmin ? '12px 12px 2px 12px' : '12px 12px 12px 2px' }};
                    background:{{ $isFromAgencyOrAdmin ? '#16A34A' : '#F1F5F9' }};
                    color:{{ $isFromAgencyOrAdmin ? 'white' : '#1E293B' }};">
                    {{ $msg->body }}
                </div>
                <div style="font-size:10px;color:#94A3B8;margin-top:2px;">{{ $msg->created_at->format('H:i') }}</div>
            </div>
            @endforeach
        </div>

        <div style="padding:12px 16px;border-top:1px solid #E2E8F0;display:flex;gap:8px;flex-shrink:0;">
            <input wire:model="newMessage" wire:keydown.enter="sendMessage"
                   placeholder="Écrire un message..."
                   style="flex:1;padding:10px 14px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;outline:none;" />
            <button wire:click="sendMessage"
                    style="background:#16A34A;color:white;border:none;border-radius:8px;padding:10px 20px;font-size:13px;font-weight:700;cursor:pointer;">
                Envoyer
            </button>
        </div>
        @else
        <div style="flex:1;display:flex;align-items:center;justify-content:center;color:#94A3B8;font-size:14px;">
            Sélectionner une conversation
        </div>
        @endif
    </div>
</div>

<script>
    document.addEventListener('livewire:updated', () => {
        const c = document.getElementById('msg-container');
        if (c) c.scrollTop = c.scrollHeight;
    });
</script>
</x-filament-panels::page>
