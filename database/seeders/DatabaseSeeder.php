<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Client;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Har role ka ek demo user. Password: .env ka SEED_PASSWORD (local mein default "password").
     */
    public function run(): void
    {
        $password = env('SEED_PASSWORD', app()->isProduction() ? null : 'password');

        if (! $password) {
            $this->command->error('Production mein .env mein SEED_PASSWORD set karein, phir seed chalayein.');

            return;
        }

        $users = [
            ['Admin User', 'admin@workflow.test', Role::Admin],
            ['Manager User', 'manager@workflow.test', Role::Manager],
            ['Approver User', 'approver@workflow.test', Role::Approver],
            ['Team Member', 'team@workflow.test', Role::TeamMember],
            ['Client User', 'client@workflow.test', Role::Client],
        ];

        foreach ($users as [$name, $email, $role]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => $password,
                'role' => $role,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        $client = Client::firstOrCreate(['name' => 'Demo Brand'], ['contact_email' => 'brand@workflow.test']);
        User::where('email', 'client@workflow.test')->update(['client_id' => $client->id]);
    }
}
