<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageControllerTest extends TestCase
{
    use RefreshDatabase;

    private function startConversation(User $from, User $to, string $body = 'Hello'): Conversation
    {
        return $this->messaging()->start($from, [$to->id], 'Supplies question', $body);
    }

    private function messaging(): MessagingService
    {
        return app(MessagingService::class);
    }

    public function test_requester_sends_a_message_to_an_administrator(): void
    {
        $admin = $this->admin();
        $requester = $this->requester();

        $this->actingAs($requester)->post(route('messages.store'), [
            'recipients' => [$admin->id], 'subject' => 'Toner request', 'body' => 'When will the toner arrive?',
        ])->assertRedirect();

        $conversation = Conversation::sole();
        $this->assertSame('Toner request', $conversation->subject);
        $this->assertEqualsCanonicalizing([$admin->id, $requester->id], $conversation->participants()->pluck('users.id')->all());
        $this->assertSame('When will the toner arrive?', $conversation->messages()->sole()->body);
        $this->assertSame(1, Conversation::unreadFor($admin)->count());
        $this->assertSame(0, Conversation::unreadFor($requester)->count());
    }

    public function test_requester_cannot_message_another_requester(): void
    {
        $requester = $this->requester();
        $otherDepartment = $this->requester();

        $this->actingAs($requester)->post(route('messages.store'), [
            'recipients' => [$otherDepartment->id], 'subject' => 'Hi', 'body' => 'Hello there',
        ])->assertSessionHasErrors(['recipients.0' => 'You can only message the supply office administrators.']);

        $this->assertSame(0, Conversation::count());
    }

    public function test_administrator_can_message_a_requester(): void
    {
        $admin = $this->admin();
        $requester = $this->requester();

        $this->actingAs($admin)->post(route('messages.store'), [
            'recipients' => [$requester->id], 'subject' => 'Your RIS', 'body' => 'Ready for pickup.',
        ])->assertRedirect();

        $this->assertSame(1, Conversation::unreadFor($requester)->count());
    }

    public function test_conversation_is_private_even_from_other_administrators(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();
        $conversation = $this->startConversation($this->requester(), $admin);

        $this->actingAs($otherAdmin)->get(route('messages.show', $conversation))->assertForbidden();
        $this->actingAs($otherAdmin)->post(route('messages.reply', $conversation), ['body' => 'Butting in'])->assertForbidden();

        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_opening_a_conversation_marks_it_read(): void
    {
        $admin = $this->admin();
        $conversation = $this->startConversation($this->requester(), $admin);

        $this->actingAs($admin)->get(route('messages.show', $conversation))->assertOk()->assertSee('Supplies question');

        $this->assertSame(0, Conversation::unreadFor($admin)->count());
    }

    public function test_reply_is_unread_for_the_other_participants_only(): void
    {
        $admin = $this->admin();
        $requester = $this->requester();
        $conversation = $this->startConversation($requester, $admin);
        $this->messaging()->markRead($conversation->fresh(), $admin);

        $this->actingAs($admin)->post(route('messages.reply', $conversation), ['body' => 'Arriving Friday.'])
            ->assertRedirect(route('messages.show', $conversation).'#latest');

        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame(1, Conversation::unreadFor($requester)->count());
        $this->assertSame(0, Conversation::unreadFor($admin)->count());
    }

    public function test_empty_reply_is_rejected(): void
    {
        $admin = $this->admin();
        $conversation = $this->startConversation($this->requester(), $admin);

        $this->actingAs($admin)->post(route('messages.reply', $conversation), ['body' => ''])
            ->assertSessionHasErrors(['body' => 'Type a message before sending.']);

        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_requester_cannot_link_someone_elses_requisition(): void
    {
        $admin = $this->admin();
        $item = $this->makeItem(10, by: $admin);
        $owner = $this->requester();
        $intruder = $this->requester();
        $r = $this->ris()->createDraft(['office_id' => $this->office->id, 'requisition_date' => today(), 'purpose' => 'Private'],
            [['item_id' => $item->id, 'quantity' => 1]], $owner);

        $this->actingAs($intruder)->post(route('messages.store'), [
            'recipients' => [$admin->id], 'subject' => 'About it', 'body' => 'Hi', 'requisition_id' => $r->id,
        ])->assertSessionHasErrors(['requisition_id' => 'You can only link a requisition you have access to.']);

        $this->assertSame(0, Conversation::count());
    }

    public function test_message_body_is_escaped_when_displayed(): void
    {
        $admin = $this->admin();
        $conversation = $this->startConversation($this->requester(), $admin, '<script>alert(1)</script>');

        $this->actingAs($admin)->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_unread_badge_appears_in_navigation(): void
    {
        $admin = $this->admin();
        $this->startConversation($this->requester(), $admin);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('title="Unread conversations">1</span>', false);
    }
}
