<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Kelola pengguna dan perannya (FR-01). Hanya Admin.
 */
#[Layout('layouts.app')]
#[Title('Pengguna')]
class Users extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'operator';

    public ?int $unitId = null;

    public string $password = '';

    public function mount(): void
    {
        $this->authorize('kelola-sistem');
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorize('kelola-sistem');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role' => ['required', Rule::enum(Role::class)],
            'unitId' => [Rule::requiredIf($this->role === Role::Operator->value), 'nullable', 'integer', 'exists:units,id'],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', Password::min(10)],
        ], [], ['unitId' => 'satuan', 'role' => 'peran', 'password' => 'kata sandi']);

        if ($this->editingId === auth()->id() && $data['role'] !== Role::Admin->value) {
            $this->addError('role', 'Admin tidak bisa menurunkan perannya sendiri.');

            return;
        }

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'unit_id' => $data['unitId'],
        ];
        if (filled($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        $logData = collect($attributes)->except('password')->all() + ['kata_sandi_diubah' => filled($data['password'])];

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $user->update($attributes);
            $audit->record('pengguna_diubah', $user, $logData);
        } else {
            $user = User::create($attributes + ['email_verified_at' => now()]);
            $audit->record('pengguna_dibuat', $user, $logData);
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $this->authorize('kelola-sistem');
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->unitId = $user->unit_id;
        $this->password = '';
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'name', 'email', 'role', 'unitId', 'password');
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.users', [
            'users' => User::query()->with('unit')->orderBy('role')->orderBy('name')->get(),
            'units' => Unit::query()->orderBy('name')->get(),
        ]);
    }
}
