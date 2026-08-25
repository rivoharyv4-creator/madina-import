<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ManagerSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email'=>env('ADMIN_EMAIL','manager@madina-import.mg')],[
            'name'=>'Manager Madina',
            'password'=>Hash::make(env('ADMIN_PASSWORD','ChangeMe!2026')),
            'email_verified_at'=>now(),
        ]);
    }
}
