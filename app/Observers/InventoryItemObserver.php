<?php
// app/Observers/InventoryItemObserver.php

namespace App\Observers;

use App\Models\InventoryItem;
use App\Models\User;
use App\Notifications\LowStockAlert;

/** E-mails the back office the moment an item crosses into low stock (not on every change). */
class InventoryItemObserver
{
    public function updated(InventoryItem $item): void
    {
        if (! $item->wasChanged(['quantity', 'low_stock_threshold']) || $item->status !== 'active' || ! $item->isLow()) {
            return;
        }

        $wasLow = (float) $item->getOriginal('quantity') <= (float) $item->getOriginal('low_stock_threshold');

        if ($wasLow) {
            return;     // already low before: the daily digest covers reminders
        }

        try {
            User::where('is_active', true)->whereIn('role', ['admin', 'staff'])->get()
                ->each(fn (User $user) => $user->notify(new LowStockAlert(collect([$item]))));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
