<?php
// app/Notifications/LowStockAlert.php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Back office: one or more stock items are at or below their alert level. */
class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param Collection<int, \App\Models\InventoryItem> $items */
    public function __construct(public Collection $items, public bool $digest = false)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(($this->digest ? 'Daily low-stock reminder' : 'Low stock alert') . ' (' . $this->items->count() . ')')
            ->greeting("Hello {$notifiable->name},")
            ->line($this->digest ? 'These items are still at or below their alert level:' : 'These items just reached their alert level:');

        foreach ($this->items as $item) {
            $mail->line("{$item->name}: {$this->fmt($item->quantity)} {$item->unit} left (alert at {$this->fmt($item->low_stock_threshold)})");
        }

        return $mail->action('Open inventory', route('admin.inventory.index', ['filter' => 'low']));
    }

    private function fmt(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
