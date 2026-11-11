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

class BankBookController extends Controller
{
    /**
     * Display Buku Bank monthly sheet interface.
     */
    public function index(Request $request, BookkeepingService $service): Response
    {
        $year = FiscalYearContext::resolve($request);
        $month = (int) $request->input('month', 1); // Default Januari

        $monthTabs = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agust',
            9 => 'Sept', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        $data = $service->getBukuBankMonth($year, $month);
        $pptks = Pptk::where('is_active', true)->orderBy('sort_order')->get();

        $period = AccountingPeriod::firstOrCreate(
            ['fiscal_year' => $year, 'month' => $month],
            ['name' => ($monthTabs[$month] ?? "Bulan {$month}")." {$year}", 'is_closed' => false]
        );

        return Inertia::render('BankBook/Index', [
            'year' => $year,
            'current_month' => $month,
            'month_tabs' => $monthTabs,
            'bank_data' => $data,
            'pptks' => $pptks,
            'period' => $period,
            'is_locked' => (bool) $period->is_closed,
        ]);
    }

    /**
     * Display printable official Buku Bank document (pure data without webpage chrome).
     */
    public function printDocument(Request $request, BookkeepingService $service)
    {
        $year = FiscalYearContext::resolve($request);
        $month = (int) $request->input('month', 1);
        $withNotes = $request->boolean('with_notes') || $request->input('mode') === 'internal';

        $bankData = $service->getBukuBankMonth($year, $month);
        $setting = MasterSetting::current();

        return view('bank-book.print', compact('year', 'month', 'bankData', 'setting', 'withNotes'));
    }

    /**
     * Store new bank transaction.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'description' => 'required|string',
            'flow_direction' => 'required|in:receipt,disbursement',
            'amount_net' => 'required|numeric|min:0.01',
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
        $validated['amount_tax'] = 0;
        $validated['payment_method'] = 'bank_transfer';
        $validated['period_month'] = $txDate->month;
        $validated['period_year'] = $txDate->year;
        $validated['status'] = 'posted';
        $validated['is_verified'] = true;

        // Auto classify type
        $desc = strtolower($validated['description']);
        $type = 'general';
        if (str_contains($desc, 'gaji')) {
            $type = 'sp2d_ls';
        } elseif (str_contains($desc, 'panjar') || str_contains($desc, 'cash indah')) {
            $type = 'panjar_disbursement';
        } elseif (str_contains($desc, 'pengembalian')) {
            $type = 'panjar_return';
        } elseif (str_contains($desc, 'deb rek') || str_contains($desc, 'bel ')) {
            $type = 'debet_rekening';
        } elseif (str_contains($desc, 'up')) {
            $type = 'sp2d_up';
        }
        $validated['transaction_type'] = $type;

        $tx = Transaction::create($validated);
        AuditLog::log('create', 'Transaction', $tx->id, null, $tx->toArray());

        return back()->with('success', 'Mutasi bank berhasil dicatat!');
    }

    /**
     * Update bank transaction.
     */
    public function update(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'description' => 'required|string',
            'flow_direction' => 'required|in:receipt,disbursement',
            'amount_net' => 'required|numeric|min:0.01',
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

        // Auto classify type if description updated
        $desc = strtolower($validated['description']);
        if (str_contains($desc, 'gaji')) {
            $validated['transaction_type'] = 'sp2d_ls';
        } elseif (str_contains($desc, 'panjar') || str_contains($desc, 'cash indah')) {
            $validated['transaction_type'] = 'panjar_disbursement';
        } elseif (str_contains($desc, 'pengembalian')) {
            $validated['transaction_type'] = 'panjar_return';
        } elseif (str_contains($desc, 'deb rek') || str_contains($desc, 'bel ')) {
            $validated['transaction_type'] = 'debet_rekening';
        } elseif (str_contains($desc, 'up')) {
            $validated['transaction_type'] = 'sp2d_up';
        }

        $transaction->update($validated);

        AuditLog::log('update', 'Transaction', $transaction->id, $old, $transaction->toArray());

        return back()->with('success', 'Mutasi bank berhasil diperbarui!');
    }

    /**
     * Delete bank transaction.
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

        return back()->with('success', 'Mutasi bank berhasil dihapus!');
    }
}
