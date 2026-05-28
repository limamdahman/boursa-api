<?php
declare(strict_types=1);
namespace App\Filament\Agency\Pages;

use App\Models\ChatMessage;
use App\Models\Conversation;
use BackedEnum;
use App\Filament\Agency\Concerns\ChecksSubscription;
use Filament\Pages\Page;

class Messages extends Page
{
    use ChecksSubscription;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Messages';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.agency.pages.messages';

    public ?string $activeConversationId = null;
    public string $newMessage = '';

    public function mount(): void
    {
        $this->activeConversationId = request()->query('conversation');
    }

    public function getConversations(): \Illuminate\Support\Collection
    {
        $agency = auth()->user()?->agency;
        if (!$agency) return collect();

        return Conversation::with(['user', 'lastMessage'])
            ->where('agency_id', $agency->id)
            ->where('status', 'open')
            ->orderByDesc('last_message_at')
            ->get();
    }

    public function getMessages(): \Illuminate\Support\Collection
    {
        if (!$this->activeConversationId) return collect();

        $conversation = Conversation::find($this->activeConversationId);
        if (!$conversation) return collect();

        // Marquer les messages user comme lus
        ChatMessage::where('conversation_id', $this->activeConversationId)
            ->where('sender_type', 'user')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $conversation->messages()->get();
    }

    public function selectConversation(string $id): void
    {
        $this->activeConversationId = $id;
        $this->newMessage = '';
    }

    public function sendMessage(): void
    {
        if (!$this->activeConversationId || trim($this->newMessage) === '') return;

        $user = auth()->user();
        $conversation = Conversation::find($this->activeConversationId);
        if (!$conversation) return;

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => 'agency',
            'sender_id'       => $user->id,
            'body'            => trim($this->newMessage),
        ]);

        $conversation->update(['last_message_at' => now()]);
        $this->newMessage = '';
    }

    public function getUnreadCount(): int
    {
        $agency = auth()->user()?->agency;
        if (!$agency) return 0;

        return ChatMessage::whereHas('conversation', fn ($q) => $q->where('agency_id', $agency->id))
            ->where('sender_type', 'user')
            ->whereNull('read_at')
            ->count();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::isActivePro();
    }

    public static function getNavigationBadge(): ?string
    {
        $agency = auth()->user()?->agency;
        if (!$agency) return null;

        $count = ChatMessage::whereHas('conversation', fn ($q) => $q->where('agency_id', $agency->id))
            ->where('sender_type', 'user')
            ->whereNull('read_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
