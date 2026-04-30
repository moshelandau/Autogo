<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Deal;
use Illuminate\Http\Request;

class DealShareController extends Controller
{
    /**
     * Sync the set of users who can view this deal.
     * Owner (salesperson) doesn't need a row — they always see it.
     */
    public function update(Request $request, Deal $deal)
    {
        $data = $request->validate([
            'user_ids'   => 'array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);
        $userIds = collect($data['user_ids'] ?? [])
            ->reject(fn ($id) => (int) $id === (int) $deal->salesperson_id)
            ->unique()
            ->values()
            ->all();
        $deal->sharedWith()->sync($userIds);
        return back()->with('success', 'Sharing updated.');
    }
}
