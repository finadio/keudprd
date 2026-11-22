<?php

namespace App\Http\Controllers\Gaji;

use App\Http\Controllers\Controller;
use App\Models\Gaji\GajiBudgetAllocation;
use App\Models\Gaji\GajiConfig;
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

        $config->update($validated);

        return back()->with('success', 'Konfigurasi tarif acuan berhasil diperbarui.');
    }
}
