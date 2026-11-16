<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Draft recovery is restricted to the owner account (employee_code = 1001).
 */
class DraftAdminMiddleware
{
    public const ADMIN_CODE = '1001';

    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && Auth::user()->employee_code === self::ADMIN_CODE) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'غير مسموح لك بدخول هذه الصفحة.'], 403);
        }

        return redirect()->route('unauthorized');
    }
}
