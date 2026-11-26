<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\ApplicationSetting;
use App\Models\Role;
use Illuminate\Database\Seeder;

/** Roles and default settings required in every environment. Safe to re-run. */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleEnum::cases() as $role) {
            Role::updateOrCreate(['name' => $role->value], ['label' => $role->label(), 'description' => $role->description()]);
        }

        foreach (ApplicationSetting::DEFAULTS as $key => $value) {
            ApplicationSetting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
