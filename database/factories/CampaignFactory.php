<?php

namespace Database\Factories;

use App\Enums\CampaignAudienceType;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
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
            'name' => fake()->sentence(fake()->numberBetween(2, 4)),
            'message_body' => fake()->paragraphs(fake()->numberBetween(1, 2), true),
            'status' => CampaignStatus::Draft,
            'audience_type' => CampaignAudienceType::AllContacts,
            'scheduled_for' => null,
            'sent_at' => null,
        ];
    }

    /**
     * Associate the campaign with a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->for($user);
    }

    /**
     * Indicate that the campaign targets tagged contacts.
     */
    public function tagSelection(): static
    {
        return $this->state(fn (array $attributes): array => [
            'audience_type' => CampaignAudienceType::TagSelection,
        ]);
    }
}
