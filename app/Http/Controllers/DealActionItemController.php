<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealActionItem;
use Illuminate\Http\Request;

class DealActionItemController extends Controller
{
    public function store(Request $request, Deal $deal)
    {
        $data = $request->validate([
            'title'    => 'required|string|max:255',
            'due_date' => 'nullable|date',
        ]);
        $deal->actionItems()->create($data + [
            'created_by' => auth()->id(),
        ]);
        return back()->with('success', 'Action item added.');
    }

    public function update(Request $request, Deal $deal, DealActionItem $actionItem)
    {
        abort_unless($actionItem->deal_id === $deal->id, 404);
        $data = $request->validate([
            'title'        => 'sometimes|required|string|max:255',
            'due_date'     => 'sometimes|nullable|date',
            'is_completed' => 'sometimes|boolean',
        ]);
        if (array_key_exists('is_completed', $data)) {
            $data['completed_at'] = $data['is_completed'] ? now() : null;
            $data['completed_by'] = $data['is_completed'] ? auth()->id() : null;
        }
        $actionItem->update($data);
        return back();
    }

    public function destroy(Deal $deal, DealActionItem $actionItem)
    {
        abort_unless($actionItem->deal_id === $deal->id, 404);
        $actionItem->delete();
        return back()->with('success', 'Action item removed.');
    }
}
