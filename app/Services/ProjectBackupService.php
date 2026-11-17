<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use FilesystemIterator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use ZipArchive;

/**
 * A small, dependency-free backup of the project files and the MySQL
 * database. It is triggered from inside the application, so no Windows
 * Task Scheduler entry or external cron is required.
 */
class ProjectBackupService
{
    /**
     * A failed run is retried after this many minutes instead of waiting a
     * whole day.
     */
    public const RETRY_AFTER_MINUTES = 60;

    public function enabled(): bool
    {
        return (bool) config('project_backup.enabled', true);
    }

    public function isDue(?Carbon $now = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $now = $now ? $now->copy() : now();

        $runAt = $now->copy()->setTime(
            (int) config('project_backup.run_after_hour', 2),
            (int) config('project_backup.run_after_minute', 30),
            0
        );

        if ($now->lt($runAt)) {
            return false;
        }

        $lastSuccess = Setting::getValue(Setting::PROJECT_BACKUP_LAST_RUN);

        if ($lastSuccess && Carbon::parse($lastSuccess)->isSameDay($now)) {
            return false;
        }

        $lastAttempt = Setting::getValue(Setting::PROJECT_BACKUP_LAST_ATTEMPT);

        if ($lastAttempt && Carbon::parse($lastAttempt)->gt($now->copy()->subMinutes(self::RETRY_AFTER_MINUTES))) {
            return false;
        }

        return true;
    }

    /**
     * Run the backup only when the daily copy is due. Used by the automatic
     * trigger; manual requests call run() directly.
     */
    public function runIfDue(?Carbon $now = null): ?array
    {
        if (! $this->isDue($now)) {
            return null;
        }

        return $this->run();
    }

    /**
     * Create a full backup. Returns a result array and never throws so both
     * the command and the manual button can show the outcome.
     */
    public function run(): array
    {
        if (! $this->enabled()) {
            return $this->failure('النسخ الاحتياطي التلقائي متوقف من إعدادات التطبيق.');
        }

        // The automatic run happens after the response; finish it even if the
        // browser closes the connection.
        @ignore_user_abort(true);
        @set_time_limit(0);

        $lock = $this->acquireLock();

        if ($lock === null) {
            return $this->failure('عملية نسخ احتياطي أخرى قيد التنفيذ حالياً، حاول بعد قليل.');
        }

        $workDir = storage_path('app/backup-temp/run-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)));
        $startedAt = now();

        try {
            Setting::put(Setting::PROJECT_BACKUP_LAST_ATTEMPT, $startedAt->toDateTimeString());

            $this->ensureDirectory($workDir);

            $backupDir = rtrim(
                str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) config('project_backup.path')),
                '\\/'
            );
            $this->ensureDirectory($backupDir);

            $databaseFile = $workDir.DIRECTORY_SEPARATOR.'database.sql';
            $this->dumpDatabase($databaseFile);

            $archiveName = 'backup-'.$startedAt->format('Y-m-d_His').'.zip';
            $temporaryArchive = $workDir.DIRECTORY_SEPARATOR.$archiveName;

            [$filesCount, $projectBytes] = $this->createArchive($temporaryArchive, $databaseFile);

            $finalPath = $backupDir.DIRECTORY_SEPARATOR.$archiveName;
            $this->moveFile($temporaryArchive, $finalPath);

            $size = (int) @filesize($finalPath);
            $warnings = [];

            $this->prune($backupDir);

            $external = config('project_backup.external_path');
            $external = $external
                ? rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $external), '\\/')
                : null;

            if ($external) {
                try {
                    $this->ensureDirectory($external);
                    $this->copyFile($finalPath, $external . DIRECTORY_SEPARATOR . $archiveName);
                    $this->prune($external);
                } catch (Throwable $e) {
                    Log::warning('Project backup external copy failed: '.$e->getMessage());
                    $warnings[] = 'تم إنشاء النسخة المحلية، لكن النسخ إلى المسار الخارجي فشل.';
                }
            }

            $message = 'تم إنشاء نسخة احتياطية كاملة ('.$filesCount.' ملف و'.$this->humanSize($size).').';

            if ($warnings !== []) {
                $message .= ' '.implode(' ', $warnings);
            }

            $result = [
                'status' => 'success',
                'file' => $archiveName,
                'path' => $finalPath,
                'size' => $size,
                'files' => $filesCount,
                'project_bytes' => $projectBytes,
                'finished_at' => now()->toDateTimeString(),
                'message' => $message,
            ];

            $this->recordSuccess($result);

            return $result;
        } catch (Throwable $e) {
            Log::error('Project backup failed: '.$e->getMessage(), ['exception' => $e]);

            return $this->failure($e->getMessage());
        } finally {
            $this->deleteDirectory($workDir);
            $this->releaseLock($lock);
        }
    }

    /**
     * Status data shown on the drafts page.
     */
    public function status(): array
    {
        $size = Setting::getValue(Setting::PROJECT_BACKUP_LAST_SIZE);

        return [
            'enabled' => $this->enabled(),
            'lastRunAt' => Setting::getValue(Setting::PROJECT_BACKUP_LAST_RUN),
            'lastStatus' => Setting::getValue(Setting::PROJECT_BACKUP_LAST_STATUS),
            'lastFile' => Setting::getValue(Setting::PROJECT_BACKUP_LAST_FILE),
            'lastSize' => $size ? $this->humanSize((int) $size) : null,
            'lastMessage' => Setting::getValue(Setting::PROJECT_BACKUP_LAST_MESSAGE),
            'path' => (string) config('project_backup.path'),
            'externalPath' => config('project_backup.external_path'),
            'keepDays' => (int) config('project_backup.keep_days', 14),
            'runAfter' => sprintf(
                '%02d:%02d',
                (int) config('project_backup.run_after_hour', 2),
                (int) config('project_backup.run_after_minute', 30)
            ),
        ];
    }

    /**
     * Dump the default MySQL connection into one SQL file using a temporary
     * option file so the password never appears on the command line.
     */
    private function dumpDatabase(string $target): void
    {
        $connectionName = (string) config('database.default');
        $connection = (array) config('database.connections.'.$connectionName);

        if (($connection['driver'] ?? null) !== 'mysql') {
            throw new \RuntimeException('النسخ الاحتياطي الحالي يدعم اتصال MySQL فقط.');
        }

        $binary = (string) config('project_backup.mysqldump');

        if (! is_file($binary)) {
            throw new \RuntimeException('لم يتم العثور على mysqldump في المسار: '.$binary);
        }

        $optionFile = $target.'.cnf';
        $this->writeOptionFile($optionFile, $connection);

        try {
            $command = [
                $binary,
                '--defaults-extra-file='.$optionFile,
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--default-character-set=utf8mb4',
                '--routines',
                '--events',
                '--no-tablespaces',
                '--set-gtid-purged=OFF',
                '--result-file='.$target,
                (string) $connection['database'],
            ];

            $result = Process::timeout((int) config('project_backup.timeout', 300))
                ->path(base_path())
                ->run($command);

            if (! $result->successful()) {
                throw new \RuntimeException(
                    'فشل تصدير قاعدة البيانات: '.trim($result->errorOutput() ?: $result->output())
                );
            }

            if (! is_file($target) || filesize($target) === 0) {
                throw new \RuntimeException('تم تنفيذ تصدير قاعدة البيانات لكن الملف الناتج فارغ.');
            }
        } finally {
            @unlink($optionFile);
        }
    }

    private function writeOptionFile(string $path, array $connection): void
    {
        $escape = static fn (string $value): string => str_replace(
            ['\\', '"'],
            ['\\\\', '\\"'],
            $value
        );

        $lines = [
            '[client]',
            'user="'.$escape((string) ($connection['username'] ?? '')).'"',
            'password="'.$escape((string) ($connection['password'] ?? '')).'"',
            'host="'.$escape((string) ($connection['host'] ?? '127.0.0.1')).'"',
            'port="'.$escape((string) ($connection['port'] ?? '3306')).'"',
        ];

        if (! empty($connection['unix_socket'])) {
            $lines[] = 'socket="'.$escape((string) $connection['unix_socket']).'"';
        }

        if (@file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL) === false) {
            throw new \RuntimeException('تعذر إنشاء ملف بيانات اتصال قاعدة البيانات المؤقت.');
        }
    }

    /**
     * @return array{0:int,1:int} number of archived files and their total bytes
     */
    private function createArchive(string $archivePath, string $databaseFile): array
    {
        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('تعذر إنشاء ملف الأرشيف.');
        }

        $files = 0;
        $bytes = 0;

        try {
            [$files, $bytes] = $this->addProjectFiles($zip);

            $zip->addFile($databaseFile, 'database.sql');
            $zip->addFromString('RESTORE.txt', $this->restoreInstructions());

            if ($zip->close() !== true) {
                throw new \RuntimeException('تعذر إغلاق ملف الأرشيف.');
            }
        } catch (Throwable $e) {
            $zip->close();

            throw $e;
        }

        return [$files, $bytes];
    }

    /**
     * @return array{0:int,1:int}
     */
    private function addProjectFiles(ZipArchive $zip): array
    {
        $base = str_replace('\\', '/', base_path());

        // Never archive the directory the backup is written to, even when it
        // was configured inside the project by mistake.
        $backupPath = str_replace('\\', '/', (string) config('project_backup.path'));

        $excluded = array_map(
            static fn ($path) => trim(str_replace('\\', '/', (string) $path), '/'),
            (array) config('project_backup.exclude', [])
        );

        $filter = function (SplFileInfo $file) use ($base, $backupPath, $excluded): bool {
            if ($file->isLink()) {
                return false;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $relative = ltrim(substr($path, strlen($base)), '/');

            if ($backupPath !== '' && str_starts_with($path.'/', rtrim($backupPath, '/').'/')) {
                return false;
            }

            foreach ($excluded as $prefix) {
                if ($prefix === '') {
                    continue;
                }

                if ($relative === $prefix || str_starts_with($relative, $prefix.'/')) {
                    return false;
                }
            }

            return true;
        };

        $directory = new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator($directory, $filter),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $files = 0;
        $bytes = 0;

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $relative = ltrim(substr($path, strlen($base)), '/');
            $entry = 'project/'.$relative;

            if (! $zip->addFile($path, $entry)) {
                Log::warning('Project backup skipped a file it could not read: '.$relative);

                continue;
            }

            $files++;
            $bytes += (int) $file->getSize();
        }

        return [$files, $bytes];
    }

    private function restoreInstructions(): string
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        $createdAt = now()->toDateTimeString();

        return implode(PHP_EOL, [
            'نسخة احتياطية لمشروع صيدلية psych_pharmacy',
            'تاريخ الإنشاء: '.$createdAt,
            'قاعدة البيانات: '.$database,
            '',
            'محتوى الأرشيف:',
            '  project/      ملفات المشروع كاملة (بما فيها .env)',
            '  database.sql  نسخة قاعدة البيانات',
            '',
            'خطوات الاستعادة:',
            '1) فك ضغط الأرشيف في مجلد مؤقت.',
            '2) انسخ محتويات مجلد project/ فوق مجلد المشروع على السيرفر.',
            '3) أنشئ قاعدة بيانات فارغة بنفس الاسم والترميز utf8mb4_unicode_ci إن لم تكن موجودة.',
            '4) استورد قاعدة البيانات:',
            '   "C:\\Program Files\\MySQL\\MySQL Server 9.6\\bin\\mysql.exe" -u USER -p DATABASE < database.sql',
            '   (أو من phpMyAdmin: اختر قاعدة البيانات ثم Import ثم database.sql)',
            '5) نفّذ من مجلد المشروع:',
            '   composer install --no-dev --optimize-autoloader',
            '   php artisan migrate --force',
            '   php artisan config:clear && php artisan view:clear',
            '6) تأكد من صلاحيات الكتابة على storage وbootstrap/cache.',
            '',
            'ملاحظة أمنية: يحتوي هذا الأرشيف على ملف .env وبيانات دخول قاعدة البيانات.',
            'احفظه في مكان آمن ولا ترفعه إلى Git أو إلى رابط عام.',
        ]).PHP_EOL;
    }

    private function recordSuccess(array $result): void
    {
        Setting::put(Setting::PROJECT_BACKUP_LAST_RUN, $result['finished_at']);
        Setting::put(Setting::PROJECT_BACKUP_LAST_STATUS, 'success');
        Setting::put(Setting::PROJECT_BACKUP_LAST_FILE, $result['file']);
        Setting::put(Setting::PROJECT_BACKUP_LAST_SIZE, (string) $result['size']);
        Setting::put(Setting::PROJECT_BACKUP_LAST_MESSAGE, $result['message']);
    }

    private function failure(string $message): array
    {
        Setting::put(Setting::PROJECT_BACKUP_LAST_ATTEMPT, now()->toDateTimeString());
        Setting::put(Setting::PROJECT_BACKUP_LAST_STATUS, 'failed');
        Setting::put(Setting::PROJECT_BACKUP_LAST_MESSAGE, $message);

        return [
            'status' => 'failed',
            'file' => null,
            'path' => null,
            'size' => 0,
            'files' => 0,
            'project_bytes' => 0,
            'finished_at' => now()->toDateTimeString(),
            'message' => $message,
        ];
    }

    /**
     * Delete archives older than the retention window.
     */
    private function prune(string $directory): void
    {
        $keepDays = max(1, (int) config('project_backup.keep_days', 14));
        $expiresBefore = now()->subDays($keepDays)->getTimestamp();

        foreach ((array) glob(rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'backup-*.zip') as $file) {
            if (is_file($file) && @filemtime($file) < $expiresBefore) {
                @unlink($file);
            }
        }
    }

    private function moveFile(string $from, string $to): void
    {
        if (file_exists($to)) {
            throw new \RuntimeException('يوجد ملف بنفس الاسم مسبقاً: '.basename($to));
        }

        if (@rename($from, $to)) {
            return;
        }

        if (! @copy($from, $to)) {
            throw new \RuntimeException('تعذر نقل ملف النسخة الاحتياطية إلى: '.$to);
        }

        @unlink($from);
    }

    private function copyFile(string $from, string $to): void
    {
        if (! @copy($from, $to)) {
            throw new \RuntimeException('تعذر نسخ ملف النسخة الاحتياطية إلى: '.$to);
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('تعذر إنشاء المجلد: '.$directory);
        }
    }

    /**
     * @return resource|null
     */
    private function acquireLock()
    {
        $lockPath = storage_path('app/backup-temp/project-backup.lock');
        $this->ensureDirectory(dirname($lockPath));

        $handle = @fopen($lockPath, 'c');

        if ($handle === false) {
            return null;
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    /**
     * @param  resource  $handle
     */
    private function releaseLock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($directory);
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2).' ميجابايت';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' كيلوبايت';
        }

        return $bytes.' بايت';
    }
}
