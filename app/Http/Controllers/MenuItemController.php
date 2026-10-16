<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class MenuItemController extends CrudController
{
    private const CATEGORIES = ['Main Course', 'Salad', 'Dessert', 'Beverage'];

    protected function model(): string { return MenuItem::class; }
    protected function routeBase(): string { return 'admin.menu-items'; }
    protected function label(): string { return 'Menu item'; }
    protected function imageFolder(): ?string { return 'menu-items'; }

    public function index(Request $request)
    {
        $menuItems = MenuItem::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('administrator.menu-items', compact('menuItems'));
    }

    protected function fields(Model $item): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['name' => 'category', 'label' => 'Category', 'type' => 'select',
                'options' => array_combine(self::CATEGORIES, self::CATEGORIES)],
            ['name' => 'price', 'label' => 'Price (₱)', 'type' => 'number', 'step' => '0.01', 'required' => true],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true,
                'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
            ['name' => 'image', 'label' => 'Image', 'type' => 'file'],
        ];
    }

    protected function rules(?Model $item = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'in:' . implode(',', self::CATEGORIES)],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
