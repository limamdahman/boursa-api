<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\ChatMessage;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /** Ouvre ou récupère une conversation user <-> agence */
    public function getOrCreate(Request $request, string $agencyId): JsonResponse
    {
        $user = $request->user();

        $agency = Agency::findOrFail($agencyId);

        $created = false;
        $conversation = Conversation::firstOrCreate(
            ['user_id' => $user->id, 'agency_id' => $agency->id],
            ['status' => 'open']
        );

        if ($conversation->wasRecentlyCreated) {
            // Message système Boursa
            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => 'agency',
                'sender_id'       => $agency->id,
                'body'            => "Bienvenue ! / أهلاً بك! سيرد عليك فريق الوكالة في أقرب وقت ممكن.",
            ]);
            // Message auto agence
            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => 'agency',
                'sender_id'       => $agency->id,
                'body'            => "Merci de nous contacter. / شكراً للتواصل معنا. سنرد خلال 24 ساعة.",
            ]);
            $conversation->update(['last_message_at' => now()]);
        }

        return response()->json([
            'id'       => $conversation->id,
            'status'   => $conversation->status,
            'agency'   => ['id' => $agency->id, 'name' => $agency->name, 'logo_url' => $agency->logo_url],
            'user'     => ['id' => $user->id, 'name' => $user->name],
        ]);
    }

    /** Liste des messages d'une conversation (polling) */
    public function messages(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        $this->authorizeConversation($user, $conversation);

        // Marquer les messages entrants comme lus
        ChatMessage::where('conversation_id', $conversation->id)
            ->where('sender_type', $user->agency ? 'user' : 'agency')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $since = $request->query('since');
        $query = $conversation->messages();
        if ($since) {
            try {
                $sinceDate = new \DateTime($since);
                $query->where('created_at', '>', $sinceDate->format('Y-m-d H:i:s'));
            } catch (\Exception $e) {
                // since invalide, ignorer
            }
        }

        return response()->json($query->get()->map(fn ($m) => [
            'id'          => $m->id,
            'sender_type' => $m->sender_type,
            'sender_id'   => $m->sender_id,
            'body'        => $m->body,
            'read_at'     => $m->read_at?->toIso8601String(),
            'created_at'  => $m->created_at->toIso8601String(),
        ]));
    }

    /** Envoyer un message */
    public function send(Request $request, string $conversationId): JsonResponse
    {
        $request->validate(['body' => 'required|string|max:2000']);

        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        $this->authorizeConversation($user, $conversation);

        $isAgency = $conversation->agency_id !== null && $user->agency?->id === $conversation->agency_id;
        $senderType = $isAgency ? 'agency' : 'user';

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => $senderType,
            'sender_id'       => $user->id,
            'body'            => $request->body,
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'id'          => $message->id,
            'sender_type' => $message->sender_type,
            'sender_id'   => $message->sender_id,
            'body'        => $message->body,
            'read_at'     => null,
            'created_at'  => $message->created_at->toIso8601String(),
        ], 201);
    }

    /** Liste des conversations de l'agence connectée */
    // Konversationen des angemeldeten Nutzers (Privatkundenseite)
    public function userConversations(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::with(['agency', 'lastMessage'])
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn ($c) => [
                'id'              => $c->id,
                'agency_id'       => $c->agency_id,
                'agency'          => $c->agency
                    ? ['id' => $c->agency->id, 'name' => $c->agency->name, 'logo_url' => $c->agency->logo_url, 'tier' => $c->agency->subscription_tier, 'is_verified' => $c->agency->isVerified()]
                    : ['id' => null, 'name' => 'Boursa', 'logo_url' => null, 'tier' => null],
                'last_message'    => $c->lastMessage ? ['body' => $c->lastMessage->body] : null,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
                'unread_count'    => $c->unreadForUser(),
            ]);

        return response()->json($conversations);
    }

    public function agencyConversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $agency = $user->agency;

        if (!$agency) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $conversations = Conversation::with(['user', 'lastMessage'])
            ->where('agency_id', $agency->id)
            ->where('status', 'open')
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'user'           => ['id' => $c->user->id, 'name' => $c->user->name],
                'last_message'   => $c->lastMessage?->body,
                'last_at'        => $c->last_message_at?->toIso8601String(),
                'unread'         => $c->unreadForAgency(),
            ]);

        return response()->json($conversations);
    }

    /** Conversation support Boursa (admin) */
    public function getOrCreateSupport(Request $request): JsonResponse
    {
        $user = $request->user();

        // Trouver ou créer une conversation avec l'admin Boursa
        $adminUser = \App\Models\User::where('role', 'admin')->first();
        if (!$adminUser) {
            return response()->json(['error' => 'Support indisponible'], 503);
        }

        // Utiliser une agence virtuelle Boursa ou une conversation user-to-admin
        $conversation = \App\Models\Conversation::firstOrCreate(
            ['user_id' => $user->id, 'agency_id' => null],
            ['status' => 'open']
        );

        if ($conversation->wasRecentlyCreated) {
            \App\Models\ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => 'agency',
                'sender_id'       => $adminUser->id,
                'body'            => "Bonjour ! Bienvenue sur Boursa. Comment pouvons-nous vous aider ? / أهلاً بك في بورصة! كيف يمكننا مساعدتك؟",
            ]);
            $conversation->update(['last_message_at' => now()]);
        }

        return response()->json(['id' => $conversation->id]);
    }

    private function authorizeConversation($user, Conversation $conversation): void
    {
        $isUser    = $conversation->user_id === $user->id;
        $isAgency  = $conversation->agency_id && $user->agency?->id === $conversation->agency_id;
        $isAdmin   = $user->role?->value === 'admin' || $user->role === 'admin';

        if (!$isUser && !$isAgency && !$isAdmin) {
            abort(403);
        }
    }
}
