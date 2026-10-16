<?php
// database/seeders/DemoDataSeeder.php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Event;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\Package;
use App\Models\PackageSupply;
use App\Models\Payment;
use App\Models\Staff;
use App\Models\User;
use App\Support\SimplePdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Realistic sample data for local testing: staff, customers, menu, packages, inventory,
 * bookings in every status, payments (including slips to verify), events and reschedule requests.
 *
 *   php artisan demo:seed        (or: php artisan db:seed --class=DemoDataSeeder)
 *   php artisan demo:clear       removes ALL of it again (keeps the admin account)
 *
 * No e-mails are sent while seeding. Every demo account uses the password below.
 */
class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'Demo@12345';
    public const DOMAIN = 'demo.test';

    /** @var array<string, Package> */
    private array $packages = [];

    /** @var array<string, InventoryItem> */
    private array $stock = [];

    /** @var User[] */
    private array $customers = [];

    /** @var Staff[] */
    private array $crew = [];

    public function run(): void
    {
        if (User::where('email', 'like', '%@' . self::DOMAIN)->exists()) {
            $this->command?->warn('Demo data is already in the database. Run "php artisan demo:clear" first to start over.');

            return;
        }

        // No observers, no e-mails, no audit noise while filling the database.
        Model::withoutEvents(function () {
            $this->staffAccounts();
            $this->customerAccounts();
            $menu = $this->menuItems();
            $this->packages($menu);
            $this->inventory();
            $this->recipes();
            $this->kitchenCrew();
            $this->bookingsAndEverythingAroundThem();
        });

        $this->auditTrail();
        $this->printLogins();
    }

    /* ------------------------------------------------------------------ people */

    private function staffAccounts(): void
    {
        foreach ([
            ['Liza Reyes', 'staff1', true],
            ['Marco Villanueva', 'staff2', true],
            ['Dennis Cruz (deactivated)', 'staff3', false],
        ] as [$name, $handle, $active]) {
            User::create([
                'name' => $name, 'email' => "{$handle}@" . self::DOMAIN, 'password' => self::PASSWORD,
                'role' => 'staff', 'is_active' => $active, 'phone' => '0918 200 000' . substr($handle, -1),
            ]);
        }
    }

    private function customerAccounts(): void
    {
        $names = ['Maria Santos', 'Juan Dela Cruz', 'Ana Reyes', 'Pedro Garcia', 'Sofia Lim', 'Carlo Mendoza', 'Bea Ramos', 'Rico Flores (deactivated)'];

        foreach ($names as $i => $name) {
            $n = $i + 1;
            $this->customers[$n] = User::create([
                'name' => $name, 'email' => "customer{$n}@" . self::DOMAIN, 'password' => self::PASSWORD,
                'role' => 'user', 'is_active' => $n !== 8, 'phone' => '0917 100 000' . $n,
            ]);
        }
    }

    private function kitchenCrew(): void
    {
        foreach ([
            ['Roberto Alonzo', 'Head Chef'], ['Elena Bautista', 'Sous Chef'], ['Mario Soriano', 'Cook'], ['Joy Fernandez', 'Cook'],
            ['Ramon Dizon', 'Server'], ['Tess Castillo', 'Server'], ['Paolo Navarro', 'Driver'],
        ] as $i => [$name, $position]) {
            $this->crew[] = Staff::create([
                'name' => $name, 'position' => $position, 'status' => 'active',
                'phone' => '0919 300 00' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'email' => strtolower(str_replace(' ', '.', $name)) . '@' . self::DOMAIN,
            ]);
        }

        Staff::create(['name' => 'Old Cook (inactive)', 'position' => 'Cook', 'status' => 'inactive']);
    }

    /* ------------------------------------------------------------ menu, packages */

    /** @return array<string, MenuItem> */
    private function menuItems(): array
    {
        $rows = [
            ['Chicken Adobo', 'Main Course', 150, 'Classic soy-vinegar braised chicken.'],
            ['Lechon Kawali', 'Main Course', 180, 'Crispy deep-fried pork belly.'],
            ['Beef Caldereta', 'Main Course', 220, 'Beef stew in rich tomato-liver sauce.'],
            ['Pancit Canton', 'Main Course', 120, 'Stir-fried noodles with vegetables.'],
            ['Fried Chicken', 'Main Course', 140, 'Golden crispy chicken.'],
            ['Pork BBQ Skewers', 'Main Course', 130, 'Sweet and smoky grilled skewers.'],
            ['Seafood Paella', 'Main Course', 260, 'Seasonal special (currently unavailable).'],
            ['Fruit Salad', 'Salad', 90, 'Fresh fruits in cream.'],
            ['Caesar Salad', 'Salad', 100, 'Romaine, croutons and parmesan.'],
            ['Leche Flan', 'Dessert', 85, 'Silky caramel custard.'],
            ['Buko Pandan', 'Dessert', 80, 'Young coconut and pandan jelly.'],
            ['Brazo de Mercedes', 'Dessert', 95, 'Meringue roll with custard.'],
            ['Iced Tea', 'Beverage', 40, 'Bottomless house iced tea.'],
            ['Lemonade', 'Beverage', 45, 'Freshly squeezed lemonade.'],
            ['Soft Drinks', 'Beverage', 50, 'Assorted bottled soft drinks.'],
        ];

        $items = [];
        foreach ($rows as [$name, $category, $price, $description]) {
            $items[$name] = MenuItem::create([
                'name' => $name, 'category' => $category, 'price' => $price, 'description' => $description,
                'status' => $name === 'Seafood Paella' ? 'inactive' : 'active',
            ]);
        }

        return $items;
    }

    /** @param array<string, MenuItem> $menu */
    private function packages(array $menu): void
    {
        $defs = [
            'Silver' => [250, 'active', 'Simple and satisfying. Great for small gatherings.',
                ['Chicken Adobo', 'Pancit Canton', 'Fruit Salad', 'Leche Flan', 'Iced Tea']],
            'Gold' => [350, 'active', 'Our most popular package for birthdays and anniversaries.',
                ['Chicken Adobo', 'Lechon Kawali', 'Pancit Canton', 'Caesar Salad', 'Leche Flan', 'Buko Pandan', 'Iced Tea', 'Soft Drinks']],
            'Platinum' => [500, 'active', 'The full spread for weddings and corporate galas.',
                ['Chicken Adobo', 'Lechon Kawali', 'Beef Caldereta', 'Pork BBQ Skewers', 'Pancit Canton', 'Caesar Salad', 'Fruit Salad', 'Leche Flan', 'Brazo de Mercedes', 'Lemonade', 'Soft Drinks']],
            'Kiddie Party' => [220, 'active', 'Kid-friendly favorites for children\'s parties.',
                ['Fried Chicken', 'Pancit Canton', 'Fruit Salad', 'Buko Pandan', 'Soft Drinks']],
            'Holiday Special' => [450, 'inactive', 'Seasonal package (hidden from customers while inactive).',
                ['Lechon Kawali', 'Seafood Paella', 'Brazo de Mercedes', 'Lemonade']],
        ];

        foreach ($defs as $name => [$price, $status, $description, $dishes]) {
            $package = Package::create(['name' => $name, 'price' => $price, 'status' => $status, 'description' => $description]);
            $package->menuItems()->attach(collect($dishes)->map(fn ($d) => $menu[$d]->id)->all());
            $this->packages[$name] = $package;
        }
    }

    /* ---------------------------------------------------------------- inventory */

    private function inventory(): void
    {
        // [name, unit, quantity, low-stock alert at]. Beef, oil and eggs end up LOW so the alerts can be tested.
        foreach ([
            ['Rice', 'kg', 220, 30], ['Chicken', 'kg', 90, 20], ['Pork', 'kg', 70, 20], ['Beef', 'kg', 45, 25],
            ['Cooking Oil', 'L', 4, 10], ['Sugar', 'kg', 40, 10], ['Eggs', 'tray', 3, 5], ['Flour', 'kg', 45, 10],
            ['Noodles', 'kg', 60, 15], ['Soft Drinks', 'bottle', 300, 60], ['Disposable Plates', 'pcs', 900, 200],
            ['Utensil Sets', 'pcs', 850, 200], ['Ice', 'kg', 120, 30],
        ] as [$name, $unit, $qty, $alert]) {
            $this->stock[$name] = InventoryItem::create(['name' => $name, 'unit' => $unit, 'quantity' => $qty, 'low_stock_threshold' => $alert, 'status' => 'active']);
        }

        InventoryItem::create(['name' => 'Old Chafing Fuel', 'unit' => 'can', 'quantity' => 2, 'low_stock_threshold' => 5, 'status' => 'inactive']);
    }

    /** Per-guest recipes, so "Reserve from package" works on events. */
    private function recipes(): void
    {
        $recipes = [
            'Silver' => ['Rice' => 0.15, 'Chicken' => 0.2, 'Noodles' => 0.1, 'Disposable Plates' => 1, 'Utensil Sets' => 1, 'Ice' => 0.2],
            'Gold' => ['Rice' => 0.18, 'Chicken' => 0.2, 'Pork' => 0.2, 'Noodles' => 0.1, 'Soft Drinks' => 1, 'Disposable Plates' => 1, 'Utensil Sets' => 1, 'Ice' => 0.25],
            'Platinum' => ['Rice' => 0.2, 'Chicken' => 0.2, 'Pork' => 0.25, 'Beef' => 0.2, 'Noodles' => 0.1, 'Soft Drinks' => 1.5, 'Disposable Plates' => 1, 'Utensil Sets' => 1, 'Ice' => 0.3],
            'Kiddie Party' => ['Rice' => 0.1, 'Chicken' => 0.15, 'Noodles' => 0.08, 'Soft Drinks' => 1, 'Disposable Plates' => 1, 'Utensil Sets' => 1],
        ];

        foreach ($recipes as $package => $lines) {
            foreach ($lines as $item => $perGuest) {
                PackageSupply::create([
                    'package_id' => $this->packages[$package]->id,
                    'inventory_item_id' => $this->stock[$item]->id,
                    'quantity_per_guest' => $perGuest,
                ]);
            }
        }
    }

    /* ----------------------------------------------------------------- bookings */

    private function bookingsAndEverythingAroundThem(): void
    {
        $staff1 = User::where('email', 'staff1@' . self::DOMAIN)->first();

        // key => [customer, package, days from today, time, guests, venue, status, event name, event type, booked N days ago]
        $specs = [
            'done1' => [1, 'Gold', -30, '18:00', 80, 'Pavilion Garden Hall', 'completed', 'Santos 25th Anniversary', 'Anniversary', 52],
            'done2' => [2, 'Silver', -18, '12:00', 50, 'Dela Cruz Residence', 'completed', 'Juan\'s 40th Birthday', 'Birthday', 40],
            'done3' => [3, 'Platinum', -9, '17:00', 120, 'Grand Ballroom', 'completed', 'Reyes-Tan Wedding', 'Wedding', 45],
            'done4' => [8, 'Silver', -22, '11:30', 30, 'Flores Home', 'completed', 'Rico\'s House Blessing', 'Other', 38],
            'cancelled1' => [4, 'Gold', -5, '19:00', 60, 'Riverside Pavilion', 'cancelled', 'Garcia Reunion', 'Other', 30],
            'tomorrow' => [2, 'Gold', 1, '18:30', 60, 'Dela Cruz Residence', 'confirmed', 'Reminder Demo: Dela Cruz Birthday', 'Birthday', 14],
            'wedding' => [5, 'Platinum', 6, '16:00', 150, 'Lakeside Garden Venue', 'confirmed', 'Lim-Torres Wedding', 'Wedding', 10],
            'full1' => [2, 'Silver', 9, '12:00', 40, 'Venue A - Function Room', 'pending', 'Lim Corporate Lunch', 'Corporate', 3],
            'full2' => [6, 'Kiddie Party', 9, '15:00', 35, 'Venue B - Play Hall', 'confirmed', 'Mendoza Kids Party', 'Birthday', 6],
            'full3' => [7, 'Gold', 9, '18:00', 70, 'Venue C - Rooftop', 'confirmed', 'Ramos Debut', 'Debut', 8],
            'bigwedding' => [3, 'Platinum', 14, '15:30', 200, 'Grand Ballroom', 'confirmed', 'Ramos-Aquino Wedding', 'Wedding', 9],
            'birthday' => [4, 'Gold', 21, '17:00', 90, 'Riverside Pavilion', 'pending', 'Pedro\'s 50th Birthday', 'Birthday', 2],
            'silver' => [5, 'Silver', 28, '12:00', 60, 'Sofia Office Lobby', 'pending', 'Office Anniversary Lunch', 'Corporate', 1],
            'paid' => [6, 'Gold', 35, '18:00', 100, 'Hillside Resort', 'confirmed', 'Mendoza Company Dinner', 'Corporate', 12],
            'cancelled2' => [8, 'Platinum', 12, '18:00', 80, 'Flores Home', 'cancelled', 'Flores Reception', 'Other', 5],
        ];

        $bookings = [];
        foreach ($specs as $key => [$customer, $package, $offset, $time, $guests, $venue, $status, $name, $type, $bookedDaysAgo]) {
            $pkg = $this->packages[$package];
            $booking = Booking::create([
                'user_id' => $this->customers[$customer]->id, 'package_id' => $pkg->id,
                'event_name' => $name, 'event_date' => now()->addDays($offset)->toDateString(), 'event_time' => $time,
                'guests' => $guests, 'location' => $venue, 'status' => $status,
                'total_amount' => Booking::calculateTotal($pkg->price, $guests),
                'notes' => $key === 'wedding' ? 'Vegetarian option for 10 guests please.' : null,
            ]);
            $booking->forceFill(['created_at' => now()->subDays($bookedDaysAgo), 'updated_at' => now()->subDays(max(0, $bookedDaysAgo - 1))])->saveQuietly();
            $bookings[$key] = [$booking, $type];
        }

        $this->payments($bookings);
        $this->events($bookings);
        $this->rescheduleRequests($bookings, $staff1);
    }

    private function payments(array $b): void
    {
        $pay = function (string $key, float|int $fraction, string $method, string $ref, int $daysAgo, string $status = 'paid') use ($b) {
            [$booking] = $b[$key];
            $payment = Payment::create([
                'user_id' => $booking->user_id, 'booking_id' => $booking->id,
                'amount' => round((float) $booking->total_amount * $fraction, 2), 'payment_method' => $method,
                'payment_date' => now()->subDays($daysAgo)->toDateString(), 'status' => $status, 'reference_number' => $ref,
            ]);
            $payment->forceFill(['created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo)])->saveQuietly();

            return $payment;
        };

        // fully paid, finished events
        $pay('done1', .5, 'GCash', 'GC-100001', 50);  $pay('done1', .5, 'Cash', 'CASH-0001', 31);
        $pay('done2', 1, 'Bank Transfer', 'BT-200002', 20);
        $pay('done3', .6, 'Bank Transfer', 'BT-300003', 40); $pay('done3', .4, 'Cash', 'CASH-0003', 10);
        $pay('done4', 1, 'Cash', 'CASH-0004', 23);

        // upcoming events: down payments (partial) and one fully paid
        $pay('tomorrow', .5, 'GCash', 'GC-400004', 12);
        $pay('wedding', .3, 'Bank Transfer', 'BT-500005', 8);
        $pay('full2', .5, 'GCash', 'GC-600006', 5);
        $pay('full3', .3, 'Cash', 'CASH-0007', 6);
        $pay('bigwedding', .25, 'Bank Transfer', 'BT-700007', 7);
        $pay('paid', 1, 'GCash', 'GC-800008', 9);

        // a failed attempt that was later redone
        $pay('bigwedding', .25, 'GCash', 'GC-FAILED-1', 6, 'failed');

        // customer-uploaded slips waiting for staff to verify (the file is a tiny demo PDF on the private disk)
        foreach ([['wedding', .2, 'GCash', 'GC-PENDING-9', 'gcash'], ['bigwedding', .25, 'Bank Transfer', 'BT-PENDING-10', 'bank']] as [$key, $fraction, $method, $ref, $label]) {
            $payment = $pay($key, $fraction, $method, $ref, 1, 'pending');
            $path = "payment-proofs/{$payment->booking_id}/demo-slip-{$label}.pdf";

            $slip = new SimplePdf;
            $slip->text(60, 100, strtoupper($method) . ' - PAYMENT RECEIPT (DEMO)', 18, true);
            $slip->text(60, 140, 'Amount: PHP ' . number_format((float) $payment->amount, 2), 14);
            $slip->text(60, 165, "Reference no.: {$ref}", 14);
            $slip->text(60, 190, 'This is a sample slip generated by the demo data.', 10);
            Storage::disk('local')->put($path, $slip->output());

            $payment->forceFill(['proof_path' => $path, 'submitted_by_customer' => true])->saveQuietly();
        }
    }

    private function events(array $b): void
    {
        $crew = collect($this->crew);

        foreach ($b as $key => [$booking, $type]) {
            if (! in_array($booking->status, ['confirmed', 'completed'], true)) {
                continue;
            }

            $event = Event::create([
                'booking_id' => $booking->id, 'event_name' => $booking->event_name, 'event_type' => $type,
                'event_date' => $booking->event_date->toDateString(), 'event_time' => $booking->event_time,
                'location' => $booking->location, 'guests' => $booking->guests,
                'status' => $booking->status === 'completed' ? 'completed' : 'upcoming',
                'notes' => $key === 'wedding' ? 'Setup starts 3 hours before. Bring extra chafing dishes.' : null,
            ]);

            $big = $booking->guests >= 100;
            $event->staff()->attach($crew->take($big ? 6 : 4)->pluck('id')->all());

            // Two upcoming events already have their stock reserved from the package recipe.
            if (in_array($key, ['wedding', 'tomorrow'], true)) {
                $this->reserveFromRecipe($event, $booking);
            }
        }
    }

    /** Same maths as the "Reserve from package" button: per-guest x guests, deducted from stock. */
    private function reserveFromRecipe(Event $event, Booking $booking): void
    {
        foreach ($booking->package->supplies as $supply) {
            $needed = ceil((float) $supply->quantity_per_guest * $booking->guests * 100) / 100;
            $item = $supply->item;

            if ($needed > (float) $item->quantity) {
                continue;     // never reserve more than exists
            }

            $item->decrement('quantity', $needed);
            $event->inventoryItems()->attach($item->id, ['quantity' => $needed]);
        }
    }

    private function rescheduleRequests(array $b, ?User $decider): void
    {
        $make = fn (string $key, int $offset, string $time, string $status, ?string $reason, ?string $note = null, int $decidedDaysAgo = 0) => $b[$key][0]->rescheduleRequests()->create([
            'requested_date' => now()->addDays($offset)->toDateString(), 'requested_time' => $time, 'reason' => $reason, 'status' => $status,
            'admin_note' => $note, 'decided_by' => $status === 'pending' ? null : $decider?->id,
            'decided_at' => $status === 'pending' ? null : now()->subDays($decidedDaysAgo),
        ]);

        // waiting for a decision
        $make('birthday', 30, '17:00', 'pending', 'The venue is not available on the first date.');
        $make('silver', 40, '12:30', 'pending', 'Our office moved the event to the following month.');
        // history
        $make('paid', 35, '18:00', 'approved', 'Moved one week later.', null, 3);
        $make('bigwedding', 16, '15:30', 'rejected', 'We would like two days later.', 'Sorry, that week is fully booked.', 2);
    }

    /* ------------------------------------------------------------- audit + info */

    private function auditTrail(): void
    {
        $admin = User::where('role', 'admin')->first();
        $staff = User::where('email', 'staff1@' . self::DOMAIN)->first();
        $booking = Booking::where('event_name', 'like', 'Lim-Torres%')->first();

        foreach ([
            [$admin, 'login', 'Signed in', null, 300],
            [$staff, 'login', 'Signed in', null, 240],
            [$staff, 'updated', 'Booking #' . $booking?->id . ' updated', 'Booking', 200],
            [$staff, 'stock_reserved', 'Supplies reserved for the Lim-Torres wedding', 'InventoryItem', 150],
            [$admin, 'updated', 'Package Gold updated', 'Package', 90],
        ] as [$user, $action, $description, $subject, $minutesAgo]) {
            ActivityLog::create([
                'user_id' => $user?->id, 'action' => $action, 'description' => $description,
                'subject_type' => $subject, 'ip_address' => '127.0.0.1',
                'created_at' => now()->subMinutes($minutesAgo), 'updated_at' => now()->subMinutes($minutesAgo),
            ]);
        }
    }

    private function printLogins(): void
    {
        $this->command?->info('Demo data created. Every demo account uses the password: ' . self::PASSWORD);
        $this->command?->table(['Role', 'E-mail', 'Notes'], [
            ['Admin', env('ADMIN_EMAIL', 'admin@example.com'), 'your own admin account (from .env)'],
            ['Staff', 'staff1@' . self::DOMAIN, 'Liza Reyes'],
            ['Staff', 'staff2@' . self::DOMAIN, 'Marco Villanueva'],
            ['Staff', 'staff3@' . self::DOMAIN, 'deactivated: should NOT be able to sign in'],
            ['Customer', 'customer1@' . self::DOMAIN, 'Maria Santos (2 finished events)'],
            ['Customer', 'customer2@' . self::DOMAIN, 'Juan Dela Cruz (event TOMORROW: tests reminders)'],
            ['Customer', 'customer5@' . self::DOMAIN, 'Sofia Lim (wedding in 6 days, slip to verify)'],
            ['Customer', 'customer4@' . self::DOMAIN, 'Pedro Garcia (reschedule request waiting)'],
            ['Customer', 'customer1..7@' . self::DOMAIN, 'all active'],
            ['Customer', 'customer8@' . self::DOMAIN, 'deactivated: should NOT be able to sign in'],
        ]);
    }
}
