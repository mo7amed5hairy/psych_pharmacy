<?php

namespace Tests\Feature;

use App\Models\DispensedMedicine;
use App\Models\Draft;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Stock;
use App\Models\UnitType;
use App\Models\User;
use App\Services\InvoiceService;
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
            ->assertSee($owner->name);
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

        $draft->refresh();

        $this->assertSame(Draft::STATUS_FINALIZED, $draft->status);
        $this->assertSame($admin->id, $draft->finalized_by);

        $invoice = Invoice::where('referral_number', 'TR-100')->first();

        $this->assertNotNull($invoice);
        $this->assertSame($owner->id, $invoice->user_id);
        $this->assertSame($draft->uuid, $invoice->client_uuid);
        $this->assertSame('مريض تجريبي', $invoice->patient_name);
        $this->assertCount(1, $invoice->items);
        $this->assertSame(3, $invoice->items->first()->quantity);

        $this->assertSame(3, (int) Stock::where('user_id', $owner->id)->first()->dispensed);

        // Running it again must not create a second invoice or dispense again.
        $this->actingAs($admin)
            ->post(route('drafts.finalize', $draft))
            ->assertRedirect(route('drafts.index'));

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

        $this->assertSame(Draft::STATUS_FINALIZED, $invoiceDraft->fresh()->status);
        $this->assertSame(Draft::STATUS_FINALIZED, $dispensedDraft->fresh()->status);
        $this->assertSame(Draft::STATUS_FINALIZED, $unitDraft->fresh()->status);

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

        $this->assertSame(Draft::STATUS_DISCARDED, $draft->fresh()->status);
        $this->assertDatabaseMissing('unit_types', ['name' => 'وحدة محذوفة']);
    }

    // ================= middleware finalization =================

    public function test_a_successful_real_save_finalizes_the_matching_draft(): void
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

        $this->assertSame(Draft::STATUS_FINALIZED, $draft->fresh()->status);
        $this->assertSame(1, DispensedMedicine::count());
        $this->assertSame(2, (int) Stock::where('user_id', $user->id)->first()->dispensed);
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
}
