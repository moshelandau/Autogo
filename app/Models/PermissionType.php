<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionType extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    // All available pages in the system
    public const PAGES = [
        'dashboard' => ['label' => 'Dashboard', 'group' => 'Main', 'actions' => ['view']],
        'customers' => ['label' => 'Customers', 'group' => 'Main', 'actions' => ['view', 'create', 'edit', 'delete']],
        'rental_dashboard' => ['label' => 'Rental Dashboard', 'group' => 'Car Rental', 'actions' => ['view']],
        'reservations' => ['label' => 'Reservations', 'group' => 'Car Rental', 'actions' => ['view', 'create', 'edit', 'delete']],
        'vehicles' => ['label' => 'Fleet Vehicles', 'group' => 'Car Rental', 'actions' => ['view', 'create', 'edit', 'delete']],
        'rental_calendar' => ['label' => 'Calendar', 'group' => 'Car Rental', 'actions' => ['view']],
        'rental_claims' => ['label' => 'Rental Claims', 'group' => 'Car Rental', 'actions' => ['view', 'create', 'edit', 'delete']],
        'deals_pipeline' => ['label' => 'Deals Pipeline', 'group' => 'Leasing / Financing', 'actions' => ['view', 'create', 'edit', 'delete']],
        'damage_waivers' => ['label' => 'Damage Waivers', 'group' => 'Leasing / Financing', 'actions' => ['view', 'create', 'edit']],
        'lenders' => ['label' => 'Lenders', 'group' => 'Leasing / Financing', 'actions' => ['view', 'create', 'edit']],
        'claims' => ['label' => 'Insurance Claims', 'group' => 'Insurance', 'actions' => ['view', 'create', 'edit', 'delete']],
        'office_tasks' => ['label' => 'Office Tasks', 'group' => 'Office', 'actions' => ['view', 'create', 'edit', 'delete']],
        'accounting' => ['label' => 'Chart of Accounts', 'group' => 'Accounting', 'actions' => ['view', 'create', 'edit']],
        'journal_entries' => ['label' => 'Journal Entries', 'group' => 'Accounting', 'actions' => ['view', 'create', 'edit']],
        'reports' => ['label' => 'Financial Reports', 'group' => 'Accounting', 'actions' => ['view']],
        'users' => ['label' => 'User Management', 'group' => 'System', 'actions' => ['view', 'create', 'edit', 'delete']],
        'settings' => ['label' => 'Settings', 'group' => 'System', 'actions' => ['view', 'edit']],
    ];

    public function pages(): HasMany
    {
        return $this->hasMany(PermissionTypePage::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Generate all page entries for this type (all unchecked by default).
     */
    public function generatePages(): void
    {
        foreach (self::PAGES as $key => $config) {
            $this->pages()->firstOrCreate(['page_key' => $key]);
        }
    }

    /**
     * Sync Spatie permissions for a user based on this type's page settings.
     */
    public function syncToUser(User $user): void
    {
        $permissions = [];

        foreach ($this->pages as $page) {
            $pageKey = $page->page_key;

            // Map page_key + action to Spatie permission names
            $mappings = $this->getPermissionMappings($pageKey);

            if ($page->can_view && isset($mappings['view'])) {
                $permissions = array_merge($permissions, (array) $mappings['view']);
            }
            if ($page->can_create && isset($mappings['create'])) {
                $permissions = array_merge($permissions, (array) $mappings['create']);
            }
            if ($page->can_edit && isset($mappings['edit'])) {
                $permissions = array_merge($permissions, (array) $mappings['edit']);
            }
            if ($page->can_delete && isset($mappings['delete'])) {
                $permissions = array_merge($permissions, (array) $mappings['delete']);
            }
        }

        $user->syncPermissions(array_unique($permissions));
    }

    /**
     * Map page keys to Spatie permission names.
     */
    private function getPermissionMappings(string $pageKey): array
    {
        return match ($pageKey) {
            'dashboard' => ['view' => 'view_reports'],
            'customers' => ['view' => 'view_customers', 'create' => 'create_customers', 'edit' => 'edit_customers', 'delete' => 'delete_customers'],
            'rental_dashboard' => ['view' => 'view_reservations'],
            'reservations' => ['view' => 'view_reservations', 'create' => 'create_reservations', 'edit' => 'edit_reservations', 'delete' => 'delete_reservations'],
            'vehicles' => ['view' => 'view_vehicles', 'create' => 'create_vehicles', 'edit' => 'edit_vehicles', 'delete' => 'delete_vehicles'],
            'rental_calendar' => ['view' => 'view_reservations'],
            'rental_claims' => ['view' => 'view_claims', 'create' => 'create_claims', 'edit' => 'edit_claims', 'delete' => 'delete_claims'],
            'deals_pipeline' => ['view' => 'view_deals', 'create' => 'create_deals', 'edit' => 'edit_deals', 'delete' => 'delete_deals'],
            'damage_waivers' => ['view' => 'view_deals', 'create' => 'create_deals', 'edit' => 'edit_deals'],
            'lenders' => ['view' => 'view_lenders', 'create' => 'manage_lenders', 'edit' => 'manage_lenders'],
            'claims' => ['view' => 'view_claims', 'create' => 'create_claims', 'edit' => 'edit_claims', 'delete' => 'delete_claims'],
            'office_tasks' => ['view' => 'view_reports', 'create' => 'create_reservations', 'edit' => 'edit_reservations', 'delete' => 'delete_reservations'],
            'accounting' => ['view' => 'view_accounting', 'create' => 'manage_accounting', 'edit' => 'manage_accounting'],
            'journal_entries' => ['view' => 'view_accounting', 'create' => 'manage_accounting', 'edit' => 'manage_accounting'],
            'reports' => ['view' => 'view_reports'],
            'users' => ['view' => 'manage_users', 'create' => 'manage_users', 'edit' => 'manage_users', 'delete' => 'manage_users'],
            'settings' => ['view' => 'manage_settings', 'edit' => 'manage_settings'],
            default => [],
        };
    }

    /**
     * Get pages grouped by section for the UI.
     */
    public static function getPagesGrouped(): array
    {
        $grouped = [];
        foreach (self::PAGES as $key => $config) {
            $grouped[$config['group']][] = array_merge($config, ['key' => $key]);
        }
        return $grouped;
    }

    /**
     * Get this type's pages as a keyed array for the UI.
     */
    public function getPagePermissionsMap(): array
    {
        $map = [];
        foreach ($this->pages as $page) {
            $map[$page->page_key] = [
                'can_view' => $page->can_view,
                'can_create' => $page->can_create,
                'can_edit' => $page->can_edit,
                'can_delete' => $page->can_delete,
            ];
        }
        return $map;
    }
}
