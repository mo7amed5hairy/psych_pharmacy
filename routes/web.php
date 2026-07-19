<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\UnitTypeController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DispensedMedicineController;
use App\Http\Controllers\UserController;

// Public routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Unauthorized Access Page
    Route::get('/unauthorized', function () {
        return view('errors.unauthorized');
    })->name('unauthorized');

    // Dashboard
    Route::get('/index', [DashboardController::class, 'index'])->name('dashboard')->middleware('permission:dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard');

    // Medicines Search (Public for all pharmacists)
    Route::get('/medicines/search', [MedicineController::class, 'search'])->name('medicines.search');

    // User Permissions Management (Esraa and Reem Only)
    Route::get('/permissions', [\App\Http\Controllers\PermissionController::class, 'index'])->name('permissions.index');
    Route::post('/permissions', [\App\Http\Controllers\PermissionController::class, 'update'])->name('permissions.update');
    Route::get('/permissions/init', [\App\Http\Controllers\PermissionController::class, 'initTable']);

    // Users
    Route::resource('users', UserController::class)->middleware('permission:users');

    // Medicines Management
    Route::resource('medicines', MedicineController::class)->except(['search'])->middleware('permission:medicines');
    Route::post('/medicines/{medicine}/update', [MedicineController::class, 'update'])->name('medicines.update.post')->middleware('permission:medicines');
    Route::post('/medicines/{medicine}/delete', [MedicineController::class, 'destroy'])->name('medicines.destroy.post')->middleware('permission:medicines');

    // Unit Types
    Route::resource('units', UnitTypeController::class)->except(['show'])->middleware('permission:units');
    Route::post('/units/{unitType}/update', [UnitTypeController::class, 'update'])->name('units.update.post')->middleware('permission:units');
    Route::post('/units/{unitType}/delete', [UnitTypeController::class, 'destroy'])->name('units.destroy.post')->middleware('permission:units');

    // Stock
    Route::resource('stock', StockController::class)->except(['show', 'destroy'])->middleware('permission:stock');

    // Referral Number Check
    Route::get('/check-referral-number', function (\Illuminate\Http\Request $r) {
        return response()->json(['exists' => false]);
    })->name('check-referral-number');

    // Dispensed Medicines
    Route::get('/dispensed-medicines', [DispensedMedicineController::class, 'index'])->name('dispensed-medicines.index')->middleware('permission:dispensed_medicines');
    Route::get('/dispensed-medicines/create', [DispensedMedicineController::class, 'create'])->name('dispensed-medicines.create')->middleware('permission:dispensed_medicines');
    Route::post('/dispensed-medicines', [DispensedMedicineController::class, 'store'])->name('dispensed-medicines.store')->middleware('permission:dispensed_medicines');
    Route::post('/dispensed-medicines/{dispensed_medicine}/update', [DispensedMedicineController::class, 'update'])->name('dispensed-medicines.update')->middleware('permission:dispensed_medicines');
    Route::delete('/dispensed-medicines/{dispensed_medicine}', [DispensedMedicineController::class, 'destroy'])->name('dispensed-medicines.destroy')->middleware('permission:dispensed_medicines');

    // Invoices
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print')->middleware('permission:invoice_list');
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index')->middleware('permission:invoice_list');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create')->middleware('permission:invoice_create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store')->middleware('permission:invoice_create');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show')->middleware('permission:invoice_list');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy')->middleware('permission:invoice_list');
    Route::post('/invoices/{invoice}/update', [InvoiceController::class, 'update'])->name('invoices.update')->middleware('permission:invoice_list');

    // Reports
    Route::get('/reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly')->middleware('permission:report_monthly');
    Route::get('/reports/daily', [ReportController::class, 'daily'])->name('reports.daily')->middleware('permission:report_monthly');
    Route::get('/reports/clinic', [ReportController::class, 'clinic'])->name('reports.clinic')->middleware('permission:report_monthly');
    Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory')->middleware('permission:report_inventory');

    // Notifications
    Route::post('/notifications/mark-all-read', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.markAllRead');
    Route::post('/notifications/{id}/mark-read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.markRead');
});

