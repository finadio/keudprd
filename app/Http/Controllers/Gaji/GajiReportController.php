<?php

namespace App\Http\Controllers\Gaji;

use App\Http\Controllers\Controller;
use App\Models\Gaji\GajiPayrollItem;
use App\Models\Gaji\GajiPayrollPeriod;
use App\Services\Gaji\GajiExportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GajiReportController extends Controller
{
    public function index(Request $request): Response
    {
        $year = (int) $request->input('year', 2026);
        $month = $request->input('month');

        $periods = GajiPayrollPeriod::where('period_year', $year)
            ->when($month, fn ($q) => $q->where('period_month', (int) $month))
            ->orderBy('period_month', 'desc')
            ->get();

        $selectedPeriodId = $request->input('period_id');
        $activePeriod = null;

        if ($selectedPeriodId) {
            $activePeriod = GajiPayrollPeriod::with('creator')->find($selectedPeriodId);
        } elseif ($periods->isNotEmpty()) {
            $activePeriod = $periods->first();
        }

        $items = null;
        if ($activePeriod) {
            $search = trim((string) $request->input('search', ''));
            $category = $request->input('category');

            $itemsQuery = GajiPayrollItem::where('period_id', $activePeriod->id)
                ->with('recipient');

            if ($search) {
                $itemsQuery->whereHas('recipient', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('position_name', 'like', "%{$search}%");
                });
            }

            if ($category && in_array($category, ['asn', 'dprd'])) {
                $itemsQuery->where('category', $category);
            }

            $items = $itemsQuery->orderBy('category')
                ->orderBy('id')
                ->paginate(15)
                ->withQueryString();
        }

        return Inertia::render('Gaji/Laporan/Index', [
            'year' => $year,
            'month' => $month,
            'periods' => $periods,
            'active_period' => $activePeriod,
            'items' => $items,
            'filters' => [
                'search' => $request->input('search', ''),
                'category' => $request->input('category', ''),
            ],
        ]);
    }

    /**
     * Unduh Rekap Nominatif dalam format Excel (.xlsx).
     */
    public function exportExcel(GajiPayrollPeriod $period, GajiExportService $exportService): BinaryFileResponse
    {
        return $exportService->exportNominatifExcel($period);
    }

    /**
     * Unduh Slip Gaji Individual dalam format PDF.
     */
    public function exportSlip(GajiPayrollItem $item, GajiExportService $exportService): \Illuminate\Http\Response
    {
        return $exportService->exportSlipPdf($item);
    }
}
