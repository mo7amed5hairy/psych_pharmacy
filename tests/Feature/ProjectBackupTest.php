<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\ProjectBackupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ProjectBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::forgetMemo();
        config(['project_backup.enabled' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeUser(string $code, string $name = 'مستخدم'): User
    {
        return User::create([
            'name' => $name,
            'employee_code' => $code,
            'password_hash' => 'secret',
        ]);
    }

    private function backup(): ProjectBackupService
    {
        return app(ProjectBackupService::class);
    }

    public function test_the_backup_is_not_due_before_the_configured_time(): void
    {
        Carbon::setTestNow('2026-11-17 02:00:00');

        $this->assertFalse($this->backup()->isDue());
    }

    public function test_the_backup_is_due_on_the_first_visit_after_the_configured_time(): void
    {
        Carbon::setTestNow('2026-11-17 02:31:00');

        $this->assertTrue($this->backup()->isDue());
    }

    public function test_a_successful_backup_runs_only_once_per_day(): void
    {
        Carbon::setTestNow('2026-11-17 02:31:00');

        Setting::put(Setting::PROJECT_BACKUP_LAST_RUN, '2026-11-17 02:31:00');

        $this->assertFalse($this->backup()->isDue());

        // The next day it is due again.
        Carbon::setTestNow('2026-11-18 02:31:00');

        $this->assertTrue($this->backup()->isDue());
    }

    public function test_a_failed_backup_is_retried_after_one_hour_not_on_every_request(): void
    {
        Carbon::setTestNow('2026-11-17 02:31:00');

        Setting::put(Setting::PROJECT_BACKUP_LAST_ATTEMPT, '2026-11-17 02:31:00');
        Setting::put(Setting::PROJECT_BACKUP_LAST_STATUS, 'failed');

        Carbon::setTestNow('2026-11-17 02:50:00');
        $this->assertFalse($this->backup()->isDue());

        Carbon::setTestNow('2026-11-17 03:32:00');
        $this->assertTrue($this->backup()->isDue());
    }

    public function test_the_backup_is_never_due_when_disabled(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');

        config(['project_backup.enabled' => false]);

        $this->assertFalse($this->backup()->isDue());
    }

    public function test_the_first_visit_after_the_configured_time_schedules_the_backup_in_the_background(): void
    {
        Carbon::setTestNow('2026-11-17 02:31:00');

        $owner = $this->makeUser('1001', 'محمد');

        $fake = new class extends ProjectBackupService
        {
            public int $runs = 0;

            public function isDue(?Carbon $now = null): bool
            {
                return true;
            }

            public function runIfDue(?Carbon $now = null): ?array
            {
                $this->runs++;

                return null;
            }
        };

        $this->app->instance(ProjectBackupService::class, $fake);

        $this->actingAs($owner)
            ->get(route('drafts.index'))
            ->assertOk();

        // The deferred callback runs after the response is sent.
        $this->assertSame(1, $fake->runs);
    }

    public function test_only_the_owner_can_create_a_backup_from_the_drafts_page(): void
    {
        $user = $this->makeUser('2222');

        UserPermission::create([
            'user_id' => $user->id,
            'module' => 'drafts',
            'is_granted' => true,
        ]);

        $this->actingAs($user)
            ->post(route('drafts.settings.backup'))
            ->assertRedirect(route('unauthorized'));
    }

    public function test_the_owner_can_create_a_backup_from_the_drafts_page(): void
    {
        $owner = $this->makeUser('1001', 'محمد');

        $this->mock(ProjectBackupService::class, function (MockInterface $mock) {
            $mock->shouldReceive('run')->once()->andReturn([
                'status' => 'success',
                'file' => 'backup-2026-11-17_023100.zip',
                'message' => 'تم إنشاء نسخة احتياطية كاملة.',
            ]);
        });

        $this->actingAs($owner)
            ->post(route('drafts.settings.backup'))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_the_owner_sees_the_backup_card_on_the_drafts_page(): void
    {
        $owner = $this->makeUser('1001', 'محمد');

        Setting::put(Setting::PROJECT_BACKUP_LAST_RUN, now()->toDateTimeString());
        Setting::put(Setting::PROJECT_BACKUP_LAST_ATTEMPT, now()->toDateTimeString());
        Setting::put(Setting::PROJECT_BACKUP_LAST_STATUS, 'success');
        Setting::put(Setting::PROJECT_BACKUP_LAST_FILE, 'backup-2026-11-17_023100.zip');
        Setting::put(Setting::PROJECT_BACKUP_LAST_SIZE, '4096');

        $this->actingAs($owner)
            ->get(route('drafts.index'))
            ->assertOk()
            ->assertSee('النسخ الاحتياطي للمشروع وقاعدة البيانات')
            ->assertSee('backup-2026-11-17_023100.zip')
            ->assertSee('نسخة احتياطية الآن');
    }

    public function test_the_command_fails_cleanly_when_the_backup_is_disabled(): void
    {
        config(['project_backup.enabled' => false]);

        $this->artisan('backup:project')->assertFailed();
    }
}
