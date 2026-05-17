<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'vehicle.create',
            'vehicle.update.own',
            'vehicle.update.any',
            'vehicle.delete.own',
            'vehicle.delete.any',
            'vehicle.moderate',
            'vehicle.view.draft',
            'agency.update.own',
            'agency.update.any',
            'agency.verify',
            'agency.suspend',
            'user.list',
            'user.update.any',
            'user.delete.any',
            'lead.view.own',
            'lead.view.any',
            'analytics.view.own',
            'analytics.view.global',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => UserRole::ADMIN->value, 'guard_name' => 'web']);
        $agency = Role::firstOrCreate(['name' => UserRole::AGENCY->value, 'guard_name' => 'web']);
        $user = Role::firstOrCreate(['name' => UserRole::USER->value, 'guard_name' => 'web']);

        $admin->syncPermissions(Permission::all());

        $agency->syncPermissions([
            'vehicle.create',
            'vehicle.update.own',
            'vehicle.delete.own',
            'vehicle.view.draft',
            'agency.update.own',
            'lead.view.own',
            'analytics.view.own',
        ]);

        $user->syncPermissions([]);

        $this->command->info('  Roles + permissions seeded (admin, agency, user)');
    }
}
