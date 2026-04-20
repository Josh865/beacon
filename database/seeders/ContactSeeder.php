<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->each(function (User $user): void {
            $tagIds = $user->tags()->pluck('id');

            Contact::factory()
                ->count(320)
                ->forUser($user)
                ->create()
                ->each(function (Contact $contact) use ($tagIds): void {
                    if ($tagIds->isEmpty()) {
                        return;
                    }

                    $contact->tags()->sync(
                        $tagIds
                            ->shuffle()
                            ->take(fake()->numberBetween(0, min(4, $tagIds->count())))
                            ->all(),
                    );
                });
        });
    }
}
