<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions
        $permissions = [
            // Customer
            'view_customers', 'create_customers', 'edit_customers', 'delete_customers',
            // Rental
            'view_reservations', 'create_reservations', 'edit_reservations', 'delete_reservations',
            'view_vehicles', 'create_vehicles', 'edit_vehicles', 'delete_vehicles',
            'view_fleet', 'manage_fleet',
            // Leasing
            'view_deals', 'create_deals', 'edit_deals', 'delete_deals',
            'view_lenders', 'manage_lenders',
            'view_quotes', 'create_quotes',
            // Bodyshop
            'view_repair_jobs', 'create_repair_jobs', 'edit_repair_jobs',
            'view_tow_requests', 'create_tow_requests', 'edit_tow_requests',
            // Insurance
            'view_claims', 'create_claims', 'edit_claims',
            // Accounting
            'view_accounting', 'manage_accounting',
            'view_expenses', 'create_expenses', 'edit_expenses',
            'view_checks', 'manage_checks',
            // Communication
            'send_sms', 'view_notifications', 'manage_templates',
            // Settings
            'manage_settings', 'manage_users', 'manage_roles',
            // Reports
            'view_reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all());

        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->givePermissionTo([
            'view_customers', 'create_customers', 'edit_customers',
            'view_reservations', 'create_reservations', 'edit_reservations',
            'view_vehicles', 'create_vehicles', 'edit_vehicles', 'view_fleet', 'manage_fleet',
            'view_deals', 'create_deals', 'edit_deals',
            'view_lenders', 'view_quotes', 'create_quotes',
            'view_repair_jobs', 'create_repair_jobs', 'edit_repair_jobs',
            'view_tow_requests', 'create_tow_requests', 'edit_tow_requests',
            'view_claims', 'create_claims', 'edit_claims',
            'view_accounting', 'view_expenses', 'create_expenses',
            'send_sms', 'view_notifications',
            'view_reports',
        ]);

        $staff = Role::firstOrCreate(['name' => 'staff']);
        $staff->givePermissionTo([
            'view_customers', 'create_customers', 'edit_customers',
            'view_reservations', 'create_reservations', 'edit_reservations',
            'view_vehicles', 'view_fleet',
            'view_deals', 'create_deals', 'edit_deals',
            'view_quotes', 'create_quotes',
            'view_repair_jobs', 'create_repair_jobs',
            'view_tow_requests', 'create_tow_requests',
            'view_claims',
            'send_sms', 'view_notifications',
        ]);

        $driver = Role::firstOrCreate(['name' => 'driver']);
        $driver->givePermissionTo([
            'view_tow_requests', 'edit_tow_requests',
            'view_vehicles',
        ]);
    }
}
