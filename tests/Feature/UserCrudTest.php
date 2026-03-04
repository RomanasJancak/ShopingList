<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_gets_authenticated_user_information_from_me_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);
    }

    public function test_guest_cannot_access_me_endpoint(): void
    {
        $response = $this->get('/api/me');

        $response->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_it_lists_users(): void
    {
        User::factory()->count(3)->create();

        $response = $this->getJson('/api/users');

        $response->assertOk()->assertJsonCount(3);
    }

    public function test_it_gets_single_user_information(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->getJson("/api/users/{$user->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);
    }

    public function test_user_cannot_view_another_user_information(): void
    {
        $authenticatedUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($authenticatedUser);

        $response = $this->getJson("/api/users/{$otherUser->id}");

        $response->assertForbidden();
    }

    public function test_it_creates_a_user(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'Romanas',
            'email' => 'romanas@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Romanas',
                'email' => 'romanas@example.com',
            ]);

        $createdUser = User::where('email', 'romanas@example.com')->first();

        $this->assertNotNull($createdUser);
        $this->assertTrue(Hash::check('password123', $createdUser->password));
    }

    public function test_it_validates_user_creation_data(): void
    {
        $response = $this->postJson('/api/users', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_it_updates_a_user_without_changing_password_when_password_is_empty(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('initialPass123'),
        ]);

        $oldPasswordHash = $user->password;

        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'password' => null,
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $user->id,
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ]);

        $user->refresh();

        $this->assertSame($oldPasswordHash, $user->password);
    }

    public function test_it_updates_a_user_password_when_provided(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('initialPass123'),
        ]);

        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'password' => 'newPassword123',
        ]);

        $response->assertOk();

        $user->refresh();

        $this->assertTrue(Hash::check('newPassword123', $user->password));
    }

    public function test_it_deletes_a_user(): void
    {
        $user = User::factory()->create();

        $response = $this->deleteJson("/api/users/{$user->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}