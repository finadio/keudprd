<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\FiscalYearContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Search transactions across all BKU sheets and Bank Book months.
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        $year = FiscalYearContext::resolve($request);

        if (mb_strlen($query) < 2) {
            return response()->json([
                'query' => $query,
                'count' => 0,
                'results' => [],
            ]);
        }

        $numericOnly = preg_replace('/[^0-9]/', '', $query);

        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agust',
            9 => 'Sept', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        $transactions = Transaction::with(['pptk'])
            ->where(function ($q) use ($query, $numericOnly) {
                $q->where('description', 'like', "%{$query}%")
                    ->orWhere('document_number', 'like', "%{$query}%")
                    ->orWhere('notes', 'like', "%{$query}%")
                    ->orWhere('vendor_name', 'like', "%{$query}%")
                    ->orWhereHas('pptk', function ($sub) use ($query) {
                        $sub->where('name', 'like', "%{$query}%")
                            ->orWhere('display_name', 'like', "%{$query}%")
                            ->orWhere('sheet_name', 'like', "%{$query}%");
                    });

                if (! empty($numericOnly) && strlen($numericOnly) >= 3) {
                    $q->orWhere('amount_net', 'like', "%{$numericOnly}%")
                        ->orWhere('amount_gross', 'like', "%{$numericOnly}%");
                }
            })
            ->when($year > 0, fn ($q) => $q->where('period_year', $year))
            ->orderBy('transaction_date', 'desc')
            ->limit(40)
            ->get();

        $results = $transactions->map(function ($tx) use ($monthNames) {
            // Determine BKU sheet tab
            $bkuTab = 'UP';
            if ($tx->pptk && ! empty($tx->pptk->sheet_name)) {
                $bkuTab = $tx->pptk->sheet_name;
            } elseif ($tx->payment_method === 'cash_tunai' || $tx->transaction_type === 'kas_kecil') {
                $bkuTab = 'KK';
            } elseif ($tx->transaction_type === 'reses_1') {
                $bkuTab = 'Reses 1';
            } elseif ($tx->transaction_type === 'reses_2') {
                $bkuTab = 'Reses2';
            } elseif ($tx->transaction_type === 'reses_3') {
                $bkuTab = 'Reses3';
            }

            $monthNumber = (int) ($tx->period_month ?? $tx->transaction_date?->month ?? 1);
            $monthName = $monthNames[$monthNumber] ?? "Bulan {$monthNumber}";
            $year = $tx->period_year ?? $tx->transaction_date?->year ?? 2026;

            return [
                'id' => $tx->id,
                'date' => $tx->transaction_date ? $tx->transaction_date->format('d/m/Y') : '-',
                'raw_date' => $tx->transaction_date ? $tx->transaction_date->format('Y-m-d') : '',
                'description' => $tx->description,
                'document_number' => $tx->document_number ?: '-',
                'flow_direction' => $tx->flow_direction,
                'amount_net' => (float) $tx->amount_net,
                'amount_formatted' => 'Rp '.number_format((float) $tx->amount_net, 2, ',', '.'),
                'notes' => $tx->notes,
                'is_verified' => (bool) $tx->is_verified,
                'pptk_name' => $tx->pptk ? $tx->pptk->display_name : null,
                'bku_tab' => $bkuTab,
                'bku_url' => "/bku?year={$year}&tab=".urlencode($bkuTab)."&highlight={$tx->id}",
                'bank_month' => $monthNumber,
                'bank_tab' => $monthName,
                'bank_url' => "/buku-bank?year={$year}&month={$monthNumber}&highlight={$tx->id}",
            ];
        });

        return response()->json([
            'query' => $query,
            'count' => $results->count(),
            'results' => $results,
        ]);
    }
}
