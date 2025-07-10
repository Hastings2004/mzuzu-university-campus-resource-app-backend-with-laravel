<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the basic roles
        $roles = [
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
            ],
            [
                'name' => 'staff',
                'display_name' => 'Staff Member',
            ],
            [
                'name' => 'porters',
                'display_name' => 'Porters lodge',
            ],
            [
                'name' => 'student',
                'display_name' => 'Student',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }
} 