<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\FamilyRole;
use App\Models\FamilyUserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Family>
 */
class FamilyFactory extends Factory
{
    protected $model = Family::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'owner_user_id' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Family $family): void {
            $ownerRole = FamilyRole::query()->firstOrCreate(
                [
                    'family_id' => $family->id,
                    'name' => 'owner',
                ],
                [
                    'level' => 100,
                    'permissions' => ['family.manage', 'roles.manage', 'members.manage'],
                ]
            );

            FamilyUserRole::query()->updateOrCreate(
                [
                    'family_id' => $family->id,
                    'user_id' => $family->owner_user_id,
                ],
                [
                    'family_role_id' => $ownerRole->id,
                ]
            );
        });
    }

    public function forOwner(User $owner): static
    {
        return $this->state(fn () => [
            'owner_user_id' => $owner->id,
        ]);
    }

    public function ownerOnly(): static
    {
        return $this;
    }

    public function withMembers(int $membersCount = 1): static
    {
        return $this->afterCreating(function (Family $family) use ($membersCount): void {
            if ($membersCount <= 0) {
                return;
            }

            $memberRole = FamilyRole::query()->firstOrCreate(
                [
                    'family_id' => $family->id,
                    'name' => 'member',
                ],
                [
                    'level' => 10,
                    'permissions' => ['profile.view'],
                ]
            );

            User::factory()->count($membersCount)->create()->each(function (User $user) use ($family, $memberRole): void {
                FamilyUserRole::query()->updateOrCreate(
                    [
                        'family_id' => $family->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'family_role_id' => $memberRole->id,
                    ]
                );
            });
        });
    }
}