<?php

namespace App\Http\Controllers;

use App\Models\PermissionType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PermissionTypeController extends Controller
{
    public function index()
    {
        return Inertia::render('Users/PermissionTypes', [
            'types' => PermissionType::withCount(['pages' => fn($q) => $q->where('can_view', true), 'users'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permission_types,name',
            'description' => 'nullable|string|max:500',
        ]);

        $type = PermissionType::create($validated);
        $type->generatePages();

        return redirect()->route('permission-types.edit', $type)
            ->with('success', "Permission type '{$type->name}' created. Now set page access.");
    }

    public function edit(PermissionType $permissionType)
    {
        $permissionType->load('pages');

        return Inertia::render('Users/PermissionTypeEdit', [
            'type' => $permissionType,
            'pagesGrouped' => PermissionType::getPagesGrouped(),
            'pagePermissions' => $permissionType->getPagePermissionsMap(),
        ]);
    }

    public function update(Request $request, PermissionType $permissionType)
    {
        $validated = $request->validate([
            'name' => "required|string|max:255|unique:permission_types,name,{$permissionType->id}",
            'description' => 'nullable|string|max:500',
            'pages' => 'required|array',
            'pages.*.page_key' => 'required|string',
            'pages.*.can_view' => 'boolean',
            'pages.*.can_create' => 'boolean',
            'pages.*.can_edit' => 'boolean',
            'pages.*.can_delete' => 'boolean',
        ]);

        $permissionType->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
        ]);

        // Update page permissions
        foreach ($validated['pages'] as $pageData) {
            $permissionType->pages()->updateOrCreate(
                ['page_key' => $pageData['page_key']],
                [
                    'can_view' => $pageData['can_view'] ?? false,
                    'can_create' => $pageData['can_create'] ?? false,
                    'can_edit' => $pageData['can_edit'] ?? false,
                    'can_delete' => $pageData['can_delete'] ?? false,
                ]
            );
        }

        // Re-sync all users with this permission type
        foreach ($permissionType->users as $user) {
            $permissionType->syncToUser($user);
        }

        return redirect()->route('permission-types.index')
            ->with('success', "Permission type '{$permissionType->name}' updated. All users re-synced.");
    }

    public function destroy(PermissionType $permissionType)
    {
        if ($permissionType->users()->count() > 0) {
            return back()->with('error', 'Cannot delete a permission type that has users assigned.');
        }

        $permissionType->delete();

        return redirect()->route('permission-types.index')
            ->with('success', 'Permission type deleted.');
    }
}
