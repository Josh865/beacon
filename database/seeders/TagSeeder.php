<?php

namespace Database\Seeders;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->each(function (User $user): void {
            collect([
                'Members',
                'Volunteers',
                'Staff',
                'Parents',
                'Youth',
                'Donors',
                'Small Groups',
                'Prayer Team',
            ])->each(function (string $name) use ($user): void {
                Tag::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'slug' => Str::slug($name),
                    ],
                    ['name' => $name],
                );
            });
        });
    }
}
