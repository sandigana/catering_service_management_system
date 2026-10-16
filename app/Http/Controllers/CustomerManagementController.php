<?php
// app/Http/Controllers/CustomerManagementController.php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Lets back-office staff view and correct a CUSTOMER's contact details.
 * Deliberately limited to name / e-mail / phone: no role, password or active flag
 * (those stay admin-only in Users).
 */
class CustomerManagementController extends CrudController
{
    protected function model(): string { return User::class; }
    protected function routeBase(): string { return 'admin.customers'; }
    protected function label(): string { return 'Customer'; }

    protected function indexUrl(): string
    {
        return route('admin.customers');
    }

    // Only real customers: this screen can never be used to touch an admin or staff account.
    protected function find($id): Model
    {
        return User::where('role', 'user')->findOrFail($id);
    }

    protected function fields(Model $item): array
    {
        return [
            ['name' => 'name', 'label' => 'Full name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => true],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
        ];
    }

    protected function rules(?Model $item = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($item?->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
        ];
    }
}
