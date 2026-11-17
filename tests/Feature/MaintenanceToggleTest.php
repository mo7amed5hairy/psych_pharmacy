<?php

namespace Tests\Feature;

use App\Models\Draft;
use App\Models\Medicine;
use App\Models\Setting;
use App\Models\UnitType;
use App\Models\User;
use App\Models\UserPermission;
use App\Support\DraftClosure;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MaintenanceToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::forgetMemo();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeUser(string $code = '1111', string $name = 'رانيا جلال'): User
    {
        return User::create([
            'name' => $name,
            'employee_code' => $code,
            'password_hash' => 'secret',
        ]);
    }

    private function makeAdmin(): User
    {
        $admin = $this->makeUser('1001', 'محمد');

        UserPermission::create([
            'user_id' => $admin->id,
            'module' => 'dashboard',
            'is_granted' => true,
        ]);

        return $admin;
    }

    private function makeDraft(User $user, string $status, Carbon $updatedAt): Draft
    {
        $id = DB::table('drafts')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'unit',
            'title' => 'مسودة اختبار',
            'payload' => json_encode(['name' => 'وحدة اختبار'], JSON_UNESCAPED_UNICODE),
            'revision' => 1,
            'status' => $status,
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);

        return Draft::findOrFail($id);
    }

    private function makeStock(User $user, Medicine $medicine, string $date, int $quantity): void
    {
        DB::table('stocks')->insert([
            'user_id' => $user->id,
            'medicine_id' => $medicine->id,
            'quantity' => $quantity,
            'dispensed' => 0,
            'stock_date' => $date,
            'is_init' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeMedicine(): Medicine
    {
        $unit = UnitType::first() ?: UnitType::create(['name' => 'علبة']);

        return Medicine::create([
            'name' => 'دواء اختبار ' . Str::random(5),
            'unit_type_id' => $unit->id,
            'price_hotline' => 10,
            'price_contract' => 8,
            'price_clinic' => 12,
        ]);
    }

    // ================= purge toggle =================

    public function test_only_the_admin_can_toggle_automatic_features(): void
    {
        $user = $this->makeUser('2222', 'هدير');

        $this->actingAs($user)
            ->post(route('drafts.settings.purge'))
            ->assertRedirect(route('unauthorized'));

        $this->actingAs($user)
            ->post(route('drafts.settings.transfer'))
            ->assertRedirect(route('unauthorized'));

        $this->assertNull(Setting::getValue(Setting::DRAFTS_PURGE_ENABLED));
        $this->assertNull(Setting::getValue(Setting::STOCK_TRANSFER_ENABLED));
    }

    public function test_enabling_the_purge_runs_it_immediately_and_keeps_only_pending_drafts(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeUser();

        $oldFinalized = $this->makeDraft($owner, Draft::STATUS_FINALIZED, now()->subDays(40));
        $recentFinalized = $this->makeDraft($owner, Draft::STATUS_FINALIZED, now()->subDays(2));
        $oldDiscarded = $this->makeDraft($owner, Draft::STATUS_DISCARDED, now()->subDays(45));
        $oldPending = $this->makeDraft($owner, Draft::STATUS_PENDING, now()->subDays(45));

        $this->actingAs($admin)
            ->post(route('drafts.settings.purge'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(Setting::enabled(Setting::DRAFTS_PURGE_ENABLED));
        $this->assertNotNull(Setting::getValue(Setting::DRAFTS_LAST_PURGE_AT));

        $this->assertDatabaseMissing('drafts', ['id' => $oldFinalized->id]);
        $this->assertDatabaseMissing('drafts', ['id' => $oldDiscarded->id]);
        $this->assertDatabaseHas('drafts', ['id' => $recentFinalized->id]);
        $this->assertDatabaseHas('drafts', ['id' => $oldPending->id]);

        // A delayed sync must not re-create the purged tombstones.
        $this->assertTrue(DraftClosure::isClosed($owner->id, $oldFinalized->uuid));
        $this->assertTrue(DraftClosure::isClosed($owner->id, $oldDiscarded->uuid));
        $this->assertFalse(DraftClosure::isClosed($owner->id, $oldPending->uuid));
    }

    public function test_the_purge_command_can_remove_all_closed_tombstones_and_remembers_them(): void
    {
        $owner = $this->makeUser();
        $closed = $this->makeDraft($owner, Draft::STATUS_FINALIZED, now());
        $pending = $this->makeDraft($owner, Draft::STATUS_PENDING, now());

        $this->artisan('drafts:purge', ['--days' => 0])->assertSuccessful();

        $this->assertDatabaseMissing('drafts', ['id' => $closed->id]);
        $this->assertDatabaseHas('drafts', ['id' => $pending->id]);
        $this->assertTrue(DraftClosure::isClosed($owner->id, $closed->uuid));
        $this->assertFalse(DraftClosure::isClosed($owner->id, $pending->uuid));
    }

    public function test_pressing_the_purge_button_again_turns_it_off(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('drafts.settings.purge'))->assertRedirect();
        $this->assertTrue(Setting::enabled(Setting::DRAFTS_PURGE_ENABLED));

        $this->actingAs($admin)->post(route('drafts.settings.purge'))->assertRedirect();
        $this->assertFalse(Setting::enabled(Setting::DRAFTS_PURGE_ENABLED));
    }

    // ================= stock transfer toggle =================

    public function test_enabling_the_stock_transfer_targets_the_next_month_without_changing_current_balances(): void
    {
        Carbon::setTestNow('2026-11-16 10:00:00');

        $admin = $this->makeAdmin();
        $owner = $this->makeUser();
        $medicine = $this->makeMedicine();
        $this->makeStock($owner, $medicine, '2026-11-01', 25);

        $this->actingAs($admin)
            ->post(route('drafts.settings.transfer'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(Setting::enabled(Setting::STOCK_TRANSFER_ENABLED));
        $this->assertSame('2026-12', Setting::getValue(Setting::STOCK_TRANSFER_NEXT_DUE));

        // November balances are untouched by the toggle itself.
        $this->assertSame(25, (int) DB::table('stocks')->value('quantity'));
        $this->assertSame(1, DB::table('stocks')->count());

        $this->actingAs($admin)->post(route('drafts.settings.transfer'))->assertRedirect();

        $this->assertFalse(Setting::enabled(Setting::STOCK_TRANSFER_ENABLED));
        $this->assertNull(Setting::getValue(Setting::STOCK_TRANSFER_NEXT_DUE));
    }

    public function test_the_monthly_transfer_runs_automatically_on_the_first_page_load_of_the_month(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeUser();
        $medicine = $this->makeMedicine();
        $this->makeStock($owner, $medicine, '2026-11-01', 40);

        Setting::put(Setting::STOCK_TRANSFER_ENABLED, '1');
        Setting::put(Setting::STOCK_TRANSFER_NEXT_DUE, '2026-12');

        Carbon::setTestNow('2026-12-01 08:00:00');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('الترحيل التلقائي لأرصدة الأدوية');

        $december = DB::table('stocks')
            ->where('user_id', $owner->id)
            ->where('medicine_id', $medicine->id)
            ->whereDate('stock_date', '2026-12-01')
            ->first();

        $this->assertNotNull($december);
        $this->assertSame(40, (int) $december->quantity);
        $this->assertSame(1, (int) $december->is_init);

        $this->assertSame('2027-01', Setting::getValue(Setting::STOCK_TRANSFER_NEXT_DUE));
        $this->assertNotNull(Setting::getValue(Setting::STOCK_TRANSFER_LAST_RUN));
    }

    public function test_the_dashboard_card_hidden_until_the_transfer_is_enabled(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('الترحيل التلقائي لأرصدة الأدوية');

        $this->actingAs($admin)->post(route('drafts.settings.transfer'))->assertRedirect();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('الترحيل التلقائي لأرصدة الأدوية');
    }
}
