<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'sadmin@localhost.lt'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Z9@pF3!uVx7m#LrT2sQe4WjY8n%bDcH6kR^aP1gUo5tJ&iC0zX*Nh$EyMlOqSd'),
            ]
        );
        $superAdmin->syncRoles(['super-admin']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password123'),
            ]
        );
        $admin->syncRoles(['admin']);

        $manager = User::firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Manager User',
                'password' => Hash::make('password123'),
            ]
        );
        $manager->syncRoles(['manager']);

        $member = User::firstOrCreate(
            ['email' => 'member@example.com'],
            [
                'name' => 'Member User',
                'password' => Hash::make('password123'),
            ]
        );
        $member->syncRoles(['member']);

        User::query()
            ->where('id', '!=', $superAdmin->id)
            ->get()
            ->each(fn (User $user) => $user->removeRole('super-admin'));
    }
}