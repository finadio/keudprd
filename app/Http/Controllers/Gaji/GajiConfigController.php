<?php

namespace App\Http\Controllers\Gaji;

use App\Http\Controllers\Controller;
use App\Models\Gaji\GajiBudgetAllocation;
use App\Models\Gaji\GajiConfig;
use App\Models\Gaji\GajiPayrollPeriod;
use App\Services\Gaji\GajiCalculatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GajiConfigController extends Controller
{
    public function index(Request $request): Response
    {
        $year = (int) $request->input('year', 2026);

        $allocations = GajiBudgetAllocation::where('fiscal_year', $year)->get();
        $configs = GajiConfig::where('is_active', true)->get();

        return Inertia::render('Gaji/Pengaturan/Index', [
            'year' => $year,
            'allocations' => $allocations,
            'configs' => $configs,
        ]);
    }

    public function updateAllocation(Request $request, GajiBudgetAllocation $allocation): RedirectResponse
    {
        $validated = $request->validate([
            'budget_name' => 'required|string|max:200',
            'account_code' => 'nullable|string|max:50',
            'annual_pagu' => 'required|numeric|min:0',
            'monthly_allocation' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $allocation->update($validated);

        return back()->with('success', 'Alokasi plafon kontrol anggaran berhasil diperbarui.');
    }

    public function updateConfig(Request $request, GajiConfig $config): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'legal_reference' => 'nullable|string|max:200',
            'config_value' => 'required|array',
        ]);

        // Pastikan nilai numeric terformat dengan benar
        $parsedValues = [];
        foreach ($validated['config_value'] as $k => $v) {
            if (is_numeric($v)) {
                $parsedValues[$k] = (float) $v;
            } else {
                $parsedValues[$k] = $v;
            }
        }
        $validated['config_value'] = $parsedValues;

        $config->update($validated);

        return back()->with('success', 'Formula dan tarif acuan "'.$config->name.'" berhasil diperbarui.');
    }

    /**
     * Sinkronkan rumus dan tarif terbaru ke seluruh periode penggajian & data tersimpan.
     */
    public function syncAll(Request $request): RedirectResponse
    {
        $year = (int) $request->input('year', 2026);
        $calculator = app(GajiCalculatorService::class);
        $calculator->loadConfigs();

        $periods = GajiPayrollPeriod::where('period_year', $year)->get();
        $totalItemsUpdated = 0;

        foreach ($periods as $period) {
            $prodPct = (float) $period->productivity_pct;
            $dispPct = (float) $period->discipline_pct;
            $isReses = (bool) $period->is_reses_month;

            $totalGross = 0;
            $totalNet = 0;
            $totalDeductions = 0;
            $totalDprd = 0;
            $totalAsn = 0;

            foreach ($period->items as $item) {
                $recipient = $item->recipient;
                if (! $recipient) {
                    continue;
                }

                $calc = $calculator->calculateForRecipient($recipient, $prodPct, $dispPct, $isReses);

                $item->update([
                    'basic_salary_or_rep' => $calc['basic_salary_or_rep'],
                    'package_allowance' => $calc['package_allowance'],
                    'position_allowance' => $calc['position_allowance'],
                    'family_allowance' => $calc['family_allowance'],
                    'rice_allowance' => $calc['rice_allowance'],
                    'akd_allowance' => $calc['akd_allowance'],
                    'tki_allowance' => $calc['tki_allowance'],
                    'housing_allowance' => $calc['housing_allowance'],
                    'transport_allowance' => $calc['transport_allowance'],
                    'reses_allowance' => $calc['reses_allowance'],
                    'tpp_amount' => $calc['tpp_amount'],
                    'gross_amount' => $calc['gross_amount'],
                    'deduction_iwp' => $calc['deduction_iwp'],
                    'deduction_bpjs' => $calc['deduction_bpjs'],
                    'deduction_pph21' => $calc['deduction_pph21'],
                    'total_deductions' => $calc['total_deductions'],
                    'net_amount' => $calc['net_amount'],
                    'calculation_meta' => $calc['calculation_meta'],
                ]);

                $totalGross += $calc['gross_amount'];
                $totalNet += $calc['net_amount'];
                $totalDeductions += $calc['total_deductions'];
                if ($recipient->category === 'dprd') {
                    $totalDprd += $calc['gross_amount'];
                } else {
                    $totalAsn += $calc['gross_amount'];
                }
                $totalItemsUpdated++;
            }

            $period->update([
                'total_gross' => $totalGross,
                'total_net' => $totalNet,
                'total_deductions' => $totalDeductions,
                'total_dprd_gross' => $totalDprd,
                'total_asn_gross' => $totalAsn,
            ]);
        }

        return back()->with('success', "Seluruh formula & tarif terbaru berhasil disinkronkan ke {$periods->count()} periode penggajian ({$totalItemsUpdated} rincian penerima diperbarui).");
    }
}
