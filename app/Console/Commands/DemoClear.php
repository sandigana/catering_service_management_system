<?php
// app/Console/Commands/DemoClear.php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * php artisan demo:clear
 * Wipes ALL business data (bookings, payments, events, packages, menu, kitchen staff,
 * inventory, requests, activity log, uploaded payment slips) and every NON-admin account.
 * Administrator accounts are kept. Use it to go from "testing" to "real use".
 */
class DemoClear extends Command
{
    protected $signature = 'demo:clear {--force : Skip the confirmation question}';

    protected $description = 'Delete all test data (everything except administrator accounts)';

    /** Children first, so foreign keys never get in the way. */
    private const TABLES = [
        'event_inventory', 'event_staff', 'package_menu_item', 'package_supplies', 'reschedule_requests',
        'payments', 'events', 'bookings', 'packages', 'menu_items', 'staff', 'inventory_items',
        'activity_logs', 'password_reset_tokens',
    ];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Refusing to delete data in production without --force.');

            return self::FAILURE;
        }

        $rows = collect(self::TABLES)->filter(fn ($t) => Schema::hasTable($t))->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);
        $accounts = User::withTrashed()->where('role', '!=', 'admin')->count();

        $this->warn('This will permanently delete:');
        $this->table(['What', 'Rows'], [...$rows->map(fn ($n, $t) => [$t, $n])->values()->all(), ['non-admin accounts (staff and customers)', $accounts]]);
        $this->line('Administrator accounts are kept.');

        if (! $this->option('force') && ! $this->confirm('Delete all of it?', false)) {
            $this->info('Nothing was deleted.');

            return self::SUCCESS;
        }

        Schema::disableForeignKeyConstraints();

        foreach ($rows->keys() as $table) {
            DB::table($table)->truncate();     // also resets the ID counters
        }

        User::withTrashed()->where('role', '!=', 'admin')->forceDelete();

        Schema::enableForeignKeyConstraints();

        // Uploaded payment slips live on the private disk.
        Storage::disk('local')->deleteDirectory('payment-proofs');

        $this->info('Done. The system is clean. Only administrator accounts remain.');

        return self::SUCCESS;
    }
}
