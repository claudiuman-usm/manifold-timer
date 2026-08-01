<?php

namespace App\Http\Controllers;

use App\Models\FeedbackThread;
use App\Models\Kid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The kid-facing side of the feedback chat: a kid raises glitch / feature
 * reports and reads the parent's replies. A kid only ever touches their own
 * threads (kid is resolved from the session by the `kid` middleware).
 */
class KidFeedbackController extends Controller
{
    /** The kid's threads + messages, plus how many parent replies are unread. */
    public function index(Request $request): JsonResponse
    {
        $kid = $this->kid($request);

        return response()->json($this->payload($kid));
    }

    /** Start a new report (thread) with its first kid message. */
    public function store(Request $request): JsonResponse
    {
        $kid = $this->kid($request);
        $data = $request->validate([
            'type' => ['required', 'in:glitch,feature'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $thread = $kid->feedbackThreads()->create(['type' => $data['type']]);
        $thread->messages()->create(['sender' => 'kid', 'body' => $data['body']]);

        return response()->json($this->payload($kid));
    }

    /** Mark every parent message in the kid's threads as read (panel opened). */
    public function seen(Request $request): JsonResponse
    {
        $kid = $this->kid($request);

        $this->unreadParentMessages($kid)->update(['read_at' => Carbon::now()]);

        return response()->json($this->payload($kid));
    }

    /** @return array<string,mixed> */
    private function payload(Kid $kid): array
    {
        $threads = $kid->feedbackThreads()
            ->with(['messages' => fn ($q) => $q->orderBy('created_at')])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (FeedbackThread $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'resolved' => $t->isResolved(),
                'messages' => $t->messages->map(fn ($m) => [
                    'sender' => $m->sender,
                    'body' => $m->body,
                    'at' => $m->created_at->toIso8601String(),
                ])->all(),
            ]);

        return [
            'threads' => $threads,
            'unread' => $this->unreadParentMessages($kid)->count(),
        ];
    }

    private function unreadParentMessages(Kid $kid)
    {
        return \App\Models\FeedbackMessage::query()
            ->where('sender', 'parent')
            ->whereNull('read_at')
            ->whereHas('thread', fn ($q) => $q->where('kid_id', $kid->id));
    }

    private function kid(Request $request): Kid
    {
        return $request->attributes->get('kid');
    }
}
