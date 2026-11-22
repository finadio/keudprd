<?php

use App\Http\Controllers\Gaji\GajiCalculationController;
use App\Http\Controllers\Gaji\GajiConfigController;
use App\Http\Controllers\Gaji\GajiDashboardController;
use App\Http\Controllers\Gaji\GajiRecipientController;
use App\Http\Controllers\Gaji\GajiReportController;
use Illuminate\Support\Facades\Route;

// Modul Perhitungan Gaji dan Hak Keuangan DPRD & ASN (Khusus Staf Keuangan)
Route::prefix('gaji')->name('gaji.')->group(function () {
    // 1. Dashboard & Kontrol Anggaran
    Route::get('/', [GajiDashboardController::class, 'index'])->name('index');

    // 2. Master Data Penerima (ASN & DPRD)
    Route::prefix('penerima')->name('penerima.')->group(function () {
        Route::get('/', [GajiRecipientController::class, 'index'])->name('index');
        Route::post('/', [GajiRecipientController::class, 'store'])->name('store');
        Route::put('/{recipient}', [GajiRecipientController::class, 'update'])->name('update');
        Route::post('/{recipient}/toggle-active', [GajiRecipientController::class, 'toggleActive'])->name('toggle-active');
        Route::delete('/{recipient}', [GajiRecipientController::class, 'destroy'])->name('destroy');
        Route::post('/impor-excel', [GajiRecipientController::class, 'importExcel'])->name('import');
        Route::get('/download-template', [GajiRecipientController::class, 'downloadTemplate'])->name('template');
    });

    // 3. Simulasi Pembayaran & Mesin Hitung Otomatis
    Route::prefix('simulasi')->name('simulasi.')->group(function () {
        Route::get('/', [GajiCalculationController::class, 'index'])->name('index');
        Route::post('/commit', [GajiCalculationController::class, 'commit'])->name('commit');
    });

    // 4. Laporan, Riwayat Penggajian & Ekspor (Excel & PDF)
    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::get('/', [GajiReportController::class, 'index'])->name('index');
        Route::get('/periode/{period}/export-excel', [GajiReportController::class, 'exportExcel'])->name('export-excel');
        Route::get('/item/{item}/export-slip', [GajiReportController::class, 'exportSlip'])->name('export-slip');
    });

    // 5. Pengaturan Tarif, Regulasi Acuan & Plafon Anggaran
    Route::prefix('pengaturan')->name('pengaturan.')->group(function () {
        Route::get('/', [GajiConfigController::class, 'index'])->name('index');
        Route::put('/allocation/{allocation}', [GajiConfigController::class, 'updateAllocation'])->name('allocation.update');
        Route::put('/config/{config}', [GajiConfigController::class, 'updateConfig'])->name('config.update');
        Route::post('/sync-all', [GajiConfigController::class, 'syncAll'])->name('sync-all');
    });
});
