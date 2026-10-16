<?php
// app/Http/Controllers/AdminController.php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Event;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        $users = User::where('role', 'user')->count();
        $bookings = Booking::count();
        $events = Event::count();
        $packages = Package::count();
        $menuItems = MenuItem::count();
        $payments = Payment::count();
        $lowStock = InventoryItem::low()->where('status', 'active')->count();
        $pendingReschedules = \App\Models\RescheduleRequest::pending()->count();
        $proofsToVerify = Payment::where('status', 'pending')->where('submitted_by_customer', true)->count();

        $recentBookings = Booking::with(['user', 'package'])->latest()->take(5)->get();

        // --- KPI cards: this month vs last month ---
        $thisMonth = [now()->startOfMonth(), now()->endOfMonth()];
        $lastMonth = [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()];

        $accepted = fn ($range) => Booking::whereIn('status', ['confirmed', 'completed'])->whereBetween('created_at', $range);
        $cancelled = fn ($range) => Booking::where('status', 'cancelled')->whereBetween('created_at', $range);

        $kpi = [
            'accepted' => ['value' => $accepted($thisMonth)->count(), 'delta' => $this->percentChange($accepted($thisMonth)->count(), $accepted($lastMonth)->count())],
            'cancelled' => ['value' => $cancelled($thisMonth)->count(), 'delta' => $this->percentChange($cancelled($thisMonth)->count(), $cancelled($lastMonth)->count())],
            'lost' => ['value' => (float) $cancelled($thisMonth)->sum('total_amount'), 'delta' => $this->percentChange((float) $cancelled($thisMonth)->sum('total_amount'), (float) $cancelled($lastMonth)->sum('total_amount'))],
        ];

        // --- booking carousels ---
        $newBookings = Booking::with(['user', 'package'])->where('status', 'pending')->orderBy('event_date')->take(10)->get();
        $upcomingBookings = Booking::with(['user', 'package'])->where('status', 'confirmed')
            ->whereDate('event_date', '>=', today())->orderBy('event_date')->orderBy('event_time')->take(10)->get();

        // --- payment split on live bookings ---
        $live = Booking::withPaid()->whereIn('status', Booking::ACTIVE_STATUSES)->get();
        $paymentSplit = [
            'paid' => $live->where('payment_status', 'paid')->count(),
            'partial' => $live->where('payment_status', 'partial')->count(),
            'unpaid' => $live->where('payment_status', 'unpaid')->count(),
        ];

        // --- booking status bars ---
        $statusCounts = collect(['pending', 'confirmed', 'completed', 'cancelled'])
            ->mapWithKeys(fn ($st) => [$st => Booking::where('status', $st)->count()]);

        // --- bookings received per day, last 7 days ---
        $received = Booking::where('created_at', '>=', now()->subDays(6)->startOfDay())->get(['created_at'])
            ->groupBy(fn ($b) => $b->created_at->format('Y-m-d'));
        $dailySeries = collect(range(6, 0))->map(function ($ago) use ($received) {
            $day = now()->subDays($ago);

            return ['label' => $day->format('M d'), 'count' => $received->get($day->format('Y-m-d'), collect())->count()];
        });

        return view('administrator.dashboard', compact(
            'users', 'bookings', 'events', 'packages', 'menuItems', 'payments', 'lowStock',
            'recentBookings', 'pendingReschedules', 'proofsToVerify', 'kpi', 'newBookings', 'upcomingBookings', 'paymentSplit', 'statusCounts', 'dailySeries'
        ));
    }

    /** % change from previous to current; +100 when growing from zero. */
    private function percentChange(float|int $current, float|int $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    public function customers(Request $request)
    {
        $customers = User::where('role', 'user')
            ->withCount('bookings')
            ->when($request->search, fn ($q, $s) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")
            ))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('administrator.customer', compact('customers'));
    }

    public function reports(Request $request)
    {
        return view('administrator.reports', $this->reportData($request));
    }

    /** CSV export (opens in Excel). Same filters as the on-screen report. */
    public function exportReports(Request $request)
    {
        $r = $this->reportData($request);

        $rows = [['Catering report', $r['from']->toDateString() . ' to ' . $r['to']->toDateString(), ucfirst($r['period'])], []];

        $rows[] = ['Bookings per ' . $r['period']];
        $rows[] = ['Period', 'Bookings', 'Booked value (PHP)'];
        foreach ($r['bookingSeries'] as $label => $row) {
            $rows[] = [$label, $row['count'], $row['value']];
        }

        $rows[] = [];
        $rows[] = ['Sales (paid payments) per ' . $r['period']];
        $rows[] = ['Period', 'Payments', 'Amount received (PHP)'];
        foreach ($r['salesSeries'] as $label => $row) {
            $rows[] = [$label, $row['count'], $row['value']];
        }

        $rows[] = [];
        $rows[] = ['Top customers'];
        $rows[] = ['Customer', 'Bookings', 'Paid (PHP)'];
        foreach ($r['topCustomers'] as $c) {
            $rows[] = [$c->name, $c->bookings_count, number_format($c->paid_total, 2, '.', '')];
        }

        $rows[] = [];
        $rows[] = ['Top packages'];
        $rows[] = ['Package', 'Bookings', 'Booked value (PHP)'];
        foreach ($r['topPackages'] as $p) {
            $rows[] = [$p->name, $p->bookings_count, number_format((float) $p->revenue, 2, '.', '')];
        }

        $filename = 'catering-report-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ₱/accents correctly

            foreach ($rows as $row) {
                fputcsv($out, array_map([$this, 'csvSafe'], $row));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stops spreadsheet formula injection (=, +, -, @ at the start of a cell). */
    private function csvSafe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    /** Builds every number the report page and the CSV export need. */
    private function reportData(Request $request): array
    {
        $request->validate([
            'period' => ['nullable', 'in:daily,weekly,monthly'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $period = $request->input('period', 'monthly');
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay();
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : $to->copy()->subDays(89)->startOfDay();

        $bucket = fn (Carbon $date) => match ($period) {
            'daily' => $date->format('Y-m-d'),
            'weekly' => $date->format('o-\WW'),
            default => $date->format('Y-m'),
        };

        // Bookings by event date (cancelled ones excluded from value).
        $bookingSeries = Booking::whereBetween('event_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('event_date')->get(['event_date', 'status', 'total_amount'])
            ->groupBy(fn ($b) => $bucket($b->event_date))
            ->map(fn ($group) => [
                'count' => $group->count(),
                'value' => number_format($group->where('status', '!=', 'cancelled')->sum('total_amount'), 2, '.', ''),
            ])->all();

        // Sales = money actually received.
        $salesSeries = Payment::where('status', 'paid')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('payment_date')->get(['payment_date', 'amount'])
            ->groupBy(fn ($p) => $bucket($p->payment_date))
            ->map(fn ($group) => [
                'count' => $group->count(),
                'value' => number_format($group->sum('amount'), 2, '.', ''),
            ])->all();

        $topCustomers = User::where('role', 'user')
            ->withCount('bookings')
            ->withSum(['payments as paid_total' => fn ($q) => $q->where('status', 'paid')], 'amount')
            ->orderByDesc('paid_total')
            ->orderByDesc('bookings_count')
            ->take(5)
            ->get()
            ->each(fn ($u) => $u->paid_total = (float) $u->paid_total);

        $topPackages = Package::withCount('bookings')
            ->withSum('bookings as revenue', 'total_amount')
            ->orderByDesc('bookings_count')
            ->take(5)
            ->get();

        $outstanding = Booking::withPaid()->whereIn('status', Booking::ACTIVE_STATUSES)->get()
            ->sum(fn ($b) => (float) $b->balance);

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'bookingSeries' => $bookingSeries,
            'salesSeries' => $salesSeries,
            'topCustomers' => $topCustomers,
            'topPackages' => $topPackages,
            'outstanding' => $outstanding,
            'totalCustomers' => User::where('role', 'user')->count(),
            'totalBookings' => Booking::count(),
            'totalEvents' => Event::count(),
            'totalPackages' => Package::count(),
            'totalPayments' => Payment::count(),
            'totalRevenue' => Payment::where('status', 'paid')->sum('amount'),
            'recentBookings' => Booking::with(['user', 'package'])->latest()->take(10)->get(),
        ];
    }
}
