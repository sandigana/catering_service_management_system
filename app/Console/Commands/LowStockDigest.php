<?php
// app/Console/Commands/LowStockDigest.php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Illuminate\Console\Command;

/** php artisan inventory:low-stock-digest : one e-mail listing everything still low. */
class LowStockDigest extends Command
{
    protected $signature = 'inventory:low-stock-digest';

    protected $description = 'E-mail the back office a daily list of low-stock items';

    public function handle(): int
    {
        $items = InventoryItem::low()->where('status', 'active')->orderBy('name')->get();

        if ($items->isEmpty()) {
            $this->info('Nothing is low on stock.');

            return self::SUCCESS;
        }

        User::where('is_active', true)->whereIn('role', ['admin', 'staff'])->get()
            ->each(fn (User $user) => $user->notify(new LowStockAlert($items, digest: true)));

        $this->info("Digest sent for {$items->count()} item(s).");

        return self::SUCCESS;
    }
}
