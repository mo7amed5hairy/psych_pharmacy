<?php

namespace App\Services;

use App\Models\Draft;
use App\Models\Setting;
use App\Support\DraftClosure;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class MaintenanceService
{
    /**
     * Closed drafts older than this are removed by the purge.
     */
    public const PURGE_DAYS = 30;

    public function purgeEnabled(): bool
    {
        return Setting::enabled(Setting::DRAFTS_PURGE_ENABLED);
    }

    public function transferEnabled(): bool
    {
        return Setting::enabled(Setting::STOCK_TRANSFER_ENABLED);
    }

    public function lastPurgeAt(): ?string
    {
        return Setting::getValue(Setting::DRAFTS_LAST_PURGE_AT);
    }

    public function lastTransferAt(): ?string
    {
        return Setting::getValue(Setting::STOCK_TRANSFER_LAST_RUN);
    }

    /**
     * The month (Y-m) when the next automatic carry-over is due.
     */
    public function nextTransferDue(): ?string
    {
        return Setting::getValue(Setting::STOCK_TRANSFER_NEXT_DUE);
    }

    /**
     * Delete finalized/discarded tombstones older than PURGE_DAYS.
     */
    public function purgeNow(): int
    {
        $rows = Draft::whereIn('status', [Draft::STATUS_FINALIZED, Draft::STATUS_DISCARDED])
            ->where('updated_at', '<', now()->subDays(self::PURGE_DAYS))
            ->get(['id', 'uuid', 'user_id']);

        if ($rows->isEmpty()) {
            Setting::put(Setting::DRAFTS_LAST_PURGE_AT, now()->toDateTimeString());

            return 0;
        }

        // Remember the UUIDs before deleting the tombstones, otherwise a
        // delayed sync from a browser could re-create a draft for data that
        // was already saved.
        $rows->groupBy('user_id')->each(function ($group, $userId) {
            DraftClosure::closeMany(
                (int) $userId,
                $group->pluck('uuid')->filter()->all()
            );
        });

        $deleted = Draft::whereKey($rows->pluck('id'))->delete();

        Setting::put(Setting::DRAFTS_LAST_PURGE_AT, now()->toDateTimeString());

        return $deleted;
    }

    /**
     * Turn the purge on and run it immediately for instant feedback.
     */
    public function enablePurge(): int
    {
        Setting::put(Setting::DRAFTS_PURGE_ENABLED, '1');

        return $this->purgeNow();
    }

    public function disablePurge(): void
    {
        Setting::put(Setting::DRAFTS_PURGE_ENABLED, '0');
    }

    /**
     * Enable the monthly carry-over. It never changes the current month's
     * balances on the spot: the next run happens at the next month start.
     */
    public function enableTransfer(): void
    {
        Setting::put(Setting::STOCK_TRANSFER_ENABLED, '1');
        Setting::put(Setting::STOCK_TRANSFER_NEXT_DUE, $this->firstDueDate()->format('Y-m'));
    }

    public function disableTransfer(): void
    {
        Setting::put(Setting::STOCK_TRANSFER_ENABLED, '0');
        Setting::put(Setting::STOCK_TRANSFER_NEXT_DUE, null);
    }

    /**
     * Runs whatever is due. This is called on normal authenticated page
     * loads, so the toggles work without a Windows task or external cron.
     */
    public function runDueTasks(): void
    {
        $this->runPurgeIfDue();
        $this->runStockTransferIfDue();
    }

    private function runPurgeIfDue(): void
    {
        if (!$this->purgeEnabled()) {
            return;
        }

        $last = $this->lastPurgeAt();

        if ($last && Carbon::parse($last)->isToday()) {
            return;
        }

        try {
            $this->purgeNow();
        } catch (Throwable $e) {
            Log::error('Automatic draft purge failed: ' . $e->getMessage());
        }
    }

    private function runStockTransferIfDue(): void
    {
        if (!$this->transferEnabled()) {
            return;
        }

        $due = $this->nextTransferDue();

        // Enabled before the first due date existed: start from next month.
        if ($due === null) {
            Setting::put(Setting::STOCK_TRANSFER_NEXT_DUE, $this->nextMonth()->format('Y-m'));

            return;
        }

        if (strcmp(now()->format('Y-m'), $due) < 0) {
            return;
        }

        try {
            StockService::initializeAllUsersMonthlyStock();

            Setting::put(Setting::STOCK_TRANSFER_LAST_RUN, now()->toDateTimeString());
            Setting::put(Setting::STOCK_TRANSFER_NEXT_DUE, $this->nextMonth()->format('Y-m'));
        } catch (Throwable $e) {
            // Keep the due date unchanged so the next request retries.
            Log::error('Automatic stock carry-over failed: ' . $e->getMessage());
        }
    }

    /**
     * Enabling on the 1st runs right away; any other day waits for next month.
     */
    private function firstDueDate(): Carbon
    {
        return now()->day === 1
            ? now()->startOfMonth()
            : $this->nextMonth();
    }

    private function nextMonth(): Carbon
    {
        return now()->startOfMonth()->addMonthNoOverflow();
    }
}
