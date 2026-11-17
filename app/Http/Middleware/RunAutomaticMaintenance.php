<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceService;
use App\Services\ProjectBackupService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies the admin toggles of the drafts page without relying on an
 * external scheduler: on normal page loads it runs the daily purge, the
 * monthly stock carry-over and the daily project backup when they are due.
 */
class RunAutomaticMaintenance
{
    public function __construct(
        private MaintenanceService $maintenance,
        private ProjectBackupService $backup
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && $request->isMethod('GET') && !$request->expectsJson()) {
            try {
                $this->maintenance->runDueTasks();

                // The backup reads the whole project and dumps the database,
                // so it runs after the response has been sent to the user.
                if ($this->backup->isDue()) {
                    defer(fn () => $this->backup->runIfDue(), 'project-backup')->always();
                }
            } catch (Throwable $e) {
                Log::error('Automatic maintenance middleware failed: ' . $e->getMessage());
            }
        }

        return $next($request);
    }
}
