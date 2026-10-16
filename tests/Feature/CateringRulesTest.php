<?php
// tests/Feature/CateringRulesTest.php
// Covers the business rules from the capstone paper: double-booking, balances,
// receipts, roles, throttling, inventory, audit log and reports.

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Event;
use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/* ---------- helpers (prefixed so they never clash with CateringTest) ---------- */

function rulesUser(string $role = 'user', array $attrs = []): User
{
    return User::factory()->create(['role' => $role] + $attrs);
}

function rulesPackage(float $price = 500): Package
{
    return Package::create(['name' => 'Gold ' . uniqid(), 'price' => $price, 'status' => 'active']);
}

function rulesBooking(User $owner, array $attrs = []): Booking
{
    return Booking::create($attrs + [
        'user_id' => $owner->id,
        'event_name' => 'Party',
        'event_date' => now()->addMonth()->toDateString(),
        'event_time' => '12:00',
        'guests' => 10,
        'location' => 'Grand Hall',
        'status' => 'confirmed',
        'total_amount' => 1000,
    ]);
}

function rulesCustomerPayload(Package $package, array $over = []): array
{
    return $over + [
        'package_id' => $package->id,
        'event_name' => 'Anniversary',
        'event_date' => now()->addMonth()->toDateString(),
        'event_time' => '18:00',
        'guests' => 20,
        'location' => 'Grand Hall',
    ];
}

/* ---------- double booking ---------- */

test('a venue cannot be booked twice around the same time', function () {
    $owner = rulesUser();
    rulesBooking($owner, ['event_time' => '18:00', 'location' => 'Grand Hall']);

    $this->actingAs(rulesUser())
        ->post(route('customer.bookings.store'), rulesCustomerPayload(rulesPackage(), ['event_time' => '19:30', 'location' => '  grand   HALL ']))
        ->assertSessionHasErrors('event_date');

    expect(Booking::count())->toBe(1);
});

test('same venue far enough apart, or a different venue, is allowed', function () {
    rulesBooking(rulesUser(), ['event_time' => '09:00', 'location' => 'Grand Hall']);
    $package = rulesPackage();
    $customer = rulesUser();

    $this->actingAs($customer)
        ->post(route('customer.bookings.store'), rulesCustomerPayload($package, ['event_time' => '18:00']))
        ->assertSessionHasNoErrors();

    $this->actingAs($customer)
        ->post(route('customer.bookings.store'), rulesCustomerPayload($package, ['event_time' => '09:00', 'location' => 'Beach Resort']))
        ->assertSessionHasNoErrors();

    expect(Booking::count())->toBe(3);
});

test('the daily capacity cap blocks the next booking', function () {
    config(['catering.max_events_per_day' => 2]);
    rulesBooking(rulesUser(), ['location' => 'A', 'event_time' => '08:00']);
    rulesBooking(rulesUser(), ['location' => 'B', 'event_time' => '08:00', 'status' => 'pending']);

    $this->actingAs(rulesUser())
        ->post(route('customer.bookings.store'), rulesCustomerPayload(rulesPackage(), ['location' => 'C']))
        ->assertSessionHasErrors('event_date');
});

test('cancelled bookings free their slot', function () {
    config(['catering.max_events_per_day' => 1]);
    rulesBooking(rulesUser(), ['status' => 'cancelled']);

    $this->actingAs(rulesUser())
        ->post(route('customer.bookings.store'), rulesCustomerPayload(rulesPackage()))
        ->assertSessionHasNoErrors();
});

test('admins are held to the same availability rules, but can edit a booking without clashing with itself', function () {
    config(['catering.max_events_per_day' => 1]);
    $admin = rulesUser('admin');
    $customer = rulesUser();
    $booking = rulesBooking($customer);

    $payload = [
        'user_id' => $customer->id, 'event_name' => 'Party', 'event_date' => $booking->event_date->toDateString(),
        'event_time' => '12:00:00', 'guests' => 10, 'location' => 'Grand Hall', 'status' => 'confirmed', 'total_amount' => 1000,
    ];

    // New booking on the full date: blocked.
    $this->actingAs($admin)->post(route('admin.bookings.store'), $payload)->assertSessionHasErrors('event_date');

    // Editing the existing one (form pre-fills H:i:s): fine.
    $this->actingAs($admin)->put(route('admin.bookings.update', $booking->id), $payload + ['notes' => 'x'])
        ->assertSessionHasNoErrors();
});

/* ---------- money ---------- */

test('editing guests on an untouched total recalculates it', function () {
    $admin = rulesUser('admin');
    $customer = rulesUser();
    $package = rulesPackage(500);
    $booking = rulesBooking($customer, ['package_id' => $package->id, 'guests' => 10, 'total_amount' => 5000]);

    $this->actingAs($admin)->put(route('admin.bookings.update', $booking->id), [
        'user_id' => $customer->id, 'package_id' => $package->id, 'event_name' => 'Party',
        'event_date' => $booking->event_date->toDateString(), 'event_time' => '12:00', 'guests' => 30,
        'location' => 'Grand Hall', 'status' => 'confirmed', 'total_amount' => 5000,
    ])->assertSessionHasNoErrors();

    expect((float) $booking->fresh()->total_amount)->toBe(15000.0);
});

test('totals are computed without float drift', function () {
    expect(Booking::calculateTotal('0.10', 3))->toBe('0.30')
        ->and(Booking::calculateTotal('499.99', 7))->toBe('3499.93');
});

test('payments cannot exceed the balance and statuses follow the money', function () {
    $admin = rulesUser('admin');
    $booking = rulesBooking(rulesUser(), ['total_amount' => 1000]);
    $pay = fn (float $amount, string $status = 'paid') => $this->actingAs($admin)->post(route('admin.payments.store'), [
        'booking_id' => $booking->id, 'amount' => $amount, 'status' => $status,
    ]);

    expect($booking->fresh()->payment_status)->toBe('unpaid');

    $pay(400)->assertSessionHasNoErrors();
    expect($booking->fresh()->payment_status)->toBe('partial')
        ->and($booking->fresh()->balance)->toBe('600.00');

    $pay(700)->assertSessionHasErrors('amount');       // 400 + 700 > 1000
    $pay(900, 'pending')->assertSessionHasNoErrors();  // not received yet, not counted
    expect($booking->fresh()->balance)->toBe('600.00');

    $pay(600)->assertSessionHasNoErrors();
    expect($booking->fresh()->payment_status)->toBe('paid')
        ->and($booking->fresh()->balance)->toBe('0.00');
});

test('new payments on a cancelled booking are rejected', function () {
    $booking = rulesBooking(rulesUser(), ['status' => 'cancelled']);

    $this->actingAs(rulesUser('admin'))->post(route('admin.payments.store'), [
        'booking_id' => $booking->id, 'amount' => 10, 'status' => 'paid',
    ])->assertSessionHasErrors('booking_id');
});

test('receipts exist only for paid payments and only the owner (or back office) can open them', function () {
    $owner = rulesUser();
    $booking = rulesBooking($owner);
    $paid = Payment::create(['user_id' => $owner->id, 'booking_id' => $booking->id, 'amount' => 100, 'status' => 'paid']);
    $pending = Payment::create(['user_id' => $owner->id, 'booking_id' => $booking->id, 'amount' => 100, 'status' => 'pending']);

    $this->actingAs($owner)->get(route('receipts.show', $paid->id))->assertOk()->assertSee($paid->receipt_number);
    $this->actingAs($owner)->get(route('receipts.show', $pending->id))->assertNotFound();
    $this->actingAs(rulesUser())->get(route('receipts.show', $paid->id))->assertNotFound();
    $this->actingAs(rulesUser('staff'))->get(route('receipts.show', $paid->id))->assertOk();
});

/* ---------- customer cancel ---------- */

test('customers can cancel their own upcoming booking, but not someone elses or a same-day one', function () {
    $owner = rulesUser();
    $upcoming = rulesBooking($owner, ['status' => 'pending']);
    $today = rulesBooking($owner, ['event_date' => now()->toDateString(), 'status' => 'confirmed']);

    $this->actingAs(rulesUser())->patch(route('customer.bookings.cancel', $upcoming->id))->assertNotFound();
    $this->actingAs($owner)->patch(route('customer.bookings.cancel', $today->id))->assertSessionHasErrors('cancel');
    $this->actingAs($owner)->patch(route('customer.bookings.cancel', $upcoming->id))->assertRedirect();

    expect($upcoming->fresh()->status)->toBe('cancelled')->and($today->fresh()->status)->toBe('confirmed');
});

/* ---------- auth ---------- */

test('login is rate limited', function () {
    $user = rulesUser();

    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
});

test('deactivated accounts cannot sign in and lose access mid-session', function () {
    $user = rulesUser('user', ['is_active' => false]);
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();

    $active = rulesUser();
    $this->actingAs($active);
    $active->update(['is_active' => false]);

    $this->get(route('customer.dashboard'))->assertRedirect(route('login'));
});

test('admins cannot deactivate themselves', function () {
    $admin = rulesUser('admin');

    $this->actingAs($admin)->put(route('admin.users.update', $admin->id), [
        'name' => $admin->name, 'email' => $admin->email, 'role' => 'admin', 'is_active' => 0,
    ])->assertSessionHasErrors('is_active');
});

/* ---------- roles ---------- */

test('staff run daily operations but cannot reach admin-only areas or delete records', function () {
    $staff = rulesUser('staff');
    $booking = rulesBooking(rulesUser());

    foreach (['admin.dashboard', 'admin.bookings.index', 'admin.inventory.index', 'admin.payments.index', 'admin.events.index', 'admin.customers'] as $name) {
        $this->actingAs($staff)->get(route($name))->assertOk();
    }

    foreach (['admin.users.index', 'admin.reports', 'admin.activity-log', 'admin.packages.index', 'admin.menu-items.index', 'admin.staff.index'] as $name) {
        $this->actingAs($staff)->get(route($name))->assertForbidden();
    }

    $this->actingAs($staff)->delete(route('admin.bookings.destroy', $booking->id))->assertForbidden();
    expect(Booking::find($booking->id))->not->toBeNull();

    $this->actingAs(rulesUser())->get(route('admin.inventory.index'))->assertForbidden();
});

test('deleting a user keeps their bookings and payments (soft delete)', function () {
    $customer = rulesUser();
    $booking = rulesBooking($customer);
    Payment::create(['user_id' => $customer->id, 'booking_id' => $booking->id, 'amount' => 50, 'status' => 'paid']);

    $this->actingAs(rulesUser('admin'))->delete(route('admin.users.destroy', $customer->id))->assertRedirect();

    expect(User::find($customer->id))->toBeNull()
        ->and(Booking::find($booking->id))->not->toBeNull()
        ->and(Payment::count())->toBe(1);
});

/* ---------- inventory ---------- */

test('stock adjustments cannot go negative and are logged with a reason', function () {
    $staff = rulesUser('staff');
    $item = InventoryItem::create(['name' => 'Rice', 'unit' => 'kg', 'quantity' => 10, 'low_stock_threshold' => 5, 'status' => 'active']);

    $this->actingAs($staff)->post(route('admin.inventory.adjust', $item->id), ['change' => -11, 'reason' => 'oops'])
        ->assertSessionHasErrors('change');
    $this->actingAs($staff)->post(route('admin.inventory.adjust', $item->id), ['change' => -6, 'reason' => 'used'])
        ->assertSessionHasNoErrors();

    expect((float) $item->fresh()->quantity)->toBe(4.0)
        ->and($item->fresh()->isLow())->toBeTrue()
        ->and(InventoryItem::low()->count())->toBe(1)
        ->and(ActivityLog::where('action', 'stock_adjusted')->count())->toBe(1);
});

test('reserving supplies for an event deducts stock and returning restores it', function () {
    $staff = rulesUser('staff');
    $event = Event::create(['event_name' => 'Gala', 'event_date' => now()->addWeek()->toDateString(), 'guests' => 50, 'status' => 'upcoming']);
    $item = InventoryItem::create(['name' => 'Chicken', 'unit' => 'kg', 'quantity' => 20, 'low_stock_threshold' => 2, 'status' => 'active']);

    $this->actingAs($staff)->post(route('admin.events.supplies.store', $event->id), ['inventory_item_id' => $item->id, 'quantity' => 25])
        ->assertSessionHasErrors('quantity');

    $this->actingAs($staff)->post(route('admin.events.supplies.store', $event->id), ['inventory_item_id' => $item->id, 'quantity' => 8]);
    $this->actingAs($staff)->post(route('admin.events.supplies.store', $event->id), ['inventory_item_id' => $item->id, 'quantity' => 2]);

    expect((float) $item->fresh()->quantity)->toBe(10.0)
        ->and((float) $event->inventoryItems()->first()->pivot->quantity)->toBe(10.0);

    $this->actingAs($staff)->get(route('admin.events.supplies', $event->id))->assertOk()->assertSee('Chicken');
    $this->actingAs($staff)->delete(route('admin.events.supplies.destroy', [$event->id, $item->id]));

    expect((float) $item->fresh()->quantity)->toBe(20.0)->and($event->inventoryItems()->count())->toBe(0);
});

/* ---------- audit log ---------- */

test('changes are audited and secrets are never stored', function () {
    $admin = rulesUser('admin');

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'New Person', 'email' => 'new@example.com', 'password' => 'SuperSecret123', 'role' => 'staff', 'is_active' => 1,
    ])->assertSessionHasNoErrors();

    $log = ActivityLog::where('subject_type', 'User')->where('action', 'created')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->id)
        ->and(json_encode($log->changes))->not->toContain('SuperSecret123')
        ->and(json_encode($log->changes))->not->toContain('password');

    $this->actingAs($admin)->get(route('admin.activity-log'))->assertOk()->assertSee('User #');
});

/* ---------- reports ---------- */

test('reports honour the date filter and export a formula-safe CSV', function () {
    $admin = rulesUser('admin');
    $evil = rulesUser('user', ['name' => '=HYPERLINK("http://evil")']);

    $inRange = rulesBooking($evil, ['event_date' => '2026-03-10', 'status' => 'confirmed', 'total_amount' => 2000]);
    rulesBooking($evil, ['event_date' => '2025-01-01', 'status' => 'confirmed', 'total_amount' => 9999, 'event_time' => '09:00', 'location' => 'Elsewhere']);
    Payment::create(['user_id' => $evil->id, 'booking_id' => $inRange->id, 'amount' => 700, 'status' => 'paid', 'payment_date' => '2026-03-12']);

    $query = ['from' => '2026-03-01', 'to' => '2026-03-31', 'period' => 'monthly'];

    $this->actingAs($admin)->get(route('admin.reports', $query))
        ->assertOk()->assertSee('2026-03')->assertDontSee('2025-01');

    $csv = $this->actingAs($admin)->get(route('admin.reports.export', $query));
    $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $body = $csv->streamedContent();
    expect($body)->toContain('2026-03')
        ->and($body)->toContain("'=HYPERLINK")           // neutralised
        ->and($body)->not->toContain(',=HYPERLINK');

    $this->actingAs(rulesUser('staff'))->get(route('admin.reports.export'))->assertForbidden();
});

/* ---------- customer booking page ---------- */

test('the customer booking page shows time, balance, receipt link and the cancel button', function () {
    $owner = rulesUser();
    $booking = rulesBooking($owner, ['event_time' => '18:30', 'total_amount' => 1000, 'status' => 'pending']);
    $payment = Payment::create(['user_id' => $owner->id, 'booking_id' => $booking->id, 'amount' => 250, 'status' => 'paid']);

    $this->actingAs($owner)->get(route('customer.bookings.show', $booking->id))
        ->assertOk()
        ->assertSee('6:30 PM')
        ->assertSee('750.00')
        ->assertSee('Partial')
        ->assertSee($payment->receipt_number)
        ->assertSee('Cancel booking');
});

/* ---------- admin dashboard ---------- */

test('the admin dashboard shows KPI cards, carousels and chart data', function () {
    $admin = rulesUser('admin');
    $customer = rulesUser('user', ['name' => 'Maria Santos']);
    rulesBooking($customer, ['status' => 'pending', 'event_time' => '18:30', 'event_name' => 'Pending Fiesta']);
    rulesBooking($customer, ['status' => 'confirmed', 'location' => 'Elsewhere', 'event_name' => 'Confirmed Gala']);
    rulesBooking($customer, ['status' => 'cancelled', 'location' => 'Third', 'total_amount' => 750]);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Accepted Bookings')
        ->assertSee('Lost Revenue')
        ->assertSee('₱750.00')
        ->assertSee('Maria Santos')
        ->assertSee('6:30 PM')
        ->assertSee('Payment Status');

    // staff get the same page minus admin-only quick links
    $this->actingAs(rulesUser('staff'))->get(route('admin.dashboard'))->assertOk()->assertDontSee('Menu items');
});

/* ---------- customer dashboard ---------- */

test('the customer dashboard shows the next booking, a calendar and the balance due', function () {
    $me = rulesUser();
    $date = now()->addDays(10);
    $booking = rulesBooking($me, [
        'event_name' => 'Debut Party', 'event_date' => $date->toDateString(), 'event_time' => '17:00',
        'total_amount' => 2000, 'status' => 'confirmed',
    ]);
    Payment::create(['user_id' => $me->id, 'booking_id' => $booking->id, 'amount' => 500, 'status' => 'paid']);

    $month = $date->format('Y-m');

    $this->actingAs($me)->get(route('customer.dashboard', ['month' => $month]))
        ->assertOk()
        ->assertSee('Debut Party')
        ->assertSee('5:00 PM')
        ->assertSee($date->format('F Y'))
        ->assertSee('1,500.00')               // balance due
        ->assertSee(route('customer.bookings.show', $booking->id));

    // Garbage month values fall back to the current month instead of erroring.
    $this->actingAs($me)->get(route('customer.dashboard', ['month' => '9999-99']))->assertOk()->assertSee(now()->format('F Y'));
    $this->actingAs($me)->get(route('customer.dashboard', ['month' => ['x']]))->assertOk();

    // Someone else's bookings never appear.
    $this->actingAs(rulesUser())->get(route('customer.dashboard', ['month' => $month]))->assertOk()->assertDontSee('Debut Party');
});

/* ---------- login / register styling ---------- */

test('login and register link a stylesheet that really exists', function () {
    foreach (['login', 'register'] as $page) {
        $html = $this->get("/{$page}")->assertOk()->getContent();

        preg_match('/href="([^"]*css\/style\.css[^"]*)"/', $html, $m);
        expect($m)->not->toBeEmpty();
    }

    expect(file_exists(public_path('css/style.css')))->toBeTrue()
        ->and(file_exists(public_path('css/admin.css')))->toBeTrue()
        ->and(file_exists(public_path('css/customer.css')))->toBeTrue();
});

/* ---------- registration flow ---------- */

test('after registering, the customer lands on the login page with a success message and can then sign in', function () {
    $this->post('/register', [
        'name' => 'Nina', 'email' => 'nina@example.com',
        'password' => 'password123', 'password_confirmation' => 'password123',
    ])->assertRedirect(route('login'))
      ->assertSessionHas('success');

    $this->assertGuest();

    $this->followingRedirects()->get(route('login'))->assertSee('Account created successfully');

    $this->post('/login', ['email' => 'nina@example.com', 'password' => 'password123'])
        ->assertRedirect(route('customer.dashboard'));
    $this->assertAuthenticated();
});

/* =====================================================================
   Round 2: password reset, e-mails, lists, availability, hardening
   ===================================================================== */

use App\Notifications\BookingReceived;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingAlert;
use App\Notifications\PaymentReceived;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;

/* ---------- forgot / reset password ---------- */

test('the login page links to a working forgot-password page', function () {
    $this->get('/login')->assertOk()->assertSee(route('password.request'), false);
    $this->get(route('password.request'))->assertOk()->assertSee('Send reset link');
});

test('forgot-password e-mails a link and gives the same answer for unknown addresses', function () {
    Notification::fake();
    $user = rulesUser('user', ['email' => 'real@example.com']);

    $known = $this->post(route('password.email'), ['email' => 'real@example.com']);
    $unknown = $this->post(route('password.email'), ['email' => 'ghost@example.com']);

    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertCount(1);

    // No account enumeration: identical feedback either way.
    expect(session('success'))->not->toBeNull();
    $known->assertSessionHas('success');
    $unknown->assertSessionHas('success')->assertSessionHasNoErrors();
});

test('a valid link sets a new password, a weak one is refused, and the old password stops working', function () {
    $user = rulesUser('user', ['email' => 'reset@example.com']);
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token, 'email' => $user->email, 'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh',
    ])->assertSessionHasErrors('password');           // no digits

    $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee($user->email);

    $this->post(route('password.update'), [
        'token' => $token, 'email' => $user->email, 'password' => 'Brand-New-123', 'password_confirmation' => 'Brand-New-123',
    ])->assertRedirect(route('login'))->assertSessionHas('success');

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->post('/login', ['email' => $user->email, 'password' => 'Brand-New-123'])->assertRedirect(route('customer.dashboard'));

    expect(ActivityLog::where('action', 'password_reset')->count())->toBe(1);
});

test('a used or wrong token cannot reset the password', function () {
    $user = rulesUser();

    $this->post(route('password.update'), [
        'token' => 'not-a-real-token', 'email' => $user->email, 'password' => 'Brand-New-123', 'password_confirmation' => 'Brand-New-123',
    ])->assertSessionHasErrors('email');

    expect(\Illuminate\Support\Facades\Hash::check('password', $user->fresh()->password))->toBeTrue();
});

/* ---------- e-mail notifications ---------- */

test('a customer booking e-mails the customer and alerts active back-office users only', function () {
    Notification::fake();
    $customer = rulesUser();
    $admin = rulesUser('admin');
    $staff = rulesUser('staff');
    $inactiveStaff = rulesUser('staff', ['is_active' => false]);

    $this->actingAs($customer)->post(route('customer.bookings.store'), rulesCustomerPayload(rulesPackage()))->assertSessionHasNoErrors();

    Notification::assertSentTo($customer, BookingReceived::class);
    Notification::assertSentTo([$admin, $staff], NewBookingAlert::class);
    Notification::assertNotSentTo($inactiveStaff, NewBookingAlert::class);
    Notification::assertNotSentTo($customer, NewBookingAlert::class);
});

test('changing a booking status e-mails the customer, other edits do not', function () {
    $customer = rulesUser();
    $booking = rulesBooking($customer, ['status' => 'pending']);
    Notification::fake();

    $booking->update(['notes' => 'just a note']);
    Notification::assertNothingSent();

    $booking->update(['status' => 'confirmed']);
    Notification::assertSentTo($customer, BookingStatusChanged::class, fn ($n) => $n->booking->status === 'confirmed' && $n->oldStatus === 'pending');
});

test('only received payments send a payment e-mail', function () {
    $customer = rulesUser();
    $booking = rulesBooking($customer);
    Notification::fake();

    $payment = Payment::create(['user_id' => $customer->id, 'booking_id' => $booking->id, 'amount' => 100, 'status' => 'pending']);
    Notification::assertNothingSent();

    $payment->update(['status' => 'paid']);
    Notification::assertSentToTimes($customer, PaymentReceived::class, 1);

    Payment::create(['user_id' => $customer->id, 'booking_id' => $booking->id, 'amount' => 50, 'status' => 'paid']);
    Notification::assertSentToTimes($customer, PaymentReceived::class, 2);
});

test('the e-mail text carries the booking facts and links', function () {
    $customer = rulesUser('user', ['name' => 'Ana']);
    $booking = rulesBooking($customer, ['event_name' => 'Debut', 'event_time' => '18:30', 'total_amount' => 1500]);

    $mail = (new BookingReceived($booking))->toMail($customer);
    $text = implode("\n", array_merge($mail->introLines, [$mail->actionUrl, $mail->subject]));

    expect($text)->toContain('Debut')->toContain('6:30 PM')->toContain('1,500.00')
        ->and($mail->actionUrl)->toBe(route('customer.bookings.show', $booking->id));
});

test('a mail failure never breaks saving a booking', function () {
    $customer = rulesUser();
    // Make the notification layer explode.
    Notification::shouldReceive('send')->andThrow(new RuntimeException('smtp down'));
    Notification::shouldReceive('sendNow')->andThrow(new RuntimeException('smtp down'));

    $booking = rulesBooking($customer, ['status' => 'pending']);

    expect(Booking::find($booking->id))->not->toBeNull();
});

/* ---------- lists: correct totals + pagination ---------- */

test('the bookings cards count every booking, not only the visible page', function () {
    $admin = rulesUser('admin');
    $customer = rulesUser();

    foreach (range(1, 13) as $i) {
        rulesBooking($customer, [
            'event_date' => now()->addDays($i)->toDateString(), 'status' => $i <= 4 ? 'pending' : 'confirmed', 'location' => "V{$i}",
        ]);
    }

    $response = $this->actingAs($admin)->get(route('admin.bookings.index'))->assertOk();

    expect($response->viewData('bookings')->count())->toBe(10);          // one page of rows...
    $stats = $response->viewData('stats');
    expect($stats->sum())->toBe(13)                                       // ...but cards cover all 13
        ->and($stats->get('pending'))->toBe(4)
        ->and($stats->get('confirmed'))->toBe(9);
});

test('payments are paginated and the cards cover all payments', function () {
    $admin = rulesUser('admin');
    $customer = rulesUser();
    $booking = rulesBooking($customer, ['total_amount' => 100000]);

    foreach (range(1, 20) as $i) {
        Payment::create(['user_id' => $customer->id, 'booking_id' => $booking->id, 'amount' => 100, 'status' => $i <= 15 ? 'paid' : 'pending']);
    }

    $page1 = $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk();
    expect($page1->viewData('payments')->count())->toBe(15)
        ->and($page1->viewData('payments')->total())->toBe(20)
        ->and($page1->viewData('stats'))->toBe(['total' => 2000.0, 'paid' => 15, 'pending' => 5]);

    $this->actingAs($admin)->get(route('admin.payments.index', ['page' => 2]))->assertOk()
        ->assertViewHas('payments', fn ($p) => $p->count() === 5);
});

test('every admin list is paginated', function () {
    $admin = rulesUser('admin');

    foreach (['admin.events.index', 'admin.staff.index', 'admin.menu-items.index', 'admin.packages.index', 'admin.inventory.index'] as $route) {
        $this->actingAs($admin)->get(route($route))->assertOk();
    }

    $this->actingAs($admin)->get(route('admin.events.index'))->assertViewHas('events', fn ($e) => $e instanceof \Illuminate\Pagination\LengthAwarePaginator);
});

test('paid amounts on the lists come from SQL and are correct', function () {
    $admin = rulesUser('admin');
    $customer = rulesUser();
    $booking = rulesBooking($customer, ['total_amount' => 1000]);
    Payment::create(['user_id' => $customer->id, 'booking_id' => $booking->id, 'amount' => 300, 'status' => 'paid']);
    Payment::create(['user_id' => $customer->id, 'booking_id' => $booking->id, 'amount' => 900, 'status' => 'pending']);

    $row = Booking::withPaid()->find($booking->id);
    expect($row->amount_paid)->toBe('300.00')->and($row->balance)->toBe('700.00')->and($row->payment_status)->toBe('partial');

    $this->actingAs($admin)->get(route('admin.bookings.index'))->assertOk()->assertSee('700.00')->assertSee('Partial');
});

/* ---------- availability endpoint ---------- */

test('the availability endpoint reports free, full and venue-clash dates', function () {
    config(['catering.max_events_per_day' => 2]);
    $customer = rulesUser();
    $date = now()->addDays(5)->toDateString();
    rulesBooking(rulesUser(), ['event_date' => $date, 'event_time' => '18:00', 'location' => 'Grand Hall']);

    $free = $this->actingAs($customer)->getJson(route('customer.availability', ['date' => $date]))->assertOk();
    $free->assertJson(['available' => true, 'remaining' => 1, 'full_dates' => []]);

    $clash = $this->actingAs($customer)->getJson(route('customer.availability', ['date' => $date, 'time' => '19:00', 'location' => 'grand hall']));
    $clash->assertJson(['available' => false])->assertJsonPath('message', fn ($m) => str_contains($m, 'venue'));

    rulesBooking(rulesUser(), ['event_date' => $date, 'event_time' => '09:00', 'location' => 'Elsewhere']);

    $full = $this->actingAs($customer)->getJson(route('customer.availability', ['date' => $date]));
    $full->assertJson(['available' => false, 'remaining' => 0])
        ->assertJsonPath('full_dates.0', \Carbon\Carbon::parse($date)->format('M j'));
});

test('the availability endpoint validates input and is customer-only', function () {
    $customer = rulesUser();

    $this->actingAs($customer)->getJson(route('customer.availability', ['date' => now()->subDay()->toDateString()]))->assertStatus(422);
    $this->actingAs($customer)->getJson(route('customer.availability', ['date' => 'nonsense']))->assertStatus(422);
    $this->actingAs($customer)->getJson(route('customer.availability'))->assertStatus(422);

    $this->actingAs(rulesUser('staff'))->getJson(route('customer.availability', ['date' => now()->addDay()->toDateString()]))->assertForbidden();
    auth()->logout();
    $this->getJson(route('customer.availability', ['date' => now()->addDay()->toDateString()]))->assertUnauthorized();
});

test('the endpoint never leaks other customers details', function () {
    $other = rulesUser('user', ['name' => 'Secret Person']);
    $date = now()->addDays(3)->toDateString();
    rulesBooking($other, ['event_date' => $date, 'event_name' => 'Hidden Wedding', 'location' => 'Private Villa']);

    $body = $this->actingAs(rulesUser())->getJson(route('customer.availability', ['date' => $date]))->getContent();

    expect($body)->not->toContain('Secret Person')->not->toContain('Hidden Wedding')->not->toContain('Private Villa');
});

test('the booking form contains the live availability hint', function () {
    $this->actingAs(rulesUser())->get(route('customer.bookings.create'))->assertOk()
        ->assertSee('id="availability"', false)
        ->assertSee(json_encode(route('customer.availability')), false);
});

/* ---------- production hardening ---------- */

test('security headers are sent, and HSTS only over https', function () {
    $plain = $this->get('/login');
    $plain->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    expect($plain->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'")->toContain("default-src 'self'");
    expect($plain->headers->has('Strict-Transport-Security'))->toBeFalse();

    $secure = $this->get('https://localhost/login');
    expect($secure->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');
});

test('passwords need letters and numbers when registering', function () {
    $base = ['name' => 'Pat', 'email' => 'pat@example.com'];

    $this->post('/register', $base + ['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'])->assertSessionHasErrors('password');
    $this->post('/register', $base + ['password' => '12345678', 'password_confirmation' => '12345678'])->assertSessionHasErrors('password');
    $this->post('/register', $base + ['password' => 'abcd1234', 'password_confirmation' => 'abcd1234'])->assertSessionHasNoErrors();
});

test('login throttling also works per e-mail across different IPs', function () {
    $victim = rulesUser(attrs: ['email' => 'victim@example.com']);

    foreach (range(1, 5) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
            ->post('/login', ['email' => $victim->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    }

    // A brand-new IP is still blocked for that account.
    $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])
        ->post('/login', ['email' => $victim->email, 'password' => 'password'])->assertStatus(429);
});

test('only jpg, png and webp images can be uploaded for packages', function () {
    Storage::fake('public');
    $admin = rulesUser('admin');
    $send = fn (UploadedFile $file) => $this->actingAs($admin)->post(route('admin.packages.store'), [
        'name' => 'Pkg ' . uniqid(), 'price' => 100, 'status' => 'active', 'image' => $file,
    ]);

    $send(UploadedFile::fake()->create('evil.svg', 5, 'image/svg+xml'))->assertSessionHasErrors('image');
    $send(UploadedFile::fake()->create('script.php', 5, 'application/x-php'))->assertSessionHasErrors('image');
    // A real 1x1 PNG (no GD extension needed to build it).
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $send(UploadedFile::fake()->createWithContent('ok.png', $png))->assertSessionHasNoErrors();
});

test('db:backup copies a sqlite database and refuses an in-memory one', function () {
    $dir = storage_path('app/backups');
    $tmp = tempnam(sys_get_temp_dir(), 'bk') ;
    file_put_contents($tmp, 'sqlite-bytes');

    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $tmp]);
    $before = glob($dir . '/db-*.sqlite') ?: [];

    $this->artisan('db:backup')->assertExitCode(0);

    $after = glob($dir . '/db-*.sqlite');
    expect(count($after))->toBe(count($before) + 1);
    foreach (array_diff($after, $before) as $file) { expect(file_get_contents($file))->toBe('sqlite-bytes'); @unlink($file); }

    config(['database.connections.sqlite.database' => ':memory:']);
    $this->artisan('db:backup')->assertExitCode(1);
    @unlink($tmp);
});

test('the customers list shows booking counts without loading every booking', function () {
    $admin = rulesUser('admin');
    $customer = rulesUser('user', ['name' => 'Counted Carl']);
    rulesBooking($customer, ['location' => 'A']);
    rulesBooking($customer, ['location' => 'B', 'event_time' => '07:00']);

    $this->actingAs($admin)->get(route('admin.customers'))->assertOk()
        ->assertViewHas('customers', fn ($c) => $c->first()->bookings_count === 2 && ! $c->first()->relationLoaded('bookings'));
});
