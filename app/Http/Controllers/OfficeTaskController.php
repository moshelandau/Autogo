<?php

namespace App\Http\Controllers;

use App\Models\OfficeTask;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OfficeTaskController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user_id;

        $query = OfficeTask::with(['assignedToUser', 'comments'])
            ->when($userId, fn($q) => $q->where('assigned_to', $userId));

        return Inertia::render('OfficeTasks/Index', [
            'today' => (clone $query)->today()->orderBy('created_at')->get(),
            'todo' => (clone $query)->todo()->orderBy('created_at')->get(),
            'recurring' => (clone $query)->recurring()->where('is_completed', false)->orderBy('recurring_next_date')->get(),
            'completed' => (clone $query)->completed()->orderByDesc('completed_at')->take(20)->get(),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'selectedUserId' => $userId,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'section' => 'nullable|in:today,todo,recurring',
            'priority' => 'nullable|in:low,medium,high',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'is_recurring' => 'boolean',
            'recurring_frequency' => 'nullable|in:daily,weekly,monthly',
        ]);

        OfficeTask::create(array_merge($validated, [
            'section' => $validated['section'] ?? 'todo',
            'created_by' => auth()->id(),
        ]));

        return back()->with('success', 'Task added.');
    }

    public function complete(OfficeTask $officeTask)
    {
        $officeTask->markComplete();

        // Clear all notifications referencing this task
        \DB::table('notifications')
            ->whereJsonContains('data->office_task_id', $officeTask->id)
            ->update(['read_at' => now()]);

        return back()->with('success', 'Task completed.');
    }

    public function uncomplete(OfficeTask $officeTask)
    {
        $officeTask->markIncomplete();
        return back();
    }

    public function moveSection(Request $request, OfficeTask $officeTask)
    {
        $validated = $request->validate(['section' => 'required|in:today,todo,recurring']);
        $officeTask->update(['section' => $validated['section']]);
        return back();
    }

    public function addComment(Request $request, OfficeTask $officeTask)
    {
        $validated = $request->validate(['body' => 'required|string']);
        $officeTask->comments()->create(['body' => $validated['body'], 'user_id' => auth()->id()]);
        return back();
    }

    public function destroy(OfficeTask $officeTask)
    {
        $officeTask->delete();
        return back()->with('success', 'Task deleted.');
    }
}
