<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
                            {--name= : Admin full name}
                            {--email= : Admin email address}
                            {--password= : Admin password}';

    protected $description = 'Create an admin user';

    public function handle(): void
    {
        $name     = $this->option('name')     ?? $this->ask('Name');
        $email    = $this->option('email')    ?? $this->ask('Email');
        $password = $this->option('password') ?? $this->secret('Password');

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email [{$email}] already exists.");
            return;
        }

        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($password),
            'role'     => 'admin',
        ]);

        $this->info("Admin user created successfully.");
        $this->table(['ID', 'Name', 'Email', 'Role'], [
            [$user->id, $user->name, $user->email, $user->role],
        ]);
    }
}
