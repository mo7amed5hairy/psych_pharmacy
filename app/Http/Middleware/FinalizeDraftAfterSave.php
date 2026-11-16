<?php

namespace App\Http\Middleware;

use App\Models\Draft;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Marks a draft as finalized after the real controller successfully saved
 * the same data. It never re-runs the business logic (that would duplicate
 * the records); it only stores the tombstone so clients delete their copy
 * and admins stop seeing the draft.
 *
 * Runs only when a non-GET request carries draft_uuid and succeeded.
 */
class FinalizeDraftAfterSave
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (strtoupper($request->method()) === 'GET' || !Auth::check()) {
            return $response;
        }

        $uuid = $request->input('draft_uuid');

        if (!is_string($uuid) || !Str::isUuid($uuid)) {
            return $response;
        }

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        if ($request->hasSession() && $request->session()->has('errors')) {
            return $response;
        }

        Draft::where('uuid', $uuid)
            ->where('user_id', Auth::id())
            ->where('status', Draft::STATUS_PENDING)
            ->update([
                'status' => Draft::STATUS_FINALIZED,
                'finalized_at' => now(),
                'finalized_by' => Auth::id(),
                'result' => 'تم الحفظ من شاشة الإدخال الأصلية',
                'updated_at' => now(),
            ]);

        return $response;
    }
}
