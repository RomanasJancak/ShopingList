<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_roles_and_seeded_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'super-admin']);
        $this->assertDatabaseHas('roles', ['name' => 'admin']);
        $this->assertDatabaseHas('roles', ['name' => 'manager']);
        $this->assertDatabaseHas('roles', ['name' => 'member']);

        $superAdmin = User::query()->where('email', 'superadmin@example.com')->first();
        $admin = User::query()->where('email', 'admin@example.com')->first();
        $manager = User::query()->where('email', 'manager@example.com')->first();
        $member = User::query()->where('email', 'member@example.com')->first();

        $this->assertNotNull($superAdmin);
        $this->assertNotNull($admin);
        $this->assertNotNull($manager);
        $this->assertNotNull($member);

        $this->assertTrue($superAdmin->hasRole('super-admin'));
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($manager->hasRole('manager'));
        $this->assertTrue($member->hasRole('member'));

        $firstUser = User::query()->orderBy('id')->first();
        $this->assertSame($superAdmin->id, $firstUser->id);

        $this->assertSame(1, User::role('super-admin')->count());
    }
}