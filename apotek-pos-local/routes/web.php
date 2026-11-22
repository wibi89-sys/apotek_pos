<?php

declare(strict_types=1);

use App\Http\Controllers\Inventory\ProductImportExportController;
use App\Livewire\Inventory\ProductForm;
use App\Livewire\Inventory\ProductList;
use App\Livewire\License\ActivateLicense;
use App\Livewire\License\LicenseInfo;
use App\Livewire\Pos\PosScreen;
use App\Livewire\Pos\ShiftManager;
use App\Livewire\Purchases\PurchaseForm;
use App\Livewire\Reports\ProfitAnalytics;
use App\Livewire\Reports\SipnapExport;
use App\Livewire\Settings\DatabaseManager;
use App\Livewire\Settings\SyncMonitor;
use App\Livewire\Users\FingerprintEnrollment;
use App\Models\DbBackupLog;
use App\Services\Database\DatabaseBackupService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

// Public License Management Routes
Route::get('/license/activate', ActivateLicense::class)->name('license.activate');
Route::get('/license/info', LicenseInfo::class)->name('license.info');

// Emergency DB backup download for Owner (Accessible even if license is expired)
Route::get('/db/emergency-download', function () {
    $latest = DbBackupLog::where('status', 'completed')->orderBy('created_at', 'desc')->first();
    if (! $latest || ! File::exists($latest->storage_path)) {
        // Automatically generate a fresh emergency backup on-the-fly
        $service = app(DatabaseBackupService::class);
        $latest = $service->createBackup('emergency_owner');
    }

    return response()->download($latest->storage_path, $latest->filename);
})->name('db.emergency-download');

// Root Route
Route::get('/', function () {
    return redirect()->route('pos.index');
});

// Protected Core POS & Inventory Routes
Route::middleware(['license.valid'])->group(function () {
    Route::get('/products', ProductList::class)->name('products.index');
    Route::get('/products/create', ProductForm::class)->name('products.create');
    Route::get('/inventory/export', [ProductImportExportController::class, 'export'])->name('products.export');
    Route::get('/inventory/export-csv', [ProductImportExportController::class, 'exportCsv'])->name('products.export.csv');
    Route::get('/inventory/template', [ProductImportExportController::class, 'template'])->name('products.template');
    Route::get('/inventory/template-csv', [ProductImportExportController::class, 'templateCsv'])->name('products.template.csv');
    Route::post('/inventory/import', [ProductImportExportController::class, 'import'])->name('products.import');
    Route::get('/purchases/create', PurchaseForm::class)->name('purchases.create');
    
    // Database Management & Maintenance (Owner/Supervisor)
    Route::get('/settings/database', DatabaseManager::class)->name('settings.database');
    Route::get('/settings/database/download/{id}', function (string $id) {
        $log = DbBackupLog::findOrFail($id);
        if (! File::exists($log->storage_path)) {
            abort(404, 'File arsip backup tidak ditemukan.');
        }
        return response()->download($log->storage_path, $log->filename);
    })->name('settings.database.download');

    // Biometric Fingerprint (PRO Feature)
    Route::get('/settings/fingerprint', FingerprintEnrollment::class)
        ->middleware('license.feature:fingerprint_auth')
        ->name('settings.fingerprint');

    // Cloud Sync Monitor
    Route::get('/settings/sync', SyncMonitor::class)->name('settings.sync');

    // PRO Analytics & Regulatory Reporting
    Route::get('/reports/profit', ProfitAnalytics::class)
        ->middleware('license.feature:profit_analytics')
        ->name('reports.profit');

    Route::get('/reports/sipnap', SipnapExport::class)
        ->middleware('license.feature:sipnap_export')
        ->name('reports.sipnap');
});

Route::middleware(['license.valid', 'terminal.quota'])->group(function () {
    Route::get('/pos/shift', ShiftManager::class)->name('pos.shift');
    Route::get('/pos', PosScreen::class)->middleware('shift.open')->name('pos.index');
});

// Protected Feature Routes for Entitlement Testing
Route::middleware(['license.valid'])->group(function () {
    Route::get('/test/feature-fingerprint', function () {
        return response()->json(['status' => 'authorized', 'feature' => 'fingerprint_auth']);
    })->middleware('license.feature:fingerprint_auth');

    Route::get('/test/feature-cloud-sync', function () {
        return response()->json(['status' => 'authorized', 'feature' => 'cloud_sync_push']);
    })->middleware('license.feature:cloud_sync_push');
});
