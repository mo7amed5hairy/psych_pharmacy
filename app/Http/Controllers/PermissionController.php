<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class PermissionController extends Controller
{
    public function index()
    {
        $adminCodes = ['7777', '1010'];
        if (!in_array(auth()->user()->employee_code, $adminCodes)) {
            return redirect()->route('unauthorized');
        }

        // Failsafe: Create table if missing
        if (!Schema::hasTable('user_permissions')) {
            $this->initTable();
        }

        $users = User::with('permissions')->get();
        $modules = [
            'dashboard' => 'الرئيسية',
            'users' => 'إدارة المستخدمين',
            'medicines' => 'قاموس الأدوية',
            'units' => 'أنواع الوحدات',
            'stock' => 'أرصدة الأدوية(المخزن)',
            'dispensed_medicines' => 'الأدوية المنصرفة',
            'invoice_create' => 'إضافة فواتير صرف',
            'invoice_list' => 'قائمة الفواتير',
            'report_monthly' => 'كشف المنصرف',
            'report_inventory' => 'الجرد',
        ];

        return view('permissions.index', compact('users', 'modules'));
    }

    public function update(Request $request)
    {
        $adminCodes = ['7777', '1010'];
        if (!in_array(auth()->user()->employee_code, $adminCodes)) {
            return abort(403);
        }

        $data = $request->input('perms', []); // user_id => [module1, module2]

        // Clear all permissions and rebuild
        // Warning: This is a simple implementation. In production, use sync or individual updates.
        UserPermission::truncate();

        foreach ($data as $userId => $modules) {
            foreach ($modules as $module => $on) {
                UserPermission::create([
                    'user_id' => $userId,
                    'module' => $module,
                    'is_granted' => true
                ]);
            }
        }

        return back()->with('success', 'تم تحديث الصلاحيات بنجاح');
    }

    // Failsafe to create the table if migrations failed
    public function initTable()
    {
        $adminCodes = ['7777', '1010'];
        if (!auth()->check() || !in_array(auth()->user()->employee_code, $adminCodes)) {
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
