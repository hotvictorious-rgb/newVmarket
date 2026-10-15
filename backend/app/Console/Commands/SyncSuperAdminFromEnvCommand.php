<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SyncSuperAdminFromEnvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vmarket:sync-superadmin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provisions or synchronizes the Super Administrator account strictly from server environment variables (ENV).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('  👑 Victorious MARKET - Super Admin Environment Sync');
        $this->info('====================================================');

        $email = config('auth.superadmin.email', env('SUPERADMIN_EMAIL'));
        $password = config('auth.superadmin.password', env('SUPERADMIN_PASSWORD'));
        $name = config('auth.superadmin.name', env('SUPERADMIN_NAME', 'Victorious Super Admin'));
        $phone = config('auth.superadmin.phone', env('SUPERADMIN_PHONE', '+2348011110001'));

        if (empty($email) || empty($password)) {
            $this->error('ERROR: SUPERADMIN_EMAIL and SUPERADMIN_PASSWORD must be defined in your .env file.');
            return 1;
        }

        $superAdmin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'user_type' => 'super_admin',
                'status' => 'active',
                'is_demo' => false,
                'password' => Hash::make($password),
            ]
        );

        $this->info("✅ Super Administrator account synchronized successfully.");
        $this->line("   • Name:      {$superAdmin->name}");
        $this->line("   • Email:     {$superAdmin->email}");
        $this->line("   • Role:      {$superAdmin->user_type}");
        $this->line("   • Is Demo:   " . ($superAdmin->is_demo ? 'true (VIOLATION)' : 'false (SECURE)'));
        $this->line("   • Status:    {$superAdmin->status}");

        return 0;
    }
}
