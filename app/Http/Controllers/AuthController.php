<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'employee_code' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('employee_code', $request->employee_code)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return back()->withErrors(['employee_code' => 'كود الموظف أو كلمة المرور غير صحيحة']);
        }

        Auth::login($user, $request->boolean('remember'));

        return redirect()->route('dashboard');
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('login');
    }
}
