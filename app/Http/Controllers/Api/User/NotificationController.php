<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(5, min(50, $perPage));

        $notifications = $request->user()->notifications()->paginate($perPage);

        return response()->json($notifications);
    }

    public function recent(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->limit(5)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $notifications]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->find($id);
        if (! $notification) {
            return response()->json(['message' => 'Notification introuvable.'], 404);
        }
        $notification->markAsRead();
        return response()->json(['message' => 'Notification marquée comme lue.']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'Toutes les notifications ont été lues.']);
    }
}
