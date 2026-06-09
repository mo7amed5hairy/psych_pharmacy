<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $adminCodes = ['7777', '1010'];

        if (Auth::check() && in_array(Auth::user()->employee_code, $adminCodes)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'غير مسموح لك بدخول هذه الصفحة.'], 403);
        }

        return redirect()->route('unauthorized');
    }
}
