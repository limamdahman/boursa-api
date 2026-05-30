<?php
declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'agency_id', 'status', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->orderByDesc('created_at');
    }

    public function unreadForAgency(): int
    {
        return $this->messages()
            ->where('sender_type', 'user')
            ->whereNull('read_at')
            ->count();
    }

    // Ungelesene Nachrichten aus Sicht des Nutzers (von der Agentur gesendet)
    public function unreadForUser(): int
    {
        return $this->messages()
            ->where('sender_type', 'agency')
            ->whereNull('read_at')
            ->count();
    }
}
