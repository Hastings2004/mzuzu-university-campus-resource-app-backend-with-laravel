<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles first
        $this->call([
            RoleSeeder::class,
        ]);

        // User::factory(10)->create();

        User::firstOrCreate(
            [
                'email' => 'test@example.com',
            ],
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'user_type' => 'staff',
                // Add other required fields here, or set defaults if needed
            ]
        );

        // Other seeders...
        $this->call(FeatureSeeder::class);
    }
}
