<?php

use App\Models\Booking;
use App\Models\MenuItem;
use App\Models\Package;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function makeCustomer(): User
{
    return User::factory()->create(['role' => 'user']);
}

function makePackage(array $attrs = []): Package
{
    return Package::create($attrs + ['name' => 'Gold', 'price' => 500, 'status' => 'active']);
}

test('home redirects to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('guests are sent to login', function () {
    $this->get('/admin/dashboard')->assertRedirect(route('login'));
    $this->get('/customer/dashboard')->assertRedirect(route('login'));
});

test('roles are kept apart', function () {
    $this->actingAs(makeCustomer())->get('/admin/dashboard')->assertForbidden();
    $this->actingAs(makeAdmin())->get('/customer/dashboard')->assertForbidden();
});

test('registration always creates a customer, even if role is posted', function () {
    $this->post('/register', [
        'name' => 'Eve',
        'email' => 'eve@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'admin',
    ])->assertRedirect(route('login'));

    // Registering does not sign anyone in; they must log in first.
    $this->assertGuest();

    expect(User::where('email', 'eve@example.com')->value('role'))->toBe('user');
});

test('every admin page renders', function () {
    $admin = makeAdmin();

    foreach ([
        'admin.dashboard', 'admin.reports', 'admin.customers',
        'admin.bookings.index', 'admin.bookings.create',
        'admin.events.index', 'admin.events.create',
        'admin.menu-items.index', 'admin.menu-items.create',
        'admin.packages.index', 'admin.packages.create',
        'admin.payments.index', 'admin.payments.create',
        'admin.users.index', 'admin.users.create',
        'admin.staff.index', 'admin.staff.create',
        'admin.inventory.index', 'admin.inventory.create', 'admin.activity-log',
    ] as $name) {
        $this->actingAs($admin)->get(route($name))->assertOk();
    }
});

test('every customer page renders', function () {
    $customer = makeCustomer();

    foreach ([
        'customer.dashboard', 'customer.packages', 'customer.profile',
        'customer.bookings.index', 'customer.bookings.create',
    ] as $name) {
        $this->actingAs($customer)->get(route($name))->assertOk();
    }
});

test('customer booking price and ownership are set by the server', function () {
    $customer = makeCustomer();
    $other = makeCustomer();
    $package = makePackage(['price' => 500]);

    $this->actingAs($customer)->post(route('customer.bookings.store'), [
        'package_id' => $package->id,
        'event_name' => 'Birthday',
        'event_date' => now()->addWeek()->toDateString(),
        'event_time' => '18:00',
        'guests' => 40,
        'location' => 'Home',
        // spoofed fields must be ignored
        'user_id' => $other->id,
        'status' => 'confirmed',
        'total_amount' => 1,
    ])->assertRedirect();

    $booking = Booking::firstOrFail();

    expect($booking->user_id)->toBe($customer->id)
        ->and($booking->status)->toBe('pending')
        ->and((float) $booking->total_amount)->toBe(20000.0);
});

test('customers cannot open other customers bookings', function () {
    $owner = makeCustomer();
    $booking = Booking::create([
        'user_id' => $owner->id,
        'event_name' => 'Wedding',
        'event_date' => now()->addMonth()->toDateString(),
        'guests' => 10,
    ]);

    $this->actingAs($owner)->get(route('customer.bookings.show', $booking->id))->assertOk();
    $this->actingAs(makeCustomer())->get(route('customer.bookings.show', $booking->id))->assertNotFound();
});

test('admin can create a package with menu items', function () {
    $item = MenuItem::create(['name' => 'Lechon', 'price' => 100, 'status' => 'active']);

    $this->actingAs(makeAdmin())->post(route('admin.packages.store'), [
        'name' => 'Fiesta',
        'price' => 650,
        'status' => 'active',
        'menu_item_ids' => [$item->id],
    ])->assertRedirect(route('admin.packages.index'));

    expect(Package::first()->menuItems->pluck('id')->all())->toBe([$item->id]);
});

test('admin payment inherits the bookings customer', function () {
    $customer = makeCustomer();
    $booking = Booking::create([
        'user_id' => $customer->id,
        'event_name' => 'Debut',
        'event_date' => now()->addMonth()->toDateString(),
        'guests' => 100,
        'total_amount' => 50000,
    ]);

    $this->actingAs(makeAdmin())->post(route('admin.payments.store'), [
        'booking_id' => $booking->id,
        'amount' => 10000,
        'status' => 'paid',
    ])->assertRedirect(route('admin.payments.index'));

    expect($booking->payments()->first()->user_id)->toBe($customer->id);
});

test('admin can assign staff to an event', function () {
    $cook = Staff::create(['name' => 'Ana', 'status' => 'active']);

    $this->actingAs(makeAdmin())->post(route('admin.events.store'), [
        'event_name' => 'Gala',
        'event_date' => now()->addWeek()->toDateString(),
        'guests' => 80,
        'status' => 'upcoming',
        'staff_ids' => [$cook->id],
    ])->assertRedirect(route('admin.events.index'));

    expect($cook->events()->count())->toBe(1);
});

test('admin cannot delete their own account', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $admin->id))->assertSessionHasErrors('delete');

    expect(User::find($admin->id))->not->toBeNull();
});
