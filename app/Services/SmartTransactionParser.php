<?php

namespace App\Services;

use App\Models\Pptk;
use Carbon\Carbon;
use Smalot\PdfParser\Parser as PdfParser;

class SmartTransactionParser
{
    /**
     * Parse raw copied text containing one or multiple transactions.
     */
    public function parseText(string $rawText): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($rawText));
        $transactions = [];
        $pptks = Pptk::all();

        // 1. Check if input is structured as tab/pipe delimited lines
        $structuredLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Check if line contains date, document number, or currency
            if (preg_match('/\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}/', $line) ||
                preg_match('/\d{5,}\/[A-Z0-9\/\.]+/', $line) ||
                preg_match('/Rp\s*[\d\.\,]+/i', $line) ||
                preg_match('/\b\d{1,3}(\.\d{3})+(,\d{2})?\b/', $line)) {
                $structuredLines[] = $line;
            }
        }

        if (count($structuredLines) > 0) {
            foreach ($structuredLines as $idx => $line) {
                $tx = $this->parseSingleLine($line, $idx + 1, $pptks);
                if ($tx) {
                    $transactions[] = $tx;
                }
            }
        }

        // If line-by-line found nothing or few results, try block-based parsing
        if (count($transactions) === 0) {
            $blocks = preg_split('/(?=\b(?:No|Nomor|SP2D|Tanggal|Bukti|TRF|000\d{3})\b)/i', $rawText);
            foreach ($blocks as $idx => $block) {
                $block = trim($block);
                if (strlen($block) < 15) {
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
     * Parse an uploaded PDF file by extracting its raw text.
     */
    public function parsePdf(string $filePath): array
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($filePath);
            $pages = $pdf->getPages();
            $allTransactions = [];

            foreach ($pages as $pageNum => $page) {
                $text = $page->getText();
                $pageTransactions = $this->parseText($text);
                foreach ($pageTransactions as &$tx) {
                    $tx['page_number'] = $pageNum + 1;
                }
                $allTransactions = array_merge($allTransactions, $pageTransactions);
            }

            return $allTransactions;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function parseSingleLine(string $line, int $index, $pptks): ?array
    {
        // Delimiters: tab, double space, semicolon, or pipe
        $parts = preg_split('/\t|\s{2,}|;|\|/', $line);
        $parts = array_values(array_filter(array_map('trim', $parts)));

        if (count($parts) < 2) {
            return $this->parseBlockText($line, $index, $pptks);
        }

        $date = null;
        $docNo = null;
        $description = '';
        $amounts = [];

        foreach ($parts as $part) {
            // Check date
            if (! $date && preg_match('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/', $part, $m)) {
                $date = $this->normalizeDate($m[1]);

                continue;
            }

            // Check doc number
            if (! $docNo && preg_match('/(\d{5,}\/[A-Z0-9\/\.\-]+)/i', $part, $m)) {
                $docNo = $m[1];

                continue;
            }

            // Check money amount
            if (preg_match('/^(?:Rp\.?\s*)?(\d{1,3}(?:\.\d{3})*(?:,\d{2})?|\d+)$/i', $part)) {
                $cleanNum = preg_replace('/[^\d]/', '', $part);
                if (strlen($cleanNum) > 0) {
                    $amounts[] = (float) $cleanNum;

                    continue;
                }
            }

            // Otherwise, append to description
            if (strlen($part) > 2 && ! is_numeric($part)) {
                $description .= ($description ? ' - ' : '').$part;
            }
        }

        if (count($amounts) === 0) {
            return null;
        }

        $gross = $amounts[0] ?? 0.0;
        $tax = (count($amounts) > 2) ? $amounts[1] : 0.0;
        $net = (count($amounts) > 2) ? $amounts[2] : ($gross - $tax);

        $matchedPptk = $this->matchPptk($description, $pptks);
        $direction = (stripos($description, 'penerimaan') !== false || stripos($description, 'sp2d') !== false || stripos($description, 'masuk') !== false)
            ? 'receipt' : 'disbursement';

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
            'pptk_name' => $matchedPptk?->display_name,
            'status' => 'draft',
            'confidence' => ($date && $gross > 0 && $description) ? 'high' : 'medium',
        ];
    }

    private function parseBlockText(string $text, int $index, $pptks): ?array
    {
        // Extract Date
        $date = null;
        if (preg_match('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/', $text, $m)) {
            $date = $this->normalizeDate($m[1]);
        }

        // Extract Doc Number
        $docNo = null;
        if (preg_match('/(\d{5,}\/[A-Z0-9\/\.\-]+)/i', $text, $m)) {
            $docNo = $m[1];
        }

        // Extract Amounts
        preg_match_all('/(?:Rp\.?\s*)?(\d{1,3}(?:\.\d{3})+(?:,\d{2})?)/i', $text, $matches);
        $amounts = [];
        if (! empty($matches[1])) {
            foreach ($matches[1] as $mStr) {
                $num = (float) preg_replace('/[^\d]/', '', $mStr);
                if ($num > 0) {
                    $amounts[] = $num;
                }
            }
        }

        if (count($amounts) === 0) {
            return null;
        }

        $gross = $amounts[0];
        $tax = (count($amounts) > 2) ? $amounts[1] : 0.0;
        $net = (count($amounts) > 2) ? $amounts[2] : ($gross - $tax);

        $cleanText = preg_replace('/\s+/', ' ', trim($text));
        $matchedPptk = $this->matchPptk($cleanText, $pptks);

        $direction = (stripos($cleanText, 'penerimaan') !== false || stripos($cleanText, 'sp2d') !== false || stripos($cleanText, 'incoming') !== false)
            ? 'receipt' : 'disbursement';

        return [
            'temp_id' => 'TMP-'.$index,
            'transaction_date' => $date ?: date('Y-m-d'),
            'document_number' => $docNo,
            'description' => substr($cleanText, 0, 200),
            'amount_gross' => $gross,
            'amount_tax' => $tax,
            'amount_net' => $net,
            'flow_direction' => $direction,
            'transaction_type' => $this->guessType($cleanText),
            'payment_method' => 'bank_transfer',
            'pptk_id' => $matchedPptk?->id,
            'pptk_name' => $matchedPptk?->display_name,
            'status' => 'draft',
            'confidence' => ($date && $gross > 0) ? 'high' : 'medium',
        ];
    }

    private function matchPptk(string $text, $pptks): ?Pptk
    {
        $search = strtolower($text);
        foreach ($pptks as $p) {
            if (str_contains($search, strtolower($p->display_name)) ||
                str_contains($search, strtolower($p->name)) ||
                str_contains($search, strtolower($p->code))) {
                return $p;
            }
        }
        if (str_contains($search, 'suratman') || str_contains($search, 'ratman')) {
            return $pptks->firstWhere('code', 'RATMAN');
        }
        if (str_contains($search, 'ety') || str_contains($search, 'aulia')) {
            return $pptks->firstWhere('code', 'AUL_ETY');
        }
        if (str_contains($search, 'gaya')) {
            return $pptks->firstWhere('code', 'GAYA');
        }
        if (str_contains($search, 'sigit')) {
            return $pptks->firstWhere('code', 'SIGIT');
        }

        return null;
    }

    private function guessType(string $text): string
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
        if (str_contains($t, 'panjar') || str_contains($t, 'ambil tunai')) {
            return 'panjar_disbursement';
        }
        if (str_contains($t, 'pengembalian')) {
            return 'panjar_return';
        }
        if (str_contains($t, 'deb rek') || str_contains($t, 'belanja') || str_contains($t, 'pembelian')) {
            return 'debet_rekening';
        }
        if (str_contains($t, 'bunga') || str_contains($t, 'jagir')) {
            return 'bank_interest';
        }

        return 'general';
    }

    private function normalizeDate(string $rawDate): string
    {
        try {
            $clean = str_replace('.', '/', $rawDate);

            return Carbon::parse($clean)->format('Y-m-d');
        } catch (\Exception $e) {
            return date('Y-m-d');
        }
    }
}
