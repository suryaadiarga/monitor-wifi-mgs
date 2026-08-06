<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateInitialAdmin extends Command
{
    protected $signature = 'isp:create-admin {--email= : Email administrator} {--name=Administrator : Nama administrator}';

    protected $description = 'Membuat atau mereset Super Admin memakai INITIAL_ADMIN_PASSWORD tanpa menaruh password pada argumen';

    public function handle(): int
    {
        $email = trim((string) $this->option('email'));
        $name = trim((string) $this->option('name'));
        $password = (string) getenv('INITIAL_ADMIN_PASSWORD');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Opsi --email wajib berupa email valid.');

            return self::FAILURE;
        }

        if (strlen($password) < 12) {
            $this->error('INITIAL_ADMIN_PASSWORD wajib tersedia dan minimal 12 karakter.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(['email' => $email], ['name' => $name ?: 'Administrator', 'password' => $password]);
        $user->syncRoles(['Super Admin']);

        AuditLog::create([
            'correlation_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'module' => 'users',
            'action' => 'bootstrap_super_admin',
            'target_type' => User::class,
            'target_id' => (string) $user->id,
            'after' => ['email' => $email, 'role' => 'Super Admin'],
            'status' => 'success',
        ]);

        $this->info("Super Admin siap: {$email}");

        return self::SUCCESS;
    }
}
