<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;

class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin';

    protected $description = 'Create the initial Super Admin account securely';

    public function handle(): int
    {
        $name = $this->ask('Super Admin name');
        $email = $this->ask('Super Admin email');
        $password = $this->secret('Password (at least 12 characters, mixed case, number, and symbol)');
        $passwordConfirmation = $this->secret('Confirm password');

        if (! is_string($password) || $password !== $passwordConfirmation) {
            $this->error('The password confirmation does not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => Str::lower((string) $email), 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => Str::lower((string) $email),
            'password' => Hash::make($password),
        ]);
        $user->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();

        $this->info('Super Admin account created successfully.');

        return self::SUCCESS;
    }
}
