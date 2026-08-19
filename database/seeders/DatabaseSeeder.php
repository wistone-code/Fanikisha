<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The one seeded account. Sign in with username "Admin" / password "1234" —
        // you'll be asked to set a real password immediately on first login.
        User::updateOrCreate(
            ['username' => 'Admin'],
            [
                'name' => 'Wistone Beno',
                'email' => 'wistonebeno@gmail.com',
                'password' => Hash::make('1234'),
                'is_super_user' => true,
                'must_change_password' => true,
            ]
        );
    }
}
