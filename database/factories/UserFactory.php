<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
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

    /**
     * Assign a specific role (stored as the enum's string value).
     */
    public function role(UserRole $role): static
    {
        return $this->state(fn (array $attributes) => ['role' => $role->value]);
    }

    public function owner(): static
    {
        return $this->role(UserRole::Owner);
    }

    public function admin(): static
    {
        return $this->role(UserRole::Admin);
    }

    public function office(): static
    {
        return $this->role(UserRole::Office);
    }

    public function technician(): static
    {
        return $this->role(UserRole::Technician);
    }

    public function viewer(): static
    {
        return $this->role(UserRole::Viewer);
    }
}
