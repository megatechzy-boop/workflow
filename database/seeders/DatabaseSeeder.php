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
     * One demo user per role. Password: SEED_PASSWORD from .env (local default "password").
     */
    public function run(): void
    {
        $password = env('SEED_PASSWORD', app()->isProduction() ? null : 'password');

        if (! $password) {
            $this->command->error('Set SEED_PASSWORD in .env for production, then run the seeder.');

            return;
        }

        $users = [
            ['Admin User', 'admin@workflow.test', Role::Admin],
            ['Ops Manager', 'manager@workflow.test', Role::Manager],
            ['Social Media Manager', 'approver@workflow.test', Role::SocialMediaManager],
            ['Graphic Designer', 'team@workflow.test', Role::GraphicDesigner],
            ['Video Editor', 'editor@workflow.test', Role::VideoEditor],
            ['Customer User', 'client@workflow.test', Role::Client],
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
