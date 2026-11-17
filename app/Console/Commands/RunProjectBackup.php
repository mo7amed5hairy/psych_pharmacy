<?php

namespace App\Console\Commands;

use App\Services\ProjectBackupService;
use Illuminate\Console\Command;

class RunProjectBackup extends Command
{
    protected $signature = 'backup:project
        {--if-due : نسخ فقط إذا كانت النسخة اليومية مستحقة}';

    protected $description = 'نسخ احتياطي كامل لملفات المشروع وقاعدة البيانات';

    public function __construct(private ProjectBackupService $backup)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->option('if-due')
            ? $this->backup->runIfDue()
            : $this->backup->run();

        if ($result === null) {
            $this->info('لا توجد نسخة احتياطية مستحقة الآن.');

            return self::SUCCESS;
        }

        if ($result['status'] !== 'success') {
            $this->error('فشل النسخ الاحتياطي: '.$result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);
        $this->line('المسار: '.$result['path']);

        return self::SUCCESS;
    }
}
