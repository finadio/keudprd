<?php

namespace App\Services;

use App\Models\Pptk;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class SmartTransactionParser
{
    /**
     * Parse raw copied text containing one or multiple transactions (messy, tabular, or block format).
     */
    public function parseText(string $rawText): array
    {
        $rawText = trim($rawText);
        if (empty($rawText)) {
            return [];
        }

        $pptks = Pptk::all();
        $transactions = [];

        // Pre-cleaning: remove obvious page headers/footers from PDF/web exports
        $cleanedText = $this->cleanGarbageHeaders($rawText);
        $lines = preg_split('/\r\n|\r|\n/', $cleanedText);
        $filteredLines = [];
        foreach ($lines as $l) {
            $t = trim($l);
            if (! empty($t) && ! $this->isHeaderOrNoiseLine($t)) {
                $filteredLines[] = $t;
            }
        }

        // Strategy 1: Check if input contains structured single-line rows (Date + Amount on same line)
        $singleLineCandidates = [];
        foreach ($filteredLines as $line) {
            if ($this->hasDate($line) && $this->hasAmount($line)) {
                $singleLineCandidates[] = $line;
            }
        }

        // If most lines have both date and amount, parse row by row
        if (count($singleLineCandidates) >= max(1, count($filteredLines) * 0.4)) {
            foreach ($singleLineCandidates as $idx => $line) {
                $tx = $this->parseSingleLine($line, $idx + 1, $pptks);
                if ($tx) {
                    $transactions[] = $tx;
                }
            }
        }

        // Strategy 2: If Strategy 1 yielded 0 or very few items, run Smart Multi-Line Chunking
        if (count($transactions) === 0) {
            $chunks = $this->chunkMessyLines($filteredLines);
            foreach ($chunks as $idx => $chunk) {
                $tx = $this->parseBlockText($chunk, $idx + 1, $pptks);
                if ($tx) {
                    $transactions[] = $tx;
                }
            }
        }

        // Strategy 3: Fallback Regex Boundary Splitting (e.g. raw unstructured text block)
        if (count($transactions) === 0) {
            $blocks = preg_split('/(?=\b(?:\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}|SP2D|SPM|NPD|TRF\b|000\d{3}\/))/i', $cleanedText);
            foreach ($blocks as $idx => $block) {
                $block = trim($block);
                if (strlen($block) < 10) {
                    continue;
                }
                $tx = $this->parseBlockText($block, $idx + 1, $pptks);
                if ($tx) {
                    $transactions[] = $tx;
                }
            }
        }

        return $transactions;
    }

    /**
     * Universal Document File Parser: Supports PDF, Excel (.xlsx/.xls), CSV/TSV, Word (.docx), and Images/Scans (.png/.jpg/.webp).
     */
    public function parseFile(string $filePath, string $originalName, ?string $mimeType = null): array
    {
        if (! file_exists($filePath)) {
            return [];
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // 1. Excel workbooks (.xlsx, .xls, .ods)
        if (in_array($ext, ['xlsx', 'xls', 'ods'])) {
            return $this->parseExcelFile($filePath);
        }

        // 2. CSV / TSV / Plain text
        if (in_array($ext, ['csv', 'tsv', 'txt'])) {
            $content = file_get_contents($filePath);

            return $this->parseText($content);
        }

        // 3. Digital PDF files
        if ($ext === 'pdf') {
            return $this->parsePdfFile($filePath);
        }

        // 4. Scanned Images & Photos (.png, .jpg, .jpeg, .webp, .bmp, .tiff)
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'bmp', 'tiff'])) {
            return $this->parseImageWithOcr($filePath);
        }

        // 5. Word Documents (.docx, .doc)
        if (in_array($ext, ['docx', 'doc'])) {
            return $this->parseDocxFile($filePath);
        }

        // Fallback: try reading as text
        $content = @file_get_contents($filePath);
        if ($content) {
            return $this->parseText($content);
        }

        return [];
    }

    /**
     * Legacy PDF Parser compatibility wrapper.
     */
    public function parsePdf(string $filePath): array
    {
        return $this->parsePdfFile($filePath);
    }

    /**
     * Parse Excel spreadsheet into transactions using PhpSpreadsheet.
     */
    protected function parseExcelFile(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $maxRow = min($sheet->getHighestRow(), 1500); // safety cap
            $pptks = Pptk::all();
            $transactions = [];

            for ($row = 1; $row <= $maxRow; $row++) {
                $cells = [];
                for ($col = 'A'; $col <= 'Z'; $col++) {
                    $val = trim((string) $sheet->getCell("{$col}{$row}")->getFormattedValue());
                    if ($val !== '') {
                        $cells[] = $val;
                    }
                }

                if (count($cells) < 2) {
                    continue;
                }

                $rowLine = implode(" \t ", $cells);
                if ($this->hasDate($rowLine) && $this->hasAmount($rowLine)) {
                    $tx = $this->parseSingleLine($rowLine, count($transactions) + 1, $pptks);
                    if ($tx) {
                        $transactions[] = $tx;
                    }
                }
            }

            return $transactions;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Parse PDF: attempts fast native text extraction, falls back to Python RapidOCR for scans.
     */
    protected function parsePdfFile(string $filePath): array
    {
        $transactions = [];

        // Step 1: Attempt digital PDF text extraction
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($filePath);
            $pages = $pdf->getPages();
            $fullText = '';

            foreach ($pages as $pageNum => $page) {
                $text = $page->getText();
                if (strlen(trim($text)) > 20) {
                    $fullText .= "\n".$text;
                }
            }

            if (strlen(trim($fullText)) > 50) {
                $transactions = $this->parseText($fullText);
            }
        } catch (\Throwable $e) {
            // PDF parser error, continue to OCR fallback
        }

        // Step 2: If digital extraction yielded 0 items, run Deep Learning OCR (RapidOCR on scanned pages)
        if (count($transactions) === 0) {
            $ocrText = $this->runPythonExtractor($filePath);
            if (! empty($ocrText)) {
                $transactions = $this->parseText($ocrText);
            }
        }

        return $transactions;
    }

    /**
     * Parse Scanned Images or Photos using Python RapidOCR.
     */
    protected function parseImageWithOcr(string $filePath): array
    {
        $ocrText = $this->runPythonExtractor($filePath);
        if (empty($ocrText)) {
            return [];
        }

        return $this->parseText($ocrText);
    }

    /**
     * Parse Word Document using Python extractor.
     */
    protected function parseDocxFile(string $filePath): array
    {
        $docxText = $this->runPythonExtractor($filePath);
        if (empty($docxText)) {
            return [];
        }

        return $this->parseText($docxText);
    }

    /**
     * Helper to invoke Python document_extractor.py with error resilience.
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
     * Groups fragmented multi-line copy-pastes into cohesive transaction blocks.
     */
    protected function chunkMessyLines(array $lines): array
    {
        $chunks = [];
        $currentChunk = [];

        foreach ($lines as $line) {
            // A new block boundary typically starts with a Date, Document Number, or row number
            $isNewBoundary = $this->isBoundaryLine($line);

            if ($isNewBoundary && count($currentChunk) > 0) {
                $joined = implode("\n", $currentChunk);
                if ($this->hasAmount($joined)) {
                    $chunks[] = $joined;
                }
                $currentChunk = [];
            }

            $currentChunk[] = $line;
        }

        if (count($currentChunk) > 0) {
            $joined = implode("\n", $currentChunk);
            if ($this->hasAmount($joined)) {
                $chunks[] = $joined;
            }
        }

        return $chunks;
    }

    /**
     * Check if a line signifies the start of a new transaction entry.
     */
    protected function isBoundaryLine(string $line): bool
    {
        // 1. Starts with date (e.g. 15/10/2026, 2026-10-15, 15 Okt 2026)
        if (preg_match('/^\s*(?:\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}|\d{4}[\/\-]\d{2}[\/\-]\d{2}|\d{1,2}\s+(?:Jan|Feb|Mar|Apr|Mei|Jun|Jul|Agu|Ags|Sep|Okt|Nov|Des)[a-z]*\s+\d{2,4})/i', $line)) {
            return true;
        }

        // 2. Starts with sequential row index: e.g. "1.", "1 ", "[1]"
        if (preg_match('/^\s*(?:\[\d+\]|\d+[\.\)\-]\s+)/', $line)) {
            return true;
        }

        // 3. Starts with official doc code
        if (preg_match('/^\s*(?:SP2D|SPM|NPD|BKT|TRF|CMS)\b/i', $line)) {
            return true;
        }

        return false;
    }

    /**
     * Parse a single tabular or delimited line.
     */
    protected function parseSingleLine(string $line, int $index, $pptks): ?array
    {
        $parts = preg_split('/\t|\s{2,}|;|\|/', $line);
        $parts = array_values(array_filter(array_map('trim', $parts)));

        if (count($parts) < 2) {
            return $this->parseBlockText($line, $index, $pptks);
        }

        $date = null;
        $docNo = null;
        $descriptionParts = [];
        $amounts = [];
        $hasCredit = false;
        $hasDebit = false;

        foreach ($parts as $part) {
            // Check Credit / Debit indicator
            if (preg_match('/^(?:CR|KREDIT|PENERIMAAN)$/i', $part)) {
                $hasCredit = true;

                continue;
            }
            if (preg_match('/^(?:DB|DEBET|PENGELUARAN)$/i', $part)) {
                $hasDebit = true;

                continue;
            }

            // Check Date
            if (! $date && $this->extractDate($part)) {
                $date = $this->extractDate($part);

                continue;
            }

            // Check Document Number (SP2D, SPM, NPD, etc.)
            if (! $docNo && preg_match('/(\b\d{3,}\/[A-Z0-9\/\.\-]+\b|\b[A-Z]{2,}\/\d+\/[A-Z0-9\/\.\-]+\b)/i', $part, $m)) {
                $docNo = $m[1];

                continue;
            }

            // Check Money Amount
            $num = $this->extractNumericAmount($part);
            if ($num !== null && $num > 0) {
                $amounts[] = $num;

                continue;
            }

            // Otherwise, append to description
            if (strlen($part) > 1 && ! is_numeric($part)) {
                $descriptionParts[] = $part;
            }
        }

        if (count($amounts) === 0) {
            return null;
        }

        $description = implode(' - ', $descriptionParts);
        $gross = $amounts[0] ?? 0.0;
        $tax = (count($amounts) >= 3) ? $amounts[1] : 0.0;
        $net = (count($amounts) >= 3) ? $amounts[2] : ($gross - $tax);

        $matchedPptk = $this->matchPptk($description.' '.$line, $pptks);
        $direction = $hasCredit ? 'receipt' : ($hasDebit ? 'disbursement' : $this->guessDirection($description));

        return [
            'temp_id' => 'TMP-'.$index,
            'transaction_date' => $date ?: date('Y-m-d'),
            'document_number' => $docNo,
            'description' => $description ?: 'Transaksi Terdeteksi #'.$index,
            'amount_gross' => $gross,
            'amount_tax' => $tax,
            'amount_net' => $net,
            'flow_direction' => $direction,
            'transaction_type' => $this->guessType($description),
            'payment_method' => 'bank_transfer',
            'pptk_id' => $matchedPptk?->id,
            'pptk_code' => $matchedPptk?->code,
            'pptk_name' => $matchedPptk?->display_name,
            'status' => 'draft',
            'confidence' => ($date && $gross > 0 && ! empty($description)) ? 'high' : 'medium',
        ];
    }

    /**
     * Parse unstructured or multi-line block text.
     */
    protected function parseBlockText(string $text, int $index, $pptks): ?array
    {
        $date = $this->extractDate($text);

        // Document Number
        $docNo = null;
        if (preg_match('/(\b\d{3,}\/[A-Z0-9\/\.\-]+\b|\b[A-Z]{2,}\/\d+\/[A-Z0-9\/\.\-]+\b)/i', $text, $m)) {
            $docNo = $m[1];
        }

        // Extract Amounts
        $amounts = $this->extractAllAmounts($text);
        if (count($amounts) === 0) {
            return null;
        }

        $gross = $amounts[0];
        $tax = (count($amounts) >= 3) ? $amounts[1] : 0.0;
        $net = (count($amounts) >= 3) ? $amounts[2] : ($gross - $tax);

        // Clean description
        $cleanDescription = $this->cleanDescription($text, $docNo, $date);
        $matchedPptk = $this->matchPptk($text, $pptks);
        $direction = $this->guessDirection($text);

        return [
            'temp_id' => 'TMP-'.$index,
            'transaction_date' => $date ?: date('Y-m-d'),
            'document_number' => $docNo,
            'description' => substr($cleanDescription, 0, 250) ?: 'Transaksi Terdeteksi #'.$index,
            'amount_gross' => $gross,
            'amount_tax' => $tax,
            'amount_net' => $net,
            'flow_direction' => $direction,
            'transaction_type' => $this->guessType($text),
            'payment_method' => 'bank_transfer',
            'pptk_id' => $matchedPptk?->id,
            'pptk_code' => $matchedPptk?->code,
            'pptk_name' => $matchedPptk?->display_name,
            'status' => 'draft',
            'confidence' => ($date && $gross > 0) ? 'high' : 'medium',
        ];
    }

    /**
     * Extract date from varied formats including Indonesian month names.
     */
    protected function extractDate(string $str): ?string
    {
        // 1. Numeric date: DD/MM/YYYY, DD-MM-YYYY, DD.MM.YYYY
        if (preg_match('/\b(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})\b/', $str, $m)) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            if ($y < 100) {
                $y += 2000;
            }
            if ($d > 0 && $d <= 31 && $mo > 0 && $mo <= 12 && $y >= 2020 && $y <= 2035) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }

        // 2. ISO date: YYYY-MM-DD
        if (preg_match('/\b(202\d)[\/\-](\d{1,2})[\/\-](\d{1,2})\b/', $str, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // 3. Indonesian text date: "15 Oktober 2026", "24-Okt-2026", "10 Nov 26"
        $indoMonths = [
            'januari' => 1, 'jan' => 1,
            'februari' => 2, 'feb' => 2,
            'maret' => 3, 'mar' => 3,
            'april' => 4, 'apr' => 4,
            'mei' => 5,
            'juni' => 6, 'jun' => 6,
            'juli' => 7, 'jul' => 7,
            'agustus' => 8, 'agu' => 8, 'ags' => 8,
            'september' => 9, 'sep' => 9,
            'oktober' => 10, 'okt' => 10,
            'november' => 11, 'nov' => 11,
            'desember' => 12, 'des' => 12,
        ];

        $monthPattern = implode('|', array_keys($indoMonths));
        if (preg_match('/(\d{1,2})[\s\-]+('.$monthPattern.')[a-z]*[\s\-]+(\d{2,4})/i', $str, $m)) {
            $d = (int) $m[1];
            $moName = strtolower($m[2]);
            $mo = $indoMonths[$moName] ?? 1;
            $y = (int) $m[3];
            if ($y < 100) {
                $y += 2000;
            }

            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }

        return null;
    }

    /**
     * Extract a single clean numeric amount from a token.
     */
    protected function extractNumericAmount(string $part): ?float
    {
        $trim = trim($part);
        // Exclude dates, doc numbers, phone numbers
        if (preg_match('/[\/\-]/', $trim) && ! str_starts_with($trim, '-')) {
            return null;
        }

        // Matches currency like "Rp 15.000.000", "15.000.000,00", "15000000"
        if (preg_match('/^(?:Rp\.?\s*)?([\-]?[0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?|[\-]?[0-9]+(?:[\.,][0-9]{2})?)$/i', $trim)) {
            $hasCents = preg_match('/(?:,|\.)(\d{2})$/', $trim, $centsMatch);
            if ($hasCents && (str_contains($trim, '.') || str_contains($trim, ','))) {
                $trimWithoutCents = preg_replace('/(?:,|\.)\d{2}$/', '', $trim);
                $cleaned = preg_replace('/[^\d]/', '', $trimWithoutCents);
                $cents = (float) $centsMatch[1] / 100.0;
                $val = (float) $cleaned + $cents;
            } else {
                $cleaned = preg_replace('/[^\d]/', '', $trim);
                $val = (float) $cleaned;
            }

            if ($val >= 1000) { // filter out small line numbers or IDs
                return $val;
            }
        }

        return null;
    }

    /**
     * Extract all numeric currency amounts found in a block of text.
     */
    protected function extractAllAmounts(string $text): array
    {
        $amounts = [];

        // Match patterns like "Rp 15.000.000", "15.000.000,00", "15,000,000.00"
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

    /**
     * Fuzzy matching for PPTK across all DPRD officials.
     */
    protected function matchPptk(string $text, $pptks): ?Pptk
    {
        $search = strtolower($text);

        // Specific high-priority nickname mappings
        if (str_contains($search, 'suratman') || str_contains($search, 'ratman')) {
            return $pptks->firstWhere('code', 'RATMAN');
        }
        if (str_contains($search, 'ety') || str_contains($search, 'aulia ety') || str_contains($search, 'aul_ety')) {
            return $pptks->firstWhere('code', 'AUL_ETY');
        }
        if (str_contains($search, 'rina') || str_contains($search, 'aulia rina') || str_contains($search, 'aul_rina')) {
            return $pptks->firstWhere('code', 'AUL_RINA');
        }
        if (str_contains($search, 'gaya') || str_contains($search, 'gayatri')) {
            return $pptks->firstWhere('code', 'GAYA');
        }
        if (str_contains($search, 'sigit') || str_contains($search, 'yunianto')) {
            return $pptks->firstWhere('code', 'SIGIT');
        }
        if (str_contains($search, 'syesa') || str_contains($search, 'syesaria')) {
            return $pptks->firstWhere('code', 'SGNG_SYESA');
        }
        if (str_contains($search, 'emi') || str_contains($search, 'sugeng emi')) {
            return $pptks->firstWhere('code', 'SGNG_EMI');
        }
        if (str_contains($search, 'sri lestari') || str_contains($search, 'sri_l')) {
            return $pptks->firstWhere('code', 'SRI_L');
        }
        if (str_contains($search, 'irna') || str_contains($search, 'irnawatingrum')) {
            return $pptks->firstWhere('code', 'IRNA');
        }

        // Generic search against display_name, name, code
        foreach ($pptks as $p) {
            if (str_contains($search, strtolower($p->display_name)) ||
                str_contains($search, strtolower($p->name)) ||
                str_contains($search, strtolower($p->code))) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Guess flow direction: receipt (penerimaan) vs disbursement (pengeluaran).
     */
    protected function guessDirection(string $text): string
    {
        $t = strtolower($text);
        if (str_contains($t, 'penerimaan') ||
            str_contains($t, 'sp2d') ||
            str_contains($t, 'kredit') ||
            str_contains($t, ' cr') ||
            str_contains($t, 'masuk') ||
            str_contains($t, 'setor') ||
            str_contains($t, 'bunga')) {
            return 'receipt';
        }

        return 'disbursement';
    }

    /**
     * Guess transaction category type.
     */
    protected function guessType(string $text): string
    {
        $t = strtolower($text);
        if (str_contains($t, 'up 2026') || str_contains($t, 'uang persediaan')) {
            return 'sp2d_up';
        }
        if (str_contains($t, 'gaji') || str_contains($t, 'tunjangan') || str_contains($t, 'bop')) {
            return 'sp2d_ls';
        }
        if (str_contains($t, 'reses')) {
            return 'sp2d_tu';
        }
        if (str_contains($t, 'spj') || str_contains($t, 'gu')) {
            return 'sp2d_gu';
        }
        if (str_contains($t, 'panjar') || str_contains($t, 'ambil tunai') || str_contains($t, 'tarik tunai')) {
            return 'panjar_disbursement';
        }
        if (str_contains($t, 'pengembalian') || str_contains($t, 'sisa')) {
            return 'panjar_return';
        }
        if (str_contains($t, 'deb rek') || str_contains($t, 'belanja') || str_contains($t, 'pembelian') || str_contains($t, 'giro')) {
            return 'debet_rekening';
        }
        if (str_contains($t, 'bunga') || str_contains($t, 'jagir')) {
            return 'bank_interest';
        }

        return 'general';
    }

    protected function hasDate(string $line): bool
    {
        return $this->extractDate($line) !== null;
    }

    protected function hasAmount(string $line): bool
    {
        return count($this->extractAllAmounts($line)) > 0;
    }

    protected function cleanGarbageHeaders(string $text): string
    {
        $patterns = [
            '/PEMERINTAH KABUPATEN BANYUMAS/i',
            '/SEKRETARIAT DEWAN PERWAKILAN RAKYAT DAERAH/i',
            '/BUKU KAS UMUM/i',
            '/LAPORAN PENATAUSAHAAN KEUANGAN/i',
            '/Halaman\s*:\s*\d+\s*(?:dari|\/)\s*\d+/i',
            '/Dicetak\s*pada\s*:\s*[\d\:\-\s]+/i',
        ];

        return preg_replace($patterns, '', $text);
    }

    protected function isHeaderOrNoiseLine(string $line): bool
    {
        $l = strtolower(trim($line));
        if (strlen($l) < 3) {
            return true;
        }

        $noisePhrases = [
            'no tanggal uraian debet kredit saldo',
            'no. tanggal uraian penerimaan pengeluaran',
            'daftar transaksi buku kas',
            'rekening koran bank jateng',
            'halaman', 'dicetak pada',
            'total jumlah', 'saldo awal', 'saldo akhir',
        ];

        foreach ($noisePhrases as $np) {
            if (str_contains($l, $np)) {
                return true;
            }
        }

        return false;
    }

    protected function cleanDescription(string $text, ?string $docNo, ?string $date): string
    {
        $clean = $text;
        if ($docNo) {
            $clean = str_replace($docNo, '', $clean);
        }
        // Remove raw numbers that looked like amounts
        $clean = preg_replace('/(?:Rp\.?\s*)?\b\d{1,3}(?:\.\d{3})+(?:,\d{2})?\b/i', '', $clean);
        $clean = preg_replace('/[0-9]{1,2}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{2,4}/', '', $clean);
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        return $clean;
    }
}
