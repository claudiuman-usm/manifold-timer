<?php

namespace App\Http\Controllers;

use App\Models\FeedbackMessage;
use App\Models\FeedbackThread;
use App\Models\Kid;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

/**
 * The parent-facing side of the feedback chat: read every kid's reports,
 * reply, and mark reports resolved. Unread = kid messages the parent hasn't
 * seen yet, which drives the badge on the parent's floating widget.
 */
class ParentFeedbackController extends Controller
{
    /** All reports (both kids), newest activity first, + unread count. */
    public function index(): JsonResponse
    {
        return response()->json($this->payload());
    }

    public function reply(Request $request, FeedbackThread $thread): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);
        $thread->messages()->create(['sender' => 'parent', 'body' => $data['body']]);
        $thread->touch(); // bump ordering

        return response()->json($this->payload());
    }

    /** Toggle a report between open and resolved. */
    public function resolve(FeedbackThread $thread): JsonResponse
    {
        $thread->resolved_at = $thread->isResolved() ? null : Carbon::now();
        $thread->save();

        return response()->json($this->payload());
    }

    /** Mark every kid message as read (parent opened the panel). */
    public function seen(): JsonResponse
    {
        FeedbackMessage::query()
            ->where('sender', 'kid')
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);

        return response()->json($this->payload());
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        $names = Kid::pluck('name', 'id');
        $colors = Kid::pluck('color', 'id');

        $threads = FeedbackThread::query()
            ->with(['messages' => fn ($q) => $q->orderBy('created_at')])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (FeedbackThread $t) => [
                'id' => $t->id,
                'kid' => $names[$t->kid_id] ?? 'A kid',
                'color' => $colors[$t->kid_id] ?? '#888',
                'type' => $t->type,
                'resolved' => $t->isResolved(),
                'messages' => $t->messages->map(fn ($m) => [
                    'sender' => $m->sender,
                    'body' => $m->body,
                    'at' => $m->created_at->toIso8601String(),
                ])->all(),
            ]);

        $unread = FeedbackMessage::query()
            ->where('sender', 'kid')
            ->whereNull('read_at')
            ->count();

        return ['threads' => $threads, 'unread' => $unread];
    }
}
