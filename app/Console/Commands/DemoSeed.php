<?php
// app/Console/Commands/DemoSeed.php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;

/** php artisan demo:seed : fill the database with sample data for testing. */
class DemoSeed extends Command
{
    protected $signature = 'demo:seed {--force : Allow this in a production environment}';

    protected $description = 'Create sample bookings, packages, menu, staff, customers, inventory and payments for testing';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Refusing to add demo data in production. Use --force if you really mean it.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
