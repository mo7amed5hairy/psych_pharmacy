<?php

namespace App\Console\Commands;

use App\Models\Draft;
use App\Support\DraftClosure;
use Illuminate\Console\Command;

class PurgeDrafts extends Command
{
    protected $signature = 'drafts:purge {--days=30 : حذف المسودات المغلقة الأقدم من عدد الأيام (0 = كل المغلقة)}';

    protected $description = 'حذف المسودات المنتهية (المحفوظة/المتجاهلة) الأقدم من مدة محددة';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));

        $query = Draft::whereIn('status', [Draft::STATUS_FINALIZED, Draft::STATUS_DISCARDED]);

        if ($days > 0) {
            $query->where('updated_at', '<', now()->subDays($days));
        }

        $rows = $query->get(['id', 'uuid', 'user_id']);

        // Remember the UUIDs before deleting the tombstones so a delayed sync
        // cannot re-create a draft for data that was already saved.
        $rows->groupBy('user_id')->each(function ($group, $userId) {
            DraftClosure::closeMany(
                (int) $userId,
                $group->pluck('uuid')->filter()->all()
            );
        });

        $deleted = $rows->isEmpty() ? 0 : Draft::whereKey($rows->pluck('id'))->delete();

        $this->info("تم حذف {$deleted} مسودة مغلقة" . ($days > 0 ? " أقدم من {$days} يوم." : '.'));

        return self::SUCCESS;
    }
}
