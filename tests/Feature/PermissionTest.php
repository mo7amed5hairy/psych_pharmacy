<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $code, string $name = 'مستخدم اختبار'): User
    {
        return User::create([
            'name' => $name,
            'employee_code' => $code,
            'password_hash' => 'secret',
        ]);
    }

    public function test_admin_codes_can_open_permissions_page_and_see_its_sidebar_link(): void
    {
        foreach (['1001' => 'محمد', '7777' => 'اسراء', '1010' => 'ريم محسن'] as $code => $name) {
            $admin = $this->makeUser($code, $name);

            $this->actingAs($admin)
                ->get(route('permissions.index'))
                ->assertOk()
                ->assertSee('<span>صلاحيات المستخدمين</span>', false);
        }
    }

    public function test_regular_user_cannot_open_permissions_page_or_see_its_link(): void
    {
        $user = $this->makeUser('1111', 'رانيا جلال');

        UserPermission::create([
            'user_id' => $user->id,
            'module' => 'dashboard',
            'is_granted' => true,
        ]);

        $this->actingAs($user)
            ->get(route('permissions.index'))
            ->assertRedirect(route('unauthorized'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('<span>صلاحيات المستخدمين</span>', false);
    }

    public function test_saving_the_permissions_form_adds_and_removes_sidebar_links_and_access(): void
    {
        $admin = $this->makeUser('1001', 'محمد');
        $user = $this->makeUser('1111', 'رانيا جلال');

        $this->actingAs($admin)
            ->post(route('permissions.update'), [
                'perms' => [
                    $user->id => ['users' => 1, 'medicines' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $user->id,
            'module' => 'users',
            'is_granted' => true,
        ]);
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $user->id,
            'module' => 'medicines',
            'is_granted' => true,
        ]);

        $this->actingAs($user)
            ->get(route('unauthorized'))
            ->assertSee('<span>إدارة المستخدمين</span>', false)
            ->assertSee('<span>قاموس الأدوية</span>', false);

        $this->actingAs($user)->get(route('users.index'))->assertOk();
        $this->actingAs($user)->get(route('medicines.index'))->assertOk();

        // Uncheck medicines and save again: the link and the direct URL stop working.
        $this->actingAs($admin)
            ->post(route('permissions.update'), [
                'perms' => [
                    $user->id => ['users' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('user_permissions', [
            'user_id' => $user->id,
            'module' => 'medicines',
        ]);

        $this->actingAs($user)
            ->get(route('unauthorized'))
            ->assertSee('<span>إدارة المستخدمين</span>', false)
            ->assertDontSee('<span>قاموس الأدوية</span>', false);

        $this->actingAs($user)
            ->get(route('medicines.index'))
            ->assertRedirect(route('unauthorized'));
    }

    public function test_each_permission_controls_its_own_sidebar_link_and_direct_url(): void
    {
        $modules = [
            ['dashboard', 'الرئيسية', route('dashboard')],
            ['users', 'إدارة المستخدمين', route('users.index')],
            ['medicines', 'قاموس الأدوية', route('medicines.index')],
            ['units', 'أنواع الوحدات', route('units.index')],
            ['stock', 'أرصدة الأدوية', route('stock.index')],
            ['stock_transfer', 'تحويل الأرصدة', route('stock-transfer.index')],
            ['dispensed_medicines', 'الأدوية المنصرفة', route('dispensed-medicines.index')],
            ['invoice_create', 'إضافة فاتورة صرف', route('invoices.create')],
            ['invoice_list', 'قائمة الفواتير', route('invoices.index')],
            ['report_monthly', 'كشف المنصرف', route('reports.monthly')],
            ['report_inventory', 'الجرد', route('reports.inventory')],
        ];

        foreach ($modules as $index => [$module, $label, $url]) {
            $user = $this->makeUser('T' . $index, 'مستخدم ' . $module);
            $link = '<span>' . $label . '</span>';

            // Off: no sidebar link and no direct access.
            $this->actingAs($user)
                ->get(route('unauthorized'))
                ->assertDontSee($link, false);

            $this->actingAs($user)
                ->get($url)
                ->assertRedirect(route('unauthorized'));

            // On: both the sidebar link and the direct URL work.
            UserPermission::create([
                'user_id' => $user->id,
                'module' => $module,
                'is_granted' => true,
            ]);

            $this->actingAs($user)
                ->get(route('unauthorized'))
                ->assertSee($link, false);

            $this->actingAs($user)
                ->get($url)
                ->assertOk();

            // Off again: the link and access are removed.
            UserPermission::where('user_id', $user->id)->where('module', $module)->delete();

            $this->actingAs($user)
                ->get(route('unauthorized'))
                ->assertDontSee($link, false);

            $this->actingAs($user)
                ->get($url)
                ->assertRedirect(route('unauthorized'));
        }
    }

    public function test_admin_can_switch_off_a_permission_for_esraa_and_reem(): void
    {
        $admin = $this->makeUser('1001', 'محمد');
        $targets = [
            $this->makeUser('7777', 'اسراء'),
            $this->makeUser('1010', 'ريم محسن'),
        ];

        $permissions = [];

        foreach ($targets as $target) {
            $permissions[$target->id] = ['dashboard' => 1]; // drafts intentionally unchecked
        }

        $this->actingAs($admin)
            ->post(route('permissions.update'), ['perms' => $permissions])
            ->assertRedirect();

        foreach ($targets as $target) {
            $this->assertDatabaseHas('user_permissions', [
                'user_id' => $target->id,
                'module' => 'drafts',
                'is_granted' => false,
            ]);

            $this->assertFalse($target->fresh()->hasPermission('drafts'));

            $this->actingAs($target)
                ->get(route('drafts.index'))
                ->assertRedirect(route('unauthorized'));

            $this->actingAs($target)
                ->get(route('unauthorized'))
                ->assertDontSee('<span>المسودات</span>', false);
        }

        // Their other permissions stay on until explicitly switched off.
        $this->assertTrue($targets[0]->fresh()->hasPermission('dashboard'));
        $this->assertTrue($targets[1]->fresh()->hasPermission('dashboard'));
    }

    public function test_inventory_permission_is_a_visible_table_column(): void
    {
        $admin = $this->makeUser('1001', 'محمد');

        $response = $this->actingAs($admin)
            ->get(route('permissions.index'))
            ->assertOk();

        $response->assertSee('>الجرد</th>', false)
            ->assertSee('id="permissionsTable"', false)
            ->assertSee('responsive: false', false)
            ->assertDontSee('class="data data-table cell-border"', false);
    }

    public function test_unauthorized_page_names_all_three_admin_accounts(): void
    {
        $user = $this->makeUser('1111', 'رانيا جلال');

        $this->actingAs($user)
            ->get(route('unauthorized'))
            ->assertOk()
            ->assertSee('محمد / إسراء / ريم محسن');
    }
}
