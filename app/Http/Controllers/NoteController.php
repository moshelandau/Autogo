<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\Note;
use App\Models\NoteActivity;
use App\Models\NoteComment;
use App\Models\User;
use App\Notifications\MentionedInNoteNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Polymorphic notes — attaches to Deal, Customer (and any future morphable
 * model). Carries the mention/assignment/todo machinery copied from the
 * Derech app: @-mentions notify users, assignees get reminders, comments
 * thread under the note, every action is journaled into note_activities.
 */
class NoteController extends Controller
{
    private const NOTABLE_TYPES = [
        'deal'     => Deal::class,
        'customer' => Customer::class,
    ];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'notable_type'        => ['required', Rule::in(array_keys(self::NOTABLE_TYPES))],
            'notable_id'          => ['required', 'integer'],
            'subject'             => ['nullable', 'string', 'max:120'],
            'body'                => ['required', 'string'],
            'reminder_date'       => ['nullable', 'date'],
            'assigned_user_ids'   => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $notableClass = self::NOTABLE_TYPES[$validated['notable_type']];
        // Verify the parent exists — fails 404 if someone hand-crafts a bad id.
        $notableClass::findOrFail($validated['notable_id']);

        return DB::transaction(function () use ($validated, $notableClass, $request) {
            $note = Note::create([
                'notable_type'  => $notableClass,
                'notable_id'    => $validated['notable_id'],
                'subject'       => $validated['subject'] ?? null,
                'body'          => $validated['body'],
                'reminder_date' => $validated['reminder_date'] ?? null,
                'is_resolved'   => false,
                'user_id'       => $request->user()->id,
            ]);

            NoteActivity::create([
                'note_id' => $note->id,
                'user_id' => $request->user()->id,
                'action'  => 'created',
                'detail'  => null,
            ]);

            $assignedIds = collect($validated['assigned_user_ids'] ?? [])->unique()->values()->all();

            // @mention parser — pulls "@First Last" tokens from the body and
            // matches against active users by name. Mentioned users are
            // auto-added to the assignee list (mention = assign).
            $mentionedIds = $this->extractMentionedUserIds($note->body);
            $allAssignees = collect($assignedIds)->merge($mentionedIds)->unique()->values()->all();

            if (!empty($allAssignees)) {
                $note->assignedUsers()->sync($allAssignees);
                foreach ($allAssignees as $uid) {
                    NoteActivity::create([
                        'note_id' => $note->id,
                        'user_id' => $request->user()->id,
                        'action'  => 'assigned',
                        'detail'  => 'Assigned ' . optional(User::find($uid))->name,
                    ]);
                }
            }

            // Notify mentioned users (database channel feeds the bell).
            if (!empty($mentionedIds)) {
                $mentionedBy = $request->user();
                $users = User::whereIn('id', $mentionedIds)->where('id', '!=', $mentionedBy->id)->get();
                foreach ($users as $u) {
                    $u->notify(new MentionedInNoteNotification($note, $mentionedBy));
                }
            }

            return back()->with('success', 'Note added.');
        });
    }

    public function update(Request $request, Note $note)
    {
        $validated = $request->validate([
            'subject'             => ['nullable', 'string', 'max:120'],
            'body'                => ['required', 'string'],
            'reminder_date'       => ['nullable', 'date'],
            'assigned_user_ids'   => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $reminderChanged = $note->reminder_date?->toDateString() !== ($validated['reminder_date'] ?? null);

        $note->update([
            'subject'       => $validated['subject'] ?? null,
            'body'          => $validated['body'],
            'reminder_date' => $validated['reminder_date'] ?? null,
        ]);

        if ($reminderChanged) {
            // Reset email_sent so users get re-notified for the new date.
            $note->assignedUsers()->newPivotQuery()->update(['email_sent' => false]);
        }

        if ($request->has('assigned_user_ids')) {
            $note->assignedUsers()->sync(collect($validated['assigned_user_ids'] ?? [])->unique()->values()->all());
        }

        NoteActivity::create([
            'note_id' => $note->id,
            'user_id' => $request->user()->id,
            'action'  => 'updated',
            'detail'  => null,
        ]);

        return back()->with('success', 'Note updated.');
    }

    public function destroy(Request $request, Note $note)
    {
        $note->delete();
        return back()->with('success', 'Note deleted.');
    }

    public function resolve(Request $request, Note $note)
    {
        $note->update(['is_resolved' => true]);
        NoteActivity::create([
            'note_id' => $note->id,
            'user_id' => $request->user()->id,
            'action'  => 'resolved',
            'detail'  => 'Marked as done',
        ]);
        return back()->with('success', 'Reminder marked done.');
    }

    public function reopen(Request $request, Note $note)
    {
        $note->update(['is_resolved' => false]);
        NoteActivity::create([
            'note_id' => $note->id,
            'user_id' => $request->user()->id,
            'action'  => 'reopened',
            'detail'  => null,
        ]);
        return back()->with('success', 'Reminder reopened.');
    }

    public function updateReminderDate(Request $request, Note $note)
    {
        $validated = $request->validate(['reminder_date' => ['required', 'date']]);
        $note->update(['reminder_date' => $validated['reminder_date']]);
        // Reset email_sent so users get re-notified for the new date.
        $note->assignedUsers()->newPivotQuery()->update(['email_sent' => false]);
        NoteActivity::create([
            'note_id' => $note->id,
            'user_id' => $request->user()->id,
            'action'  => 'updated',
            'detail'  => 'Snoozed to ' . $validated['reminder_date'],
        ]);
        return back()->with('success', 'Reminder date updated.');
    }

    public function addComment(Request $request, Note $note): JsonResponse
    {
        $validated = $request->validate(['body' => ['required', 'string']]);
        $comment = NoteComment::create([
            'note_id' => $note->id,
            'user_id' => $request->user()->id,
            'body'    => $validated['body'],
        ]);
        NoteActivity::create([
            'note_id' => $note->id,
            'user_id' => $request->user()->id,
            'action'  => 'commented',
            'detail'  => 'Added a reply',
        ]);

        // Mentions in replies notify too — mirrors note-body parsing.
        $mentionedIds = $this->extractMentionedUserIds($comment->body);
        if (!empty($mentionedIds)) {
            $users = User::whereIn('id', $mentionedIds)->where('id', '!=', $request->user()->id)->get();
            foreach ($users as $u) {
                $u->notify(new MentionedInNoteNotification($note, $request->user()));
            }
        }

        $comment->load('user');
        return response()->json([
            'id'         => $comment->id,
            'body'       => $comment->body,
            'user_name'  => $comment->user?->name,
            'user_initials' => $comment->user?->initials,
            'created_at' => $comment->created_at,
        ]);
    }

    public function thread(Note $note): JsonResponse
    {
        $note->load(['comments.user', 'activities.user', 'assignedUsers']);
        return response()->json([
            'comments'   => $note->comments->map(fn ($c) => [
                'id'             => $c->id,
                'body'           => $c->body,
                'user_name'      => $c->user?->name,
                'user_initials'  => $c->user?->initials,
                'created_at'     => $c->created_at,
            ])->all(),
            'activities' => $note->activities->map(fn ($a) => [
                'id'             => $a->id,
                'action'         => $a->action,
                'detail'         => $a->detail,
                'user_name'      => $a->user?->name,
                'created_at'     => $a->created_at,
            ])->all(),
            'assignees'  => $note->assignedUsers->map(fn ($u) => [
                'id'        => $u->id,
                'name'      => $u->name,
                'initials'  => $u->initials,
            ])->all(),
        ]);
    }

    /**
     * Typeahead source for the @-mention dropdown and the assignee
     * checkbox list. Returns active users whose name matches the query.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $users = User::query()
            ->when($q !== '', fn ($qb) => $qb->where('name', 'ilike', '%' . $q . '%'))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);
        return response()->json(['users' => $users->map(fn ($u) => [
            'id'       => $u->id,
            'name'     => $u->name,
            'email'    => $u->email,
            'initials' => $u->initials,
        ])->all()]);
    }

    /**
     * Mark a single bell notification (mention) as read. Reminders are
     * dismissed by resolving the note, not by marking-read.
     */
    public function markNotificationRead(Request $request, string $id)
    {
        $request->user()->unreadNotifications()->where('id', $id)->each->markAsRead();
        return back();
    }

    public function markAllNotificationsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return back();
    }

    private function extractMentionedUserIds(string $body): array
    {
        if (!preg_match_all('/@([\w][\w\s\-\.\']{1,60}?)(?=\s@|\s*$|[,;!?\n]|\.\s)/u', $body, $m)) {
            return [];
        }
        $names = array_unique(array_map('trim', $m[1] ?? []));
        if (empty($names)) return [];
        // Match by exact lowercase name. Pull all users once and filter
        // in-memory rather than 1-query-per-name.
        $all = User::pluck('id', 'name');
        $lower = $all->mapWithKeys(fn ($id, $n) => [strtolower((string) $n) => $id]);
        $ids = [];
        foreach ($names as $n) {
            $key = strtolower($n);
            if (isset($lower[$key])) $ids[] = $lower[$key];
        }
        return array_values(array_unique($ids));
    }
}
