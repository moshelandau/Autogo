<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        Location::firstOrCreate(['name' => 'Monroe'], [
            'address' => '',
            'city' => 'Monroe',
            'state' => 'NY',
            'zip' => '10950',
            'is_active' => true,
        ]);

        Location::firstOrCreate(['name' => 'Monsey'], [
            'address' => '',
            'city' => 'Monsey',
            'state' => 'NY',
            'zip' => '10952',
            'is_active' => true,
        ]);
    }
}
