<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Package;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PackageController extends CrudController
{
    protected function model(): string { return Package::class; }
    protected function routeBase(): string { return 'admin.packages'; }
    protected function label(): string { return 'Package'; }
    protected function imageFolder(): ?string { return 'packages'; }
    protected function relationKeys(): array { return ['menu_item_ids']; }

    public function index(Request $request)
    {
        $packages = Package::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('administrator.packages', compact('packages'));
    }

    protected function fields(Model $item): array
    {
        return [
            ['name' => 'name', 'label' => 'Package name', 'type' => 'text', 'required' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['name' => 'price', 'label' => 'Price per guest (₱)', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true,
                'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
            ['name' => 'image', 'label' => 'Image', 'type' => 'file'],
            ['name' => 'menu_item_ids', 'label' => 'Menu items included', 'type' => 'checkboxes',
                'options' => MenuItem::orderBy('name')->pluck('name', 'id')->all(),
                'selected' => $item->exists ? $item->menuItems()->pluck('menu_items.id')->all() : []],
        ];
    }

    protected function rules(?Model $item = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'menu_item_ids' => ['nullable', 'array'],
            'menu_item_ids.*' => ['exists:menu_items,id'],
        ];
    }

    protected function afterSave(Model $item, Request $request): void
    {
        $item->menuItems()->sync($request->input('menu_item_ids', []));
    }
}
