<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\Pptk;
use App\Models\Transaction;
use App\Services\SmartTransactionParser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportTransactionController extends Controller
{
    /**
     * Show Import Transaksi view.
     */
    public function index(): Response
    {
        $batches = ImportBatch::with('user')
            ->latest()
            ->take(15)
            ->get();

        $pptks = Pptk::where('is_active', true)->orderBy('sort_order')->get();

        return Inertia::render('Import/Index', [
            'recent_batches' => $batches,
            'pptks' => $pptks,
        ]);
    }

    /**
     * Process Paste Text or PDF Upload and return parsed transactions for preview.
     */
    public function parse(Request $request, SmartTransactionParser $parser)
    {
        $mode = $request->input('mode', 'text'); // 'text' or 'pdf'
        $parsedItems = [];

        if ($mode === 'text') {
            $request->validate([
                'raw_text' => 'required|string|min:5',
            ]);
            $parsedItems = $parser->parseText($request->input('raw_text'));
        } elseif ($mode === 'pdf') {
            $request->validate([
                'pdf_file' => 'required|file|mimes:pdf|max:10240',
            ]);
            $filePath = $request->file('pdf_file')->getRealPath();
            $parsedItems = $parser->parsePdf($filePath);
        }

        // Enhanced Duplicate Check against existing database transactions and within batch
        $seenInBatch = [];
        foreach ($parsedItems as &$item) {
            $sig = ($item['transaction_date'] ?? '').'_'.(int) ($item['amount_net'] ?? 0);
            $inBatchDup = isset($seenInBatch[$sig]);
            $seenInBatch[$sig] = true;

            $dbDup = false;
            $dbReason = null;
            if (! empty($item['document_number'])) {
                $existsDoc = Transaction::where('document_number', $item['document_number'])->first();
                if ($existsDoc) {
                    $dbDup = true;
                    $dbReason = 'No Dokumen '.$item['document_number'].' sudah ada di sistem';
                }
            }
            if (! $dbDup && ! empty($item['transaction_date']) && ! empty($item['amount_net'])) {
                $existsDateAmount = Transaction::where('transaction_date', $item['transaction_date'])
                    ->where('amount_net', $item['amount_net'])
                    ->first();
                if ($existsDateAmount) {
                    $dbDup = true;
                    $dbReason = 'Sudah ada transaksi Rp '.number_format($item['amount_net'], 0, ',', '.').' pada tanggal '.Carbon::parse($item['transaction_date'])->format('d/m/Y');
                }
            }

            $item['is_duplicate'] = $dbDup || $inBatchDup;
            $item['duplicate_reason'] = $dbReason ?? ($inBatchDup ? 'Duplikat baris ganda dalam teks impor' : null);
            // Uncheck duplicate items by default to prevent accidental double-commit
            if ($item['is_duplicate']) {
                $item['selected'] = false;
            }
        }

        return response()->json([
            'success' => true,
            'total_parsed' => count($parsedItems),
            'items' => $parsedItems,
        ]);
    }

    /**
     * Commit verified transactions into the active bookkeeping database.
     */
    public function commit(Request $request)
    {
        $validated = $request->validate([
            'import_type' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.transaction_date' => 'required|date',
            'items.*.document_number' => 'nullable|string',
            'items.*.description' => 'required|string',
            'items.*.amount_gross' => 'required|numeric|min:0',
            'items.*.amount_tax' => 'nullable|numeric|min:0',
            'items.*.amount_net' => 'required|numeric|min:0',
            'items.*.flow_direction' => 'required|in:receipt,disbursement',
            'items.*.transaction_type' => 'required|string',
            'items.*.pptk_id' => 'nullable|exists:pptks,id',
            'items.*.payment_method' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
        ]);

        $batch = ImportBatch::create([
            'batch_number' => 'IMP-'.date('Ymd-His'),
            'import_type' => $validated['import_type'],
            'parsed_count' => count($validated['items']),
            'status' => 'posted',
            'user_id' => auth()->id(),
        ]);

        $createdTx = [];
        foreach ($validated['items'] as $item) {
            $txDate = Carbon::parse($item['transaction_date']);
            $tx = Transaction::create([
                'batch_id' => $batch->id,
                'transaction_date' => $txDate->format('Y-m-d'),
                'document_number' => $item['document_number'] ?? null,
                'description' => $item['description'],
                'transaction_type' => $item['transaction_type'] ?? 'general',
                'payment_method' => $item['payment_method'] ?? 'bank_transfer',
                'amount_gross' => $item['amount_gross'] ?? $item['amount_net'],
                'amount_tax' => $item['amount_tax'] ?? 0,
                'amount_net' => $item['amount_net'],
                'flow_direction' => $item['flow_direction'],
                'pptk_id' => $item['pptk_id'] ?? null,
                'period_month' => $txDate->month,
                'period_year' => $txDate->year,
                'is_verified' => true,
                'status' => 'posted',
                'notes' => $item['notes'] ?? null,
            ]);
            $createdTx[] = $tx->id;
        }

        AuditLog::log('import', 'ImportBatch', $batch->id, null, ['count' => count($createdTx)]);

        return redirect()->route('impor.index')->with('success', count($createdTx).' transaksi berhasil dibukukan ke database!');
    }
}
