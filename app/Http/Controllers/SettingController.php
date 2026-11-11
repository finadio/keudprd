<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\MasterSetting;
use App\Models\Pptk;
use App\Models\Transaction;
use App\Services\BackupExportService;
use App\Services\FiscalYearContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    /**
     * Display settings and master data management.
     */
    public function index(Request $request): Response
    {
        $activeYear = FiscalYearContext::resolve($request);
        FiscalYearContext::ensurePeriodsExist($activeYear);

        $setting = MasterSetting::where('fiscal_year', $activeYear)->latest()->first()
            ?? MasterSetting::current();
        $pptks = Pptk::orderBy('sort_order')->get();
        $periods = AccountingPeriod::where('fiscal_year', $activeYear)->orderBy('month')->get();
        $auditLogs = AuditLog::with('user')->latest('id')->take(25)->get();

        $availableYears = FiscalYearContext::availableYears();
        $yearSummaries = $this->buildYearSummaries($availableYears);
        $storageStats = $this->buildStorageStats($activeYear);

        return Inertia::render('Setting/Index', [
            'setting' => $setting,
            'pptks' => $pptks,
            'periods' => $periods,
            'audit_logs' => $auditLogs,
            'active_year' => $activeYear,
            'default_year' => FiscalYearContext::defaultYear(),
            'available_years' => $availableYears,
            'year_summaries' => $yearSummaries,
            'storage_stats' => $storageStats,
        ]);
    }

    /**
     * Update SKPD and official identities.
     */
    public function updateMaster(Request $request)
    {
        $validated = $request->validate([
            'skpd_name' => 'required|string|max:255',
            'pa_kpa_name' => 'required|string|max:255',
            'pa_kpa_nip' => 'nullable|string|max:50',
            'treasurer_name' => 'required|string|max:255',
            'treasurer_nip' => 'nullable|string|max:50',
            'fiscal_year' => 'required|integer',
        ]);

        $setting = MasterSetting::where('fiscal_year', $validated['fiscal_year'])->latest()->first()
            ?? MasterSetting::current();

        if ($setting && (int) $setting->fiscal_year === (int) $validated['fiscal_year']) {
            $setting->update($validated);
        } else {
            $setting = MasterSetting::create([
                ...$validated,
                'is_active' => $setting === null,
            ]);
        }

        AuditLog::log('update', 'MasterSetting', $setting?->id, null, $validated);

        return back()->with('success', 'Konfigurasi SKPD dan pejabat berhasil diperbarui!');
    }

    /**
     * Toggle lock on an accounting period by year and month.
     */
    public function togglePeriodByYearMonth(int $year, int $month)
    {
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $period = AccountingPeriod::firstOrCreate(
            ['fiscal_year' => $year, 'month' => $month],
            ['name' => ($monthNames[$month] ?? "Bulan {$month}")." {$year}", 'is_closed' => false]
        );

        $period->is_closed = ! $period->is_closed;
        $period->closed_at = $period->is_closed ? now() : null;
        $period->closed_by = $period->is_closed ? auth()->id() : null;
        $period->save();

        AuditLog::log(
            $period->is_closed ? 'lock_period' : 'unlock_period',
            'AccountingPeriod',
            $period->id,
            null,
            ['is_closed' => $period->is_closed, 'year' => $year, 'month' => $month]
        );

        $bulanStr = $monthNames[$month] ?? "Bulan {$month}";
        $msg = $period->is_closed
            ? "Periode {$bulanStr} {$year} berhasil ditutup & dikunci!"
            : "Kunci periode {$bulanStr} {$year} berhasil dibuka!";

        return back()->with('success', $msg);
    }

    /**
     * Toggle lock on an accounting period.
     */
    public function togglePeriodLock(AccountingPeriod $period)
    {
        $period->is_closed = ! $period->is_closed;
        $period->closed_at = $period->is_closed ? now() : null;
        $period->closed_by = $period->is_closed ? auth()->id() : null;
        $period->save();

        AuditLog::log(
            $period->is_closed ? 'lock_period' : 'unlock_period',
            'AccountingPeriod',
            $period->id,
            null,
            ['is_closed' => $period->is_closed]
        );

        $msg = $period->is_closed ? 'Periode berhasil dikunci!' : 'Kunci periode berhasil dibuka!';

        return back()->with('success', $msg);
    }

    /**
     * Switch active viewing/working fiscal year stored in session.
     */
    public function switchFiscalYear(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'current_url' => 'nullable|string',
        ]);

        $year = (int) $validated['year'];
        session(['active_fiscal_year' => $year]);

        $targetUrl = $validated['current_url'] ?? url()->previous();
        $parsed = parse_url($targetUrl);
        $path = $parsed['path'] ?? '/dashboard';
        $queryParams = [];
        if (! empty($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
        }
        unset($queryParams['year']);
        $queryParams['year'] = $year;

        $cleanRedirect = $path.'?'.http_build_query($queryParams);

        return redirect($cleanRedirect)->with('success', "Berhasil beralih ke Tahun Anggaran {$year}.");
    }

    /**
     * Open a new fiscal year cleanly while preserving all prior years' data.
     */
    public function openNewFiscalYear(Request $request)
    {
        $validated = $request->validate([
            'new_year' => 'required|integer|min:2020|max:2100',
            'lock_previous_year' => 'nullable|boolean',
        ]);

        $newYear = (int) $validated['new_year'];
        $lockPrevious = (bool) ($validated['lock_previous_year'] ?? true);
        $previousYear = FiscalYearContext::defaultYear();

        DB::transaction(function () use ($newYear, $lockPrevious, $previousYear) {
            if ($lockPrevious && $previousYear !== $newYear) {
                FiscalYearContext::ensurePeriodsExist($previousYear);
                AccountingPeriod::where('fiscal_year', $previousYear)->update([
                    'is_closed' => true,
                    'closed_at' => now(),
                    'closed_by' => auth()->id(),
                ]);
            }

            FiscalYearContext::ensurePeriodsExist($newYear);

            $currentSetting = MasterSetting::current();
            MasterSetting::query()->update(['is_active' => false]);

            $existingForYear = MasterSetting::where('fiscal_year', $newYear)->latest()->first();
            if ($existingForYear) {
                $existingForYear->update(['is_active' => true]);
            } else {
                MasterSetting::create([
                    'fiscal_year' => $newYear,
                    'skpd_name' => $currentSetting?->skpd_name ?? 'SETWAN KABUPATEN BANYUMAS',
                    'pa_kpa_name' => $currentSetting?->pa_kpa_name ?? 'ARIF TRIYANTO, S.Sos. M.Eng.',
                    'pa_kpa_nip' => $currentSetting?->pa_kpa_nip,
                    'treasurer_name' => $currentSetting?->treasurer_name ?? 'INDAH SETIYOWATI, A.Md.AB.',
                    'treasurer_nip' => $currentSetting?->treasurer_nip,
                    'is_active' => true,
                ]);
            }
        });

        session(['active_fiscal_year' => $newYear]);

        AuditLog::log(
            'open_new_fiscal_year',
            'MasterSetting',
            null,
            ['previous_year' => $previousYear],
            ['new_year' => $newYear, 'lock_previous_year' => $lockPrevious]
        );

        $lockNote = ($lockPrevious && $previousYear !== $newYear)
            ? " Seluruh periode TA {$previousYear} telah dikunci aman sebagai arsip."
            : '';

        return redirect("/pengaturan?year={$newYear}")
            ->with('success', "Tahun Anggaran baru {$newYear} berhasil dibuka!{$lockNote}");
    }

    /**
     * Prune older backup ZIP files to keep server storage clean.
     */
    public function pruneBackups(BackupExportService $backupService)
    {
        $deleted = $backupService->pruneOldBackups(5);

        $msg = $deleted > 0
            ? "{$deleted} file cadangan ZIP lama berhasil dibersihkan (5 cadangan terbaru tetap disimpan)."
            : 'Penyimpanan cadangan sudah rapi (kurang dari atau sama dengan 5 file ZIP).';

        return back()->with('success', $msg);
    }

    /**
     * Update the authenticated user's password.
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();
        if (! $user || ! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'Password saat ini tidak sesuai.',
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        AuditLog::log('change_password', 'User', $user->id);

        return back()->with('success', 'Password akun berhasil diperbarui!');
    }

    /**
     * Build per-year archive summaries for the Multi-Year Management panel.
     *
     * @param  array<int, int>  $years
     * @return array<int, array<string, mixed>>
     */
    private function buildYearSummaries(array $years): array
    {
        $txAgg = Transaction::query()
            ->selectRaw('period_year, COUNT(*) as tx_count, SUM(CASE WHEN flow_direction = "receipt" THEN amount_net ELSE 0 END) as total_receipt')
            ->groupBy('period_year')
            ->get()
            ->keyBy('period_year');

        $periodAgg = AccountingPeriod::query()
            ->selectRaw('fiscal_year, COUNT(*) as period_count, SUM(CASE WHEN is_closed = 1 THEN 1 ELSE 0 END) as closed_count')
            ->groupBy('fiscal_year')
            ->get()
            ->keyBy('fiscal_year');

        $defaultYear = FiscalYearContext::defaultYear();

        return array_map(function (int $yr) use ($txAgg, $periodAgg, $defaultYear): array {
            $txRow = $txAgg->get($yr);
            $pRow = $periodAgg->get($yr);

            return [
                'year' => $yr,
                'is_default' => $yr === $defaultYear,
                'tx_count' => (int) ($txRow?->tx_count ?? 0),
                'total_receipt' => (float) ($txRow?->total_receipt ?? 0),
                'closed_months' => (int) ($pRow?->closed_count ?? 0),
                'total_months' => (int) ($pRow?->period_count ?? 0),
            ];
        }, $years);
    }

    /**
     * Gather real database and backup folder capacity statistics.
     *
     * @return array<string, mixed>
     */
    private function buildStorageStats(int $activeYear): array
    {
        $totalTransactions = Transaction::count();
        $activeYearTransactions = Transaction::where('period_year', $activeYear)->count();

        $dbSizeMb = null;
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                $dbName = DB::connection()->getDatabaseName();
                $res = DB::selectOne(
                    'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = ?',
                    [$dbName]
                );
                $dbSizeMb = $res?->size_mb !== null ? (float) $res->size_mb : null;
            } elseif ($driver === 'sqlite') {
                $dbPath = DB::connection()->getDatabaseName();
                if ($dbPath && file_exists($dbPath)) {
                    $dbSizeMb = round(filesize($dbPath) / 1024 / 1024, 2);
                }
            }
        } catch (\Throwable) {
            $dbSizeMb = null;
        }

        $backupCount = 0;
        $backupBytes = 0;
        $backupDir = storage_path('app/backups');
        if (File::isDirectory($backupDir)) {
            foreach (File::files($backupDir) as $file) {
                if (strtolower($file->getExtension()) === 'zip') {
                    $backupCount++;
                    $backupBytes += $file->getSize();
                }
            }
        }

        return [
            'total_transactions' => $totalTransactions,
            'active_year_transactions' => $activeYearTransactions,
            'db_size_mb' => $dbSizeMb,
            'backup_files_count' => $backupCount,
            'backup_size_mb' => round($backupBytes / 1024 / 1024, 2),
            'backup_keep_limit' => 5,
        ];
    }
}
