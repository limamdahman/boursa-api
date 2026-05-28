<?php
declare(strict_types=1);
namespace App\Filament\Admin\Pages;

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
        $count = \App\Models\ChatMessage::where('sender_type', 'user')->whereNull('read_at')->count();
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
        $conversations = Conversation::orderByDesc('last_message_at')->get();
        if ($conversations->isNotEmpty()) {
            $this->activeConvId = $conversations->first()->id;
        }
    }

    public function getConversations(): \Illuminate\Support\Collection
    {
        return Conversation::with(['user', 'agency', 'lastMessage'])
            ->orderByDesc('last_message_at')
            ->get();
    }

    public function getMessages(): \Illuminate\Support\Collection
    {
        if (!$this->activeConvId) return collect();
        return \App\Models\ChatMessage::where('conversation_id', $this->activeConvId)
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
        \App\Models\ChatMessage::create([
            'conversation_id' => $this->activeConvId,
            'sender_type'     => 'agency',
            'sender_id'       => $admin->id,
            'body'            => trim($this->newMessage),
        ]);
        \App\Models\Conversation::where('id', $this->activeConvId)
            ->update(['last_message_at' => now()]);
        $this->newMessage = '';
    }
}
