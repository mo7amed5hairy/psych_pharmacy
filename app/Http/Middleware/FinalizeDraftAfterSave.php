<?php

namespace App\Http\Middleware;

use App\Models\Draft;
use App\Support\DraftClosure;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Deletes the matching draft right after the real controller saved the same
 * data. It never re-runs the business logic (that would duplicate the
 * records); it only removes the pending row so the drafts table holds
 * unsaved data only.
 *
 * The UUID is remembered in DraftClosure so a delayed sync request cannot
 * create the draft again. Runs only when a non-GET request carries
 * draft_uuid and succeeded.
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

        // Only a real save counts: a 2xx JSON response or a redirect that
        // flashes the usual success message. Anything else (login redirect,
        // unauthorized page...) must leave the draft untouched.
        $isHttpSuccess = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        $isFlashedSuccess = $request->hasSession() && $request->session()->has('success');

        if (!$isHttpSuccess && !$isFlashedSuccess) {
            return $response;
        }

        if ($response instanceof RedirectResponse) {
            $target = $response->getTargetUrl();

            if (str_starts_with($target, route('login')) || str_starts_with($target, route('unauthorized'))) {
                return $response;
            }
        }

        $userId = (int) Auth::id();

        // Close first, then delete: any sync that starts in between sees the
        // UUID as closed and refuses to insert it again.
        DraftClosure::close($userId, $uuid);

        Draft::where('uuid', $uuid)
            ->where('user_id', $userId)
            ->where('status', Draft::STATUS_PENDING)
            ->delete();

        return $response;
    }
}
