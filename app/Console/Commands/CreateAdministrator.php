<?php

namespace App\Console\Commands;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Creates the first administrator account (the supported method in production). */
class CreateAdministrator extends Command
{
    protected $signature = 'app:create-admin {--name=} {--email=} {--designation=}';

    protected $description = 'Create an administrator account (prompts for a password)';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Email address');
        $password = $this->secret('Password (min. 8 characters, letters and numbers)');
        $confirm = $this->secret('Confirm password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirm],
            ['name' => ['required', 'max:150'], 'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $role = Role::firstOrCreate(['name' => RoleEnum::Admin->value], ['label' => RoleEnum::Admin->label(), 'description' => RoleEnum::Admin->description()]);
        $user = User::create([
            'name' => $name, 'email' => $email, 'password' => $password,
            'designation' => $this->option('designation'), 'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);
        AuditLogger::log('user.created', "Administrator {$email} created from the command line", $user);

        $this->info("Administrator {$email} created.");

        return self::SUCCESS;
    }
}
