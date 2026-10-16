<?php
// tests/Feature/FeatureSuiteTest.php
// Round 3: rescheduling, payment proofs, day sheet, calendar, customer editing,
// package supplies, PDFs, reminders/SMS and low-stock alerts.

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Event;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\Package;
use App\Models\PackageSupply;
use App\Models\Payment;
use App\Models\RescheduleRequest;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\BookingReminder;
use App\Notifications\LowStockAlert;
use App\Notifications\PaymentProofSubmitted;
use App\Notifications\PaymentReceived;
use App\Notifications\RescheduleDecided;
use App\Notifications\RescheduleRequested;
use App\Services\DocumentPdf;
use App\Services\Sms\SemaphoreSms;
use App\Services\Sms\SmsGateway;
use App\Support\SimplePdf;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/* ---------- helpers (prefix fs so they never clash with other test files) ---------- */

function fsUser(string $role = 'user', array $attrs = []): User
{
    return User::factory()->create(['role' => $role] + $attrs);
}

function fsBooking(User $owner, array $attrs = []): Booking
{
    static $n = 0;
    $n++;

    return Booking::create($attrs + [
        'user_id' => $owner->id,
        'event_name' => "Party {$n}",
        'event_date' => now()->addDays(20)->toDateString(),
        'event_time' => '12:00',
        'guests' => 10,
        'location' => "Venue {$n}",
        'status' => 'confirmed',
        'total_amount' => 1000,
    ]);
}

function fsPackage(float $price = 100): Package
{
    return Package::create(['name' => 'Pkg ' . uniqid(), 'price' => $price, 'status' => 'active']);
}

function fsItem(string $name = 'Rice', float $qty = 100, float $threshold = 5, string $unit = 'kg'): InventoryItem
{
    return InventoryItem::create(['name' => $name, 'unit' => $unit, 'quantity' => $qty, 'low_stock_threshold' => $threshold, 'status' => 'active']);
}

function fsPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

function fsRequest(Booking $booking, array $attrs = []): RescheduleRequest
{
    return $booking->rescheduleRequests()->create($attrs + [
        'requested_date' => now()->addDays(40)->toDateString(), 'requested_time' => '15:00', 'status' => 'pending',
    ]);
}

/* =====================================================================
   1. Rescheduling
   ===================================================================== */

test('a customer can request a new date, staff are told, and the booking page shows it', function () {
    Notification::fake();
    $customer = fsUser();
    $staff = fsUser('staff');
    $booking = fsBooking($customer);
    $new = now()->addDays(45)->toDateString();

    $this->actingAs($customer)->post(route('customer.bookings.reschedule', $booking->id), [
        'requested_date' => $new, 'requested_time' => '17:30', 'reason' => 'Venue changed',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $request = RescheduleRequest::first();
    expect($request->status)->toBe('pending')->and($request->requested_date->toDateString())->toBe($new);
    Notification::assertSentTo($staff, RescheduleRequested::class);

    // The original date is untouched until a decision.
    expect($booking->fresh()->event_date->toDateString())->not->toBe($new);

    $this->actingAs($customer)->get(route('customer.bookings.show', $booking->id))
        ->assertOk()->assertSee('Date change requested')->assertSee('Withdraw request');
});

test('reschedule requests are validated: too soon, same slot, taken date, duplicates, strangers, inactive bookings', function () {
    config(['catering.max_events_per_day' => 1]);
    $customer = fsUser();
    $booking = fsBooking($customer);
    $post = fn ($b, array $data) => $this->actingAs($customer)->post(route('customer.bookings.reschedule', $b->id), $data);

    // earlier than today + 2 days
    $post($booking, ['requested_date' => now()->addDay()->toDateString(), 'requested_time' => '10:00'])->assertSessionHasErrors('requested_date');
    // identical slot
    $post($booking, ['requested_date' => $booking->event_date->toDateString(), 'requested_time' => '12:00'])->assertSessionHasErrors('requested_date');
    // a full day (cap 1)
    $busy = now()->addDays(30)->toDateString();
    fsBooking(fsUser(), ['event_date' => $busy]);
    $post($booking, ['requested_date' => $busy, 'requested_time' => '12:00'])->assertSessionHasErrors('requested_date');
    expect(RescheduleRequest::count())->toBe(0);

    // fine, then a second one while the first is pending
    $free = now()->addDays(50)->toDateString();
    $post($booking, ['requested_date' => $free, 'requested_time' => '10:00'])->assertSessionHasNoErrors();
    $post($booking, ['requested_date' => now()->addDays(51)->toDateString(), 'requested_time' => '10:00'])->assertSessionHasErrors('reschedule');
    expect(RescheduleRequest::count())->toBe(1);

    // someone else's booking, and a cancelled one
    $this->actingAs(fsUser())->post(route('customer.bookings.reschedule', $booking->id), ['requested_date' => $free, 'requested_time' => '10:00'])->assertNotFound();
    $cancelled = fsBooking($customer, ['status' => 'cancelled', 'event_date' => now()->addDays(60)->toDateString()]);
    $post($cancelled, ['requested_date' => now()->addDays(65)->toDateString(), 'requested_time' => '10:00'])->assertSessionHasErrors('reschedule');

    // an event only 1 day away can no longer be moved online
    $close = fsBooking($customer, ['event_date' => now()->addDay()->toDateString()]);
    $post($close, ['requested_date' => now()->addDays(70)->toDateString(), 'requested_time' => '10:00'])->assertSessionHasErrors('reschedule');
});

test('a customer can withdraw a pending request', function () {
    $customer = fsUser();
    $booking = fsBooking($customer);
    $request = fsRequest($booking);

    $this->actingAs($customer)->delete(route('customer.bookings.reschedule.withdraw', $booking->id))->assertRedirect();

    expect($request->fresh()->status)->toBe('withdrawn');
    $this->actingAs($customer)->get(route('customer.bookings.show', $booking->id))->assertSee('Request date change');
});

test('approving moves the booking and its event, resets the reminder and e-mails the customer', function () {
    Notification::fake();
    $customer = fsUser();
    $staff = fsUser('staff');
    $booking = fsBooking($customer, ['reminder_sent_at' => now()]);
    $event = Event::create(['booking_id' => $booking->id, 'event_name' => 'X', 'event_date' => $booking->event_date->toDateString(), 'guests' => 10, 'status' => 'upcoming']);
    $request = fsRequest($booking, ['requested_date' => now()->addDays(40)->toDateString(), 'requested_time' => '15:00']);

    $this->actingAs($staff)->post(route('admin.reschedules.approve', $request->id))->assertSessionHasNoErrors();

    $booking->refresh();
    expect($booking->event_date->toDateString())->toBe(now()->addDays(40)->toDateString())
        ->and(substr($booking->event_time, 0, 5))->toBe('15:00')
        ->and($booking->reminder_sent_at)->toBeNull()
        ->and($event->fresh()->event_date->toDateString())->toBe(now()->addDays(40)->toDateString())
        ->and($request->fresh()->status)->toBe('approved')
        ->and($request->fresh()->decided_by)->toBe($staff->id);

    Notification::assertSentTo($customer, RescheduleDecided::class, fn ($n) => $n->request->status === 'approved');

    // deciding twice is refused
    $this->actingAs($staff)->post(route('admin.reschedules.approve', $request->id))->assertStatus(409);
});

test('approval re-checks availability: a date that got taken meanwhile cannot be approved', function () {
    config(['catering.max_events_per_day' => 1]);
    $customer = fsUser();
    $booking = fsBooking($customer);
    $date = now()->addDays(40)->toDateString();
    $request = fsRequest($booking, ['requested_date' => $date, 'requested_time' => '15:00']);

    fsBooking(fsUser(), ['event_date' => $date]);   // someone else grabs the only slot

    $this->actingAs(fsUser('admin'))->post(route('admin.reschedules.approve', $request->id))->assertSessionHasErrors('event_date');

    expect($request->fresh()->status)->toBe('pending')
        ->and($booking->fresh()->event_date->toDateString())->not->toBe($date);
});

test('rejecting keeps the old date, stores the note and e-mails the customer', function () {
    Notification::fake();
    $customer = fsUser();
    $booking = fsBooking($customer);
    $original = $booking->event_date->toDateString();
    $request = fsRequest($booking);

    $this->actingAs(fsUser('staff'))->post(route('admin.reschedules.reject', $request->id), ['admin_note' => 'Fully booked that week'])->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe('rejected')->and($request->fresh()->admin_note)->toBe('Fully booked that week')
        ->and($booking->fresh()->event_date->toDateString())->toBe($original);
    Notification::assertSentTo($customer, RescheduleDecided::class, fn ($n) => $n->request->status === 'rejected');

    $this->actingAs($customer)->get(route('customer.bookings.show', $booking->id))->assertSee('Fully booked that week');
});

test('only the back office can see or decide reschedule requests', function () {
    $customer = fsUser();
    $request = fsRequest(fsBooking($customer));

    $this->actingAs($customer)->get(route('admin.reschedules.index'))->assertForbidden();
    $this->actingAs($customer)->post(route('admin.reschedules.approve', $request->id))->assertForbidden();
    $this->actingAs(fsUser('staff'))->get(route('admin.reschedules.index'))->assertOk()->assertSee('Waiting for a decision (1)');
    expect($request->fresh()->status)->toBe('pending');
});

/* =====================================================================
   2. Payment proof uploads
   ===================================================================== */

function fsProof(array $over = [], ?UploadedFile $file = null): array
{
    return $over + [
        'amount' => 400, 'payment_method' => 'GCash', 'reference_number' => 'GC' . uniqid(),
        'proof' => $file ?? UploadedFile::fake()->createWithContent('slip.png', fsPng()),
    ];
}

test('uploading a slip creates a pending payment on the PRIVATE disk that does not touch the balance', function () {
    Storage::fake('local');
    Storage::fake('public');
    Notification::fake();
    $customer = fsUser();
    $staff = fsUser('staff');
    $booking = fsBooking($customer, ['total_amount' => 1000]);

    $this->actingAs($customer)->post(route('customer.bookings.payments.store', $booking->id), fsProof())
        ->assertSessionHasNoErrors()->assertRedirect();

    $payment = Payment::first();
    expect($payment->status)->toBe('pending')->and($payment->submitted_by_customer)->toBeTrue()
        ->and($payment->user_id)->toBe($customer->id)
        ->and((float) $payment->amount)->toBe(400.0);

    Storage::disk('local')->assertExists($payment->proof_path);
    expect(Storage::disk('public')->exists($payment->proof_path))->toBeFalse();
    expect($payment->proof_path)->toStartWith('payment-proofs/' . $booking->id . '/')->not->toContain('slip');

    expect(Booking::withPaid()->find($booking->id)->balance)->toBe('1000.00');     // pending money is not money
    Notification::assertSentTo($staff, PaymentProofSubmitted::class);
    Notification::assertNotSentTo($customer, PaymentReceived::class);
});

test('slip uploads are strictly validated', function () {
    Storage::fake('local');
    $customer = fsUser();
    $booking = fsBooking($customer, ['total_amount' => 1000]);
    $post = fn (array $data) => $this->actingAs($customer)->post(route('customer.bookings.payments.store', $booking->id), $data);

    $post(fsProof(['amount' => 1500]))->assertSessionHasErrors('amount');                                   // more than the balance
    $post(fsProof(['payment_method' => 'Cash']))->assertSessionHasErrors('payment_method');                 // only GCash / bank
    $post(fsProof(['reference_number' => '']))->assertSessionHasErrors('reference_number');
    $post(fsProof([], UploadedFile::fake()->create('evil.svg', 5, 'image/svg+xml')))->assertSessionHasErrors('proof');
    $post(fsProof([], UploadedFile::fake()->create('shell.php', 5, 'application/x-php')))->assertSessionHasErrors('proof');
    $post(fsProof([], UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')))->assertSessionHasErrors('proof');
    $post(Arr::except(fsProof(), 'proof'))->assertSessionHasErrors('proof');
    expect(Payment::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);

    // a real PDF is fine
    $pdf = UploadedFile::fake()->createWithContent('slip.pdf', (new SimplePdf)->output());
    $post(fsProof([], $pdf))->assertSessionHasNoErrors();
    expect(Payment::count())->toBe(1);
});

test('duplicate references, spam, cancelled and fully paid bookings are refused', function () {
    Storage::fake('local');
    $customer = fsUser();
    $booking = fsBooking($customer, ['total_amount' => 1000]);
    $post = fn ($b, array $data) => $this->actingAs($customer)->post(route('customer.bookings.payments.store', $b->id), $data);

    $post($booking, fsProof(['reference_number' => 'SAME1', 'amount' => 100]))->assertSessionHasNoErrors();
    $post($booking, fsProof(['reference_number' => 'SAME1', 'amount' => 100]))->assertSessionHasErrors('reference_number');

    $post($booking, fsProof(['amount' => 100]))->assertSessionHasNoErrors();
    $post($booking, fsProof(['amount' => 100]))->assertSessionHasNoErrors();           // 3 open now
    $post($booking, fsProof(['amount' => 100]))->assertSessionHasErrors('proof');       // 4th refused
    expect(Payment::count())->toBe(3);

    $cancelled = fsBooking($customer, ['status' => 'cancelled', 'event_date' => now()->addDays(80)->toDateString()]);
    $post($cancelled, fsProof())->assertSessionHasErrors('proof');

    $paid = fsBooking($customer, ['total_amount' => 500, 'event_date' => now()->addDays(81)->toDateString()]);
    Payment::create(['user_id' => $customer->id, 'booking_id' => $paid->id, 'amount' => 500, 'status' => 'paid']);
    $post($paid, fsProof())->assertSessionHasErrors('proof');

    $this->actingAs(fsUser())->post(route('customer.bookings.payments.store', $booking->id), fsProof())->assertNotFound();   // not their booking
});

test('only the owner and the back office can open a slip, and it is not reachable by URL', function () {
    Storage::fake('local');
    $owner = fsUser();
    $booking = fsBooking($owner);
    $this->actingAs($owner)->post(route('customer.bookings.payments.store', $booking->id), fsProof());
    $payment = Payment::first();

    $this->actingAs($owner)->get(route('proofs.show', $payment->id))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs(fsUser('staff'))->get(route('proofs.show', $payment->id))->assertOk();
    $this->actingAs(fsUser('admin'))->get(route('proofs.show', $payment->id))->assertOk();
    $this->actingAs(fsUser())->get(route('proofs.show', $payment->id))->assertNotFound();

    expect($this->get('/storage/' . $payment->proof_path)->status())->toBeIn([403, 404]);   // no public URL (unsigned = refused)
    auth()->logout();
    $this->get(route('proofs.show', $payment->id))->assertRedirect(route('login'));

    // a payment without a slip has nothing to show
    $manual = Payment::create(['user_id' => $owner->id, 'booking_id' => $booking->id, 'amount' => 10, 'status' => 'paid']);
    $this->actingAs(fsUser('staff'))->get(route('proofs.show', $manual->id))->assertNotFound();
});

test('staff verifying the slip turns it into a real payment with receipt e-mail and balance update', function () {
    Storage::fake('local');
    $customer = fsUser();
    $booking = fsBooking($customer, ['total_amount' => 1000]);
    $this->actingAs($customer)->post(route('customer.bookings.payments.store', $booking->id), fsProof(['amount' => 400]));
    $payment = Payment::first();

    Notification::fake();
    $this->actingAs(fsUser('staff'))->put(route('admin.payments.update', $payment->id), [
        'booking_id' => $booking->id, 'amount' => 400, 'payment_method' => 'GCash', 'status' => 'paid', 'reference_number' => $payment->reference_number,
    ])->assertSessionHasNoErrors();

    expect(Booking::withPaid()->find($booking->id)->balance)->toBe('600.00')->and($payment->fresh()->proof_path)->not->toBeNull();
    Notification::assertSentTo($customer, PaymentReceived::class);

    $this->actingAs(fsUser('staff'))->get(route('admin.payments.index'))->assertOk()->assertSee('Proof');
    $this->actingAs($customer)->get(route('customer.bookings.show', $booking->id))->assertOk()->assertSee('PDF');
});

/* =====================================================================
   3. Event day sheet
   ===================================================================== */

test('the day sheet gathers client, menu, team and supplies for staff', function () {
    $client = fsUser('user', ['name' => 'Client Carla', 'phone' => '0917 555 0000']);
    $package = fsPackage();
    $package->menuItems()->attach(MenuItem::create(['name' => 'Lechon Belly', 'category' => 'Main', 'price' => 1, 'status' => 'active'])->id);
    $booking = fsBooking($client, ['package_id' => $package->id, 'total_amount' => 2000, 'notes' => 'No peanuts please']);
    Payment::create(['user_id' => $client->id, 'booking_id' => $booking->id, 'amount' => 500, 'status' => 'paid']);
    $event = Event::create(['booking_id' => $booking->id, 'event_name' => 'Carla Debut', 'event_date' => $booking->event_date->toDateString(), 'event_time' => '18:30', 'guests' => 80, 'location' => 'Grand Hall', 'status' => 'upcoming']);
    $event->staff()->attach(Staff::create(['name' => 'Chef Mario', 'position' => 'Head Cook', 'status' => 'active'])->id);
    $event->inventoryItems()->attach(fsItem('Chicken', 50, 5)->id, ['quantity' => 12.5]);

    $this->actingAs(fsUser('staff'))->get(route('admin.events.sheet', $event->id))->assertOk()
        ->assertSee('Carla Debut')->assertSee('Client Carla')->assertSee('0917 555 0000')
        ->assertSee('6:30 PM')->assertSee('Grand Hall')->assertSee('No peanuts please')
        ->assertSee('Lechon Belly')->assertSee('Chef Mario')->assertSee('Head Cook')
        ->assertSee('Chicken')->assertSee('12.5 kg')->assertSee('1,500.00');            // balance to collect

    $this->actingAs($client)->get(route('admin.events.sheet', $event->id))->assertForbidden();
    $this->actingAs(fsUser('staff'))->get(route('admin.events.sheet', 9999))->assertNotFound();
});

test('the events list links to the day sheet', function () {
    $booking = fsBooking(fsUser());
    $event = Event::create(['booking_id' => $booking->id, 'event_name' => 'E', 'event_date' => $booking->event_date->toDateString(), 'guests' => 1, 'status' => 'upcoming']);

    $this->actingAs(fsUser('staff'))->get(route('admin.events.index'))->assertOk()->assertSee(route('admin.events.sheet', $event->id), false);
});

/* =====================================================================
   4. Admin calendar
   ===================================================================== */

test('the admin calendar shows live bookings, marks full days and hides cancelled ones', function () {
    config(['catering.max_events_per_day' => 2]);
    $date = now()->addDays(10);
    $customer = fsUser();
    fsBooking($customer, ['event_date' => $date->toDateString(), 'event_name' => 'Pending Fiesta', 'status' => 'pending']);
    fsBooking($customer, ['event_date' => $date->toDateString(), 'event_name' => 'Confirmed Gala', 'event_time' => '19:00']);
    fsBooking($customer, ['event_date' => $date->toDateString(), 'event_name' => 'Cancelled Bash', 'status' => 'cancelled']);

    $response = $this->actingAs(fsUser('staff'))->get(route('admin.calendar', ['month' => $date->format('Y-m')]))
        ->assertOk()->assertSee($date->format('F Y'))->assertSee('Pending Fiesta')->assertSee('Confirmed Gala')
        ->assertSee('7:00pm')->assertSee('FULL')->assertDontSee('Cancelled Bash');

    $this->actingAs(fsUser('admin'))->get(route('admin.calendar', ['month' => 'nonsense']))->assertOk()->assertSee(now()->format('F Y'));
    $this->actingAs(fsUser('admin'))->get(route('admin.calendar', ['month' => ['x']]))->assertOk();
    $this->actingAs(fsUser())->get(route('admin.calendar'))->assertForbidden();
});

/* =====================================================================
   5. Staff can edit customers
   ===================================================================== */

test('staff can view and correct a customers contact details, and it is audited', function () {
    $customer = fsUser('user', ['name' => 'Old Name', 'email' => 'old@example.com']);
    $staff = fsUser('staff');

    $this->actingAs($staff)->get(route('admin.customers.show', $customer->id))->assertOk()->assertSee('Old Name');
    $this->actingAs($staff)->get(route('admin.customers.edit', $customer->id))->assertOk()->assertSee('Phone');

    $this->actingAs($staff)->put(route('admin.customers.update', $customer->id), [
        'name' => 'New Name', 'email' => 'new@example.com', 'phone' => '+63 917 123 4567',
        'role' => 'admin', 'is_active' => 0, 'password' => 'Hacked-123',          // must all be ignored
    ])->assertRedirect(route('admin.customers'));

    $customer->refresh();
    expect($customer->name)->toBe('New Name')->and($customer->phone)->toBe('+63 917 123 4567')
        ->and($customer->role)->toBe('user')->and($customer->is_active)->toBeTrue()
        ->and(\Illuminate\Support\Facades\Hash::check('password', $customer->password))->toBeTrue();

    expect(ActivityLog::where('subject_type', 'User')->where('subject_id', $customer->id)->where('action', 'updated')->where('user_id', $staff->id)->exists())->toBeTrue();
});

test('the customer editor validates input and can never touch staff or admin accounts', function () {
    $customer = fsUser();
    $other = fsUser('user', ['email' => 'taken@example.com']);
    $staff = fsUser('staff');
    $admin = fsUser('admin');
    $put = fn ($id, array $d) => $this->actingAs($staff)->put(route('admin.customers.update', $id), $d + ['name' => 'N', 'email' => 'n@example.com']);

    $put($customer->id, ['email' => 'taken@example.com'])->assertSessionHasErrors('email');
    $put($customer->id, ['phone' => 'call me maybe'])->assertSessionHasErrors('phone');
    $put($customer->id, ['name' => ''])->assertSessionHasErrors('name');
    $put($customer->id, ['email' => $customer->email, 'phone' => ''])->assertSessionHasNoErrors();    // blank phone is fine

    $this->actingAs($staff)->get(route('admin.customers.edit', $admin->id))->assertNotFound();
    $put($admin->id, [])->assertNotFound();
    expect($admin->fresh()->email)->not->toBe('n@example.com');

    $this->actingAs($customer)->get(route('admin.customers.edit', $other->id))->assertForbidden();
});

test('the customers list sends staff to pages they are allowed to open', function () {
    $customer = fsUser('user', ['phone' => '0999 111 2222']);

    $this->actingAs(fsUser('staff'))->get(route('admin.customers'))->assertOk()
        ->assertSee('0999 111 2222')
        ->assertSee(route('admin.customers.edit', $customer->id), false)
        ->assertDontSee(route('admin.users.edit', $customer->id), false);
});

test('a phone number is saved at registration and in the profile', function () {
    $this->post('/register', ['name' => 'Pia', 'email' => 'pia@example.com', 'phone' => '0917 000 1111', 'password' => 'abcd1234', 'password_confirmation' => 'abcd1234']);
    $pia = User::where('email', 'pia@example.com')->first();
    expect($pia->phone)->toBe('0917 000 1111');

    $this->post('/register', ['name' => 'Bad', 'email' => 'bad@example.com', 'phone' => 'xxx', 'password' => 'abcd1234', 'password_confirmation' => 'abcd1234'])->assertSessionHasErrors('phone');

    $this->actingAs($pia)->put(route('customer.profile.update'), ['name' => 'Pia', 'email' => 'pia@example.com', 'phone' => '0918 222 3333'])->assertSessionHasNoErrors();
    expect($pia->fresh()->phone)->toBe('0918 222 3333');
    $this->actingAs($pia)->get(route('customer.profile'))->assertSee('0918 222 3333');
});

/* =====================================================================
   6. Package supply recipes -> reserve stock for an event
   ===================================================================== */

function fsEventWithRecipe(array $recipe, int $guests = 50): array
{
    $package = fsPackage();
    $items = [];
    foreach ($recipe as $name => [$perGuest, $stock]) {
        $items[$name] = fsItem($name, $stock);
        PackageSupply::create(['package_id' => $package->id, 'inventory_item_id' => $items[$name]->id, 'quantity_per_guest' => $perGuest]);
    }
    $booking = fsBooking(fsUser(), ['package_id' => $package->id, 'guests' => $guests]);
    $event = Event::create(['booking_id' => $booking->id, 'event_name' => 'E', 'event_date' => $booking->event_date->toDateString(), 'guests' => $guests, 'status' => 'upcoming']);

    return [$event, $items, $package];
}

test('only the admin defines a package recipe, and re-adding an item updates it', function () {
    $package = fsPackage();
    $item = fsItem();
    $post = fn ($user, array $d) => $this->actingAs($user)->post(route('admin.packages.supplies.store', $package->id), $d);

    $post(fsUser('staff'), ['inventory_item_id' => $item->id, 'quantity_per_guest' => 1])->assertForbidden();
    $post(fsUser('admin'), ['inventory_item_id' => $item->id, 'quantity_per_guest' => 0])->assertSessionHasErrors('quantity_per_guest');
    $post(fsUser('admin'), ['inventory_item_id' => 9999, 'quantity_per_guest' => 1])->assertSessionHasErrors('inventory_item_id');

    $post(fsUser('admin'), ['inventory_item_id' => $item->id, 'quantity_per_guest' => 0.25])->assertSessionHasNoErrors();
    $post(fsUser('admin'), ['inventory_item_id' => $item->id, 'quantity_per_guest' => 0.5]);
    expect(PackageSupply::count())->toBe(1)->and((float) PackageSupply::first()->quantity_per_guest)->toBe(0.5);

    $this->actingAs(fsUser('admin'))->get(route('admin.packages.supplies', $package->id))->assertOk()->assertSee('Rice')->assertSee('25 kg');
    $this->actingAs(fsUser('admin'))->delete(route('admin.packages.supplies.destroy', [$package->id, PackageSupply::first()->id]))->assertRedirect();
    expect(PackageSupply::count())->toBe(0);
});

test('reserving from the package multiplies per-guest amounts by the guest count, once', function () {
    [$event, $items] = fsEventWithRecipe(['Rice' => [0.25, 100], 'Chicken' => [0.3, 100]], 50);
    $staff = fsUser('staff');

    $this->actingAs($staff)->get(route('admin.events.supplies', $event->id))->assertOk()->assertSee('12.5')->assertSee('15');

    $this->actingAs($staff)->post(route('admin.events.supplies.package', $event->id))->assertSessionHasNoErrors();
    expect((float) $items['Rice']->fresh()->quantity)->toBe(87.5)->and((float) $items['Chicken']->fresh()->quantity)->toBe(85.0);

    // pressing it again must not take more
    $this->actingAs($staff)->post(route('admin.events.supplies.package', $event->id))->assertSessionHasNoErrors();
    expect((float) $items['Rice']->fresh()->quantity)->toBe(87.5)
        ->and((float) $event->inventoryItems()->where('inventory_items.id', $items['Rice']->id)->first()->pivot->quantity)->toBe(12.5);

    // guests grow -> only the extra is reserved
    $event->update(['guests' => 100]);
    $this->actingAs($staff)->post(route('admin.events.supplies.package', $event->id));
    expect((float) $items['Rice']->fresh()->quantity)->toBe(75.0);
});

test('a shortage reserves nothing at all', function () {
    [$event, $items] = fsEventWithRecipe(['Rice' => [0.25, 100], 'Beef' => [1, 10]], 50);        // beef: need 50, have 10

    $this->actingAs(fsUser('staff'))->post(route('admin.events.supplies.package', $event->id))->assertSessionHasErrors('package');

    expect((float) $items['Rice']->fresh()->quantity)->toBe(100.0)->and((float) $items['Beef']->fresh()->quantity)->toBe(10.0)
        ->and($event->inventoryItems()->count())->toBe(0);
});

test('the package plan falls back to the booking guest count and the page explains a missing recipe', function () {
    [$event] = fsEventWithRecipe(['Rice' => [0.2, 100]], 40);
    $event->update(['guests' => 0]);
    $this->actingAs(fsUser('staff'))->get(route('admin.events.supplies', $event->id))->assertOk()->assertSee('8');   // 0.2 x 40 guests

    $bare = fsBooking(fsUser(), ['package_id' => fsPackage()->id]);
    $e = Event::create(['booking_id' => $bare->id, 'event_name' => 'E2', 'event_date' => $bare->event_date->toDateString(), 'guests' => 5, 'status' => 'upcoming']);
    $this->actingAs(fsUser('admin'))->get(route('admin.events.supplies', $e->id))->assertOk()->assertSee('no supply recipe');
});

/* =====================================================================
   7. PDF receipts and invoices
   ===================================================================== */

test('the PDF writer produces a structurally valid file and survives awkward text', function () {
    $pdf = new SimplePdf;
    $pdf->text(50, 50, 'Plain', 12);
    $pdf->text(50, 80, 'Tricky (parens) \\ back-slash ñ ₱500 “quotes”', 12, true);
    $pdf->rect(40, 40, 200, 60, [1, .9, .8], [0, 0, 0]);
    $pdf->line(10, 10, 100, 10);
    $out = $pdf->output();

    expect($out)->toStartWith('%PDF-1.4')->toEndWith('%%EOF')->toContain('/Type /Catalog')->toContain('startxref')
        ->toContain('(Plain)')->toContain('\\(parens\\)')->toContain('PHP 500')
        ->and($pdf->textWidth('iiii', 10))->toBeLessThan($pdf->textWidth('MMMM', 10));

    // every xref offset points at "N 0 obj"
    preg_match('/startxref\n(\d+)/', $out, $m);
    expect(substr($out, (int) $m[1], 4))->toBe('xref');
    preg_match_all('/(\d{10}) 00000 n/', $out, $offsets);
    foreach ($offsets[1] as $i => $offset) {
        expect(substr($out, (int) $offset, strlen((string) ($i + 1)) + 6))->toBe(($i + 1) . ' 0 obj');
    }
});

test('receipt PDFs exist only for paid payments and only for the owner or the back office', function () {
    $owner = fsUser('user', ['name' => 'Pay Payer']);
    $booking = fsBooking($owner, ['total_amount' => 3000]);
    $paid = Payment::create(['user_id' => $owner->id, 'booking_id' => $booking->id, 'amount' => 1250.5, 'status' => 'paid', 'payment_method' => 'GCash']);
    $pending = Payment::create(['user_id' => $owner->id, 'booking_id' => $booking->id, 'amount' => 10, 'status' => 'pending']);

    $response = $this->actingAs($owner)->get(route('receipts.pdf', $paid->id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $body = $response->getContent();
    expect($body)->toStartWith('%PDF-1.4')->toContain($paid->receipt_number)->toContain('PHP 1,250.50')->toContain('Pay Payer')->toContain('PHP 1,749.50');

    $this->actingAs($owner)->get(route('receipts.pdf', $pending->id))->assertNotFound();
    $this->actingAs(fsUser())->get(route('receipts.pdf', $paid->id))->assertNotFound();
    $this->actingAs(fsUser('staff'))->get(route('receipts.pdf', $paid->id))->assertOk();
    auth()->logout();
    $this->get(route('receipts.pdf', $paid->id))->assertRedirect(route('login'));
});

test('invoice PDFs list charges, payments and the balance, with the same access rules', function () {
    $owner = fsUser('user', ['phone' => '0917 999 8888']);
    $package = Package::create(['name' => 'Platinum', 'price' => 250, 'status' => 'active']);
    $booking = fsBooking($owner, ['package_id' => $package->id, 'guests' => 40, 'total_amount' => 10000, 'event_name' => 'Big Wedding']);
    Payment::create(['user_id' => $owner->id, 'booking_id' => $booking->id, 'amount' => 4000, 'status' => 'paid', 'reference_number' => 'REF77']);

    $body = $this->actingAs($owner)->get(route('invoices.pdf', $booking->id))->assertOk()->getContent();
    expect($body)->toContain('Big Wedding')->toContain('Platinum')->toContain('PHP 250.00 x 40 guests')->toContain('PHP 10,000.00')
        ->toContain('#REF77')->toContain('PHP 6,000.00')->toContain('0917 999 8888')->toContain('PARTIAL');

    $this->actingAs(fsUser())->get(route('invoices.pdf', $booking->id))->assertNotFound();
    $this->actingAs(fsUser('staff'))->get(route('invoices.pdf', $booking->id))->assertOk();
});

test('a long payment history continues on a second page', function () {
    $owner = fsUser();
    $booking = fsBooking($owner, ['total_amount' => 999999]);
    foreach (range(1, 70) as $i) {
        Payment::create(['user_id' => $owner->id, 'booking_id' => $booking->id, 'amount' => 10, 'status' => 'paid']);
    }

    $pdf = app(DocumentPdf::class)->invoice($booking);

    expect(substr_count($pdf, '/Type /Page '))->toBeGreaterThanOrEqual(2);
});

test('the payment e-mail carries the receipt PDF as an attachment', function () {
    $owner = fsUser();
    $booking = fsBooking($owner);
    $payment = Payment::create(['user_id' => $owner->id, 'booking_id' => $booking->id, 'amount' => 100, 'status' => 'paid']);

    $mail = (new PaymentReceived($payment))->toMail($owner);

    expect($mail->rawAttachments)->toHaveCount(1)
        ->and($mail->rawAttachments[0]['name'])->toBe($payment->receipt_number . '.pdf')
        ->and($mail->rawAttachments[0]['options']['mime'])->toBe('application/pdf')
        ->and($mail->rawAttachments[0]['data'])->toStartWith('%PDF-1.4');
});

test('the receipt page and booking page link to the PDFs', function () {
    $owner = fsUser();
    $booking = fsBooking($owner);
    $payment = Payment::create(['user_id' => $owner->id, 'booking_id' => $booking->id, 'amount' => 100, 'status' => 'paid']);

    $this->actingAs($owner)->get(route('receipts.show', $payment->id))->assertSee(route('receipts.pdf', $payment->id), false);
    $this->actingAs($owner)->get(route('customer.bookings.show', $booking->id))
        ->assertSee(route('invoices.pdf', $booking->id), false)->assertSee(route('receipts.pdf', $payment->id), false);
});

/* =====================================================================
   8. Reminders (e-mail + SMS)
   ===================================================================== */

function fsSmsSpy(?string $failFor = null): object
{
    $spy = new class implements SmsGateway {
        public array $sent = [];
        public ?string $failFor = null;

        public function send(string $to, string $message): bool
        {
            if ($this->failFor === $to) {
                throw new RuntimeException('provider down');
            }
            $this->sent[] = [$to, $message];

            return true;
        }
    };
    $spy->failFor = $failFor;
    app()->instance(SmsGateway::class, $spy);

    return $spy;
}

test('reminders go only to confirmed bookings on the target day, once, by e-mail and SMS', function () {
    Notification::fake();
    $sms = fsSmsSpy();
    config(['catering.reminder_days_before' => 1]);
    $tomorrow = now()->addDay()->toDateString();

    $ana = fsUser('user', ['phone' => '0917 111 2222']);
    $noPhone = fsUser();
    $target = fsBooking($ana, ['event_date' => $tomorrow, 'event_name' => 'Ana Fiesta', 'location' => 'Hall A', 'event_time' => '18:00']);
    $second = fsBooking($noPhone, ['event_date' => $tomorrow]);
    fsBooking(fsUser(), ['event_date' => $tomorrow, 'status' => 'pending']);
    fsBooking(fsUser(), ['event_date' => $tomorrow, 'status' => 'cancelled']);
    fsBooking(fsUser(), ['event_date' => now()->addDays(2)->toDateString()]);
    fsBooking(fsUser(), ['event_date' => $tomorrow, 'reminder_sent_at' => now()->subHour()]);
    fsBooking(fsUser('user', ['is_active' => false]), ['event_date' => $tomorrow]);

    $this->artisan('bookings:send-reminders')->expectsOutput('Reminders sent: 2')->assertExitCode(0);

    Notification::assertSentTo([$ana, $noPhone], BookingReminder::class);
    Notification::assertSentTimes(BookingReminder::class, 2);
    expect($target->fresh()->reminder_sent_at)->not->toBeNull()->and($second->fresh()->reminder_sent_at)->not->toBeNull();

    expect($sms->sent)->toHaveCount(1)->and($sms->sent[0][0])->toBe('09171112222')
        ->and($sms->sent[0][1])->toContain('Ana Fiesta')->toContain('Hall A')->toContain('6:00PM')
        ->and(mb_strlen($sms->sent[0][1]))->toBeLessThanOrEqual(160);

    // running again sends nothing more
    Notification::fake();
    $this->artisan('bookings:send-reminders')->expectsOutput('Reminders sent: 0');
    Notification::assertNothingSent();

    // marking a reminder is not an audit-log event
    expect(ActivityLog::where('subject_type', 'Booking')->where('subject_id', $target->id)->where('action', 'updated')->count())->toBe(0);
});

test('an SMS failure never causes a duplicate e-mail or blocks other reminders', function () {
    Notification::fake();
    fsSmsSpy(failFor: '0917000001');
    $tomorrow = now()->addDay()->toDateString();
    $bad = fsUser('user', ['phone' => '0917000001']);
    $good = fsUser('user', ['phone' => '0917000002']);
    $badBooking = fsBooking($bad, ['event_date' => $tomorrow]);
    fsBooking($good, ['event_date' => $tomorrow]);

    $this->artisan('bookings:send-reminders')->assertExitCode(0);

    Notification::assertSentTo($bad, BookingReminder::class);
    Notification::assertSentTo($good, BookingReminder::class);
    expect($badBooking->fresh()->reminder_sent_at)->not->toBeNull();       // so it is not e-mailed again tomorrow

    Notification::fake();
    $this->artisan('bookings:send-reminders');
    Notification::assertNothingSent();
});

test('the reminder e-mail says how soon the event is and what is still owed', function () {
    $customer = fsUser('user', ['name' => 'Rina']);
    $booking = fsBooking($customer, ['event_date' => now()->addDay()->toDateString(), 'total_amount' => 800, 'event_name' => 'Rina Party']);

    $mail = (new BookingReminder($booking))->toMail($customer);
    $text = implode("\n", $mail->introLines) . "\n" . $mail->subject;

    expect($mail->subject)->toContain('tomorrow')->and($text)->toContain('Rina Party')->toContain('800.00')->toContain('remaining balance');
});

test('the Semaphore driver posts the message with the key the provider expects', function () {
    Http::fake(['api.semaphore.co/*' => Http::response([['message_id' => 1]], 200)]);

    expect((new SemaphoreSms('secret-key-123', 'CaterEase'))->send('09171234567', 'Hello'))->toBeTrue();

    Http::assertSent(fn ($r) => $r->url() === 'https://api.semaphore.co/api/v4/messages'
        && $r['apikey'] === 'secret-key-123' && $r['number'] === '09171234567' && $r['sendername'] === 'CaterEase');
});

test('the Semaphore driver reports a provider failure', function () {
    Http::fake(['api.semaphore.co/*' => Http::response('nope', 500)]);

    expect((new SemaphoreSms('k', 'S'))->send('0917', 'x'))->toBeFalse();
});

test('the Semaphore driver sends nothing without an API key', function () {
    Http::fake();

    expect((new SemaphoreSms(null, 'S'))->send('0917', 'x'))->toBeFalse();
    Http::assertNothingSent();
});

test('the SMS driver is chosen by configuration and defaults to the safe log driver', function () {
    expect(app(SmsGateway::class))->toBeInstanceOf(\App\Services\Sms\LogSms::class);

    config(['services.sms.driver' => 'semaphore', 'services.sms.semaphore.key' => 'abc']);
    expect(app(SmsGateway::class))->toBeInstanceOf(SemaphoreSms::class);
});

test('the morning jobs are scheduled', function () {
    $commands = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode(' | ');

    expect($commands)->toContain('bookings:send-reminders')->toContain('inventory:low-stock-digest')->toContain('db:backup');
});

/* =====================================================================
   9. Low-stock e-mails
   ===================================================================== */

test('crossing into low stock alerts active admin and staff once, not on every change', function () {
    Notification::fake();
    $admin = fsUser('admin');
    $staff = fsUser('staff');
    $inactive = fsUser('staff', ['is_active' => false]);
    $customer = fsUser();
    $item = fsItem('Cooking Oil', 10, 5, 'L');

    $item->update(['quantity' => 8]);                       // still fine
    Notification::assertNothingSent();

    $item->update(['quantity' => 4]);                       // crosses the line
    Notification::assertSentTo([$admin, $staff], LowStockAlert::class);
    Notification::assertNotSentTo([$inactive, $customer], LowStockAlert::class);
    Notification::assertCount(2);

    $item->update(['quantity' => 3]);                       // already low: no spam
    Notification::assertCount(2);

    $item->update(['quantity' => 20]);
    $item->update(['quantity' => 5]);                       // crosses again (<= threshold)
    Notification::assertCount(4);
});

test('inactive items, other fields and raised thresholds behave sensibly', function () {
    Notification::fake();
    fsUser('admin');
    $off = fsItem('Old Stock', 10, 5);
    $off->update(['status' => 'inactive']);
    $off->update(['quantity' => 1]);
    Notification::assertNothingSent();

    $item = fsItem('Salt', 10, 5);
    $item->update(['name' => 'Sea Salt']);                   // not a stock change
    Notification::assertNothingSent();

    $item->update(['low_stock_threshold' => 12]);            // threshold raised above the quantity
    Notification::assertCount(1);
});

test('the stock-adjust screen triggers the alert and the mail lists the item', function () {
    Notification::fake();
    $admin = fsUser('admin');
    $item = fsItem('Flour', 10, 5, 'kg');

    $this->actingAs(fsUser('staff'))->post(route('admin.inventory.adjust', $item->id), ['change' => -6, 'reason' => 'used'])->assertSessionHasNoErrors();
    Notification::assertSentTo($admin, LowStockAlert::class, function ($n) use ($admin) {
        $mail = $n->toMail($admin);

        return str_contains(implode(' ', $mail->introLines), 'Flour: 4 kg left (alert at 5)') && $mail->actionUrl === route('admin.inventory.index', ['filter' => 'low']);
    });
});

test('the daily digest lists everything still low and stays quiet when all is well', function () {
    Notification::fake();
    $admin = fsUser('admin');
    $staff = fsUser('staff');

    $this->artisan('inventory:low-stock-digest')->expectsOutput('Nothing is low on stock.')->assertExitCode(0);
    Notification::assertNothingSent();

    fsItem('Sugar', 2, 5);
    fsItem('Eggs', 1, 3, 'tray');
    fsItem('Plenty', 100, 5);

    $this->artisan('inventory:low-stock-digest')->expectsOutput('Digest sent for 2 item(s).')->assertExitCode(0);

    Notification::assertSentTo([$admin, $staff], LowStockAlert::class, fn ($n) => $n->digest && $n->items->pluck('name')->sort()->values()->all() === ['Eggs', 'Sugar']);
    Notification::assertCount(2);
});

/* =====================================================================
   Dashboard and navigation
   ===================================================================== */

test('the dashboard and menu surface pending reschedules and payment proofs', function () {
    Storage::fake('local');
    $customer = fsUser();
    $booking = fsBooking($customer);
    fsRequest($booking);
    $this->actingAs($customer)->post(route('customer.bookings.payments.store', $booking->id), fsProof(['amount' => 100]));

    $staff = fsUser('staff');
    $page = $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()
        ->assertSee('Reschedule requests')->assertSee('Payment proofs to verify')->assertSee(route('admin.calendar'), false);
    expect($page->viewData('pendingReschedules'))->toBe(1)->and($page->viewData('proofsToVerify'))->toBe(1);

    $this->actingAs($staff)->get(route('admin.bookings.index'))->assertSee('nav-badge');
});

test('every new page renders for its role', function () {
    $admin = fsUser('admin');
    $package = fsPackage();
    $customer = fsUser();

    foreach (['admin.calendar', 'admin.reschedules.index'] as $name) {
        $this->actingAs($admin)->get(route($name))->assertOk();
    }
    $this->actingAs($admin)->get(route('admin.packages.supplies', $package->id))->assertOk();
    $this->actingAs($admin)->get(route('admin.customers.edit', $customer->id))->assertOk();
    $this->actingAs($customer)->get(route('customer.dashboard'))->assertOk();
    $this->actingAs($customer)->get(route('customer.bookings.show', fsBooking($customer)->id))
        ->assertOk()->assertSee('Need a different date?')->assertSee('Pay by GCash or bank transfer');
});
