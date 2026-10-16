<?php
// app/Http/Controllers/PackageSupplyController.php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\PackageSupply;
use Illuminate\Http\Request;

/** Admin defines a package's per-guest stock recipe (e.g. 0.25 kg rice per guest). */
class PackageSupplyController extends Controller
{
    public function index($packageId)
    {
        $package = Package::with('supplies')->findOrFail($packageId);

        return view('administrator.package-supplies', [
            'package' => $package,
            'items' => InventoryItem::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, $packageId)
    {
        $package = Package::findOrFail($packageId);

        $data = $request->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'quantity_per_guest' => ['required', 'numeric', 'gt:0', 'max:99999'],
        ]);

        // Adding the same item again simply updates its quantity.
        PackageSupply::updateOrCreate(
            ['package_id' => $package->id, 'inventory_item_id' => $data['inventory_item_id']],
            ['quantity_per_guest' => $data['quantity_per_guest']]
        );

        return back()->with('success', 'Package supplies saved.');
    }

    public function destroy($packageId, $supplyId)
    {
        PackageSupply::where('package_id', $packageId)->findOrFail($supplyId)->delete();

        return back()->with('success', 'Item removed from the package recipe.');
    }
}
