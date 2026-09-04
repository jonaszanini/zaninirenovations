<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Minimal team/role management: list admin accounts and toggle each one
 * between "admin" (full access, incl. deletes) and "staff" (blocked from
 * destructive actions — see RestrictDestructiveActions middleware).
 * Account creation/deletion stays a deliberate, out-of-band ops task
 * (tinker/DB) — this screen only manages the role of existing accounts.
 */
class UserController extends Controller
{
    public function index()
    {
        $this->authorizeAdmin();

        $users = User::query()->orderBy('name')->get();

        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user)
    {
        $this->authorizeAdmin();

        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_STAFF])],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        if ($user->id === auth()->id() && $data['role'] !== User::ROLE_ADMIN) {
            return back()->with('error', 'You cannot remove your own admin role.');
        }

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated.');
    }

    /**
     * Only admins may view/change roles — otherwise a staff account could
     * just promote itself back to admin. The base Controller here has no
     * middleware-registration trait, so this is checked per-action instead.
     */
    private function authorizeAdmin(): void
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403);
        }
    }
}
