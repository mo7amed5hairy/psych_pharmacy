<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->paginate(20);
        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'employee_code' => 'required|string|max:20|unique:users,employee_code',
            'password' => 'required|string|min:4|confirmed',
        ]);

        User::create([
            'name' => $validated['name'],
            'employee_code' => $validated['employee_code'],
            'password_hash' => Hash::make($validated['password']),
        ]);

        return redirect()->route('users.index')->with('success', 'تم إنشاء المستخدم بنجاح');
    }

    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'employee_code' => 'required|string|max:20|unique:users,employee_code,' . $user->id,
            'password' => 'nullable|string|min:4|confirmed',
        ]);

        $data = [
            'name' => $validated['name'],
            'employee_code' => $validated['employee_code'],
        ];

        if ($request->filled('password')) {
            $data['password_hash'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'تم تحديث بيانات المستخدم بنجاح');
    }

    public function destroy(User $user)
    {
        // Prevent deleting yourself if needed, but the user didn't ask.
        // Also might want to prevent deleting users with existing data.

        $user->delete();
        return redirect()->route('users.index')->with('success', 'تم حذف المستخدم بنجاح');
    }
}
