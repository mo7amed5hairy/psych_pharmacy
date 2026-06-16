<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $module
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $module)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if (!Auth::user()->hasPermission($module)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'غير مسموح لك بدخول هذه الصفحة.'], 403);
            }
            return redirect()->route('unauthorized');
        }

        return $next($request);
    }
}
