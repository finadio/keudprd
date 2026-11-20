<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankBookController;
use App\Http\Controllers\BkuController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportTransactionController;
use App\Http\Controllers\PaguController;
use App\Http\Controllers\PptkController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // BKU Per PPTK (20 Sheet Tabs)
    Route::prefix('bku')->name('bku.')->group(function () {
        Route::get('/', [BkuController::class, 'index'])->name('index');
        Route::get('/cetak', [BkuController::class, 'printDocument'])->name('print');
        Route::post('/transaksi', [BkuController::class, 'store'])->name('store');
        Route::put('/transaksi/{transaction}', [BkuController::class, 'update'])->name('update');
        Route::post('/transaksi/{transaction}/inline', [BkuController::class, 'updateInline'])->name('inline');
        Route::delete('/transaksi/{transaction}', [BkuController::class, 'destroy'])->name('destroy');
    });

    // Buku Bank (Bulanan Jan-Okt / 12 Bulan)
    Route::prefix('buku-bank')->name('buku-bank.')->group(function () {
        Route::get('/', [BankBookController::class, 'index'])->name('index');
        Route::get('/cetak', [BankBookController::class, 'printDocument'])->name('print');
        Route::post('/transaksi', [BankBookController::class, 'store'])->name('store');
        Route::put('/transaksi/{transaction}', [BankBookController::class, 'update'])->name('update');
        Route::post('/transaksi/{transaction}/inline', [BkuController::class, 'updateInline'])->name('inline');
        Route::delete('/transaksi/{transaction}', [BankBookController::class, 'destroy'])->name('destroy');
    });

    // Smart Transaction Import
    Route::prefix('impor')->name('impor.')->group(function () {
        Route::get('/', [ImportTransactionController::class, 'index'])->name('index');
        Route::post('/parse', [ImportTransactionController::class, 'parse'])->name('parse');
        Route::post('/commit', [ImportTransactionController::class, 'commit'])->name('commit');
    });

    // Rekonsiliasi
    Route::prefix('rekonsiliasi')->name('rekonsiliasi.')->group(function () {
        Route::get('/', [ReconciliationController::class, 'index'])->name('index');
        Route::post('/assign/{transaction}', [ReconciliationController::class, 'assignPptk'])->name('assign');
    });

    // Laporan & Ekspor
    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/export-bank', [ReportController::class, 'exportBukuBank'])->name('export-bank');
        Route::get('/export-hitungan', [ReportController::class, 'exportHitungan'])->name('export-hitungan');
        Route::get('/backup-zip', [ReportController::class, 'downloadBackupZip'])->name('backup-zip');
        Route::post('/backup-email', [ReportController::class, 'emailBackupZip'])->name('backup-email');
    });

    // Pantauan Realisasi Pagu
    Route::prefix('pagu')->name('pagu.')->group(function () {
        Route::get('/', [PaguController::class, 'index'])->name('index');
        Route::get('/transaksi', [PaguController::class, 'transactions'])->name('transactions');
        Route::post('/transaksi', [PaguController::class, 'storeTransaction'])->name('transactions.store');
        Route::put('/transaksi/{realization}', [PaguController::class, 'updateTransaction'])->name('transactions.update');
        Route::delete('/transaksi/{realization}', [PaguController::class, 'destroyTransaction'])->name('transactions.destroy');
        Route::post('/transaksi/{realization}/toggle-spj', [PaguController::class, 'toggleSpj'])->name('transactions.toggle-spj');
        Route::get('/subkegiatan', [PaguController::class, 'activities'])->name('activities');
        Route::post('/subkegiatan/{activity}/assign-pptk', [PaguController::class, 'assignPptk'])->name('activities.assign-pptk');
        Route::get('/impor', [PaguController::class, 'importView'])->name('import');
        Route::post('/impor', [PaguController::class, 'import'])->name('import.post');
    });

    // Pencarian Global (Global Search)
    Route::get('/api/search', [SearchController::class, 'search'])->name('api.search');

    // Global Transaction Utilities (Audit Trail & Verification)
    Route::post('/transaksi/{transaction}/toggle-verified', [BkuController::class, 'toggleVerified'])->name('transaksi.toggle-verified');
    Route::get('/transaksi/{transaction}/audit-logs', [BkuController::class, 'getAuditLogs'])->name('transaksi.audit-logs');
    Route::post('/periode/{year}/{month}/toggle-lock', [SettingController::class, 'togglePeriodByYearMonth'])->name('periode.toggle-lock');

    // Tahun Anggaran Switcher
    Route::post('/tahun-anggaran/pilih', [SettingController::class, 'switchFiscalYear'])->name('fiscal-year.switch');

    // Pengaturan & Data Master
    Route::prefix('pengaturan')->name('pengaturan.')->group(function () {
        Route::get('/', [SettingController::class, 'index'])->name('index');
        Route::post('/master', [SettingController::class, 'updateMaster'])->name('master');
        Route::post('/ganti-password', [SettingController::class, 'changePassword'])->name('change-password');
        Route::post('/tahun-baru', [SettingController::class, 'openNewFiscalYear'])->name('open-new-year');
        Route::post('/bersihkan-backup', [SettingController::class, 'pruneBackups'])->name('prune-backups');
        Route::post('/period/{period}/toggle-lock', [SettingController::class, 'togglePeriodLock'])->name('toggle-lock');

        // Manajemen PPTK (Tambah, Edit, Hapus, Status Aktif)
        Route::post('/pptk', [PptkController::class, 'store'])->name('pptk.store');
        Route::put('/pptk/{pptk}', [PptkController::class, 'update'])->name('pptk.update');
        Route::delete('/pptk/{pptk}', [PptkController::class, 'destroy'])->name('pptk.destroy');
        Route::post('/pptk/{pptk}/toggle-active', [PptkController::class, 'toggleActive'])->name('pptk.toggle-active');
    });
});
