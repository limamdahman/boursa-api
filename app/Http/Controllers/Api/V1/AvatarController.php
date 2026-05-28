<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AvatarController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user = $request->user();

        // Supprimer l'ancien avatar
        if ($user->avatar_url) {
            $old = str_replace(config('app.url') . '/storage/', '', $user->avatar_url);
            Storage::disk('s3')->delete($old);
        }

        $file = $request->file('avatar');
        $ext  = $file->getClientOriginalExtension();
        $path = 'avatars/' . $user->id . '-' . Str::random(8) . '.' . $ext;

        Storage::disk('s3')->putFileAs(
            dirname($path),
            $file,
            basename($path),
            'public'
        );
        $url = Storage::disk('s3')->url($path);

        $user->update(['avatar_url' => $url]);

        return response()->json(['avatar_url' => $url]);
    }
}
