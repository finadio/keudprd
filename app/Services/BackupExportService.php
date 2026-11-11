<?php

namespace App\Services;

use App\Mail\BackupArchiveMail;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\ManualAdjustment;
use App\Models\MasterSetting;
use App\Models\Pptk;
use App\Models\Reconciliation;
use App\Models\Subunit;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use ZipArchive;

class BackupExportService
{
    /**
     * Generate complete backup archive containing formula-linked Excel workbooks and DB dumps.
     *
     * @return array{path: string, filename: string, size_bytes: int, transaction_count: int}
     */
    public function createBackupZip(int $year = 2026, ?string $operatorName = null): array
    {
        $backupDir = storage_path('app/backups');
        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true, true);
        }

        $timestamp = date('Ymd_His');
        $fileName = "Cadangan_KeuDPRD_Banyumas_TA{$year}_{$timestamp}.zip";
        $zipPath = $backupDir.DIRECTORY_SEPARATOR.$fileName;

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Gagal membuat arsip ZIP di path: {$zipPath}");
        }

        // 1. Add Excel Template BKU Per PPTK & Buku Bank (for TA 2026 original workbooks)
        if ($year === 2026) {
            $bkuFile = base_path('BKU Per PPTK 2026 (1).xlsx');
            if (File::exists($bkuFile)) {
                $zip->addFile($bkuFile, "BKU Per PPTK {$year}.xlsx");
            }

            $bankFile = base_path('BUKU BANK 2026 (1).xlsx');
            if (File::exists($bankFile)) {
                $zip->addFile($bankFile, "BUKU BANK {$year}.xlsx");
            }
        }

        // 3. Export JSON Database Snapshot
        $txCount = Transaction::where('period_year', $year)->count();
        $dbData = [
            'meta' => [
                'fiscal_year' => $year,
                'created_at' => date('c'),
                'operator' => $operatorName ?: 'Sistem KeuDPRD',
                'application' => 'KeuDPRD Setwan Kab. Banyumas',
                'total_transactions' => $txCount,
            ],
            'master_settings' => MasterSetting::where('fiscal_year', $year)->get()->toArray(),
            'pptks' => Pptk::all()->toArray(),
            'subunits' => Subunit::all()->toArray(),
            'accounting_periods' => AccountingPeriod::where('fiscal_year', $year)->get()->toArray(),
            'manual_adjustments' => ManualAdjustment::where('period_year', $year)->get()->toArray(),
            'transactions' => Transaction::where('period_year', $year)->orderBy('transaction_date')->get()->toArray(),
            'reconciliations' => Reconciliation::all()->toArray(),
        ];
        $zip->addFromString("database_backup_{$year}.json", json_encode($dbData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 4. Export SQL Database Dump
        $sqlDump = $this->generateSqlDump($year);
        $zip->addFromString("database_backup_{$year}.sql", $sqlDump);

        // 5. Add Human-Readable Documentation & Instructions
        $setting = MasterSetting::current();
        $readme = $this->generateReadme($year, $operatorName, $txCount, $setting);
        $zip->addFromString('README_CADANGAN.txt', $readme);

        $zip->close();

        // Auto-prune old ZIP files to prevent disk space bloat over years
        $this->pruneOldBackups(5);

        return [
            'path' => $zipPath,
            'filename' => $fileName,
            'size_bytes' => File::size($zipPath),
            'transaction_count' => $txCount,
        ];
    }

    /**
     * Keep only the latest N backup ZIP files in storage/app/backups to prevent storage bloat.
     */
    public function pruneOldBackups(int $keepLatest = 5, ?string $directory = null): int
    {
        $backupDir = $directory ?? storage_path('app/backups');
        if (! File::isDirectory($backupDir)) {
            return 0;
        }

        $files = collect(File::files($backupDir))
            ->filter(fn (\SplFileInfo $file): bool => strtolower($file->getExtension()) === 'zip')
            ->sortByDesc(fn (\SplFileInfo $file): int => $file->getMTime())
            ->values();

        if ($files->count() <= $keepLatest) {
            return 0;
        }

        $deleted = 0;
        foreach ($files->slice($keepLatest) as $oldFile) {
            if (File::delete($oldFile->getPathname())) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Send generated backup ZIP directly to recipient email.
     */
    public function emailBackupZip(string $recipientEmail, int $year = 2026, ?string $operatorName = null): array
    {
        $backup = $this->createBackupZip($year, $operatorName);

        Mail::to($recipientEmail)->send(new BackupArchiveMail(
            zipFilePath: $backup['path'],
            zipFileName: $backup['filename'],
            year: $year,
            operatorName: $operatorName ?: 'Administrator',
            txCount: $backup['transaction_count']
        ));

        AuditLog::log(
            'export_email',
            'BackupArchive',
            0,
            null,
            [
                'recipient' => $recipientEmail,
                'filename' => $backup['filename'],
                'size_bytes' => $backup['size_bytes'],
                'year' => $year,
            ]
        );

        return $backup;
    }

    /**
     * Generate raw SQL INSERT dump for quick DB restore.
     */
    private function generateSqlDump(int $year): string
    {
        $sql = "-- ============================================================\n";
        $sql .= "-- CADANGAN DATABASE KEUANGAN DPRD KABUPATEN BANYUMAS\n";
        $sql .= "-- Tahun Anggaran : {$year}\n";
        $sql .= '-- Waktu Cadangan : '.date('Y-m-d H:i:s')."\n";
        $sql .= "-- ============================================================\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        // Transactions dump
        $transactions = Transaction::where('period_year', $year)->get();
        if ($transactions->isNotEmpty()) {
            $sql .= "-- Table: transactions ({$transactions->count()} records)\n";
            foreach ($transactions->chunk(100) as $chunk) {
                $values = [];
                foreach ($chunk as $t) {
                    $date = $t->transaction_date ? "'".$t->transaction_date->format('Y-m-d')."'" : 'NULL';
                    $doc = $t->document_number ? "'".addslashes($t->document_number)."'" : 'NULL';
                    $desc = "'".addslashes($t->description)."'";
                    $type = "'".addslashes($t->transaction_type ?: 'umum')."'";
                    $method = "'".addslashes($t->payment_method ?: 'bank_transfer')."'";
                    $gross = $t->amount_gross ?: 0;
                    $tax = $t->amount_tax ?: 0;
                    $net = $t->amount_net ?: 0;
                    $flow = "'".addslashes($t->flow_direction)."'";
                    $pptkId = $t->pptk_id ?: 'NULL';
                    $month = $t->period_month ?: 'NULL';
                    $yr = $t->period_year ?: $year;
                    $verified = $t->is_verified ? 1 : 0;
                    $status = "'".addslashes($t->status ?: 'posted')."'";
                    $notes = $t->notes ? "'".addslashes($t->notes)."'" : 'NULL';

                    $values[] = "({$t->id}, {$date}, {$doc}, {$desc}, {$type}, {$method}, {$gross}, {$tax}, {$net}, {$flow}, {$pptkId}, {$month}, {$yr}, {$verified}, {$status}, {$notes}, NOW(), NOW())";
                }
                $sql .= "INSERT INTO `transactions` (`id`, `transaction_date`, `document_number`, `description`, `transaction_type`, `payment_method`, `amount_gross`, `amount_tax`, `amount_net`, `flow_direction`, `pptk_id`, `period_month`, `period_year`, `is_verified`, `status`, `notes`, `created_at`, `updated_at`) VALUES \n"
                    .implode(",\n", $values).";\n\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        return $sql;
    }

    /**
     * Generate clear README file explaining backup contents.
     */
    private function generateReadme(int $year, ?string $operatorName, int $txCount, ?MasterSetting $setting): string
    {
        $dateStr = date('d F Y, H:i:s T');
        $skpd = $setting->skpd_name ?? 'SETWAN KABUPATEN BANYUMAS';
        $pa = $setting->pa_kpa_name ?? '-';
        $bendahara = $setting->treasurer_name ?? 'INDAH SETIYOWATI, A.Md.AB.';
        $op = $operatorName ?: 'Administrator';

        return <<<TXT
================================================================================
ARSIP CADANGAN LENGKAP KEUANGAN DPRD KABUPATEN BANYUMAS (TA {$year})
================================================================================

Tanggal Pembuatan : {$dateStr}
Instansi / SKPD   : {$skpd}
Tahun Anggaran    : {$year}
Pengguna Anggaran : {$pa}
Bendahara         : {$bendahara}
Dibuat Oleh       : {$op}
Total Transaksi   : {$txCount} mutasi

--------------------------------------------------------------------------------
DAFTAR ISI ARSIP CADANGAN (ZIP):
--------------------------------------------------------------------------------
1. BKU Per PPTK {$year}.xlsx
   Buku Kas Umum Per PPTK format Excel asli dengan 20 Sheet Tab:
   - Hitungan (Rekapitulasi otomatis dengan rumus ke sheet Jml Deb Rek & PPTK)
   - UP (Buku Kas Umum UP berumus running balance)
   - KK (Buku Kas Kecil Kas Tunai)
   - Jml Deb Rek (Rekapitulasi debet bulanan dan panjar)
   - Deb Rek (Buku Debet Rekening Induk)
   - 9 Sheet PPTK: Aul-Ety, Gaya, Ratman, Aul-Rina, Sigit, SgngSyesa, SgngEmi, Sri L, Irna
   - 6 Sheet Reses: Reses 1, Deb Reses1, Reses2, Deb Reses2, Reses3, DebReses3
   Seluruh formula antar sheet (=SUM, =Jml Deb Rek!O5, dll) tersambung aktif.

2. BUKU BANK {$year}.xlsx
   Buku Bank format Excel asli dengan 10 Sheet Tab Bulanan (Januari - Oktober 2026):
   - Kolom lengkap: NO URUT, TANGGAL, URAIAN, PENERIMAAN, PENGELUARAN, SALDO
   - Rumus running saldo otomatis: =F12+D13-E13 pada setiap baris transaksi
   - Sambungan saldo awal antar bulan (=Jan!F124, dst.) terjaga rapi.

3. database_backup_{$year}.sql
   Dump query SQL standar yang dapat langsung diimpor ke MySQL / MariaDB via phpMyAdmin atau console.

4. database_backup_{$year}.json
   Struktur snapshot data lengkap format JSON untuk integrasi API atau backup modern.

5. README_CADANGAN.txt
   Petunjuk dan ringkasan isi arsip cadangan ini.

--------------------------------------------------------------------------------
PETUNJUK PENGGUNAAN & PEMULIHAN (RESTORE):
--------------------------------------------------------------------------------
- Buka file .xlsx menggunakan Microsoft Excel atau Google Spreadsheet.
- Seluruh rumus dan kaitan saldo akan otomatis terhitung kembali saat dibuka.
- Simpan file ini di media penyimpanan yang aman (Google Drive Setwan / flashdisk bendahara).

KeuDPRD - Sistem Pembukuan Keuangan Sekretariat DPRD Kabupaten Banyumas
TXT;
    }
}
