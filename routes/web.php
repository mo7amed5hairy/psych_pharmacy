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

    // Users
    Route::resource('users', UserController::class);


    // Dashboard
    Route::get('/index', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Medicines
    Route::get('/medicines/search', [MedicineController::class, 'search'])->name('medicines.search');
    Route::resource('medicines', MedicineController::class);
    Route::post('/medicines/{medicine}/update', [MedicineController::class, 'update'])->name('medicines.update.post');
    Route::post('/medicines/{medicine}/delete', [MedicineController::class, 'destroy'])->name('medicines.destroy.post');

    // Unit Types
    Route::resource('units', UnitTypeController::class)->except(['show']);
    Route::post('/units/{unitType}/update', [UnitTypeController::class, 'update'])->name('units.update.post');
    Route::post('/units/{unitType}/delete', [UnitTypeController::class, 'destroy'])->name('units.destroy.post');

    // Stock
    Route::resource('stock', StockController::class)->except(['show', 'destroy']);

    // Referral Number Check
    Route::get('/check-referral-number', function (\Illuminate\Http\Request $r) {
        $number = $r->get('number');
        $userId = auth()->id();
        $exists = \App\Models\Invoice::where('referral_number', $number)->where('user_id', $userId)->exists()
            || \App\Models\DispensedMedicine::where('referral_number', $number)->where('user_id', $userId)->exists();
        if ($exists) {
            $maxInv = (int) \App\Models\Invoice::where('user_id', $userId)->selectRaw('MAX(CAST(referral_number AS UNSIGNED)) as m')->value('m');
            $maxDM = (int) \App\Models\DispensedMedicine::where('user_id', $userId)->selectRaw('MAX(CAST(referral_number AS UNSIGNED)) as m')->value('m');
            return response()->json(['exists' => true, 'next_available' => max($maxInv, $maxDM) + 1]);
        }
        return response()->json(['exists' => false]);
    })->name('check-referral-number');

    // Dispensed Medicines
    Route::resource('dispensed-medicines', DispensedMedicineController::class)->except(['show', 'edit', 'update']);
    Route::post('/dispensed-medicines/{dispensed_medicine}/update', [DispensedMedicineController::class, 'update'])->name('dispensed-medicines.update');

    // Invoices
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::resource('invoices', InvoiceController::class);

    // Reports
    Route::get('/reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
    Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');

    // Notifications
    Route::post('/notifications/mark-all-read', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.markAllRead');
    Route::post('/notifications/{id}/mark-read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.markRead');
});

