<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Draft recovery is available to the owner account (employee_code = 1001)
 * and to any user explicitly granted the "drafts" permission from the
 * permissions screen.
 */
class DraftAdminMiddleware
{
    public const ADMIN_CODE = '1001';

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && ($user->employee_code === self::ADMIN_CODE || $user->hasPermission('drafts'))) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'غير مسموح لك بدخول هذه الصفحة.'], 403);
        }

        return redirect()->route('unauthorized');
    }
}
