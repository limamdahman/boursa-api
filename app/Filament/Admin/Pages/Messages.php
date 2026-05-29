<?php
declare(strict_types=1);
namespace App\Filament\Admin\Pages;

use App\Models\ChatMessage;
use App\Models\Conversation;
use Filament\Pages\Page;

class Messages extends Page
{
    protected string $view = 'filament.admin.pages.messages';
    protected static ?string $navigationLabel = 'Messages';
    protected static ?int $navigationSort = 2;

    public ?string $activeConvId = null;
    public string $newMessage = '';

    public static function getNavigationBadge(): ?string
    {
        // Uniquement les conversations support (agency_id = null)
        $count = ChatMessage::whereHas('conversation', fn ($q) => $q->whereNull('agency_id'))
            ->where('sender_type', 'user')
            ->whereNull('read_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public function mount(): void
    {
        // Uniquement les conversations support
        $conv = Conversation::whereNull('agency_id')
            ->orderByDesc('last_message_at')
            ->first();

        if ($conv) {
            $this->activeConvId = $conv->id;
        }
    }

    public function getConversations(): \Illuminate\Support\Collection
    {
        // Uniquement les conversations support (agency_id = null)
        return Conversation::with(['user', 'lastMessage'])
            ->whereNull('agency_id')
            ->orderByDesc('last_message_at')
            ->get();
    }

    public function getMessages(): \Illuminate\Support\Collection
    {
        if (!$this->activeConvId) return collect();

        // Marquer les messages user comme lus
        ChatMessage::where('conversation_id', $this->activeConvId)
            ->where('sender_type', 'user')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ChatMessage::where('conversation_id', $this->activeConvId)
            ->orderBy('created_at')
            ->get();
    }

    public function selectConv(string $id): void
    {
        $this->activeConvId = $id;
    }

    public function sendMessage(): void
    {
        if (!$this->activeConvId || !trim($this->newMessage)) return;

        $admin = auth()->user();

        ChatMessage::create([
            'conversation_id' => $this->activeConvId,
            'sender_type'     => 'agency',
            'sender_id'       => $admin->id,
            'body'            => trim($this->newMessage),
        ]);

        Conversation::where('id', $this->activeConvId)
            ->update(['last_message_at' => now()]);

        $this->newMessage = '';
    }
}
