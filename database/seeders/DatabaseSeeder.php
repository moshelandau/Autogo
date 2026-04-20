<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            LocationSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'office@autogoco.com'],
            [
                'name' => 'AutoGo Admin',
                'password' => bcrypt('Autogo#10917'),
            ]
        );
        $admin->assignRole('admin');

        // Create personal team (required by Jetstream)
        if ($admin->allTeams()->count() === 0) {
            $team = Team::forceCreate([
                'user_id' => $admin->id,
                'name' => 'AutoGo',
                'personal_team' => true,
            ]);
            $admin->current_team_id = $team->id;
            $admin->save();
        }
    }
}
