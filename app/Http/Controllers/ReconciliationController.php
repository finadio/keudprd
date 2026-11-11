<?php

namespace App\Http\Controllers;

use App\Models\Pptk;
use App\Models\Reconciliation;
use App\Models\Transaction;
use App\Services\FiscalYearContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    /**
     * Display reconciliation view.
     */
    public function index(Request $request): Response
    {
        $year = FiscalYearContext::resolve($request);
        $month = (int) $request->input('month', 1);

        $bankTransactions = Transaction::where('period_year', $year)
            ->where('period_month', $month)
            ->where('payment_method', 'bank_transfer')
            ->orderBy('transaction_date')
            ->get();

        $bkuTransactions = Transaction::where('period_year', $year)
            ->where('period_month', $month)
            ->whereIn('transaction_type', ['sp2d_up', 'sp2d_ls', 'sp2d_tu', 'sp2d_gu', 'panjar_disbursement', 'debet_rekening'])
            ->orderBy('transaction_date')
            ->get();

        // Cross-match statistics
        $matched = 0;
        $unmatchedBank = 0;
        $missingPptk = 0;
        $comparison = [];

        foreach ($bankTransactions as $btx) {
            $isMatched = ! empty($btx->pptk_id) || in_array($btx->transaction_type, ['sp2d_up', 'sp2d_ls', 'bank_interest']);
            if ($isMatched) {
                $matched++;
            } else {
                $unmatchedBank++;
            }
            if (empty($btx->pptk_id) && $btx->transaction_type === 'debet_rekening') {
                $missingPptk++;
            }

            $comparison[] = [
                'bank_id' => $btx->id,
                'tanggal' => $btx->transaction_date->format('d/m/Y'),
                'uraian' => $btx->description,
                'nominal' => (float) $btx->amount_net,
                'arah' => $btx->flow_direction,
                'pptk_id' => $btx->pptk_id,
                'pptk_name' => $btx->pptk?->display_name ?? 'Belum Ditentukan',
                'status_rekonsiliasi' => $isMatched ? 'cocok' : 'perlu_periksa',
                'catatan' => empty($btx->pptk_id) && $btx->transaction_type === 'debet_rekening' ? 'PPTK belum dialokasikan' : 'Sesuai rekening bank',
            ];
        }

        $pptks = Pptk::where('is_active', true)->orderBy('sort_order')->get();

        return Inertia::render('Reconciliation/Index', [
            'year' => $year,
            'current_month' => $month,
            'stats' => [
                'total_bank' => count($bankTransactions),
                'matched' => $matched,
                'unmatched' => $unmatchedBank,
                'missing_pptk' => $missingPptk,
            ],
            'items' => $comparison,
            'pptks' => $pptks,
        ]);
    }

    /**
     * Update PPTK or match status directly from reconciliation view.
     */
    public function assignPptk(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'pptk_id' => 'required|exists:pptks,id',
        ]);

        $transaction->update(['pptk_id' => $validated['pptk_id']]);

        return back()->with('success', 'PPTK transaksi berhasil diperbarui!');
    }
}
