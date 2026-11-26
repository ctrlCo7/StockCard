<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * In-app messaging. Administrators may message anyone; requesting personnel may message
 * administrators only (the supply office), not staff of other departments.
 */
class MessagingService
{
    /**
     * @param  array<int, int>  $recipientIds
     */
    public function start(User $sender, array $recipientIds, string $subject, string $body, ?Requisition $requisition = null): Conversation
    {
        return DB::transaction(function () use ($sender, $recipientIds, $subject, $body, $requisition) {
            $conversation = Conversation::create([
                'subject' => $subject,
                'requisition_id' => $requisition?->id,
                'created_by' => $sender->id,
            ]);
            $conversation->participants()->attach(collect($recipientIds)->push($sender->id)->unique()->values()->all());

            $this->addMessage($conversation, $sender, $body);

            return $conversation;
        });
    }

    public function reply(Conversation $conversation, User $sender, string $body): Message
    {
        return DB::transaction(fn () => $this->addMessage($conversation, $sender, $body));
    }

    public function markRead(Conversation $conversation, User $user): void
    {
        if ($conversation->last_message_id) {
            $conversation->participants()->updateExistingPivot($user->id, ['last_read_message_id' => $conversation->last_message_id]);
        }
    }

    public function unreadCount(User $user): int
    {
        return Conversation::unreadFor($user)->count();
    }

    /**
     * People the user is allowed to start a conversation with.
     *
     * @return Collection<int, User>
     */
    public function contactsFor(User $user): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->when(! $user->isAdmin(), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', Role::Admin->value)))
            ->with(['roles', 'office'])
            ->orderBy('name')
            ->get();
    }

    private function addMessage(Conversation $conversation, User $sender, string $body): Message
    {
        $message = $conversation->messages()->create(['user_id' => $sender->id, 'body' => $body]);

        $conversation->forceFill(['last_message_id' => $message->id, 'last_message_at' => $message->created_at])->save();
        $conversation->participants()->updateExistingPivot($sender->id, ['last_read_message_id' => $message->id]);

        return $message;
    }
}
