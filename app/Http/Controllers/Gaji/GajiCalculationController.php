<?php

namespace App\Http\Controllers\Gaji;

use App\Http\Controllers\Controller;
use App\Models\Gaji\GajiPayrollItem;
use App\Models\Gaji\GajiPayrollPeriod;
use App\Models\Gaji\GajiRecipient;
use App\Services\Gaji\GajiBudgetControlService;
use App\Services\Gaji\GajiCalculatorService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GajiCalculationController extends Controller
{
    /**
     * Halaman Utama Simulasi Pembayaran.
     */
    public function index(
        Request $request,
        GajiCalculatorService $calculator,
        GajiBudgetControlService $budgetService
    ): Response {
        $year = (int) $request->input('year', 2026);
        $month = (int) $request->input('month', (int) date('n'));
        $prodPct = (float) $request->input('productivity_pct', 95.0);
        $dispPct = (float) $request->input('discipline_pct', 100.0);
        $isReses = (bool) $request->input('is_reses', false);

        $recipients = GajiRecipient::where('is_active', true)->get();

        $items = [];
        $asnGross = 0.0;
        $dprdGross = 0.0;
        $totalDeductions = 0.0;

        foreach ($recipients as $r) {
            $calc = $calculator->calculateForRecipient($r, $prodPct, $dispPct, $isReses);
            $calc['recipient_id'] = $r->id;
            $calc['recipient_name'] = $r->name;
            $calc['identifier_number'] = $r->identifier_number;
            $calc['position_name'] = $r->position_name;
            $calc['grade'] = $r->grade;
            $calc['mkg_years'] = $r->mkg_years;
            $calc['job_class'] = $r->job_class;
            $calc['political_party'] = $r->political_party;
            $calc['akd_role'] = $r->akd_role;

            if ($r->category === 'asn') {
                $asnGross += $calc['gross_amount'];
            } else {
                $dprdGross += $calc['gross_amount'];
            }
            $totalDeductions += $calc['total_deductions'];
            $items[] = $calc;
        }

        $totalGross = $asnGross + $dprdGross;
        $totalNetto = $totalGross - $totalDeductions;

        $budgetFeasibility = $budgetService->analyzeBudgetFeasibility(
            $year,
            $month,
            $asnGross,
            $dprdGross,
            $items
        );

        // Cek apakah periode ini sudah pernah dibukukan / disimpan
        $existingPeriod = GajiPayrollPeriod::where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        return Inertia::render('Gaji/Simulasi/Index', [
            'year' => $year,
            'month' => $month,
            'productivity_pct' => $prodPct,
            'discipline_pct' => $dispPct,
            'is_reses' => $isReses,
            'summary' => [
                'total_recipients' => count($items),
                'asn_count' => $recipients->where('category', 'asn')->count(),
                'dprd_count' => $recipients->where('category', 'dprd')->count(),
                'asn_gross' => $asnGross,
                'dprd_gross' => $dprdGross,
                'total_gross' => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_netto' => $totalNetto,
            ],
            'budget' => $budgetFeasibility,
            'items' => $items,
            'existing_period' => $existingPeriod,
        ]);
    }

    /**
     * Simpan / Bukukan Hasil Perhitungan Penggajian Periode Terpilih.
     */
    public function commit(
        Request $request,
        GajiCalculatorService $calculator,
        GajiBudgetControlService $budgetService
    ): RedirectResponse {
        $year = (int) $request->input('year', 2026);
        $month = (int) $request->input('month', 11);
        $prodPct = (float) $request->input('productivity_pct', 95.0);
        $dispPct = (float) $request->input('discipline_pct', 100.0);
        $isReses = (bool) $request->input('is_reses', false);

        $monthName = Carbon::createFromDate($year, $month, 1)->locale('id')->isoFormat('MMMM Y');
        $periodTitle = "Penggajian ASN & Hak Keuangan DPRD Bulan {$monthName}";

        DB::transaction(function () use ($year, $month, $prodPct, $dispPct, $isReses, $periodTitle, $calculator, $budgetService) {
            $recipients = GajiRecipient::where('is_active', true)->get();

            $itemsData = [];
            $asnGross = 0.0;
            $dprdGross = 0.0;
            $totalDeductions = 0.0;

            foreach ($recipients as $r) {
                $calc = $calculator->calculateForRecipient($r, $prodPct, $dispPct, $isReses);
                $calc['recipient_id'] = $r->id;
                $calc['recipient_name'] = $r->name;

                if ($r->category === 'asn') {
                    $asnGross += $calc['gross_amount'];
                } else {
                    $dprdGross += $calc['gross_amount'];
                }
                $totalDeductions += $calc['total_deductions'];
                $itemsData[] = $calc;
            }

            $totalGross = $asnGross + $dprdGross;
            $totalNetto = $totalGross - $totalDeductions;

            $budgetFeasibility = $budgetService->analyzeBudgetFeasibility(
                $year,
                $month,
                $asnGross,
                $dprdGross,
                $itemsData
            );

            $period = GajiPayrollPeriod::updateOrCreate(
                ['period_year' => $year, 'period_month' => $month],
                [
                    'period_title' => $periodTitle,
                    'status' => 'approved',
                    'total_recipients' => count($itemsData),
                    'total_asn_gross' => $asnGross,
                    'total_dprd_gross' => $dprdGross,
                    'total_gross' => $totalGross,
                    'total_deductions' => $totalDeductions,
                    'total_netto' => $totalNetto,
                    'available_budget' => $budgetFeasibility['monthly_allocation']['total'],
                    'budget_difference' => $budgetFeasibility['budget_difference'],
                    'is_budget_sufficient' => $budgetFeasibility['is_sufficient'],
                    'budget_diagnostic' => $budgetFeasibility['diagnostics'],
                    'simulated_at' => now(),
                    'created_by' => auth()->id(),
                ]
            );

            // Bersihkan item lama jika pernah disimpan sebelumnya
            GajiPayrollItem::where('period_id', $period->id)->delete();

            $insertPayload = [];
            foreach ($itemsData as $it) {
                $insertPayload[] = [
                    'period_id' => $period->id,
                    'recipient_id' => $it['recipient_id'],
                    'category' => $it['category'],
                    'sub_category' => $it['sub_category'],
                    'basic_salary_or_rep' => $it['basic_salary_or_rep'],
                    'package_allowance' => $it['package_allowance'],
                    'position_allowance' => $it['position_allowance'],
                    'family_allowance' => $it['family_allowance'],
                    'rice_allowance' => $it['rice_allowance'],
                    'akd_allowance' => $it['akd_allowance'],
                    'tki_allowance' => $it['tki_allowance'],
                    'housing_allowance' => $it['housing_allowance'],
                    'transport_allowance' => $it['transport_allowance'],
                    'reses_allowance' => $it['reses_allowance'],
                    'tpp_amount' => $it['tpp_amount'],
                    'productivity_pct' => $it['productivity_pct'],
                    'discipline_pct' => $it['discipline_pct'],
                    'other_allowance' => $it['other_allowance'],
                    'gross_amount' => $it['gross_amount'],
                    'deduction_iwp' => $it['deduction_iwp'],
                    'deduction_bpjs' => $it['deduction_bpjs'],
                    'deduction_pph21' => $it['deduction_pph21'],
                    'deduction_other' => $it['deduction_other'],
                    'total_deductions' => $it['total_deductions'],
                    'net_amount' => $it['net_amount'],
                    'calculation_meta' => json_encode($it['calculation_meta']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($insertPayload, 100) as $chunk) {
                GajiPayrollItem::insert($chunk);
            }
        });

        return redirect()->route('gaji.laporan.index', ['year' => $year, 'month' => $month])
            ->with('success', 'Perhitungan penggajian dan hak keuangan berhasil dibukukan.');
    }
}
