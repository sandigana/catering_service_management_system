<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class StaffController extends CrudController
{
    protected function model(): string { return Staff::class; }
    protected function routeBase(): string { return 'admin.staff'; }
    protected function label(): string { return 'Staff member'; }

    public function index(Request $request)
    {
        $staff = Staff::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('position', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('administrator.staff', compact('staff'));
    }

    protected function fields(Model $item): array
    {
        return [
            ['name' => 'name', 'label' => 'Full name', 'type' => 'text', 'required' => true],
            ['name' => 'position', 'label' => 'Position', 'type' => 'text'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true,
                'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
        ];
    }

    protected function rules(?Model $item = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
