<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Observers\BookingObserver;
use App\Observers\InventoryItemObserver;
use App\Observers\PaymentObserver;
use App\Services\Sms\LogSms;
use App\Services\Sms\SemaphoreSms;
use App\Services\Sms\SmsGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // SMS provider is picked by SMS_DRIVER (log | semaphore); the default only writes to the log.
        $this->app->bind(SmsGateway::class, fn () => match (config('services.sms.driver')) {
            'semaphore' => new SemaphoreSms(config('services.sms.semaphore.key'), (string) config('services.sms.semaphore.sender')),
            default => new LogSms,
        });
    }

    public function boot(): void
    {
        // Plain-HTML pager styled by public/css/admin.css (the default needs Tailwind).
        Paginator::defaultView('pagination.catering');

        // One password rule for registration, reset and admin-created users.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Behind a real domain, every generated link must be https.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Login is limited per IP *and* per email, so one attacker can't hammer one account
        // from many IPs, and one IP can't spray many accounts.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('ip:' . $request->ip()),
            Limit::perMinute(5)->by('email:' . Str::lower((string) $request->input('email'))),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(5)->by('ip:' . $request->ip()),
            Limit::perMinute(3)->by('email:' . Str::lower((string) $request->input('email'))),
        ]);

        // Customer e-mails are sent from observers so every code path (customer, admin, staff) notifies.
        Booking::observe(BookingObserver::class);
        Payment::observe(PaymentObserver::class);
        InventoryItem::observe(InventoryItemObserver::class);
    }
}
