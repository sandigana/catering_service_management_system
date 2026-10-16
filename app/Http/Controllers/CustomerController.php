<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Customer Dashboard
    |--------------------------------------------------------------------------
    */

    public function dashboard(Request $request)
    {
        $user = Auth::user();

        // Calendar month comes from ?month=YYYY-MM; anything invalid falls back to this month.
        try {
            $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
                ? Carbon::createFromFormat('Y-m-d', $request->query('month') . '-01')->startOfDay()
                : now()->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
        }

        $mine = fn () => Booking::with(['package', 'payments'])->where('user_id', $user->id);

        // The booking shown in the big card: next upcoming one, else the latest.
        $nextBooking = $mine()->whereIn('status', Booking::ACTIVE_STATUSES)
            ->whereDate('event_date', '>=', today())
            ->orderBy('event_date')->orderBy('event_time')->first()
            ?? $mine()->latest()->first();

        $monthBookings = $mine()
            ->whereBetween('event_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderBy('event_date')->get()
            ->groupBy(fn ($b) => $b->event_date->format('Y-m-d'));

        $balanceDue = $mine()->whereIn('status', Booking::ACTIVE_STATUSES)->get()
            ->sum(fn ($b) => (float) $b->balance);

        $myBookings = $mine()->count();
        $packages = Package::count();

        return view('customer.dashboard', compact(
            'user', 'packages', 'myBookings', 'nextBooking', 'month', 'monthBookings', 'balanceDue'
        ));
    }


    /*
    |--------------------------------------------------------------------------
    | Packages
    |--------------------------------------------------------------------------
    */

    public function packages()
    {
        $packages = Package::where('status', 'active')->latest()->get();

        return view(
            'customer.packages',
            compact('packages')
        );
    }




    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    public function profile()
    {
        $user = Auth::user();

        return view(
            'customer.profile',
            compact('user')
        );
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'password' => ['nullable', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Role is deliberately not fillable from this form.
        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }
}
