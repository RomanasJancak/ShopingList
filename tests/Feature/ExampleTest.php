<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyRole;
use App\Models\FamilyUserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_redirects_to_users_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('users.index'));
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_profile_page_shows_user_families_with_links(): void
    {
        $user = User::factory()->create();

        $ownedFamily = Family::factory()->forOwner($user)->create([
            'name' => 'Owned Family',
        ]);

        $joinedFamilyOwner = User::factory()->create();
        $joinedFamily = Family::factory()->forOwner($joinedFamilyOwner)->create([
            'name' => 'Joined Family',
        ]);

        $memberRole = FamilyRole::create([
            'family_id' => $joinedFamily->id,
            'name' => 'member',
            'level' => 10,
            'permissions' => ['profile.view'],
        ]);

        FamilyUserRole::create([
            'family_id' => $joinedFamily->id,
            'user_id' => $user->id,
            'family_role_id' => $memberRole->id,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('profile.show'));

        $response->assertOk()
            ->assertSee('Owned Family')
            ->assertSee('Joined Family')
            ->assertSee(route('families.index').'?family='.$ownedFamily->id, false)
            ->assertSee(route('families.index').'?family='.$joinedFamily->id, false);
    }
}
