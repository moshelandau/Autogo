<?php

namespace App\Http\Middleware;

use App\Models\PermissionType;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'pageAccess' => fn () => $this->getPageAccess($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'notifications' => fn () => $this->getNotifications($request),
            'smsUnreadCount' => fn () => $this->getSmsUnreadCount($request),
            'botPendingCount' => fn () => $this->getBotPendingCount(),
        ];
    }

    private function getBotPendingCount(): int
    {
        try {
            // In-progress (not done, not aborted) + completed-but-unhandled lease/finance Deals
            $inProgress = \App\Models\LeaseApplicationSession::query()
                ->whereNull('completed_at')->whereNull('aborted_at')->count();
            $unhandledDeals = \App\Models\LeaseApplicationSession::query()
                ->whereNotNull('completed_at')->whereNull('aborted_at')
                ->whereIn('flow', ['lease', 'finance'])
                ->whereHas('deal', fn ($q) => $q->whereNull('salesperson_id')->where('stage', 'application'))
                ->count();
            $unhandledOther = \App\Models\LeaseApplicationSession::query()
                ->whereNotNull('completed_at')->whereNull('aborted_at')
                ->whereIn('flow', ['rental', 'towing', 'bodyshop'])
                ->whereDoesntHave('deal')
                ->count();
            return $inProgress + $unhandledDeals + $unhandledOther;
        } catch (\Throwable) { return 0; }
    }

    private function getSmsUnreadCount(Request $request): int
    {
        $user = $request->user();
        if (!$user) return 0;
        try {
            // Count = messages assigned to me OR unassigned (so unclaimed messages
            // still ping everyone until someone takes ownership)
            return \App\Models\CommunicationLog::query()
                ->where('channel', 'sms')
                ->where('direction', 'inbound')
                ->where('status', 'received')
                ->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)->orWhereNull('assigned_to');
                })
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function getNotifications(Request $request): array
    {
        $user = $request->user();
        if (!$user) return ['unread_count' => 0, 'items' => []];
        try {
            $unread = $user->unreadNotifications()->limit(20)->get();
            return [
                'unread_count' => $unread->count(),
                'items' => $unread->map(fn ($n) => [
                    'id'         => $n->id,
                    'title'      => $n->data['title'] ?? 'Notification',
                    'body'       => $n->data['body'] ?? '',
                    'icon'       => $n->data['icon'] ?? '🔔',
                    'url'        => $n->data['url']  ?? '#',
                    'created_at' => $n->created_at,
                ])->all(),
            ];
        } catch (\Throwable) {
            return ['unread_count' => 0, 'items' => []];
        }
    }

    private function getPageAccess(Request $request): array
    {
        $user = $request->user();
        if (!$user) return [];

        // Admin with no permission type = full access
        if ($user->hasRole('admin') && !$user->permission_type_id) {
            $access = [];
            foreach (PermissionType::PAGES as $key => $config) {
                $access[$key] = ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true];
            }
            return $access;
        }

        // User with permission type
        if ($user->permission_type_id) {
            $type = PermissionType::with('pages')->find($user->permission_type_id);
            if ($type) {
                return $type->getPagePermissionsMap();
            }
        }

        // Fallback: all pages viewable
        $access = [];
        foreach (PermissionType::PAGES as $key => $config) {
            $access[$key] = ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false];
        }
        return $access;
    }
}
