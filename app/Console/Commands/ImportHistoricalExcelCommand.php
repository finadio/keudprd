<?php

namespace App\Console\Commands;

use App\Services\ExcelImportService;
use Illuminate\Console\Command;

class ImportHistoricalExcelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-historical-excel {--file= : Path to Excel file} {--preview : Preview only without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import historical transactions from Buku Bank Excel into database';

    /**
     * Execute the console command.
     */
    public function handle(ExcelImportService $service)
    {
        $file = $this->option('file') ?: base_path('references/BUKU BANK 2026 (1).xlsx');
        if (! file_exists($file)) {
            $file = base_path('BUKU BANK 2026 (1).xlsx');
        }

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return 1;
        }

        $isPreview = $this->option('preview');
        $this->info("Parsing file: {$file} ".($isPreview ? '(PREVIEW MODE)' : '(SAVING TO DATABASE)'));

        $result = $service->importBukuBank($file, ! $isPreview);

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Total Records Terbaca', $result['total_records']],
                ['Total Penerimaan (Rp)', number_format($result['total_receipts'], 2, ',', '.')],
                ['Total Pengeluaran (Rp)', number_format($result['total_disbursements'], 2, ',', '.')],
                ['Saldo Akhir (Rp)', number_format($result['total_receipts'] - $result['total_disbursements'], 2, ',', '.')],
            ]
        );

        $this->info('Impor data historis selesai dengan sukses!');

        return 0;
    }
}
