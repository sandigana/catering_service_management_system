<?php
// tests/Feature/DemoDataTest.php
// The demo data (for local testing), the command that removes it again, and the tab icon.

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Event;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\RescheduleRequest;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function demoSeed(): void
{
    test()->seed(AdminSeeder::class);
    test()->seed(DemoDataSeeder::class);
}

/* ---------- the seeder ---------- */

test('the demo seeder fills every area with data and sends no e-mails', function () {
    Storage::fake('local');
    Notification::fake();

    demoSeed();

    Notification::assertNothingSent();

    expect(User::where('role', 'admin')->count())->toBe(1)
        ->and(User::where('role', 'staff')->count())->toBe(3)
        ->and(User::where('role', 'user')->count())->toBe(8)
        ->and(Package::count())->toBe(5)
        ->and(MenuItem::count())->toBe(15)
        ->and(Staff::count())->toBe(8)
        ->and(InventoryItem::count())->toBe(14)
        ->and(Booking::count())->toBe(15)
        ->and(Payment::count())->toBe(15)
        ->and(Event::count())->toBe(10)
        ->and(RescheduleRequest::count())->toBe(4)
        ->and(ActivityLog::count())->toBeGreaterThan(0);

    // every booking status is represented
    expect(Booking::pluck('status')->unique()->sort()->values()->all())->toBe(['cancelled', 'completed', 'confirmed', 'pending']);
});

test('the demo data is internally consistent', function () {
    Storage::fake('local');
    demoSeed();

    // totals are price x guests
    foreach (Booking::with('package')->get() as $booking) {
        expect((float) $booking->total_amount)->toBe((float) Booking::calculateTotal($booking->package->price, $booking->guests));
    }

    // nobody paid more than the total, and no stock went negative
    foreach (Booking::withPaid()->get() as $booking) {
        expect((float) $booking->amount_paid)->toBeLessThanOrEqual((float) $booking->total_amount + 0.01);
    }
    expect(InventoryItem::where('quantity', '<', 0)->count())->toBe(0);

    // stock reserved for events was really deducted: Rice 220 - (150 x 0.2) - (60 x 0.18) = 179.2
    expect((float) InventoryItem::where('name', 'Rice')->first()->quantity)->toBe(179.2);

    // no live date goes over the daily limit, and one date is exactly FULL
    $perDay = Booking::whereIn('status', ['pending', 'confirmed'])->get()->groupBy(fn ($b) => $b->event_date->toDateString())->map->count();
    expect($perDay->max())->toBe((int) config('catering.max_events_per_day'));

    // every confirmed or completed booking has an event with crew
    foreach (Booking::whereIn('status', ['confirmed', 'completed'])->get() as $booking) {
        $event = Event::where('booking_id', $booking->id)->first();
        expect($event)->not->toBeNull()->and($event->staff()->count())->toBeGreaterThan(0);
    }
});

test('the demo data has something to test for every feature', function () {
    Storage::fake('local');
    demoSeed();

    // slips waiting for verification, stored privately, never counted as money
    $slips = Payment::where('status', 'pending')->where('submitted_by_customer', true)->get();
    expect($slips)->toHaveCount(2);
    foreach ($slips as $slip) {
        Storage::disk('local')->assertExists($slip->proof_path);
    }

    // low stock to alert on, a failed payment, pending/approved/rejected reschedules
    expect(InventoryItem::low()->where('status', 'active')->pluck('name')->sort()->values()->all())->toBe(['Beef', 'Cooking Oil', 'Eggs', 'Soft Drinks']);
    expect(Payment::where('status', 'failed')->count())->toBe(1);
    expect(RescheduleRequest::pluck('status')->sort()->values()->all())->toBe(['approved', 'pending', 'pending', 'rejected']);

    // a recipe exists for each active package that is used
    expect(Package::where('name', 'Platinum')->first()->supplies)->not->toBeEmpty();

    // tomorrow's confirmed event is waiting for its reminder
    Notification::fake();
    $this->artisan('bookings:send-reminders')->expectsOutput('Reminders sent: 1');
    Notification::assertSentTo(User::where('email', 'customer2@demo.test')->first(), \App\Notifications\BookingReminder::class);
});

test('every demo role can sign in and use its own area, and deactivated accounts cannot', function () {
    Storage::fake('local');
    demoSeed();

    $login = fn (string $email) => $this->post('/login', ['email' => $email, 'password' => DemoDataSeeder::PASSWORD]);

    $login('staff1@demo.test')->assertRedirect(route('admin.dashboard'));
    foreach (['admin.dashboard', 'admin.bookings.index', 'admin.calendar', 'admin.inventory.index', 'admin.payments.index', 'admin.events.index', 'admin.customers', 'admin.reschedules.index'] as $route) {
        $this->get(route($route))->assertOk();
    }
    $this->get(route('admin.users.index'))->assertForbidden();
    $this->post('/logout');

    $login('customer1@demo.test')->assertRedirect(route('customer.dashboard'));
    $this->get(route('customer.dashboard'))->assertOk();
    $this->get(route('customer.bookings.index'))->assertOk()->assertSee('Santos 25th Anniversary');
    $this->get(route('customer.packages'))->assertOk()->assertSee('Gold')->assertDontSee('Holiday Special');   // inactive packages stay hidden
    $this->post('/logout');

    $login('staff3@demo.test')->assertSessionHasErrors('email');
    $login('customer8@demo.test')->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('admin pages show the demo data', function () {
    Storage::fake('local');
    demoSeed();
    $admin = User::where('role', 'admin')->first();

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Sofia Lim')->assertSee('Payment Status');
    $this->actingAs($admin)->get(route('admin.packages.index'))->assertOk()->assertSee('Platinum')->assertSee('Kiddie Party');
    $this->actingAs($admin)->get(route('admin.menu-items.index'))->assertOk()->assertSee('Lechon Kawali');
    $this->actingAs($admin)->get(route('admin.staff.index'))->assertOk()->assertSee('Roberto Alonzo');
    $this->actingAs($admin)->get(route('admin.reschedules.index'))->assertOk()->assertSee('Waiting for a decision (2)');
    $this->actingAs($admin)->get(route('admin.activity-log'))->assertOk()->assertSee('Signed in');
    $this->actingAs($admin)->get(route('admin.reports'))->assertOk();
});

test('running the seeder twice does not duplicate anything', function () {
    Storage::fake('local');
    demoSeed();
    $counts = [Booking::count(), User::count(), Package::count()];

    test()->seed(DemoDataSeeder::class);

    expect([Booking::count(), User::count(), Package::count()])->toBe($counts);
});

test('demo:seed refuses to run in production without --force', function () {
    Storage::fake('local');
    $this->app['env'] = 'production';

    $this->artisan('demo:seed')->assertExitCode(1);
    expect(Booking::count())->toBe(0);
});

/* ---------- demo:clear ---------- */

test('demo:clear asks first and deletes nothing when you say no', function () {
    Storage::fake('local');
    demoSeed();

    $this->artisan('demo:clear')->expectsConfirmation('Delete all of it?', 'no')->expectsOutput('Nothing was deleted.')->assertExitCode(0);

    expect(Booking::count())->toBe(15)->and(User::count())->toBe(12);
});

test('demo:clear removes all test data and slips but keeps administrators', function () {
    Storage::fake('local');
    demoSeed();
    $admin = User::where('role', 'admin')->first();
    $anotherAdmin = User::factory()->create(['role' => 'admin', 'email' => 'second-admin@example.com']);
    $slip = Payment::whereNotNull('proof_path')->first()->proof_path;
    Storage::disk('local')->assertExists($slip);

    $this->artisan('demo:clear')->expectsConfirmation('Delete all of it?', 'yes')->assertExitCode(0);

    foreach ([Booking::class, Payment::class, Event::class, Package::class, MenuItem::class, Staff::class, InventoryItem::class, RescheduleRequest::class, ActivityLog::class] as $model) {
        expect($model::count())->toBe(0);
    }
    expect(User::withTrashed()->pluck('email')->sort()->values()->all())->toBe(collect([$admin->email, 'second-admin@example.com'])->sort()->values()->all());
    Storage::disk('local')->assertMissing($slip);
    expect(Storage::disk('local')->exists('payment-proofs'))->toBeFalse();

    // the admin can still sign in and see a clean dashboard
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
});

test('demo:clear --force skips the question, also removes soft-deleted users, and can be repeated', function () {
    Storage::fake('local');
    demoSeed();
    User::where('email', 'customer1@demo.test')->first()->delete();           // soft delete

    $this->artisan('demo:clear --force')->assertExitCode(0);
    expect(User::withTrashed()->where('role', '!=', 'admin')->count())->toBe(0);

    $this->artisan('demo:clear --force')->assertExitCode(0);                  // nothing left, still fine
});

test('after clearing you can seed again, and ids start from 1 again', function () {
    Storage::fake('local');
    demoSeed();
    $this->artisan('demo:clear --force')->assertExitCode(0);

    test()->seed(DemoDataSeeder::class);

    expect(Booking::count())->toBe(15)->and(Package::min('id'))->toBe(1);
});

test('demo:clear refuses to run in production without --force', function () {
    Storage::fake('local');
    demoSeed();
    $this->app['env'] = 'production';

    $this->artisan('demo:clear')->assertExitCode(1);
    expect(Booking::count())->toBe(15);
});

/* ---------- tab icon ---------- */

test('the logo files exist and are valid images', function () {
    foreach (['favicon.svg', 'favicon.ico', 'favicon-16x16.png', 'favicon-32x32.png', 'apple-touch-icon.png', 'images/logo.png'] as $file) {
        expect(file_exists(public_path($file)))->toBeTrue("{$file} is missing");
    }

    expect(file_get_contents(public_path('favicon.svg')))->toContain('<svg')->toContain('#ff8a00');
    expect(bin2hex(substr(file_get_contents(public_path('favicon.ico')), 0, 4)))->toBe('00000100');          // ICO header
    expect(getimagesize(public_path('apple-touch-icon.png'))[0])->toBe(180)
        ->and(getimagesize(public_path('favicon-32x32.png'))[0])->toBe(32)
        ->and(getimagesize(public_path('favicon-16x16.png'))[0])->toBe(16)
        ->and(getimagesize(public_path('images/logo.png'))[0])->toBe(512);
});

test('every kind of page links the tab icon', function () {
    $user = User::factory()->create(['role' => 'user']);
    $admin = User::factory()->create(['role' => 'admin']);
    $booking = Booking::create(['user_id' => $user->id, 'event_name' => 'E', 'event_date' => now()->addMonth()->toDateString(), 'guests' => 1, 'status' => 'confirmed', 'total_amount' => 10]);
    $payment = Payment::create(['user_id' => $user->id, 'booking_id' => $booking->id, 'amount' => 10, 'status' => 'paid']);

    $pages = [
        [null, '/login'], [null, '/register'], [null, route('password.request')], [null, route('password.reset', ['token' => 'abc'])],
        [$admin, route('admin.dashboard')], [$user, route('customer.dashboard')], [$user, route('receipts.show', $payment->id)],
    ];

    foreach ($pages as [$who, $url]) {
        $response = $who ? $this->actingAs($who)->get($url) : $this->get($url);
        $response->assertOk()
            ->assertSee(asset('favicon.svg'), false)
            ->assertSee(asset('favicon.ico'), false)
            ->assertSee(asset('apple-touch-icon.png'), false)
            ->assertSee('rel="icon"', false);
    }

    // the security policy must let same-site images load
    expect($this->get('/login')->headers->get('Content-Security-Policy'))->toContain("img-src 'self'");
});
