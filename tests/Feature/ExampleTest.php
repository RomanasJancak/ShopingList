<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyRole;
use App\Models\FamilyUserRole;
use App\Models\ShoppingList;
use App\Models\ShoppingListUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_redirects_to_shopping_lists_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('shopping-lists.index'));
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

    public function test_single_assigned_shopping_list_becomes_user_default(): void
    {
        $user = User::factory()->create();

        $shoppingList = ShoppingList::create([
            'name' => 'Only List',
            'description' => null,
            'owner_user_id' => $user->id,
        ]);

        ShoppingListUser::create([
            'shopping_list_id' => $shoppingList->id,
            'user_id' => $user->id,
            'permission' => 'owner',
        ]);

        $this->actingAs($user)->get(route('profile.show'))->assertOk();

        $user->refresh();

        $this->assertSame($shoppingList->id, $user->default_shopping_list_id);
    }

    public function test_login_redirects_to_default_shopping_list_view_when_preference_enabled(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
            'load_default_shopping_list_on_login' => true,
        ]);

        $shoppingList = ShoppingList::create([
            'name' => 'Default List',
            'description' => null,
            'owner_user_id' => $user->id,
        ]);

        ShoppingListUser::create([
            'shopping_list_id' => $shoppingList->id,
            'user_id' => $user->id,
            'permission' => 'owner',
        ]);

        $user->update([
            'default_shopping_list_id' => $shoppingList->id,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('shopping-list.view', ['id' => $shoppingList->id]));
    }

    public function test_profile_preferences_update_shows_product_pictures_toggle(): void
    {
        $user = User::factory()->create([
            'show_product_pictures_in_shopping_list' => true,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('profile.preferences.update'), [
            'show_product_pictures_in_shopping_list' => '0',
        ]);

        $response->assertRedirect(route('profile.show'));

        $user->refresh();

        $this->assertFalse($user->show_product_pictures_in_shopping_list);
    }
}
