<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\FamilyRole;
use App\Models\FamilyUserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoryCombinationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_factory_without_family_creates_user_with_no_family_memberships(): void
    {
        $user = User::factory()->withoutFamily()->create();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseCount('families', 0);
        $this->assertDatabaseCount('family_user_roles', 0);
    }

    public function test_user_factory_with_own_family_only_creates_owner_as_single_member(): void
    {
        $user = User::factory()->withOwnFamilyOnly()->create();

        $family = Family::query()->where('owner_user_id', $user->id)->first();

        $this->assertNotNull($family);

        $ownerRole = FamilyRole::query()
            ->where('family_id', $family->id)
            ->where('name', 'owner')
            ->first();

        $this->assertNotNull($ownerRole);

        $this->assertDatabaseHas('family_user_roles', [
            'family_id' => $family->id,
            'user_id' => $user->id,
            'family_role_id' => $ownerRole->id,
        ]);

        $this->assertSame(1, FamilyUserRole::query()->where('family_id', $family->id)->count());
    }

    public function test_user_factory_with_family_members_creates_owner_plus_requested_members(): void
    {
        $membersCount = 3;
        $owner = User::factory()->withFamilyMembers($membersCount)->create();

        $family = Family::query()->where('owner_user_id', $owner->id)->first();

        $this->assertNotNull($family);

        $this->assertSame(
            1 + $membersCount,
            FamilyUserRole::query()->where('family_id', $family->id)->count()
        );

        $ownerRole = FamilyRole::query()
            ->where('family_id', $family->id)
            ->where('name', 'owner')
            ->first();

        $memberRole = FamilyRole::query()
            ->where('family_id', $family->id)
            ->where('name', 'member')
            ->first();

        $this->assertNotNull($ownerRole);
        $this->assertNotNull($memberRole);

        $this->assertDatabaseHas('family_user_roles', [
            'family_id' => $family->id,
            'user_id' => $owner->id,
            'family_role_id' => $ownerRole->id,
        ]);

        $assignedMemberCount = FamilyUserRole::query()
            ->where('family_id', $family->id)
            ->where('family_role_id', $memberRole->id)
            ->count();

        $this->assertSame($membersCount, $assignedMemberCount);
    }
}
