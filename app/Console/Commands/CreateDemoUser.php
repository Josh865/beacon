<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('app:create-demo-user {--name=Demo User} {--email=demo@example.test} {--password=}')]
#[Description('Create or update the shared demo user account')]
class CreateDemoUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $password = $this->option('password') ?: Str::password(20);

        $user = User::query()->firstOrNew([
            'email' => $this->option('email'),
        ]);

        $user->fill([
            'name' => $this->option('name'),
            'password' => $password,
        ]);
        $user->email_verified_at = now();
        $user->remember_token = Str::random(10);
        $user->save();

        $this->components->info('Demo user is ready.');
        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $user->name],
                ['Email', $user->email],
                ['Password', $password],
                ['Verified', 'yes'],
            ],
        );

        return self::SUCCESS;
    }
}
