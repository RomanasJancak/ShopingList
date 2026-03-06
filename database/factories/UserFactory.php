<?php

namespace Database\Factories;

use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withoutFamily(): static
    {
        return $this;
    }

    public function withOwnFamilyOnly(): static
    {
        return $this->afterCreating(function (\App\Models\User $user): void {
            Family::factory()
                ->forOwner($user)
                ->ownerOnly()
                ->create();
        });
    }

    public function withFamilyMembers(int $membersCount = 1): static
    {
        return $this->afterCreating(function (\App\Models\User $user) use ($membersCount): void {
            Family::factory()
                ->forOwner($user)
                ->withMembers($membersCount)
                ->create();
        });
    }
}
