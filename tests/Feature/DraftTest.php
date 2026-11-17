<?php

namespace Tests\Feature;

use App\Models\DispensedMedicine;
use App\Models\Draft;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Stock;
use App\Models\UnitType;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\InvoiceService;
use App\Support\DraftClosure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DraftTest extends TestCase
{
    use RefreshDatabase;

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
        return $this->makeUser('1001', 'محمد');
    }

    private function makeMedicine(string $name = 'دواء تجريبي'): Medicine
    {
        $unit = UnitType::first() ?: UnitType::create(['name' => 'علبة']);

        return Medicine::create([
            'name' => $name,
            'unit_type_id' => $unit->id,
            'price_hotline' => 10,
            'price_contract' => 8,
            'price_clinic' => 12,
        ]);
    }

    private function makeStock(User $user, Medicine $medicine, int $quantity, string $date, int $dispensed = 0): Stock
    {
        // Insert through the query builder so the column stores a plain date,
        // exactly like a MySQL DATE column (Eloquent's date cast would store
        // a full datetime, which SQLite keeps verbatim).
        $id = DB::table('stocks')->insertGetId([
            'user_id' => $user->id,
            'medicine_id' => $medicine->id,
            'quantity' => $quantity,
            'dispensed' => $dispensed,
            'stock_date' => $date,
            'is_init' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Stock::findOrFail($id);
    }

    private function makeDraft(User $user, string $type, array $payload, string $status = Draft::STATUS_PENDING): Draft
    {
        return Draft::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => $type,
            'title' => Draft::titleFor($type, $payload),
            'payload' => $payload,
            'revision' => 1,
            'status' => $status,
        ]);
    }

    // ================= sync =================

    public function test_sync_creates_a_pending_draft_for_the_logged_in_user(): void
    {
        $user = $this->makeUser();
        $uuid = (string) Str::uuid();

        $response = $this->actingAs($user)->postJson(route('drafts.sync'), [
            'uuid' => $uuid,
            'type' => 'dispensed_medicine',
            'revision' => 1,
            'payload' => [
                'medicines' => [
                    [
                        'medicine_id' => 1,
                        'quantity' => 2,
                        'dispense_date' => '2026-11-10',
                        'referral_number' => 'R-1',
                    ],
                ],
            ],
        ]);

        $response->assertOk()->assertJson([
            'status' => Draft::STATUS_PENDING,
            'revision' => 1,
        ]);

        $this->assertDatabaseHas('drafts', [
            'uuid' => $uuid,
            'user_id' => $user->id,
            'type' => 'dispensed_medicine',
            'status' => Draft::STATUS_PENDING,
            'revision' => 1,
        ]);
    }

    public function test_sync_updates_an_existing_draft_without_letting_revision_go_backwards(): void
    {
        $user = $this->makeUser();
        $draft = $this->makeDraft($user, 'dispensed_medicine', [
            'medicines' => [['medicine_id' => 1, 'quantity' => 1, 'dispense_date' => '2026-11-10']],
        ]);

        $draft->forceFill(['revision' => 9])->save();

        $response = $this->actingAs($user)->postJson(route('drafts.sync'), [
            'uuid' => $draft->uuid,
            'type' => 'dispensed_medicine',
            'revision' => 2,
            'payload' => [
                'medicines' => [['medicine_id' => 1, 'quantity' => 5, 'dispense_date' => '2026-11-10']],
            ],
        ]);

        $response->assertOk();

        $draft->refresh();

        $this->assertSame(10, $draft->revision);
        $this->assertSame(5, $draft->payload['medicines'][0]['quantity']);
    }

    public function test_sync_rejects_a_draft_owned_by_another_user(): void
    {
        $owner = $this->makeUser();
        $intruder = $this->makeUser('2222', 'هدير');
        $draft = $this->makeDraft($owner, 'dispensed_medicine', [
            'medicines' => [['medicine_id' => 1, 'quantity' => 1, 'dispense_date' => '2026-11-10']],
        ]);

        $response = $this->actingAs($intruder)->postJson(route('drafts.sync'), [
            'uuid' => $draft->uuid,
            'type' => 'dispensed_medicine',
            'revision' => 2,
            'payload' => [
                'medicines' => [['medicine_id' => 1, 'quantity' => 9, 'dispense_date' => '2026-11-10']],
            ],
        ]);

        $response->assertStatus(403);
        $this->assertSame(1, $draft->fresh()->payload['medicines'][0]['quantity']);
    }

    public function test_sync_returns_closed_status_without_changing_a_finalized_draft(): void
    {
        $user = $this->makeUser();
        $draft = $this->makeDraft($user, 'dispensed_medicine', [
            'medicines' => [['medicine_id' => 1, 'quantity' => 1, 'dispense_date' => '2026-11-10']],
        ], Draft::STATUS_FINALIZED);

        $response = $this->actingAs($user)->postJson(route('drafts.sync'), [
            'uuid' => $draft->uuid,
            'type' => 'dispensed_medicine',
            'revision' => 50,
            'payload' => [
                'medicines' => [['medicine_id' => 1, 'quantity' => 99, 'dispense_date' => '2026-11-10']],
            ],
        ]);

        $response->assertOk()->assertJson(['status' => Draft::STATUS_FINALIZED]);

        $draft->refresh();

        $this->assertSame(Draft::STATUS_FINALIZED, $draft->status);
        $this->assertSame(1, $draft->payload['medicines'][0]['quantity']);
    }

    public function test_sync_rejects_an_unknown_draft_type(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->postJson(route('drafts.sync'), [
            'uuid' => (string) Str::uuid(),
            'type' => 'not_a_real_type',
            'revision' => 1,
            'payload' => ['foo' => 'bar'],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('drafts', 0);
    }

    public function test_mine_returns_pending_drafts_and_recent_tombstones(): void
    {
        $user = $this->makeUser();
        $pending = $this->makeDraft($user, 'dispensed_medicine', [
            'medicines' => [['medicine_id' => 1, 'quantity' => 1, 'dispense_date' => '2026-11-10']],
        ]);
        $closed = $this->makeDraft($user, 'dispensed_medicine', [
            'medicines' => [['medicine_id' => 1, 'quantity' => 1, 'dispense_date' => '2026-11-10']],
        ], Draft::STATUS_FINALIZED);

        $response = $this->actingAs($user)->getJson(route('drafts.mine'));

        $response->assertOk()
            ->assertJsonFragment(['uuid' => $pending->uuid]);

        $this->assertCount(1, $response->json('pending'));
        $this->assertContains($closed->uuid, $response->json('closed'));
    }

    // ================= admin access =================

    public function test_draft_admin_page_is_forbidden_for_non_admin_users(): void
    {
        $user = $this->makeUser('2222', 'هدير');

        $this->actingAs($user)
            ->get(route('drafts.index'))
            ->assertRedirect(route('unauthorized'));

        $this->actingAs($user)
            ->getJson(route('drafts.index'))
            ->assertStatus(403);
    }

    public function test_draft_admin_page_is_available_for_employee_1001(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeUser();

        $this->makeDraft($owner, 'dispensed_medicine', [
            'medicines' => [['medicine_id' => 1, 'quantity' => 2, 'dispense_date' => '2026-11-10']],
        ]);

        $this->actingAs($admin)
            ->get(route('drafts.index'))
            ->assertOk()
            ->assertSee('المسودات')
            ->assertSee('هذه الشاشة مخصصة للمسؤول')
            ->assertSee('أدوات الصيانة التلقائية')
            ->assertSee($owner->name);
    }

    public function test_draft_admin_page_is_available_for_a_user_granted_the_drafts_permission(): void
    {
        $user = $this->makeUser('1111', 'رانيا جلال');
        $owner = $this->makeUser('2222', 'هدير');

        UserPermission::create([
            'user_id' => $user->id,
            'module' => 'drafts',
            'is_granted' => true,
        ]);

        $this->makeDraft($owner, 'dispensed_medicine', [
            'medicines' => [['medicine_id' => 1, 'quantity' => 2, 'dispense_date' => '2026-11-10']],
        ]);

        $this->actingAs($user)
            ->get(route('drafts.index'))
            ->assertOk()
            ->assertSee($owner->name)
            ->assertDontSee('هذه الشاشة مخصصة للمسؤول')
            ->assertDontSee('أدوات الصيانة التلقائية');
    }

    public function test_a_granted_user_cannot_change_maintenance_settings(): void
    {
        $user = $this->makeUser('1111', 'رانيا جلال');

        UserPermission::create([
            'user_id' => $user->id,
            'module' => 'drafts',
            'is_granted' => true,
        ]);

        $this->actingAs($user)
            ->post(route('drafts.settings.purge'))
            ->assertRedirect(route('unauthorized'));

        $this->actingAs($user)
            ->postJson(route('drafts.settings.transfer'))
            ->assertStatus(403);
    }

    public function test_the_drafts_permission_controls_the_sidebar_link(): void
    {
        $user = $this->makeUser('1111', 'رانيا جلال');

        UserPermission::create([
            'user_id' => $user->id,
            'module' => 'dashboard',
            'is_granted' => true,
        ]);

        $draftsUrl = route('drafts.index');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('href="' . $draftsUrl . '"', false);

        UserPermission::create([
            'user_id' => $user->id,
            'module' => 'drafts',
            'is_granted' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="' . $draftsUrl . '"', false);
    }

    public function test_the_permissions_screen_always_shows_the_drafts_column_and_switch(): void
    {
        $admin = $this->makeUser('7777', 'اسراء');
        $user = $this->makeUser('1111', 'رانيا جلال');

        $response = $this->actingAs($admin)
            ->get(route('permissions.index'))
            ->assertOk()
            ->assertSee('>المسودات</th>', false);

        $html = $response->getContent();

        // The column and its switch are rendered from the fixed module list,
        // so they stay visible whether the permission is on or off.
        $this->assertMatchesRegularExpression(
            '/name="perms\[' . $user->id . '\]\[drafts\][\s\S]{0,120}?value="1"(\s*)>/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="perms\[' . $user->id . '\]\[drafts\][\s\S]{0,120}?checked/',
            $html
        );
    }

    public function test_turning_the_drafts_permission_off_blocks_direct_access(): void
    {
        $admin = $this->makeUser('7777', 'اسراء');
        $user = $this->makeUser('1111', 'رانيا جلال');

        UserPermission::create([
            'user_id' => $user->id,
            'module' => 'drafts',
            'is_granted' => true,
        ]);

        $this->actingAs($user)
            ->get(route('drafts.index'))
            ->assertOk();

        // The admin unchecks drafts but keeps another permission for the user.
        $this->actingAs($admin)
            ->post(route('permissions.update'), [
                'perms' => [
                    $user->id => ['dashboard' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('user_permissions', [
            'user_id' => $user->id,
            'module' => 'drafts',
        ]);

        $this->actingAs($user)
            ->get(route('drafts.index'))
            ->assertRedirect(route('unauthorized'));

        $this->actingAs($user)
            ->getJson(route('drafts.index'))
            ->assertStatus(403);
    }

    public function test_a_user_without_the_drafts_permission_cannot_open_the_page_directly(): void
    {
        $user = $this->makeUser('1111', 'رانيا جلال');

        UserPermission::create([
            'user_id' => $user->id,
            'module' => 'dashboard',
            'is_granted' => true,
        ]);

        $this->actingAs($user)
            ->get(route('drafts.index'))
            ->assertRedirect(route('unauthorized'));

        $this->actingAs($user)
            ->getJson(route('drafts.index'))
            ->assertStatus(403);
    }

    // ================= invoice finalize =================

    public function test_admin_finalizes_an_invoice_draft_once_and_writes_it_under_its_owner(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeUser();
        $medicine = $this->makeMedicine();
        $this->makeStock($owner, $medicine, 10, '2026-11-01');

        $draft = $this->makeDraft($owner, 'invoice', [
            'referral_number' => 'TR-100',
            'patient_name' => 'مريض تجريبي',
            'invoice_date' => '2026-11-10',
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 3],
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('drafts.finalize', $draft))
            ->assertRedirect(route('drafts.index'));

        $this->assertDatabaseMissing('drafts', ['id' => $draft->id]);

        $invoice = Invoice::where('referral_number', 'TR-100')->first();

        $this->assertNotNull($invoice);
        $this->assertSame($owner->id, $invoice->user_id);
        $this->assertSame($draft->uuid, $invoice->client_uuid);
        $this->assertSame('مريض تجريبي', $invoice->patient_name);
        $this->assertCount(1, $invoice->items);
        $this->assertSame(3, $invoice->items->first()->quantity);

        $this->assertSame(3, (int) Stock::where('user_id', $owner->id)->first()->dispensed);

        // The row is gone, so repeating the request must not write anything twice.
        $this->actingAs($admin)
            ->post(route('drafts.finalize', $draft))
            ->assertNotFound();

        $this->assertSame(1, Invoice::count());
        $this->assertSame(3, (int) Stock::where('user_id', $owner->id)->first()->dispensed);
    }

    public function test_invoice_service_is_idempotent_for_the_same_client_uuid(): void
    {
        $owner = $this->makeUser();
        $medicine = $this->makeMedicine();
        $this->makeStock($owner, $medicine, 10, '2026-11-01');

        $service = app(InvoiceService::class);
        $uuid = (string) Str::uuid();
        $payload = [
            'referral_number' => 'TR-200',
            'patient_name' => 'مريض',
            'invoice_date' => '2026-11-10',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 2]],
        ];

        $first = $service->create($owner, $payload, $uuid);
        $second = $service->create($owner, $payload, $uuid);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Invoice::count());
        $this->assertSame(2, (int) $first->items()->sum('quantity'));
    }

    public function test_admin_finalizes_all_pending_drafts_for_one_user(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeUser();
        $medicine = $this->makeMedicine();
        $this->makeStock($owner, $medicine, 20, '2026-11-01');

        $invoiceDraft = $this->makeDraft($owner, 'invoice', [
            'referral_number' => 'TR-300',
            'patient_name' => 'مريض جماعي',
            'invoice_date' => '2026-11-10',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 2]],
        ]);

        $dispensedDraft = $this->makeDraft($owner, 'dispensed_medicine', [
            'medicines' => [
                [
                    'medicine_id' => $medicine->id,
                    'quantity' => 4,
                    'dispense_date' => '2026-11-11',
                    'referral_number' => 'R-300',
                ],
            ],
        ]);

        $unitDraft = $this->makeDraft($owner, 'unit', ['name' => 'وحدة اختبار ' . Str::random(6)]);

        $this->actingAs($admin)
            ->post(route('drafts.finalize-user', $owner))
            ->assertRedirect(route('drafts.index'));

        $this->assertDatabaseMissing('drafts', ['id' => $invoiceDraft->id]);
        $this->assertDatabaseMissing('drafts', ['id' => $dispensedDraft->id]);
        $this->assertDatabaseMissing('drafts', ['id' => $unitDraft->id]);

        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, DispensedMedicine::count());
        $this->assertSame(6, (int) Stock::where('user_id', $owner->id)->first()->dispensed);
    }

    public function test_admin_can_discard_a_draft_without_writing_it(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeUser();
        $draft = $this->makeDraft($owner, 'unit', ['name' => 'وحدة محذوفة']);

        $this->actingAs($admin)
            ->delete(route('drafts.discard', $draft))
            ->assertRedirect(route('drafts.index'));

        $this->assertDatabaseMissing('drafts', ['id' => $draft->id]);
        $this->assertDatabaseMissing('unit_types', ['name' => 'وحدة محذوفة']);
    }

    public function test_admin_can_discard_all_pending_drafts_for_one_user(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeUser('5555', 'رانيا رضا');
        $other = $this->makeUser('2222', 'هدير');

        $first = $this->makeDraft($owner, 'unit', ['name' => 'وحدة محذوفة 1']);
        $second = $this->makeDraft($owner, 'unit', ['name' => 'وحدة محذوفة 2']);
        $kept = $this->makeDraft($other, 'unit', ['name' => 'وحدة أخرى']);

        $this->actingAs($admin)
            ->post(route('drafts.discard-user', $owner))
            ->assertRedirect(route('drafts.index'));

        $this->assertDatabaseMissing('drafts', ['id' => $first->id]);
        $this->assertDatabaseMissing('drafts', ['id' => $second->id]);
        $this->assertDatabaseHas('drafts', ['id' => $kept->id]);
        $this->assertDatabaseMissing('unit_types', ['name' => 'وحدة محذوفة 1']);

        // Closing the UUIDs first prevents a delayed browser sync from
        // re-creating the deleted drafts.
        $this->assertTrue(DraftClosure::isClosed($owner->id, $first->uuid));
        $this->assertTrue(DraftClosure::isClosed($owner->id, $second->uuid));

        $this->actingAs($owner)
            ->postJson(route('drafts.sync'), [
                'uuid' => $first->uuid,
                'type' => 'unit',
                'revision' => 2,
                'payload' => ['name' => 'مسودة رجعت متأخرة'],
            ])
            ->assertOk()
            ->assertJson(['status' => Draft::STATUS_FINALIZED]);

        $this->assertDatabaseMissing('drafts', ['uuid' => $first->uuid]);
    }

    public function test_the_drafts_page_renders_delete_buttons_and_javascript_pagination(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeUser('5555', 'رانيا رضا');

        for ($i = 1; $i <= 7; $i++) {
            $this->makeDraft($owner, 'unit', ['name' => 'وحدة اختبار ' . $i]);
        }

        $response = $this->actingAs($admin)
            ->get(route('drafts.index'))
            ->assertOk();

        $response->assertSee('data-draft-list data-page-size="5"', false)
            ->assertSee('data-draft-pagination', false)
            ->assertSee('حذف كل مسودات', false)
            ->assertSee('data-loading-text="جاري الحذف..."', false)
            ->assertSee("onsubmit=\"return confirm('تجاهل هذه المسودة وحذفها نهائياً؟')\"", false);

        $this->assertSame(
            7,
            substr_count($response->getContent(), 'bg-slate-50/60" data-draft-item>')
        );
    }

    // ================= middleware finalization =================

    public function test_a_successful_real_save_deletes_the_matching_draft(): void
    {
        // 7777 bypasses per-module permission checks.
        $user = $this->makeUser('7777', 'اسراء');
        $medicine = $this->makeMedicine();
        $this->makeStock($user, $medicine, 10, '2026-11-01');

        $draft = $this->makeDraft($user, 'dispensed_medicine', [
            'medicines' => [
                [
                    'medicine_id' => $medicine->id,
                    'quantity' => 2,
                    'dispense_date' => '2026-11-10',
                    'referral_number' => 'R-400',
                ],
            ],
        ]);

        $response = $this->actingAs($user)->postJson(route('dispensed-medicines.store'), [
            'draft_uuid' => $draft->uuid,
            'medicines' => [
                [
                    'medicine_id' => $medicine->id,
                    'quantity' => 2,
                    'dispense_date' => '2026-11-10',
                    'referral_number' => 'R-400',
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('drafts', ['id' => $draft->id]);
        $this->assertSame(1, DispensedMedicine::count());
        $this->assertSame(2, (int) Stock::where('user_id', $user->id)->first()->dispensed);
    }

    public function test_a_successful_invoice_save_deletes_the_matching_draft(): void
    {
        $user = $this->makeUser('7777', 'اسراء');
        $medicine = $this->makeMedicine();
        $this->makeStock($user, $medicine, 10, '2026-11-01');

        $payload = [
            'referral_number' => 'INV-900',
            'patient_name' => 'مريض فاتورة',
            'invoice_date' => '2026-11-10',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 2]],
        ];

        $draft = $this->makeDraft($user, 'invoice', $payload);

        $this->actingAs($user)
            ->postJson(route('invoices.store'), ['draft_uuid' => $draft->uuid, ...$payload])
            ->assertOk();

        $this->assertDatabaseMissing('drafts', ['id' => $draft->id]);
        $this->assertSame(1, Invoice::count());
        $this->assertSame($draft->uuid, Invoice::first()->client_uuid);
    }

    public function test_a_delayed_sync_cannot_recreate_a_draft_after_the_real_save(): void
    {
        $user = $this->makeUser('7777', 'اسراء');
        $medicine = $this->makeMedicine();
        $this->makeStock($user, $medicine, 10, '2026-11-01');

        $payload = [
            'medicines' => [
                [
                    'medicine_id' => $medicine->id,
                    'quantity' => 2,
                    'dispense_date' => '2026-11-10',
                    'referral_number' => 'R-600',
                ],
            ],
        ];

        $draft = $this->makeDraft($user, 'dispensed_medicine', $payload);

        $this->actingAs($user)->postJson(route('dispensed-medicines.store'), [
            'draft_uuid' => $draft->uuid,
            ...$payload,
        ])->assertOk();

        $this->assertDatabaseMissing('drafts', ['id' => $draft->id]);

        // A request that was already in flight arrives after the save.
        $this->actingAs($user)->postJson(route('drafts.sync'), [
            'uuid' => $draft->uuid,
            'type' => 'dispensed_medicine',
            'revision' => 99,
            'payload' => $payload,
        ])->assertOk()->assertJson(['status' => Draft::STATUS_FINALIZED]);

        $this->assertDatabaseMissing('drafts', ['uuid' => $draft->uuid]);
        $this->assertSame(1, DispensedMedicine::count());
    }

    public function test_mine_reports_saved_drafts_as_closed_and_cleans_up_a_recreated_row(): void
    {
        $user = $this->makeUser();
        $uuid = (string) Str::uuid();

        DraftClosure::close($user->id, $uuid);

        // Simulate a sync that was already in flight and re-inserted the row.
        Draft::create([
            'uuid' => $uuid,
            'user_id' => $user->id,
            'type' => 'unit',
            'title' => 'وحدة',
            'payload' => ['name' => 'وحدة'],
            'revision' => 1,
            'status' => Draft::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)->getJson(route('drafts.mine'));

        $response->assertOk();

        $this->assertCount(0, $response->json('pending'));
        $this->assertContains($uuid, $response->json('closed'));
        $this->assertDatabaseMissing('drafts', ['uuid' => $uuid]);
    }

    public function test_sync_deletes_a_draft_when_its_invoice_was_already_committed(): void
    {
        $user = $this->makeUser();

        $draft = $this->makeDraft($user, 'invoice', [
            'referral_number' => '777',
            'patient_name' => 'مريض تجريبي',
            'invoice_date' => '2026-11-14',
            'items' => [['medicine_id' => 1, 'quantity' => 1]],
        ]);

        // Simulate a save whose response never reached the browser.
        DB::table('invoices')->insert([
            'client_uuid' => $draft->uuid,
            'user_id' => $user->id,
            'patient_name' => 'مريض تجريبي',
            'referral_number' => '777',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->postJson(route('drafts.sync'), [
            'uuid' => $draft->uuid,
            'type' => 'invoice',
            'revision' => 5,
            'payload' => [
                'referral_number' => '777',
                'patient_name' => 'مريض تجريبي',
                'invoice_date' => '2026-11-14',
                'items' => [['medicine_id' => 1, 'quantity' => 1]],
            ],
        ])->assertOk()->assertJson(['status' => Draft::STATUS_FINALIZED]);

        $this->assertDatabaseMissing('drafts', ['id' => $draft->id]);
        $this->assertTrue(DraftClosure::isClosed($user->id, $draft->uuid));
    }

    public function test_mine_heals_an_invoice_draft_whose_invoice_exists(): void
    {
        $user = $this->makeUser();
        $draft = $this->makeDraft($user, 'invoice', [
            'referral_number' => '888',
            'patient_name' => 'مريض آخر',
            'invoice_date' => '2026-11-14',
            'items' => [['medicine_id' => 1, 'quantity' => 2]],
        ]);

        DB::table('invoices')->insert([
            'client_uuid' => $draft->uuid,
            'user_id' => $user->id,
            'patient_name' => 'مريض آخر',
            'referral_number' => '888',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson(route('drafts.mine'));

        $response->assertOk();
        $this->assertCount(0, $response->json('pending'));
        $this->assertContains($draft->uuid, $response->json('closed'));
        $this->assertDatabaseMissing('drafts', ['id' => $draft->id]);
    }

    public function test_a_failed_real_save_leaves_the_draft_pending(): void
    {
        $user = $this->makeUser('7777', 'اسراء');
        $medicine = $this->makeMedicine();
        $this->makeStock($user, $medicine, 1, '2026-11-01');

        $draft = $this->makeDraft($user, 'dispensed_medicine', [
            'medicines' => [
                [
                    'medicine_id' => $medicine->id,
                    'quantity' => 2,
                    'dispense_date' => '2026-11-10',
                    'referral_number' => 'R-500',
                ],
            ],
        ]);

        $response = $this->actingAs($user)->postJson(route('dispensed-medicines.store'), [
            'draft_uuid' => $draft->uuid,
            'medicines' => [
                [
                    'medicine_id' => $medicine->id,
                    'quantity' => 2,
                    'dispense_date' => '2026-11-10',
                    'referral_number' => 'R-500',
                ],
            ],
        ]);

        $response->assertStatus(422);

        $this->assertSame(Draft::STATUS_PENDING, $draft->fresh()->status);
        $this->assertSame(0, DispensedMedicine::count());
        $this->assertSame(0, (int) Stock::where('user_id', $user->id)->first()->dispensed);
    }

    public function test_a_validation_failure_leaves_the_draft_pending(): void
    {
        $user = $this->makeUser('7777', 'اسراء');

        $draft = $this->makeDraft($user, 'dispensed_medicine', [
            'medicines' => [['medicine_id' => 1, 'quantity' => 1, 'dispense_date' => '2026-11-10']],
        ]);

        $response = $this->actingAs($user)->postJson(route('dispensed-medicines.store'), [
            'draft_uuid' => $draft->uuid,
            'medicines' => [
                ['medicine_id' => 999999, 'quantity' => 0, 'dispense_date' => ''],
            ],
        ]);

        $response->assertStatus(422);

        $this->assertSame(Draft::STATUS_PENDING, $draft->fresh()->status);
    }

    public function test_a_redirect_to_the_unauthorized_page_does_not_delete_the_draft(): void
    {
        $user = $this->makeUser('1111', 'رانيا جلال');
        $draft = $this->makeDraft($user, 'unit', ['name' => 'وحدة باقية']);

        $this->actingAs($user)
            ->post(route('drafts.settings.purge'), ['draft_uuid' => $draft->uuid])
            ->assertRedirect(route('unauthorized'));

        $this->assertDatabaseHas('drafts', [
            'id' => $draft->id,
            'status' => Draft::STATUS_PENDING,
        ]);
    }
}
