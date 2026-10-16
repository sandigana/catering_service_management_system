<?php
// app/Http/Controllers/InventoryController.php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Simple stock list with low-stock alerts and manual stock adjustments. */
class InventoryController extends CrudController
{
    protected function model(): string { return InventoryItem::class; }
    protected function routeBase(): string { return 'admin.inventory'; }
    protected function label(): string { return 'Inventory item'; }

    public function index(Request $request)
    {
        $items = InventoryItem::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->filter === 'low', fn ($q) => $q->low())
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('administrator.inventory', [
            'items' => $items,
            'lowCount' => InventoryItem::low()->where('status', 'active')->count(),
        ]);
    }

    protected function fields(Model $item): array
    {
        return [
            ['name' => 'name', 'label' => 'Item name', 'type' => 'text', 'required' => true],
            ['name' => 'unit', 'label' => 'Unit (kg, pcs, liters...)', 'type' => 'text', 'required' => true],
            ['name' => 'quantity', 'label' => 'Quantity on hand', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'low_stock_threshold', 'label' => 'Low-stock alert at or below', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true,
                'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
        ];
    }

    protected function rules(?Model $item = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'low_stock_threshold' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    /** Add (+) or remove (-) stock and keep a reason in the audit log. */
    public function adjust(Request $request, $id)
    {
        $data = $request->validate([
            'change' => ['required', 'numeric', 'not_in:0', 'between:-99999999,99999999'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($id, $data) {
            $item = InventoryItem::lockForUpdate()->findOrFail($id);
            $new = round((float) $item->quantity + (float) $data['change'], 2);

            if ($new < 0) {
                throw ValidationException::withMessages(['change' => "Only {$item->quantity} {$item->unit} of {$item->name} in stock."]);
            }

            $item->update(['quantity' => $new]);

            ActivityLog::record('stock_adjusted', "{$item->name}: {$data['change']} {$item->unit} ({$data['reason']})", $item);
        });

        return back()->with('success', 'Stock updated.');
    }
}
