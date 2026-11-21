<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MasterSetting;
use App\Models\Pptk;
use App\Services\BackupExportService;
use App\Services\BookkeepingService;
use App\Services\FiscalYearContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Display report selection and preview view.
     */
    public function index(Request $request, BookkeepingService $service): Response
    {
        $year = FiscalYearContext::resolve($request);
        $pptks = Pptk::where('is_active', true)->orderBy('sort_order')->get();
        $setting = MasterSetting::current();

        return Inertia::render('Report/Index', [
            'year' => $year,
            'pptks' => $pptks,
            'setting' => $setting,
        ]);
    }

    /**
     * Export exact Buku Bank to Excel .xlsx file.
     */
    public function exportBukuBank(Request $request, BookkeepingService $service): StreamedResponse
    {
        $year = FiscalYearContext::resolve($request);
        $month = (int) $request->input('month', 1);

        $data = $service->getBukuBankMonth($year, $month);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($data['meta']['month_name']);

        // Set Header
        $sheet->setCellValue('A1', 'KABUPATEN BANYUMAS');
        $sheet->setCellValue('A2', 'BUKU BANK');
        $sheet->setCellValue('A4', 'SKPD : '.$data['meta']['skpd']);
        $sheet->setCellValue('A5', 'PENGGUNA ANGGARAN/KUASA PENGGUNA : '.$data['meta']['pa_kpa']);
        $sheet->setCellValue('A6', 'BENDAHARA PENGELUARAN : '.$data['meta']['bendahara']);
        $sheet->setCellValue('A7', 'BULAN : '.strtoupper($data['meta']['month_name']));

        // Table Header
        $sheet->setCellValue('A9', 'NO. URUT');
        $sheet->setCellValue('B9', 'TANGGAL');
        $sheet->setCellValue('C9', 'URAIAN');
        $sheet->setCellValue('D9', 'PENERIMAAN');
        $sheet->setCellValue('E9', 'PENGELUARAN');
        $sheet->setCellValue('F9', 'SALDO');
        $sheet->setCellValue('G9', 'KETERANGAN');

        $sheet->setCellValue('A10', '1');
        $sheet->setCellValue('B10', '2');
        $sheet->setCellValue('C10', '4');
        $sheet->setCellValue('D10', '5');
        $sheet->setCellValue('E10', '6');
        $sheet->setCellValue('F10', '7');
        $sheet->setCellValue('G10', '8');

        // Saldo Bulan Lalu
        $row = 11;
        $sheet->setCellValue("B{$row}", sprintf('01/%02d/%d', $month, $year));
        $sheet->setCellValue("C{$row}", 'Saldo bulan lalu');
        $sheet->setCellValue("D{$row}", $data['saldo_awal']);
        $sheet->setCellValue("E{$row}", 0);
        $sheet->setCellValue("F{$row}", $data['saldo_awal']);

        $row++;
        foreach ($data['rows'] as $item) {
            $sheet->setCellValue("A{$row}", $item['no']);
            $sheet->setCellValue("B{$row}", $item['tanggal']);
            $sheet->setCellValue("C{$row}", $item['uraian']);
            $sheet->setCellValue("D{$row}", $item['penerimaan']);
            $sheet->setCellValue("E{$row}", $item['pengeluaran']);
            $sheet->setCellValue("F{$row}", $item['saldo']);
            $sheet->setCellValue("G{$row}", $item['keterangan']);
            $row++;
        }

        // Closing Totals
        $sheet->setCellValue("A{$row}", 'JUMLAH PERIODE INI');
        $sheet->setCellValue("D{$row}", $data['summary']['periode_ini']['penerimaan']);
        $sheet->setCellValue("E{$row}", $data['summary']['periode_ini']['pengeluaran']);
        $sheet->setCellValue("F{$row}", $data['summary']['periode_ini']['saldo']);
        $row++;

        $sheet->setCellValue("A{$row}", 'JUMLAH SAMPAI PERIODE LALU');
        $sheet->setCellValue("D{$row}", $data['summary']['sampai_periode_lalu']['penerimaan']);
        $sheet->setCellValue("E{$row}", $data['summary']['sampai_periode_lalu']['pengeluaran']);
        $sheet->setCellValue("F{$row}", $data['summary']['sampai_periode_lalu']['saldo']);
        $row++;

        $sheet->setCellValue("A{$row}", 'JUMLAH SAMPAI PERIODE INI');
        $sheet->setCellValue("D{$row}", $data['summary']['sampai_periode_ini']['penerimaan']);
        $sheet->setCellValue("E{$row}", $data['summary']['sampai_periode_ini']['pengeluaran']);
        $sheet->setCellValue("F{$row}", $data['summary']['sampai_periode_ini']['saldo']);
        $row++;

        $sheet->setCellValue("A{$row}", 'SISA KAS');
        $sheet->setCellValue("F{$row}", $data['summary']['sisa_kas']);

        // Auto column width
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "Buku_Bank_{$data['meta']['month_name']}_{$year}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export Hitungan Summary to Excel .xlsx file.
     */
    public function exportHitungan(Request $request, BookkeepingService $service): StreamedResponse
    {
        $year = FiscalYearContext::resolve($request);
        $data = $service->getHitunganSummary($year);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Hitungan');

        $sheet->setCellValue('A1', 'REKAPITULASI BKU PER PPTK');
        $sheet->setCellValue('A2', 'SEKRETARIAT DPRD KABUPATEN BANYUMAS - TA '.$year);

        // Header Table
        $sheet->setCellValue('A4', 'No');
        $sheet->setCellValue('B4', 'PPTK');
        $sheet->setCellValue('C4', 'Debet');
        $sheet->setCellValue('D4', 'Panjar');
        $sheet->setCellValue('E4', 'Jml (2+3)');
        $sheet->setCellValue('F4', 'SPJ');
        $sheet->setCellValue('G4', 'Total Hutang Panjar');
        $sheet->setCellValue('H4', 'Bank');
        $sheet->setCellValue('I4', 'Tunai');
        $sheet->setCellValue('J4', 'SPJ Panjar Lanjutan');
        $sheet->setCellValue('K4', 'Sisa Panjar');
        $sheet->setCellValue('L4', 'Total Sisa Riil');
        $sheet->setCellValue('M4', 'Kontrol Selisih');

        $row = 5;
        foreach ($data['rows'] as $item) {
            $sheet->setCellValue("A{$row}", $item['no']);
            $sheet->setCellValue("B{$row}", $item['pptk_name']);
            $sheet->setCellValue("C{$row}", $item['debet']);
            $sheet->setCellValue("D{$row}", $item['panjar']);
            $sheet->setCellValue("E{$row}", $item['jml']);
            $sheet->setCellValue("F{$row}", $item['spj']);
            $sheet->setCellValue("G{$row}", $item['total_hutang_panjar']);
            $sheet->setCellValue("H{$row}", $item['bank']);
            $sheet->setCellValue("I{$row}", $item['tunai']);
            $sheet->setCellValue("J{$row}", $item['spj_lanjutan']);
            $sheet->setCellValue("K{$row}", $item['sisa_panjar']);
            $sheet->setCellValue("L{$row}", $item['total_sisa_riil']);
            $sheet->setCellValue("M{$row}", $item['kontrol_selisih']);
            $row++;
        }

        // Totals
        $sheet->setCellValue("B{$row}", 'JUMLAH');
        $sheet->setCellValue("C{$row}", $data['totals']['debet']);
        $sheet->setCellValue("D{$row}", $data['totals']['panjar']);
        $sheet->setCellValue("E{$row}", $data['totals']['jml']);
        $sheet->setCellValue("F{$row}", $data['totals']['spj']);
        $sheet->setCellValue("G{$row}", $data['totals']['total_hutang_panjar']);
        $sheet->setCellValue("H{$row}", $data['totals']['bank']);
        $sheet->setCellValue("I{$row}", $data['totals']['tunai']);
        $sheet->setCellValue("J{$row}", $data['totals']['spj_lanjutan']);
        $sheet->setCellValue("K{$row}", $data['totals']['sisa_panjar']);
        $sheet->setCellValue("L{$row}", $data['totals']['total_sisa_riil']);
        $sheet->setCellValue("M{$row}", $data['totals']['kontrol_selisih']);

        // Rekap Hitung Kas Fisik & Kontrol UP (Baris 32-44 Sheet Hitungan)
        $rKas = $data['rekap_kas'] ?? [];
        if (! empty($rKas)) {
            $rStart = $row + 3;
            $sheet->setCellValue("D{$rStart}", 'REKAP HITUNG KAS FISIK & KONTROL UP');
            $sheet->setCellValue("H{$rStart}", 'REKAPITULASI SALDO BANK / RK');

            $r1 = $rStart + 1;
            $sheet->setCellValue("D{$r1}", 'UP Awal');
            $sheet->setCellValue("F{$r1}", $rKas['up_awal'] ?? 0);

            $r2 = $rStart + 2;
            $sheet->setCellValue("D{$r2}", 'Sisa UP');
            $sheet->setCellValue("F{$r2}", $rKas['sisa_up'] ?? 0);
            $sheet->setCellValue("H{$r2}", 'UP');
            $sheet->setCellValue("I{$r2}", $rKas['bank_rekap']['up'] ?? 0);

            $r3 = $rStart + 3;
            $sheet->setCellValue("D{$r3}", 'Panjar');
            $sheet->setCellValue("F{$r3}", $rKas['panjar'] ?? 0);
            $sheet->setCellValue("H{$r3}", 'LS Reses');
            $sheet->setCellValue("I{$r3}", $rKas['bank_rekap']['ls_reses'] ?? 0);

            $r4 = $rStart + 4;
            $sheet->setCellValue("D{$r4}", 'Kas Kecil');
            $sheet->setCellValue("F{$r4}", $rKas['kas_kecil'] ?? 0);
            $sheet->setCellValue("H{$r4}", 'LS');
            $sheet->setCellValue("I{$r4}", $rKas['bank_rekap']['ls_lain'] ?? 0);

            $r5 = $rStart + 5;
            $sheet->setCellValue("D{$r5}", 'Jumlah Kas UP Berjalan');
            $sheet->setCellValue("F{$r5}", $rKas['total_kas_up'] ?? 0);
            $sheet->setCellValue("H{$r5}", 'Jumlah Total Bank');
            $sheet->setCellValue("I{$r5}", $rKas['bank_rekap']['total_bank_buku'] ?? 0);

            $r6 = $rStart + 6;
            $sheet->setCellValue("D{$r6}", 'Selisih UP');
            $sheet->setCellValue("F{$r6}", $rKas['selisih_up'] ?? 0);
            $sheet->setCellValue("H{$r6}", 'RK Bank Jateng');
            $sheet->setCellValue("I{$r6}", $rKas['bank_rekap']['rekening_koran'] ?? 0);

            $r7 = $rStart + 7;
            $sheet->setCellValue("D{$r7}", 'Rencana SPJ');
            $sheet->setCellValue("F{$r7}", $rKas['rencana_spj'] ?? 0);

            $r8 = $rStart + 8;
            $sheet->setCellValue("D{$r8}", 'Rencana LS Rampung');
            $sheet->setCellValue("F{$r8}", $rKas['rencana_ls'] ?? 0);
            $sheet->setCellValue("H{$r8}", 'Selisih RK Bank');
            $sheet->setCellValue("I{$r8}", $rKas['bank_rekap']['selisih'] ?? 0);

            $r9 = $rStart + 9;
            $sheet->setCellValue("D{$r9}", 'Sisa Kontrol UP');
            $sheet->setCellValue("F{$r9}", $rKas['sisa_kontrol_up'] ?? 0);

            $r10 = $rStart + 10;
            $sheet->setCellValue("E{$r10}", 'KONTROL SELISIH AKHIR:');
            $sheet->setCellValue("G{$r10}", $rKas['kontrol_akhir'] ?? 0);
        }

        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "Rekap_Hitungan_BKU_{$year}.xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Download full ZIP backup containing formula-linked workbooks and database dumps.
     */
    public function downloadBackupZip(Request $request, BackupExportService $backupService)
    {
        $year = FiscalYearContext::resolve($request);
        $operator = auth()->user()?->name ?: 'Administrator';

        $backup = $backupService->createBackupZip($year, $operator);

        AuditLog::log(
            'export_zip',
            'BackupArchive',
            0,
            null,
            [
                'filename' => $backup['filename'],
                'size_bytes' => $backup['size_bytes'],
                'year' => $year,
            ]
        );

        return response()->download($backup['path'], $backup['filename'], [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Email full ZIP backup to designated recipient.
     */
    public function emailBackupZip(Request $request, BackupExportService $backupService)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'year' => 'nullable|integer',
        ]);

        $year = (int) ($validated['year'] ?? FiscalYearContext::resolve($request));
        $email = $validated['email'];
        $operator = auth()->user()?->name ?: 'Administrator';

        try {
            $backup = $backupService->emailBackupZip($email, $year, $operator);

            $isLogMode = config('mail.default') === 'log';
            $msg = $isLogMode
                ? "Arsip cadangan ZIP ({$backup['filename']}) berhasil dibuat (Mode simulasi/LOG aktif di .env. Untuk mengirim ke email asli, aktifkan akun SMTP di .env)."
                : "Arsip cadangan ZIP ({$backup['filename']}) berhasil dikirim ke {$email}!";

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mengirim email: '.$e->getMessage());
        }
    }
}
