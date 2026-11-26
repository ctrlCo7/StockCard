<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConversationRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Requisition;
use App\Services\MessagingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function __construct(private MessagingService $messaging) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'unread' => ['nullable', 'boolean'],
        ]);

        $conversations = Conversation::query()
            ->forUser($user)
            ->when($filters['unread'] ?? false, fn ($q) => $q->unreadFor($user))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('subject', 'like', "%{$term}%")
                ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', "%{$term}%"))))
            ->with(['participants', 'latestMessage.sender', 'requisition'])
            ->orderByDesc('last_message_at')
            ->paginate(20)
            ->withQueryString();

        return view('messages.index', compact('conversations', 'filters'));
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $requisition = $request->integer('requisition') ? Requisition::find($request->integer('requisition')) : null;
        if ($requisition && $user->cannot('view', $requisition)) {
            $requisition = null;
        }

        $contacts = $this->messaging->contactsFor($user);
        // ?to=office preselects every administrator; otherwise ?to[]=id preselects specific people.
        $preselected = $request->query('to') === 'office'
            ? $contacts->filter->isAdmin()->modelKeys()
            : collect((array) $request->query('to', []))->map(fn ($id) => (int) $id)->intersect($contacts->modelKeys())->values()->all();

        return view('messages.create', [
            'contacts' => $contacts,
            'preselected' => $preselected,
            'requisition' => $requisition,
            'subject' => $requisition ? "About {$requisition->ris_no}" : '',
        ]);
    }

    public function store(StoreConversationRequest $request): RedirectResponse
    {
        $conversation = $this->messaging->start(
            $request->user(),
            array_map('intval', $request->validated('recipients')),
            $request->validated('subject'),
            $request->validated('body'),
            $request->requisition(),
        );

        return redirect()->route('messages.show', $conversation)->with('success', 'Message sent.');
    }

    public function show(Request $request, Conversation $conversation): View
    {
        Gate::authorize('view', $conversation);

        $conversation->load(['participants.office', 'participants.roles', 'messages.sender', 'requisition']);
        $this->messaging->markRead($conversation, $request->user());

        return view('messages.show', ['conversation' => $conversation]);
    }

    public function reply(StoreMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        $this->messaging->reply($conversation, $request->user(), $request->validated('body'));

        return redirect()->to(route('messages.show', $conversation).'#latest')->with('success', 'Reply sent.');
    }
}
