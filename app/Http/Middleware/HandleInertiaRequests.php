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
        ];
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
