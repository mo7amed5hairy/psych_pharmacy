<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies the admin toggles of the drafts page without relying on an
 * external scheduler: on normal page loads it runs the daily purge and
 * the monthly stock carry-over when they are due.
 */
class RunAutomaticMaintenance
{
    public function __construct(private MaintenanceService $maintenance)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && $request->isMethod('GET') && !$request->expectsJson()) {
            try {
                $this->maintenance->runDueTasks();
            } catch (Throwable $e) {
                Log::error('Automatic maintenance middleware failed: ' . $e->getMessage());
            }
        }

        return $next($request);
    }
}
