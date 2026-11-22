<?php

namespace App\Http\Controllers\Gaji;

use App\Http\Controllers\Controller;
use App\Models\Gaji\GajiPayrollPeriod;
use App\Models\Gaji\GajiRecipient;
use App\Services\Gaji\GajiBudgetControlService;
use App\Services\Gaji\GajiCalculatorService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GajiDashboardController extends Controller
{
    public function index(
        Request $request,
        GajiCalculatorService $calculator,
        GajiBudgetControlService $budgetService
    ): Response {
        $year = (int) $request->input('year', 2026);
        $month = (int) $request->input('month', 11);

        // Penerima Counts
        $recipients = GajiRecipient::where('is_active', true)->get();
        $totalRecipients = $recipients->count();
        $totalAsn = $recipients->where('category', 'asn')->count();
        $totalDprd = $recipients->where('category', 'dprd')->count();

        $countPns = $recipients->where('sub_category', 'pns')->count();
        $countPppkPw = $recipients->where('sub_category', 'pppk_penuh_waktu')->count();
        $countPppkParuh = $recipients->where('sub_category', 'pppk_paruh_waktu')->count();
        $countDprdPimpinan = $recipients->whereIn('sub_category', ['ketua_dprd', 'wakil_ketua_dprd'])->count();
        $countDprdAnggota = $recipients->where('sub_category', 'anggota_dprd')->count();

        // Hitung estimasi kebutuhan gaji bulanan saat ini
        $asnGross = 0.0;
        $dprdGross = 0.0;
        $totalDeductions = 0.0;
        $sampleItems = [];

        foreach ($recipients as $r) {
            $calc = $calculator->calculateForRecipient($r);
            if ($r->category === 'asn') {
                $asnGross += $calc['gross_amount'];
            } else {
                $dprdGross += $calc['gross_amount'];
            }
            $totalDeductions += $calc['total_deductions'];

            $sampleItems[] = array_merge($calc, [
                'recipient_name' => $r->name,
            ]);
        }

        $totalGross = $asnGross + $dprdGross;
        $totalNetto = $totalGross - $totalDeductions;

        // Analisis Kontrol Anggaran
        $budgetFeasibility = $budgetService->analyzeBudgetFeasibility(
            $year,
            $month,
            $asnGross,
            $dprdGross,
            $sampleItems
        );

        // Riwayat Periode Penggajian Terbaru
        $recentPeriods = GajiPayrollPeriod::orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->limit(5)
            ->get();

        return Inertia::render('Gaji/Index', [
            'year' => $year,
            'month' => $month,
            'kpis' => [
                'total_recipients' => $totalRecipients,
                'total_asn' => $totalAsn,
                'total_dprd' => $totalDprd,
                'count_pns' => $countPns,
                'count_pppk_pw' => $countPppkPw,
                'count_pppk_paruh' => $countPppkParuh,
                'count_dprd_pimpinan' => $countDprdPimpinan,
                'count_dprd_anggota' => $countDprdAnggota,
                'asn_gross' => $asnGross,
                'dprd_gross' => $dprdGross,
                'total_gross' => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_netto' => $totalNetto,
            ],
            'budget' => $budgetFeasibility,
            'recent_periods' => $recentPeriods,
        ]);
    }
}
