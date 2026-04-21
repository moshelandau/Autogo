<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function markRead(Request $request, string $id)
    {
        $user = $request->user();
        $n = $user?->notifications()->where('id', $id)->first();
        if ($n) $n->markAsRead();
        return back();
    }

    public function markAllRead(Request $request)
    {
        $request->user()?->unreadNotifications->markAsRead();
        return back();
    }
}
