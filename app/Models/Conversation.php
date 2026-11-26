<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['subject', 'requisition_id', 'created_by', 'last_message_id', 'last_message_at'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('last_read_message_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('participants', fn (Builder $q) => $q->whereKey($user->id));
    }

    /** Conversations with a message the user has not read yet. */
    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        return $query->whereHas('participants', fn (Builder $q) => $q->whereKey($user->id)
            ->where(fn (Builder $w) => $w->whereNull('conversation_user.last_read_message_id')
                ->orWhereColumn('conversation_user.last_read_message_id', '<', 'conversations.last_message_id')));
    }

    public function isUnreadFor(User $user): bool
    {
        $participant = $this->participants->firstWhere('id', $user->id);

        return $participant && $this->last_message_id
            && ($participant->pivot->last_read_message_id === null || $participant->pivot->last_read_message_id < $this->last_message_id);
    }

    /** Everyone in the conversation except the given user, as a readable list. */
    public function otherParticipantNames(User $user): string
    {
        return $this->participants->reject(fn (User $p) => $p->is($user))->pluck('name')->implode(', ') ?: $user->name;
    }
}
