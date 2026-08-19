<?php

namespace App\Console\Commands;

use App\Models\User;
use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('app:user {name} {email} {--password=}')]
#[Description('Create or edit a user')]
class UserCommand extends Command
{
    public function handle(): int
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->option('password');

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;

        if ($password) {
            $user->password = Hash::make($password);
        } elseif (! $user->password) {
            $password = Str::random(8);
            $user->password = Hash::make($password);
        }

        try {
            $user->save();
        } catch (Exception $e) {
            $this->error('User creation failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $info = "Updated user {$name}";

        if ($password) {
            $info .= ". The password is {$password}";
        }

        $this->info($info);

        return self::SUCCESS;
    }
}
