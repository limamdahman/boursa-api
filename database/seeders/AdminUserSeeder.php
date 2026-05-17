<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['phone' => '22244000001'],
            [
                'name' => 'Boursa Admin',
                'email' => 'admin@boursa.mr',
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
                'password' => Hash::make('admin1234'),
                'role' => UserRole::ADMIN,
                'language' => 'fr',
            ]
        );

        $this->command->info('  Admin user created (phone: 22244000001 / password: admin1234)');
    }
}
