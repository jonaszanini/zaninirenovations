<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetAdminPassword extends Command
{
    protected $signature = 'admin:set-password
                            {email : The user email address}
                            {--password= : New password (omit to be prompted securely)}';

    protected $description = 'Set a user password using Laravel\'s hasher (fixes plain-text passwords in the database).';

    public function handle(): int
    {
        $email = trim($this->argument('email'));

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email [{$email}]. Check spelling and that the row exists in `users`.");

            return self::FAILURE;
        }

        $password = $this->option('password');
        if ($password === null || $password === '') {
            $password = $this->secret('New password');
            $confirm = $this->secret('Confirm password');
            if ($password !== $confirm) {
                $this->error('Passwords do not match.');

                return self::FAILURE;
            }
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user->password = Hash::make($password);
        $user->save();

        $this->info("Password updated for [{$user->email}]. You can sign in at /admin/login.");

        return self::SUCCESS;
    }
}
