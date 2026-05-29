<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnreadMessagesController extends Controller
{
    public function getUnreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['count' => 0]);
        }
        
        // Pour admin
        if ($user->role === 'admin') {
            $count = ChatMessage::where('sender_type', 'user')->whereNull('read_at')->count();
            return response()->json(['count' => $count]);
        }
        
        // Pour agence
        if ($user->agency) {
            $count = ChatMessage::whereHas('conversation', fn ($q) => $q->where('agency_id', $user->agency->id))
                ->where('sender_type', 'user')
                ->whereNull('read_at')
                ->count();
            return response()->json(['count' => $count]);
        }
        
        return response()->json(['count' => 0]);
    }
}
