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
            // Self-mentions are allowed — they're useful as a private TODO
            // and they're how the author can sanity-check that the bell
            // is wired correctly without coordinating with a coworker.
            if (!empty($mentionedIds)) {
                $mentionedBy = $request->user();
                $users = User::whereIn('id', $mentionedIds)->get();
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
        $note->update(['is_resolved' => true, 'resolved_at' => now()]);
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
        // Reopen also clears the completed timestamp — if it isn't done,
        // there's no "done date" to keep around.
        $note->update(['is_resolved' => false, 'resolved_at' => null]);
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
        // `reminder_date` is nullable — passing empty string clears the
        // due date entirely (so a TODO becomes a plain note again).
        $validated = $request->validate(['reminder_date' => ['nullable', 'date']]);
        $newDate = $validated['reminder_date'] ?? null;
        $note->update(['reminder_date' => $newDate]);
        // Reset email_sent so users get re-notified for the new date.
        $note->assignedUsers()->newPivotQuery()->update(['email_sent' => false]);
        NoteActivity::create([
            'note_id' => $note->id,
            'user_id' => $request->user()->id,
            'action'  => 'updated',
            'detail'  => $newDate ? "Snoozed to {$newDate}" : 'Cleared due date',
        ]);
        return back()->with('success', $newDate ? 'Due date updated.' : 'Due date cleared.');
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
        // Self-mentions allowed (consistent with the create path).
        $mentionedIds = $this->extractMentionedUserIds($comment->body);
        if (!empty($mentionedIds)) {
            $users = User::whereIn('id', $mentionedIds)->get();
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
     * Find which users are @-mentioned in the body. Free-text regex
     * parsing is unreliable when names contain spaces (the regex either
     * stops too early at the first space or eats far past the name).
     * Since the typeahead already gives us a closed set of users, we
     * iterate that set and look for the literal `@<name>` followed by a
     * boundary (end-of-string, whitespace, or punctuation).
     */
    private function extractMentionedUserIds(string $body): array
    {
        if (!str_contains($body, '@')) return [];
        $ids = [];
        $bodyLower = mb_strtolower($body);
        foreach (User::all(['id', 'name']) as $u) {
            $needle = '@' . mb_strtolower((string) $u->name);
            $pos = 0;
            while (($pos = mb_strpos($bodyLower, $needle, $pos)) !== false) {
                $end = $pos + mb_strlen($needle);
                $next = mb_substr($bodyLower, $end, 1);
                // Boundary: end-of-string, whitespace, or punctuation.
                if ($next === '' || preg_match('/[\s,.;:!?\n\)\]\}]/u', $next)) {
                    $ids[] = $u->id;
                    break;
                }
                $pos = $end;
            }
        }
        return array_values(array_unique($ids));
    }
}
