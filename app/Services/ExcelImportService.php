<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Pptk;
use App\Models\Transaction;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ExcelImportService
{
    /**
     * Import historical data from Buku Bank Excel file.
     */
    public function importBukuBank(string $filePath, bool $execute = true): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $monthNames = [
            'Jan' => 1, 'Feb' => 2, 'Maret' => 3, 'April' => 4,
            'Mei' => 5, 'Juni' => 6, 'Juli' => 7, 'Agust' => 8,
            'Sept' => 9, 'Okt' => 10, 'Nov' => 11, 'Des' => 12,
        ];

        $pptks = Pptk::all()->keyBy(fn ($p) => strtolower($p->display_name));
        $allPptkModels = Pptk::all();

        $batch = null;
        if ($execute) {
            $batch = ImportBatch::create([
                'batch_number' => 'IMP-BANK-'.date('Ymd-His'),
                'import_type' => 'excel_historical',
                'original_filename' => basename($filePath),
                'status' => 'posted',
            ]);
        }

        $records = [];
        $totalReceipts = 0;
        $totalDisbursements = 0;

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            if (! isset($monthNames[$sheetName])) {
                continue;
            }

            $month = $monthNames[$sheetName];
            $ws = $spreadsheet->getSheetByName($sheetName);
            $maxRow = $ws->getHighestRow();

            // Find closing row e.g. "JUMLAH PERIODE INI"
            $closingRow = $maxRow;
            for ($r = 13; $r <= min(400, $maxRow); $r++) {
                $valA = trim((string) $ws->getCell("A{$r}")->getValue());
                if (stripos($valA, 'JUMLAH PERIODE INI') !== false || stripos($valA, 'SISA KAS') !== false) {
                    $closingRow = $r - 1;
                    break;
                }
            }

            // Loop transactions
            for ($r = 13; $r <= $closingRow; $r++) {
                $valB = $ws->getCell("B{$r}")->getValue(); // Tanggal
                $valC = trim((string) $ws->getCell("C{$r}")->getValue()); // Uraian
                $valD = (float) $ws->getCell("D{$r}")->getCalculatedValue(); // Penerimaan
                $valE = (float) $ws->getCell("E{$r}")->getCalculatedValue(); // Pengeluaran
                $valG = trim((string) $ws->getCell("G{$r}")->getCalculatedValue()); // Keterangan
                $valL = trim((string) $ws->getCell("L{$r}")->getValue()); // PPTK

                if (empty($valC) && $valD == 0 && $valE == 0) {
                    continue;
                }
                if (stripos($valC, 'Saldo bulan lalu') !== false) {
                    continue;
                }

                // Parse Date
                $transDate = null;
                if (is_numeric($valB)) {
                    $transDate = Carbon::instance(ExcelDate::excelToDateTimeObject($valB))->format('Y-m-d');
                } elseif (! empty($valB)) {
                    try {
                        $transDate = Carbon::parse($valB)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $transDate = sprintf('2026-%02d-01', $month);
                    }
                } else {
                    $transDate = sprintf('2026-%02d-01', $month);
                }

                $isReceipt = ($valD > 0);
                $amount = $isReceipt ? $valD : $valE;
                if ($amount <= 0) {
                    continue;
                }

                if ($isReceipt) {
                    $totalReceipts += $amount;
                } else {
                    $totalDisbursements += $amount;
                }

                // Identify PPTK
                $matchedPptkId = null;
                $searchPptk = strtolower($valL ?: $valG ?: $valC);
                foreach ($allPptkModels as $p) {
                    if (str_contains($searchPptk, strtolower($p->display_name)) ||
                        str_contains($searchPptk, strtolower($p->name)) ||
                        str_contains($searchPptk, strtolower($p->code))) {
                        $matchedPptkId = $p->id;
                        break;
                    }
                }
                if (! $matchedPptkId && (str_contains($searchPptk, 'suratman') || str_contains($searchPptk, 'ratman'))) {
                    $matchedPptkId = $pptks['p ratman']->id ?? null;
                } elseif (! $matchedPptkId && (str_contains($searchPptk, 'ety') || str_contains($searchPptk, 'aulia'))) {
                    $matchedPptkId = $pptks['b aulia/ety']->id ?? null;
                } elseif (! $matchedPptkId && str_contains($searchPptk, 'gaya')) {
                    $matchedPptkId = $pptks['b gaya']->id ?? null;
                }

                // Classify type
                $type = 'general';
                if (str_contains($valC, '000006/LS/SETWAN') || str_contains($valG, 'UP 2026') || str_contains($valC, 'UP')) {
                    $type = 'sp2d_up';
                } elseif (str_contains($valC, 'GAJI') || str_contains($valG, 'gaji')) {
                    $type = 'sp2d_ls';
                } elseif (str_contains($valC, 'RESES') || str_contains($valG, 'reses')) {
                    $type = 'sp2d_tu';
                } elseif (str_contains($valC, 'GU') || str_contains($valG, 'gu')) {
                    $type = 'sp2d_gu';
                } elseif (str_contains($valC, 'panjar') || str_contains($valG, 'panjar') || str_contains($valC, 'CASH INDAH')) {
                    $type = 'panjar_disbursement';
                } elseif (str_contains($valC, 'pengembalian') || str_contains($valG, 'pengembalian')) {
                    $type = 'panjar_return';
                } elseif (str_contains($valC, 'BEL ') || str_contains($valC, 'Deb Rek')) {
                    $type = 'debet_rekening';
                } elseif (str_contains($valC, 'BUNGA') || str_contains($valC, 'JAGIR')) {
                    $type = 'bank_interest';
                }

                $recordData = [
                    'batch_id' => $batch?->id,
                    'transaction_date' => $transDate,
                    'document_number' => $this->extractDocNumber($valC),
                    'description' => $valC,
                    'transaction_type' => $type,
                    'payment_method' => 'bank_transfer',
                    'amount_gross' => $amount,
                    'amount_tax' => 0,
                    'amount_net' => $amount,
                    'flow_direction' => $isReceipt ? 'receipt' : 'disbursement',
                    'pptk_id' => $matchedPptkId,
                    'period_month' => $month,
                    'period_year' => 2026,
                    'is_verified' => true,
                    'status' => 'posted',
                    'notes' => $valG ?: null,
                    'meta' => [
                        'source_sheet' => $sheetName,
                        'source_row' => $r,
                        'tag' => $valG,
                        'raw_pptk' => $valL,
                    ],
                ];

                if ($execute) {
                    Transaction::create($recordData);
                }

                $records[] = $recordData;
            }
        }

        if ($execute && $batch) {
            $batch->update(['parsed_count' => count($records)]);
        }

        return [
            'total_records' => count($records),
            'total_receipts' => $totalReceipts,
            'total_disbursements' => $totalDisbursements,
            'records' => $records,
        ];
    }

    private function extractDocNumber(string $text): ?string
    {
        if (preg_match('/(\d{5,6}\/[A-Z0-9\/\.]+)/i', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
