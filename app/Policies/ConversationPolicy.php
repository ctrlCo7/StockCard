<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/**
 * Messages are private to the conversation's participants, administrators included
 * (AppServiceProvider exempts conversations from the administrator bypass).
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $this->participates($user, $conversation);
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $this->participates($user, $conversation);
    }

    private function participates(User $user, Conversation $conversation): bool
    {
        return $conversation->participants()->whereKey($user->id)->exists();
    }
}
