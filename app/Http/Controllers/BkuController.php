<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\MasterSetting;
use App\Models\Pptk;
use App\Models\Transaction;
use App\Services\BookkeepingService;
use App\Services\FiscalYearContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BkuController extends Controller
{
    /**
     * Display BKU Per PPTK workbook interface.
     */
    public function index(Request $request, BookkeepingService $service): Response
    {
        $year = FiscalYearContext::resolve($request);
        $activeTab = $request->input('tab', 'Hitungan'); // Default first sheet

        $allTabs = [
            'Hitungan', 'UP', 'KK', 'Jml Deb Rek', 'Deb Rek',
            'Aul-Ety', 'Gaya', 'Ratman', 'Aul-Rina', 'Sigit',
            'SgngSyesa', 'SgngEmi', 'Sri L', 'Irna',
            'Reses3', 'DebReses3', 'Reses2', 'Deb Reses2', 'Reses 1', 'Deb Reses1',
        ];

        $setting = MasterSetting::current();
        $pptks = Pptk::where('is_active', true)->orderBy('sort_order')->get();
        $periods = AccountingPeriod::where('fiscal_year', $year)->orderBy('month')->get();

        $sheetData = $this->resolveSheetData($activeTab, $year, $pptks, $service);

        return Inertia::render('Bku/Index', [
            'year' => $year,
            'active_tab' => $activeTab,
            'all_tabs' => $allTabs,
            'pptks' => $pptks,
            'sheet_data' => $sheetData,
            'setting' => $setting,
            'periods' => $periods,
        ]);
    }

    /**
     * Display printable official document (pure data without webpage chrome).
     */
    public function printDocument(Request $request, BookkeepingService $service)
    {
        $year = FiscalYearContext::resolve($request);
        $activeTab = $request->input('tab', 'Hitungan');
        $withNotes = $request->boolean('with_notes') || $request->input('mode') === 'internal';

        $setting = MasterSetting::current();
        $pptks = Pptk::where('is_active', true)->orderBy('sort_order')->get();

        $sheetData = $this->resolveSheetData($activeTab, $year, $pptks, $service);

        $titles = [
            'Hitungan' => 'REKAPITULASI BKU PER PPTK',
            'UP' => 'BUKU KAS UMUM (UP)',
            'KK' => 'BUKU KAS KECIL (KAS TUNAI)',
            'Jml Deb Rek' => 'JUMLAH DEBET REKENING & PANJAR BULANAN',
            'Deb Rek' => 'BUKU DEBET REKENING INDUK',
            'Reses 1' => 'BUKU RESES SIDANG 1',
            'Deb Reses1' => 'DEBET REKENING RESES 1 & PPH 23',
            'Reses2' => 'BUKU RESES SIDANG 2',
            'Deb Reses2' => 'DEBET REKENING RESES 2 & PPH 23',
            'Reses3' => 'BUKU RESES SIDANG 3',
            'DebReses3' => 'DEBET REKENING RESES 3 & PPH 23',
        ];

        $title = $titles[$activeTab] ?? ('BUKU PEMBANTU PPTK: '.($sheetData['meta']['pptk'] ?? $activeTab));

        return view('bku.print', compact('year', 'activeTab', 'sheetData', 'setting', 'pptks', 'title', 'withNotes'));
    }

    /**
     * Resolve sheet data based on active tab.
     */
    private function resolveSheetData(string $activeTab, int $year, $pptks, BookkeepingService $service): array
    {
        switch ($activeTab) {
            case 'Hitungan':
                return $service->getHitunganSummary($year);

            case 'UP':
                return $service->getBkuUp($year);

            case 'KK':
                return $service->getKasKecil($year);

            case 'Jml Deb Rek':
                return $service->getJmlDebRek($year);

            case 'Deb Rek':
                return $this->getDebRekData($year, $pptks);

            case 'Reses 1':
                return $service->getBkuResesData(1, $year);

            case 'Deb Reses1':
                return $service->getDebResesData(1, $year);

            case 'Reses2':
                return $service->getBkuResesData(2, $year);

            case 'Deb Reses2':
                return $service->getDebResesData(2, $year);

            case 'Reses3':
                return $service->getBkuResesData(3, $year);

            case 'DebReses3':
                return $service->getDebResesData(3, $year);

            default:
                $matchedPptk = $pptks->first(fn ($p) => $p->sheet_name === $activeTab);
                if ($matchedPptk) {
                    return $service->getPptkBook($matchedPptk, $year);
                }

                return $service->getHitunganSummary($year);
        }
    }

    /**
     * Store new manual transaction from BKU sheet form.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'document_number' => 'nullable|string|max:100',
            'description' => 'required|string',
            'transaction_type' => 'required|string',
            'flow_direction' => 'required|in:receipt,disbursement',
            'amount_net' => 'required|numeric|min:0',
            'payment_method' => 'required|in:bank_transfer,cash_tunai,debit_card_giro',
            'pptk_id' => 'nullable|exists:pptks,id',
            'notes' => 'nullable|string',
        ]);

        $txDate = Carbon::parse($validated['transaction_date']);
        $isLocked = AccountingPeriod::where('fiscal_year', $txDate->year)
            ->where('month', $txDate->month)
            ->where('is_closed', true)
            ->exists();
        if ($isLocked) {
            return back()->with('error', "Periode bulan {$txDate->month} tahun {$txDate->year} sedang dikunci (Tutup Buku). Transaksi baru tidak dapat ditambahkan.");
        }

        $validated['amount_gross'] = $validated['amount_net'];
        $validated['period_month'] = $txDate->month;
        $validated['period_year'] = $txDate->year;
        $validated['status'] = 'posted';
        $validated['is_verified'] = true;

        $tx = Transaction::create($validated);
        AuditLog::log('create', 'Transaction', $tx->id, null, $tx->toArray());

        return back()->with('success', 'Transaksi berhasil disimpan!');
    }

    /**
     * Update existing transaction.
     */
    public function update(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'document_number' => 'nullable|string|max:100',
            'description' => 'required|string',
            'transaction_type' => 'nullable|string',
            'flow_direction' => 'required|in:receipt,disbursement',
            'amount_net' => 'required|numeric|min:0',
            'payment_method' => 'nullable|in:bank_transfer,cash_tunai,debit_card_giro',
            'pptk_id' => 'nullable|exists:pptks,id',
            'notes' => 'nullable|string',
        ]);

        $txDate = Carbon::parse($validated['transaction_date']);
        $isLocked = AccountingPeriod::where('fiscal_year', $txDate->year)
            ->where('month', $txDate->month)
            ->where('is_closed', true)
            ->exists();
        if ($isLocked) {
            return back()->with('error', "Periode bulan {$txDate->month} tahun {$txDate->year} sedang dikunci (Tutup Buku). Transaksi tidak dapat diubah.");
        }

        $old = $transaction->toArray();
        $validated['amount_gross'] = $validated['amount_net'];
        $validated['period_month'] = $txDate->month;
        $validated['period_year'] = $txDate->year;

        $transaction->update($validated);

        AuditLog::log('update', 'Transaction', $transaction->id, $old, $transaction->toArray());

        return back()->with('success', 'Transaksi berhasil diperbarui!');
    }

    /**
     * Delete transaction.
     */
    public function destroy(Transaction $transaction)
    {
        $txDate = Carbon::parse($transaction->transaction_date);
        $isLocked = AccountingPeriod::where('fiscal_year', $txDate->year)
            ->where('month', $txDate->month)
            ->where('is_closed', true)
            ->exists();
        if ($isLocked) {
            return back()->with('error', "Periode bulan {$txDate->month} tahun {$txDate->year} sedang dikunci (Tutup Buku). Hapus transaksi tidak diizinkan.");
        }

        $old = $transaction->toArray();
        $transaction->delete();

        AuditLog::log('delete', 'Transaction', $transaction->id, $old, null);

        return back()->with('success', 'Transaksi berhasil dihapus!');
    }

    /**
     * Update transaction notes or custom field inline without full page reload.
     */
    public function updateInline(Request $request, Transaction $transaction)
    {
        $txDate = Carbon::parse($transaction->transaction_date);
        $isLocked = AccountingPeriod::where('fiscal_year', $txDate->year)
            ->where('month', $txDate->month)
            ->where('is_closed', true)
            ->exists();
        if ($isLocked) {
            return response()->json([
                'success' => false,
                'message' => 'Periode transaksi ini sedang dikunci (Tutup Buku). Perubahan catatan tidak diizinkan.',
            ], 403);
        }

        $field = (string) $request->input('field', 'notes');
        $value = $request->input('value');
        $oldNotes = $transaction->notes;

        if ($field === 'notes' || $field === 'keterangan') {
            $transaction->notes = $value;
        } else {
            $meta = $transaction->meta ?? [];
            $customFields = $meta['custom_fields'] ?? [];
            $customFields[$field] = $value;
            $meta['custom_fields'] = $customFields;
            $transaction->meta = $meta;
        }

        $transaction->save();

        AuditLog::log(
            'inline_update',
            'Transaction',
            $transaction->id,
            [$field => $oldNotes],
            [$field => $value]
        );

        return response()->json([
            'success' => true,
            'message' => 'Catatan berhasil disimpan',
            'notes' => $transaction->notes,
            'custom_fields' => $transaction->meta['custom_fields'] ?? [],
        ]);
    }

    /**
     * Toggle row verification status.
     */
    public function toggleVerified(Transaction $transaction)
    {
        $old = (bool) $transaction->is_verified;
        $transaction->is_verified = ! $old;
        $transaction->save();

        AuditLog::log(
            'toggle_verified',
            'Transaction',
            $transaction->id,
            ['is_verified' => $old],
            ['is_verified' => (bool) $transaction->is_verified]
        );

        return response()->json([
            'success' => true,
            'is_verified' => (bool) $transaction->is_verified,
            'message' => $transaction->is_verified ? 'Transaksi ditandai terverifikasi.' : 'Verifikasi transaksi dibatalkan.',
        ]);
    }

    /**
     * Get audit log history for a specific transaction.
     */
    public function getAuditLogs(Transaction $transaction)
    {
        $logs = AuditLog::with('user:id,name,email')
            ->where('entity_type', 'Transaction')
            ->where('entity_id', $transaction->id)
            ->latest('created_at')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user_name' => $log->user?->name ?? 'Administrator',
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'created_at' => $log->created_at?->format('d/m/Y H:i:s'),
                    'created_at_human' => $log->created_at?->diffForHumans(),
                ];
            });

        return response()->json([
            'transaction_id' => $transaction->id,
            'description' => $transaction->description,
            'logs' => $logs,
        ]);
    }

    private function getDebRekData(int $year, $pptks): array
    {
        $setting = MasterSetting::current();

        $transactions = Transaction::where('period_year', $year)
            ->where('transaction_type', 'debet_rekening')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $rows = [];
        $runningSaldo = 0.0;
        $totalPengeluaran = 0.0;
        $pptkTotals = [];

        foreach ($pptks as $p) {
            $pptkTotals[$p->id] = 0.0;
        }

        $noUrut = 1;
        foreach ($transactions as $tx) {
            $pengeluaran = (float) $tx->amount_net;
            $runningSaldo -= $pengeluaran;
            $totalPengeluaran += $pengeluaran;

            $pptkCols = [];
            foreach ($pptks as $p) {
                $val = ($tx->pptk_id == $p->id) ? $pengeluaran : 0.0;
                $pptkCols[$p->id] = $val;
                if ($val > 0) {
                    $pptkTotals[$p->id] += $val;
                }
            }

            // 1. Baris Belanja Toko
            $rows[] = [
                'id' => $tx->id,
                'no' => $noUrut++,
                'tanggal' => $tx->transaction_date->format('d/m/Y'),
                'uraian' => $tx->description,
                'penerimaan' => 0.0,
                'pengeluaran' => $pengeluaran,
                'saldo' => -$pengeluaran,
                'pptk_cols' => $pptkCols,
                'is_contra' => false,
                'keterangan' => $tx->notes ?? ($tx->meta['tag'] ?? ''),
                'notes' => $tx->notes ?? '',
                'custom_fields' => $tx->meta['custom_fields'] ?? [],
            ];

            // 2. Baris Contra PPTK Penyelesaian
            $runningSaldo = 0.0;
            $rows[] = [
                'id' => $tx->id.'-contra',
                'no' => '',
                'tanggal' => '',
                'uraian' => $tx->pptk?->name ?? 'PPTK Terkait',
                'penerimaan' => $pengeluaran,
                'pengeluaran' => 0.0,
                'saldo' => 0.0,
                'pptk_cols' => array_fill_keys(array_keys($pptkTotals), 0.0),
                'is_contra' => true,
            ];
        }

        return [
            'meta' => [
                'skpd' => $setting?->skpd_name ?? 'SETWAN KABUPATEN BANYUMAS',
                'pa_kpa' => $setting?->pa_kpa_name ?? 'ARIF TRIYANTO, S.Sos. M.Eng.',
                'bendahara' => $setting?->treasurer_name ?? 'INDAH SETIYOWATI, A.Md.AB.',
                'periode' => "JANUARI s/d DESEMBER {$year}",
            ],
            'pptks' => $pptks,
            'rows' => $rows,
            'total_pengeluaran' => $totalPengeluaran,
            'pptk_totals' => $pptkTotals,
        ];
    }
}
