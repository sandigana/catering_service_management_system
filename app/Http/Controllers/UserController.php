<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends CrudController
{
    protected function model(): string { return User::class; }
    protected function routeBase(): string { return 'admin.users'; }
    protected function label(): string { return 'User'; }

    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->search, fn ($q, $s) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")
            ))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('administrator.user', compact('users'));
    }

    protected function fields(Model $item): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
            ['name' => 'password', 'label' => $item->exists ? 'New password (leave blank to keep)' : 'Password',
                'type' => 'password', 'required' => ! $item->exists],
            ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'required' => true,
                'options' => ['user' => 'Customer', 'staff' => 'Staff (back office)', 'admin' => 'Administrator']],
            ['name' => 'is_active', 'label' => 'Account status', 'type' => 'select', 'required' => true, 'default' => '1',
                'options' => ['1' => 'Active', '0' => 'Deactivated']],
        ];
    }

    protected function rules(?Model $item = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($item?->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'password' => [$item ? 'nullable' : 'required', 'string', \Illuminate\Validation\Rules\Password::defaults()],
            'role' => ['required', 'in:admin,staff,user'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepare(array $data, Request $request, ?Model $item = null): array
    {
        // Blank password on edit = keep the current one (the model's "hashed" cast hashes new ones).
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }

    public function update(Request $request, $id)
    {
        // Stop the only admin from demoting or deactivating themselves and locking everyone out.
        if ((int) $id === $request->user()->id && $request->input('role') !== 'admin') {
            return back()->withErrors(['role' => 'You cannot remove your own admin role.'])->withInput();
        }

        if ((int) $id === $request->user()->id && ! $request->boolean('is_active')) {
            return back()->withErrors(['is_active' => 'You cannot deactivate your own account.'])->withInput();
        }

        return parent::update($request, $id);
    }

    public function destroy($id)
    {
        if ((int) $id === auth()->id()) {
            return back()->withErrors(['delete' => 'You cannot delete your own account.']);
        }

        return parent::destroy($id);
    }
}
