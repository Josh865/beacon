<?php

namespace Database\Factories;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->boolean(80) ? fake()->unique()->safeEmail() : null,
            'phone' => fake()->unique()->numerify('317-555-####'),
            'status' => fake()->randomElement([
                ContactStatus::Active,
                ContactStatus::Active,
                ContactStatus::Active,
                ContactStatus::Inactive,
            ]),
            'notes' => fake()->boolean(35) ? fake()->sentence(fake()->numberBetween(8, 15)) : null,
        ];
    }

    /**
     * Associate the contact with a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->for($user);
    }

    /**
     * Indicate that the contact is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactStatus::Active,
        ]);
    }

    /**
     * Indicate that the contact is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactStatus::Inactive,
        ]);
    }
}
