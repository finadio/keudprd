<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\BookkeepingService;
use App\Services\FiscalYearContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, BookkeepingService $service): Response
    {
        $year = FiscalYearContext::resolve($request);
        $activeMonth = (int) $request->input('month', 10); // current latest month is Oktober

        $bankData = $service->getBukuBankMonth($year, $activeMonth);
        $hitungan = $service->getHitunganSummary($year);
        $matrix = $service->getJmlDebRek($year);

        $totalTransactions = Transaction::where('period_year', $year)->count();
        $unverifiedCount = Transaction::where('period_year', $year)->where('is_verified', false)->count();

        // Monthly trends for receipt vs disbursement
        $monthlyTrends = [];
        $monthNames = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt'];
        foreach ($monthNames as $mNum => $mName) {
            $mTotals = $service->getBukuBankMonth($year, $mNum);
            $monthlyTrends[] = [
                'month' => $mName,
                'penerimaan' => $mTotals['summary']['periode_ini']['penerimaan'],
                'pengeluaran' => $mTotals['summary']['periode_ini']['pengeluaran'],
                'saldo' => $mTotals['summary']['periode_ini']['saldo'],
            ];
        }

        return Inertia::render('Dashboard/Index', [
            'year' => $year,
            'active_month' => $activeMonth,
            'bank_summary' => $bankData['summary'],
            'hitungan_totals' => $hitungan['totals'],
            'pptk_recap' => $hitungan['rows'],
            'total_transactions' => $totalTransactions,
            'unverified_count' => $unverifiedCount,
            'monthly_trends' => $monthlyTrends,
        ]);
    }
}
