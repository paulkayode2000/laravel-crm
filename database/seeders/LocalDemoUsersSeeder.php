<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * Seeds the roles and users that back the one-click sign-in panel on the admin
 * login page. Everything comes from config/demo.php, so re-running the seeder is
 * safe and always matches the buttons the login page renders.
 *
 * Usage: php artisan db:seed --class=Database\Seeders\LocalDemoUsersSeeder
 */
class LocalDemoUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->warn('LocalDemoUsersSeeder only runs in the local environment; nothing was seeded.');

            return;
        }

        $demo = config('demo', []);

        foreach ($demo['roles'] ?? [] as $name => $attributes) {
            $permissionType = $attributes['permission_type'] ?? 'custom';

            Role::query()->updateOrCreate(
                ['name' => $name],
                [
                    'description' => $attributes['description'] ?? $name,
                    'permission_type' => $permissionType,
                    'permissions' => $permissionType === 'all'
                        ? null
                        : array_values($attributes['permissions'] ?? []),
                ]
            );
        }

        foreach ($demo['accounts'] ?? [] as $account) {
            $roleId = Role::query()->where('name', $account['role'])->value('id');

            if (! $roleId) {
                $this->warn("Skipped {$account['email']}: role \"{$account['role']}\" does not exist.");

                continue;
            }

            User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => bcrypt($account['password']),
                    'role_id' => $roleId,
                    'status' => 1,
                    'view_permission' => $account['view_permission'] ?? 'global',
                ]
            );
        }

        $this->info('Local demo roles and users are ready.');
    }

    /**
     * Write a line to the console when the seeder runs from artisan.
     */
    protected function warn(string $message): void
    {
        if ($this->command) {
            $this->command->warn($message);
        }
    }

    /**
     * Write a line to the console when the seeder runs from artisan.
     */
    protected function info(string $message): void
    {
        if ($this->command) {
            $this->command->info($message);
        }
    }
}
