<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class PermissionController extends Controller
{
    /** Accounts allowed to manage user permissions. */
    private const ADMIN_CODES = ['1001', '7777', '1010'];

    /** Accounts that start with every module enabled unless explicitly switched off. */
    private const DEFAULT_ALL_CODES = ['7777', '1010'];

    /** @return array<string, string> module slug => Arabic label */
    public static function modules(): array
    {
        return [
            'drafts' => 'المسودات',
            'dashboard' => 'الرئيسية',
            'users' => 'إدارة المستخدمين',
            'medicines' => 'قاموس الأدوية',
            'units' => 'أنواع الوحدات',
            'stock' => 'أرصدة الأدوية(المخزن)',
            'stock_transfer' => 'تحويل الأرصدة',
            'dispensed_medicines' => 'الأدوية المنصرفة',
            'invoice_create' => 'إضافة فواتير صرف',
            'invoice_list' => 'قائمة الفواتير',
            'report_monthly' => 'كشف المنصرف',
            'report_inventory' => 'الجرد',
        ];
    }

    public function index()
    {
        if (!in_array(auth()->user()->employee_code, self::ADMIN_CODES)) {
            return redirect()->route('unauthorized');
        }

        // Failsafe: Create table if missing
        if (!Schema::hasTable('user_permissions')) {
            $this->initTable();
        }

        $users = User::with('permissions')->get();
        $modules = self::modules();

        return view('permissions.index', compact('users', 'modules'));
    }

    public function update(Request $request)
    {
        if (!in_array(auth()->user()->employee_code, self::ADMIN_CODES)) {
            return abort(403);
        }

        $modules = array_keys(self::modules());
        $data = $request->input('perms', []); // user_id => [module1, module2]

        // Clear all permissions and rebuild from the submitted switches.
        UserPermission::truncate();

        foreach ($data as $userId => $selected) {
            foreach ($selected as $module => $on) {
                if (!in_array($module, $modules, true)) {
                    continue;
                }

                UserPermission::create([
                    'user_id' => $userId,
                    'module' => $module,
                    'is_granted' => true,
                ]);
            }
        }

        // 7777 and 1010 are granted every module by default. To let an admin
        // switch a module off for them, store an explicit "off" row instead of
        // simply omitting it.
        foreach (User::whereIn('employee_code', self::DEFAULT_ALL_CODES)->get() as $user) {
            $selected = array_keys($data[$user->id] ?? []);

            foreach ($modules as $module) {
                if (!in_array($module, $selected, true)) {
                    UserPermission::create([
                        'user_id' => $user->id,
                        'module' => $module,
                        'is_granted' => false,
                    ]);
                }
            }
        }

        return back()->with('success', 'تم تحديث الصلاحيات بنجاح');
    }

    // Failsafe to create the table if migrations failed
    public function initTable()
    {
        if (!auth()->check() || !in_array(auth()->user()->employee_code, self::ADMIN_CODES)) {
            return "Unauthorized";
        }

        if (!Schema::hasTable('user_permissions')) {
            Schema::create('user_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('module');
                $table->boolean('is_granted')->default(true);
                $table->timestamps();
                $table->unique(['user_id', 'module']);
            });
            return "Table created successfully!";
        }

        return "Table already exists.";
    }
}
