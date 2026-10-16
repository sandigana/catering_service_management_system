<?php
// routes/web.php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CustomerManagementController;
use App\Http\Controllers\EventSheetController;
use App\Http\Controllers\PackageSupplyController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\RescheduleController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventInventoryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth()->user();

    if (! $user) {
        return redirect()->route('login');
    }

    return redirect()->route($user->isBackOffice() ? 'admin.dashboard' : 'customer.dashboard');
});

/* Guest: login and register (rate-limited against brute force / spam signups) */
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,10')->name('register.submit');

    // Forgot / reset password (rate-limited; never reveals whether an e-mail exists)
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset')->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/* Documents and uploaded proofs: customers see their own, back office sees all */
Route::middleware(['auth', 'role:admin,staff,user'])->group(function () {
    Route::get('/receipts/{payment}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::get('/receipts/{payment}/pdf', [PdfController::class, 'receipt'])->name('receipts.pdf');
    Route::get('/invoices/{booking}/pdf', [PdfController::class, 'invoice'])->name('invoices.pdf');
    Route::get('/payments/{payment}/proof', [PaymentProofController::class, 'show'])->name('proofs.show');
});

/* Back office - admin AND staff: day-to-day operations (no deleting) */
Route::middleware(['auth', 'role:admin,staff'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/customers', [AdminController::class, 'customers'])->name('customers');
        Route::get('/customers/{customer}', [CustomerManagementController::class, 'show'])->name('customers.show');
        Route::get('/customers/{customer}/edit', [CustomerManagementController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerManagementController::class, 'update'])->name('customers.update');

        Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');

        Route::get('/reschedules', [RescheduleController::class, 'index'])->name('reschedules.index');
        Route::post('/reschedules/{reschedule}/approve', [RescheduleController::class, 'approve'])->name('reschedules.approve');
        Route::post('/reschedules/{reschedule}/reject', [RescheduleController::class, 'reject'])->name('reschedules.reject');

        Route::get('/events/{event}/sheet', [EventSheetController::class, 'show'])->name('events.sheet');
        Route::post('/events/{event}/supplies/from-package', [EventInventoryController::class, 'fromPackage'])->name('events.supplies.package');

        Route::resource('bookings', BookingController::class)->except('destroy');
        Route::resource('events', EventController::class)->except('destroy');
        Route::resource('payments', PaymentController::class)->except('destroy');
        Route::resource('inventory', InventoryController::class)->except('destroy');

        Route::post('/inventory/{inventory}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');

        Route::get('/events/{event}/supplies', [EventInventoryController::class, 'index'])->name('events.supplies');
        Route::post('/events/{event}/supplies', [EventInventoryController::class, 'store'])->name('events.supplies.store');
        Route::delete('/events/{event}/supplies/{item}', [EventInventoryController::class, 'destroy'])->name('events.supplies.destroy');
    });

/* Administrator only: configuration, reports, audit trail, deleting records */
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
        Route::get('/reports/export', [AdminController::class, 'exportReports'])->name('reports.export');
        Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-log');

        Route::resource('bookings', BookingController::class)->only('destroy');
        Route::resource('events', EventController::class)->only('destroy');
        Route::resource('payments', PaymentController::class)->only('destroy');
        Route::resource('inventory', InventoryController::class)->only('destroy');

        Route::get('/packages/{package}/supplies', [PackageSupplyController::class, 'index'])->name('packages.supplies');
        Route::post('/packages/{package}/supplies', [PackageSupplyController::class, 'store'])->name('packages.supplies.store');
        Route::delete('/packages/{package}/supplies/{supply}', [PackageSupplyController::class, 'destroy'])->name('packages.supplies.destroy');

        Route::resource('menu-items', MenuItemController::class)->parameters(['menu-items' => 'menu_item']);
        Route::resource('packages', PackageController::class);
        Route::resource('users', UserController::class);
        Route::resource('staff', StaffController::class)->parameters(['staff' => 'staff']);
    });

/* Customer */
Route::middleware(['auth', 'role:user'])
    ->prefix('customer')
    ->name('customer.')
    ->group(function () {
        Route::get('/dashboard', [CustomerController::class, 'dashboard'])->name('dashboard');
        Route::get('/packages', [CustomerController::class, 'packages'])->name('packages');
        Route::get('/profile', [CustomerController::class, 'profile'])->name('profile');
        Route::put('/profile', [CustomerController::class, 'updateProfile'])->name('profile.update');

        Route::resource('bookings', BookingController::class)
            ->only(['index', 'create', 'store', 'show']);
        Route::get('/availability', [AvailabilityController::class, 'check'])->middleware('throttle:60,1')->name('availability');
        Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

        Route::post('/bookings/{booking}/reschedule', [RescheduleController::class, 'store'])->name('bookings.reschedule');
        Route::delete('/bookings/{booking}/reschedule', [RescheduleController::class, 'withdraw'])->name('bookings.reschedule.withdraw');
        Route::post('/bookings/{booking}/payments', [PaymentProofController::class, 'store'])->middleware('throttle:10,1')->name('bookings.payments.store');
    });
