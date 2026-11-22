<?php

namespace App\Services\Gaji;

use App\Models\Gaji\GajiPayrollItem;
use App\Models\Gaji\GajiPayrollPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GajiExportService
{
    /**
     * Ekspor Daftar Nominatif Penggajian ke Excel (.xlsx).
     */
    public function exportNominatifExcel(GajiPayrollPeriod $period): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Daftar Gaji & Tunjangan');

        // Header Dokumen
        $sheet->mergeCells('A1:O1');
        $sheet->setCellValue('A1', 'PEMERINTAH KABUPATEN BANYUMAS');
        $sheet->mergeCells('A2:O2');
        $sheet->setCellValue('A2', 'SEKRETARIAT DEWAN PERWAKILAN RAKYAT DAERAH (DPRD)');
        $sheet->mergeCells('A3:O3');
        $sheet->setCellValue('A3', 'DAFTAR PEMBAYARAN PENGHASILAN DAN HAK KEUANGAN '.strtoupper($period->period_title));

        $sheet->getStyle('A1:A3')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header Kolom Tabel
        $headers = [
            'No', 'Nama Penerima', 'Kategori', 'Jabatan / Golongan',
            'Gaji Pokok / Representasi', 'Tunj. Paket / Jabatan', 'Tunj. Keluarga & Beras',
            'Tunj. AKD', 'TKI', 'Tunj. Perumahan', 'Tunj. Transportasi',
            'TPP Daerah', 'Penghasilan Bruto', 'Total Potongan', 'Take Home Pay (Netto)',
        ];

        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue("{$col}5", $h);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        $sheet->getStyle('A5:O5')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A5:O5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('107C41');
        $sheet->getStyle('A5:O5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Data Baris
        $items = GajiPayrollItem::where('period_id', $period->id)
            ->with('recipient')
            ->orderBy('category')
            ->orderBy('id')
            ->get();

        $rowNum = 6;
        $no = 1;
        foreach ($items as $item) {
            $rec = $item->recipient;
            $catLabel = $rec ? $rec->sub_category_label : strtoupper($item->sub_category);
            $jabatanLabel = ($rec ? $rec->position_name : '').($rec && $rec->grade ? " ({$rec->grade})" : '');

            $sheet->setCellValue("A{$rowNum}", $no++);
            $sheet->setCellValue("B{$rowNum}", $rec?->name ?? '-');
            $sheet->setCellValue("C{$rowNum}", $catLabel);
            $sheet->setCellValue("D{$rowNum}", $jabatanLabel);
            $sheet->setCellValue("E{$rowNum}", $item->basic_salary_or_rep);
            $sheet->setCellValue("F{$rowNum}", $item->package_allowance + $item->position_allowance);
            $sheet->setCellValue("G{$rowNum}", $item->family_allowance + $item->rice_allowance);
            $sheet->setCellValue("H{$rowNum}", $item->akd_allowance);
            $sheet->setCellValue("I{$rowNum}", $item->tki_allowance);
            $sheet->setCellValue("J{$rowNum}", $item->housing_allowance);
            $sheet->setCellValue("K{$rowNum}", $item->transport_allowance);
            $sheet->setCellValue("L{$rowNum}", $item->tpp_amount);
            $sheet->setCellValue("M{$rowNum}", $item->gross_amount);
            $sheet->setCellValue("N{$rowNum}", $item->total_deductions);
            $sheet->setCellValue("O{$rowNum}", $item->net_amount);

            // Number formats
            $sheet->getStyle("E{$rowNum}:O{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $rowNum++;
        }

        // Total Baris Bawah
        $sheet->setCellValue("A{$rowNum}", 'TOTAL');
        $sheet->mergeCells("A{$rowNum}:D{$rowNum}");
        $sheet->setCellValue("E{$rowNum}", '=SUM(E6:E'.($rowNum - 1).')');
        $sheet->setCellValue("F{$rowNum}", '=SUM(F6:F'.($rowNum - 1).')');
        $sheet->setCellValue("G{$rowNum}", '=SUM(G6:G'.($rowNum - 1).')');
        $sheet->setCellValue("H{$rowNum}", '=SUM(H6:H'.($rowNum - 1).')');
        $sheet->setCellValue("I{$rowNum}", '=SUM(I6:I'.($rowNum - 1).')');
        $sheet->setCellValue("J{$rowNum}", '=SUM(J6:J'.($rowNum - 1).')');
        $sheet->setCellValue("K{$rowNum}", '=SUM(K6:K'.($rowNum - 1).')');
        $sheet->setCellValue("L{$rowNum}", '=SUM(L6:L'.($rowNum - 1).')');
        $sheet->setCellValue("M{$rowNum}", '=SUM(M6:M'.($rowNum - 1).')');
        $sheet->setCellValue("N{$rowNum}", '=SUM(N6:N'.($rowNum - 1).')');
        $sheet->setCellValue("O{$rowNum}", '=SUM(O6:O'.($rowNum - 1).')');

        $sheet->getStyle("A{$rowNum}:O{$rowNum}")->getFont()->setBold(true);
        $sheet->getStyle("A{$rowNum}:O{$rowNum}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2F0D9');
        $sheet->getStyle("E{$rowNum}:O{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');

        // Border Tabel
        $sheet->getStyle("A5:O{$rowNum}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $tempPath = storage_path('app/temp_nominatif_'.$period->id.'.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $filename = 'Daftar_Gaji_Tunjangan_'.$period->period_year.'_'.str_pad($period->period_month, 2, '0', STR_PAD_LEFT).'.xlsx';

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Ekspor Slip Gaji Individual ke PDF resmi.
     */
    public function exportSlipPdf(GajiPayrollItem $item): Response
    {
        $item->load(['period', 'recipient']);
        $rec = $item->recipient;

        $html = view('gaji.slip_pdf', [
            'item' => $item,
            'recipient' => $rec,
            'period' => $item->period,
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a5', 'landscape');
        $filename = 'Slip_Gaji_'.preg_replace('/[^a-zA-Z0-9]/', '_', $rec?->name ?? 'Pegawai').'.pdf';

        return $pdf->download($filename);
    }
}
