<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Pptk;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class SmartSipdParser
{
    /**
     * Universal File Parser for SIPD SPJ Fungsional:
     * Supports Excel (.xlsx, .xls, .ods), PDF (digital & scan), Word (.docx), CSV/TXT, and Images/Scans (.png, .jpg, .webp).
     */
    public function parseFile(string $filePath, string $originalName, ?string $mimeType = null, int $year = 2026): array
    {
        if (! file_exists($filePath)) {
            return [];
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // 1. Excel workbooks (.xlsx, .xls, .ods)
        if (in_array($ext, ['xlsx', 'xls', 'ods'])) {
            return $this->parseExcelFile($filePath, $year);
        }

        // 2. CSV / TSV / Plain text
        if (in_array($ext, ['csv', 'tsv', 'txt'])) {
            $content = file_get_contents($filePath);

            return $this->parseText($content, $year);
        }

        // 3. PDF files (digital + OCR fallback)
        if ($ext === 'pdf') {
            return $this->parsePdfFile($filePath, $year);
        }

        // 4. Scanned Images & Photos (.png, .jpg, .jpeg, .webp, .bmp, .tiff)
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'bmp', 'tiff'])) {
            return $this->parseImageWithOcr($filePath, $year);
        }

        // 5. Word Documents (.docx, .doc)
        if (in_array($ext, ['docx', 'doc'])) {
            $docxText = $this->runPythonExtractor($filePath);

            return $this->parseText($docxText, $year);
        }

        // Fallback: try reading as text
        $content = @file_get_contents($filePath);
        if ($content) {
            return $this->parseText($content, $year);
        }

        return [];
    }

    /**
     * Parse Excel spreadsheet into activities.
     */
    public function parseExcelFile(string $filePath, int $year = 2026): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getSheetByName('2 Program dan Subkegiatan')
                ?? $spreadsheet->getSheetByName('1 LPJ Lengkap')
                ?? $spreadsheet->getActiveSheet();

            $maxRow = min($sheet->getHighestRow(), 1500);
            $pptks = Pptk::all();
            $activities = [];

            $currentProgCode = '';
            $currentProgName = '';

            $getVal = function ($cellCoordinate) use ($sheet) {
                try {
                    $val = $sheet->getCell($cellCoordinate)->getCalculatedValue();

                    return is_numeric($val) ? (float) $val : 0.0;
                } catch (\Throwable) {
                    $val = $sheet->getCell($cellCoordinate)->getValue();

                    return is_numeric($val) ? (float) $val : 0.0;
                }
            };

            for ($row = 4; $row <= $maxRow; $row++) {
                $col1 = trim((string) $sheet->getCell("A{$row}")->getValue());
                $col2 = trim((string) $sheet->getCell("B{$row}")->getValue());

                if (empty($col1) && empty($col2)) {
                    continue;
                }

                // Program header (e.g. 4.02.01 or 4.02.02)
                if (str_starts_with($col1, '4.02') && substr_count($col1, '.') === 2) {
                    $currentProgCode = $col1;
                    $currentProgName = $col2;

                    continue;
                }

                // Subkegiatan row (e.g. 4.02.01.2.01.0001)
                if (str_starts_with($col1, '4.02') && substr_count($col1, '.') >= 3) {
                    $pagu = $getVal("C{$row}");
                    $totSpj = $getVal("M{$row}");
                    if ($totSpj == 0) {
                        // Check other possible SPJ columns
                        $totSpj = $getVal("D{$row}") ?: $getVal("F{$row}");
                    }
                    $sisaPagu = $pagu - $totSpj;

                    $matchedPptk = $this->matchPptk($col2, $pptks);

                    $activities[] = [
                        'temp_id' => 'ACT-'.(count($activities) + 1),
                        'code' => $col1,
                        'name' => $col2,
                        'program_code' => $currentProgCode,
                        'program_name' => $currentProgName,
                        'pagu_budget' => $pagu,
                        'spj_sipd_total' => $totSpj,
                        'sisa_pagu_sipd' => $sisaPagu,
                        'pptk_id' => $matchedPptk?->id,
                        'pptk_name' => $matchedPptk?->display_name,
                        'selected' => true,
                    ];
                }
            }

            return $activities;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Parse raw copied text (from SIPD web table, Excel copy-paste, or messaging).
     * Handles messy line breaks, stripped headers, and auto-alignment.
     */
    public function parseText(string $rawText, int $year = 2026): array
    {
        $rawText = trim($rawText);
        if (empty($rawText)) {
            return [];
        }

        $pptks = Pptk::all();
        $activities = [];

        // Pre-cleaning: remove obvious headers
        $cleaned = $this->cleanNoise($rawText);
        $lines = preg_split('/\r\n|\r|\n/', $cleaned);

        $currentProgCode = '4.02.01';
        $currentProgName = 'PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA';

        // Check if rows are single-line (Code + Name + Amounts)
        $buffer = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if (empty($t) || $this->isNoiseLine($t)) {
                continue;
            }

            // Track Program header
            if (preg_match('/^(4\.02\.\d{2})\s*[-–—]?\s*(.*)$/i', $t, $m)) {
                $currentProgCode = $m[1];
                if (! empty($m[2])) {
                    $currentProgName = trim($m[2]);
                }

                continue;
            }

            // Detect new subkegiatan boundary (starts with 4.02.xx.x.xx.xxxx or similar)
            if (preg_match('/^(4\.02(?:\.\d+){3,})\b/i', $t)) {
                if (! empty($buffer)) {
                    $item = $this->processBuffer(implode(' ', $buffer), $currentProgCode, $currentProgName, $pptks, count($activities) + 1);
                    if ($item) {
                        $activities[] = $item;
                    }
                    $buffer = [];
                }
                $buffer[] = $t;
            } else {
                if (! empty($buffer)) {
                    $buffer[] = $t;
                } else {
                    // Try parsing line without strict code prefix (e.g. matching existing activity name)
                    $item = $this->processBuffer($t, $currentProgCode, $currentProgName, $pptks, count($activities) + 1);
                    if ($item) {
                        $activities[] = $item;
                    }
                }
            }
        }

        // Flush remaining buffer
        if (! empty($buffer)) {
            $item = $this->processBuffer(implode(' ', $buffer), $currentProgCode, $currentProgName, $pptks, count($activities) + 1);
            if ($item) {
                $activities[] = $item;
            }
        }

        return $activities;
    }

    /**
     * Process a raw accumulated text buffer into an Activity entry.
     */
    protected function processBuffer(string $text, string $progCode, string $progName, $pptks, int $index): ?array
    {
        $code = null;
        $name = null;

        // 1. Extract Code
        if (preg_match('/(4\.02(?:\.\d+){3,})/i', $text, $m)) {
            $code = $m[1];
            // Remove code from text
            $text = trim(str_replace($code, '', $text));
        }

        // 2. Extract Amounts (Pagu, SPJ)
        $amounts = $this->extractAllAmounts($text);
        if (count($amounts) === 0) {
            return null;
        }

        $pagu = $amounts[0];
        $spj = (count($amounts) >= 2) ? $amounts[1] : 0.0;
        $sisa = (count($amounts) >= 3) ? $amounts[2] : ($pagu - $spj);

        // 3. Extract Clean Name
        // Remove amounts and noise numbers from text
        $name = $this->cleanActivityName($text);

        // If code wasn't found, try matching with existing activities in database
        if (! $code && ! empty($name)) {
            $existing = Activity::where('name', 'like', "%{$name}%")->first();
            if ($existing) {
                $code = $existing->code;
                $progCode = $existing->program_code ?: $progCode;
                $progName = $existing->program_name ?: $progName;
            }
        }

        if (! $code) {
            $code = '4.02.01.2.'.str_pad((string) $index, 4, '0', STR_PAD_LEFT);
        }

        if (empty($name) || strlen($name) < 4) {
            $name = 'Subkegiatan #'.$code;
        }

        $matchedPptk = $this->matchPptk($name, $pptks);

        return [
            'temp_id' => 'ACT-'.$index,
            'code' => $code,
            'name' => $name,
            'program_code' => $progCode,
            'program_name' => $progName,
            'pagu_budget' => $pagu,
            'spj_sipd_total' => $spj,
            'sisa_pagu_sipd' => $sisa,
            'pptk_id' => $matchedPptk?->id,
            'pptk_name' => $matchedPptk?->display_name,
            'selected' => true,
        ];
    }

    /**
     * Parse digital or scanned PDF.
     */
    protected function parsePdfFile(string $filePath, int $year = 2026): array
    {
        $activities = [];

        // Try fast digital PDF text extraction
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($filePath);
            $text = '';
            foreach ($pdf->getPages() as $page) {
                $t = $page->getText();
                if (strlen(trim($t)) > 20) {
                    $text .= "\n".$t;
                }
            }

            if (strlen(trim($text)) > 50) {
                $activities = $this->parseText($text, $year);
            }
        } catch (\Throwable) {
            // PDF parser error, continue to OCR fallback
        }

        // If digital text yielded 0 items, run Deep Learning RapidOCR
        if (count($activities) === 0) {
            $ocrText = $this->runPythonExtractor($filePath);
            if (! empty($ocrText)) {
                $activities = $this->parseText($ocrText, $year);
            }
        }

        return $activities;
    }

    /**
     * Parse scanned images / photos using RapidOCR.
     */
    protected function parseImageWithOcr(string $filePath, int $year = 2026): array
    {
        $ocrText = $this->runPythonExtractor($filePath);
        if (empty($ocrText)) {
            return [];
        }

        return $this->parseText($ocrText, $year);
    }

    /**
     * Helper to invoke Python document_extractor.py.
     */
    protected function runPythonExtractor(string $filePath): string
    {
        $pythonBin = 'C:\\Users\\Fina Julianti\\AppData\\Local\\Programs\\Python\\Python312\\python.exe';
        if (! file_exists($pythonBin)) {
            $pythonBin = 'python';
        }

        $scriptPath = base_path('scripts/document_extractor.py');
        if (! file_exists($scriptPath)) {
            return '';
        }

        $cmd = escapeshellarg($pythonBin).' '.escapeshellarg($scriptPath).' '.escapeshellarg($filePath);
        $output = @shell_exec($cmd);

        if (! $output) {
            return '';
        }

        $data = json_decode($output, true);
        if (! empty($data['success']) && ! empty($data['text'])) {
            return (string) $data['text'];
        }

        return '';
    }

    /**
     * Extract all numeric currency amounts found in a text block, accounting for cents.
     */
    protected function extractAllAmounts(string $text): array
    {
        $amounts = [];

        // Match patterns like "Rp 15.000.000", "15.000.000,00", "15,000,000.00", "15000000"
        preg_match_all('/(?:Rp\.?\s*)?(\b\d{1,3}(?:\.\d{3})+(?:,\d{2})?\b|\b\d{1,3}(?:,\d{3})+(?:\.\d{2})?\b|\b\d{6,}\b)/i', $text, $matches);

        if (! empty($matches[1])) {
            foreach ($matches[1] as $mStr) {
                $hasCents = preg_match('/(?:,|\.)(\d{2})$/', $mStr, $cm);
                if ($hasCents && (str_contains($mStr, '.') || str_contains($mStr, ','))) {
                    $withoutCents = preg_replace('/(?:,|\.)\d{2}$/', '', $mStr);
                    $num = (float) preg_replace('/[^\d]/', '', $withoutCents);
                } else {
                    $clean = preg_replace('/[^\d]/', '', $mStr);
                    $num = (float) $clean;
                }
                if ($num >= 1000) {
                    $amounts[] = $num;
                }
            }
        }

        return array_values(array_unique($amounts));
    }

    protected function cleanNoise(string $text): string
    {
        $patterns = [
            '/PEMERINTAH KABUPATEN BANYUMAS/i',
            '/SEKRETARIAT DEWAN PERWAKILAN RAKYAT DAERAH/i',
            '/LAPORAN PERTANGGUNGJAWABAN SKPD/i',
            '/SPJ FUNGSIONAL/i',
            '/SIPD Penatausahaan/i',
            '/Halaman\s*:\s*\d+\s*(?:dari|\/)\s*\d+/i',
            '/Dicetak\s*pada\s*:\s*[\d\:\-\s]+/i',
            '/Kode Rekening\s+Uraian\s+Jumlah Anggaran/i',
        ];

        return preg_replace($patterns, '', $text);
    }

    protected function isNoiseLine(string $line): bool
    {
        $l = strtolower(trim($line));
        if (strlen($l) < 3) {
            return true;
        }

        $noisePhrases = [
            'kode rekening', 'jumlah anggaran', 'uraian', 'bulan lalu', 'bulan ini',
            'spj – ls', 'spj - up', 's.d. bulan ini', 'sisa pagu', 'total belanja',
            'belanja operasi', 'belanja modal', 'sekretariat dprd',
        ];

        foreach ($noisePhrases as $np) {
            if ($l === $np || str_starts_with($l, $np)) {
                return true;
            }
        }

        return false;
    }

    protected function cleanActivityName(string $text): string
    {
        // Remove currency numbers
        $clean = preg_replace('/(?:Rp\.?\s*)?\b\d{1,3}(?:\.\d{3})+(?:,\d{2})?\b/i', '', $text);
        $clean = preg_replace('/\b\d{6,}\b/', '', $clean);
        $clean = preg_replace('/^[\d\s\.\-–—\t]+/', '', $clean);
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        return $clean;
    }

    /**
     * Map default PPTK based on subkegiatan name keywords.
     */
    protected function matchPptk(string $subkegiatanName, $pptks): ?Pptk
    {
        $lower = strtolower($subkegiatanName);

        $ratman = $pptks->firstWhere('code', 'RATMAN');
        $sigit = $pptks->firstWhere('code', 'SIGIT');
        $aulEty = $pptks->firstWhere('code', 'AUL_ETY');
        $gaya = $pptks->firstWhere('code', 'GAYA');
        $aulRina = $pptks->firstWhere('code', 'AUL_RINA');
        $sgngSyesa = $pptks->firstWhere('code', 'SGNG_SYESA');
        $sgngEmi = $pptks->firstWhere('code', 'SGNG_EMI');
        $sriL = $pptks->firstWhere('code', 'SRI_L');
        $irna = $pptks->firstWhere('code', 'IRNA');

        if (str_contains($lower, 'gaji') || str_contains($lower, 'perencanaan') || str_contains($lower, 'keuangan dprd')) {
            return $aulEty;
        }
        if (str_contains($lower, 'logistik') || str_contains($lower, 'rumah tangga') || str_contains($lower, 'kunjungan tamu') || str_contains($lower, 'cetakan')) {
            return $ratman;
        }
        if (str_contains($lower, 'peralatan') || str_contains($lower, 'mebel') || str_contains($lower, 'pemeliharaan')) {
            return $aulRina;
        }
        if (str_contains($lower, 'publikasi') || str_contains($lower, 'hubungan masyarakat') || str_contains($lower, 'dokumentasi')) {
            return $gaya;
        }
        if (str_contains($lower, 'peraturan daerah') || str_contains($lower, 'perda') || str_contains($lower, 'tata tertib') || str_contains($lower, 'naskah')) {
            return $sigit;
        }
        if (str_contains($lower, 'reses') || str_contains($lower, 'pokok-pokok pikiran') || str_contains($lower, 'kua') || str_contains($lower, 'apbd')) {
            return $sgngSyesa;
        }
        if (str_contains($lower, 'pengawasan') || str_contains($lower, 'infrastruktur') || str_contains($lower, 'kesejahteraan') || str_contains($lower, 'perekonomian')) {
            return $sgngEmi;
        }
        if (str_contains($lower, 'kode etik') || str_contains($lower, 'laporan kinerja')) {
            return $sriL;
        }

        return $irna ?? $aulEty;
    }
}
