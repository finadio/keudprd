<?php

use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\ManualAdjustment;
use App\Models\PaguRealization;
use App\Models\Reconciliation;
use App\Models\TaxDeduction;
use App\Models\Transaction;
use App\Models\TransactionAllocation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('data:reset-transactions', function () {
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    TransactionAllocation::truncate();
    TaxDeduction::truncate();
    Reconciliation::truncate();
    Transaction::truncate();
    ImportBatch::truncate();
    ManualAdjustment::truncate();
    PaguRealization::truncate();
    AuditLog::truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    $this->info('All transaction data has been successfully emptied.');
})->purpose('Empty all financial transaction and realization tables for clean testing');

Artisan::command('data:reset-transactions', function () {
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    TransactionAllocation::truncate();
    TaxDeduction::truncate();
    Reconciliation::truncate();
    Transaction::truncate();
    ImportBatch::truncate();
    ManualAdjustment::truncate();
    PaguRealization::truncate();
    AuditLog::truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    $this->info('All transaction data has been successfully emptied.');
})->purpose('Empty all financial transaction and realization tables for clean testing');
