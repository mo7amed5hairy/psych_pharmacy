<?php

namespace App\Console\Commands;

use App\Models\Draft;
use Illuminate\Console\Command;

class PurgeDrafts extends Command
{
    protected $signature = 'drafts:purge {--days=30 : حذف المسودات المغلقة الأقدم من عدد الأيام}';

    protected $description = 'حذف المسودات المنتهية (المحفوظة/المتجاهلة) الأقدم من مدة محددة';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = Draft::whereIn('status', [Draft::STATUS_FINALIZED, Draft::STATUS_DISCARDED])
            ->where('updated_at', '<', now()->subDays($days))
            ->delete();

        $this->info("تم حذف {$deleted} مسودة مغلقة أقدم من {$days} يوم.");

        return self::SUCCESS;
    }
}
