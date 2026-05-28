<x-filament-panels::page>
<div style="display:flex; height:calc(100vh - 10rem); gap:0; border-radius:12px; overflow:hidden; border:1px solid #E2E8F0; background:white;">

    {{-- Sidebar --}}
    <div style="width:300px; flex-shrink:0; border-right:1px solid #E2E8F0; display:flex; flex-direction:column;">
        <div style="padding:16px; border-bottom:1px solid #E2E8F0; font-weight:700; font-size:14px; color:#0F172A;">
            Conversations
        </div>
        <div style="flex:1; overflow-y:auto;">
            @forelse($this->getConversations() as $conv)
                <button
                    wire:click="selectConversation('{{ $conv->id }}')"
                    style="width:100%; text-align:left; padding:12px 16px; border:none; border-bottom:1px solid #F1F5F9; cursor:pointer; background:{{ $activeConversationId === $conv->id ? '#F0FDF4' : 'white' }}; border-right:{{ $activeConversationId === $conv->id ? '3px solid #16A34A' : '3px solid transparent' }};"
                >
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width:36px; height:36px; border-radius:50%; background:#0F172A; color:white; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; flex-shrink:0;">
                            {{ strtoupper(substr($conv->user->name ?? 'U', 0, 2)) }}
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-weight:600; font-size:13px; color:#0F172A;">{{ $conv->user->name ?? 'Utilisateur' }}</span>
                                @if($conv->unreadForAgency() > 0)
                                    <span style="background:#EF4444; color:white; font-size:11px; font-weight:700; border-radius:100px; padding:2px 7px;">{{ $conv->unreadForAgency() }}</span>
                                @endif
                            </div>
                            <p style="font-size:12px; color:#64748B; margin:2px 0 0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:180px;">{{ $conv->lastMessage?->body ?? 'Aucun message' }}</p>
                        </div>
                    </div>
                </button>
            @empty
                <div style="padding:40px 20px; text-align:center; color:#94A3B8; font-size:13px;">
                    Aucune conversation
                </div>
            @endforelse
        </div>
    </div>

    {{-- Zone messages --}}
    <div style="flex:1; display:flex; flex-direction:column; min-width:0;">
        @if($activeConversationId)
            @php $conv = \App\Models\Conversation::with('user','agency')->find($activeConversationId); @endphp
            @if($conv)
            <div style="padding:12px 20px; border-bottom:1px solid #E2E8F0; display:flex; align-items:center; gap:10px; background:white;">
                <div style="width:32px; height:32px; border-radius:50%; background:#0F172A; color:white; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px;">
                    {{ strtoupper(substr($conv->user->name ?? 'U', 0, 2)) }}
                </div>
                <div>
                    <div style="font-weight:700; font-size:14px; color:#0F172A;">{{ $conv->user->name ?? 'Utilisateur' }}</div>
                    <div style="font-size:12px; color:#64748B;">Client</div>
                </div>
            </div>

            <div style="flex:1; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:10px; background:#F8FAFC;" id="messages-container">
                @foreach($this->getMessages() as $msg)
                    <div style="display:flex; justify-content:{{ $msg->sender_type === 'agency' ? 'flex-end' : 'flex-start' }};">
                        <div style="max-width:70%; padding:10px 14px; border-radius:14px; font-size:13px; line-height:1.5;
                            background:{{ $msg->sender_type === 'agency' ? '#16A34A' : 'white' }};
                            color:{{ $msg->sender_type === 'agency' ? 'white' : '#1E293B' }};
                            border:{{ $msg->sender_type === 'agency' ? 'none' : '1px solid #E2E8F0' }};
                            border-radius:{{ $msg->sender_type === 'agency' ? '14px 14px 4px 14px' : '14px 14px 14px 4px' }};">
                            {{ $msg->body }}
                            <div style="font-size:11px; opacity:0.6; margin-top:4px; text-align:right;">
                                {{ $msg->created_at->format('H:i') }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="padding:12px 16px; border-top:1px solid #E2E8F0; display:flex; gap:8px; background:white;">
                <input
                    type="text"
                    wire:model="newMessage"
                    wire:keydown.enter="sendMessage"
                    placeholder="Ecrire un message..."
                    style="flex:1; padding:10px 14px; border:1px solid #E2E8F0; border-radius:10px; font-size:13px; outline:none; background:#F8FAFC;"
                />
                <button
                    wire:click="sendMessage"
                    style="background:#16A34A; color:white; border:none; border-radius:10px; padding:10px 18px; cursor:pointer; font-size:13px; font-weight:700;"
                >
                    &#8594;
                </button>
            </div>
            @endif
        @else
            <div style="flex:1; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:12px; color:#94A3B8;">
                <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/></svg>
                <p style="font-size:14px;">Selectionnez une conversation</p>
            </div>
        @endif
    </div>
</div>

<script>
    document.addEventListener('livewire:updated', () => {
        const c = document.getElementById('messages-container');
        if (c) c.scrollTop = c.scrollHeight;
    });
    setInterval(() => { if (window.Livewire) window.Livewire.dispatch('refresh'); }, 5000);
</script>
</x-filament-panels::page>
