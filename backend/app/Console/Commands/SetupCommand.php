<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up the Orthoplex application';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Setting up Orthoplex...');

        // Step 1: Clear caches
        $this->info('Clearing application caches...');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('cache:clear');

        // Step 2: Run migrations
        $this->info('Running migrations...');
        Artisan::call('migrate', ['--force' => true]);

        // Step 3: Run tenant migrations
        $this->info('Running tenant migrations...');
        Artisan::call('tenants:migrate', ['--force' => true]);

        // Step 4: Generate JWT secret if not exists
        if (!config('jwt.secret')) {
            $this->info('Generating JWT secret...');
            Artisan::call('jwt:secret');
        }

        // Step 5: Seed permissions if needed
        $this->info('Setting up permissions...');
        Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);

        // Step 6: Create storage links
        $this->info('Creating storage links...');
        Artisan::call('storage:link');

        $this->info('✅ Orthoplex setup completed successfully!');
        $this->newLine();
        $this->info('📚 API Documentation: /api/documentation');
        $this->info('🏠 Central Domain: ' . config('tenancy.central_domains.0'));
        $this->info('🎯 Example Tenant: tenant1.' . config('tenancy.central_domains.0'));

        return Command::SUCCESS;
    }
}