<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\PaguRealization;
use App\Models\Pptk;
use App\Services\FiscalYearContext;
use App\Services\PaguRealizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaguController extends Controller
{
    /**
     * Dashboard & Ikhtisar Pantauan Realisasi Pagu.
     */
    public function index(Request $request, PaguRealizationService $service): Response
    {
        $year = FiscalYearContext::resolve($request);
        $user = auth()->user();
        $filterPptkId = $request->filled('pptk_id') ? (int) $request->input('pptk_id') : null;

        $overview = $service->getPaguOverview($year, $user, $filterPptkId);

        return Inertia::render('Pagu/Index', [
            'overview' => $overview,
            'filters' => [
                'pptk_id' => $filterPptkId,
            ],
        ]);
    }

    /**
     * Daftar Transaksi Pencairan & Pengembalian Sisa Kas.
     */
    public function transactions(Request $request, PaguRealizationService $service): Response
    {
        $year = FiscalYearContext::resolve($request);
        $user = auth()->user();

        $filters = [
            'pptk_id' => $request->input('pptk_id'),
            'activity_id' => $request->input('activity_id'),
            'type' => $request->input('type'),
            'is_spj_submitted' => $request->input('is_spj_submitted'),
            'search' => $request->input('search'),
            'per_page' => (int) ($request->input('per_page', 15)),
        ];

        $transactions = $service->getTransactions($year, $filters, $user);
        $activities = Activity::where('period_year', $year)
            ->when($user && strtolower($user->role) === 'pptk' && $user->pptk_id, fn ($q) => $q->where('pptk_id', $user->pptk_id))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'pptk_id', 'pagu_budget']);

        $pptks = Pptk::where('is_active', true)
            ->when($user && strtolower($user->role) === 'pptk' && $user->pptk_id, fn ($q) => $q->where('id', $user->pptk_id))
            ->orderBy('sort_order')
            ->get(['id', 'code', 'display_name']);

        return Inertia::render('Pagu/Transactions', [
            'transactions' => $transactions,
            'activities' => $activities,
            'pptks' => $pptks,
            'filters' => $filters,
            'year' => $year,
            'is_pptk_user' => (bool) ($user && strtolower($user->role) === 'pptk'),
        ]);
    }

    /**
     * Catat Pencairan Kas Baru / Pengembalian Sisa.
     */
    public function storeTransaction(Request $request, PaguRealizationService $service): RedirectResponse
    {
        $validated = $request->validate([
            'pptk_id' => 'required|exists:pptks,id',
            'activity_id' => 'nullable|exists:activities,id',
            'transaction_date' => 'required|date',
            'type' => 'required|in:pencairan,pengembalian',
            'amount' => 'required|numeric|min:1',
            'recipient' => 'nullable|string|max:255',
            'description' => 'required|string|max:1000',
            'source_fund' => 'nullable|string|max:50',
            'document_number' => 'nullable|string|max:100',
            'is_spj_submitted' => 'nullable|boolean',
            'spj_submission_date' => 'nullable|date',
            'spj_document_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $validated['period_year'] = FiscalYearContext::resolve($request);

        $service->storeRealization($validated, auth()->user());

        $typeLabel = $validated['type'] === 'pencairan' ? 'Pencairan Dana' : 'Pengembalian Sisa Kas';

        return redirect()->back()->with('success', "{$typeLabel} berhasil dicatat!");
    }

    /**
     * Update Transaksi Realisasi.
     */
    public function updateTransaction(Request $request, PaguRealization $realization, PaguRealizationService $service): RedirectResponse
    {
        $validated = $request->validate([
            'pptk_id' => 'required|exists:pptks,id',
            'activity_id' => 'nullable|exists:activities,id',
            'transaction_date' => 'required|date',
            'type' => 'required|in:pencairan,pengembalian',
            'amount' => 'required|numeric|min:1',
            'recipient' => 'nullable|string|max:255',
            'description' => 'required|string|max:1000',
            'source_fund' => 'nullable|string|max:50',
            'document_number' => 'nullable|string|max:100',
            'is_spj_submitted' => 'nullable|boolean',
            'spj_submission_date' => 'nullable|date',
            'spj_document_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $service->updateRealization($realization, $validated);

        return redirect()->back()->with('success', 'Transaksi realisasi pagu berhasil diperbarui!');
    }

    /**
     * Hapus Transaksi.
     */
    public function destroyTransaction(PaguRealization $realization, PaguRealizationService $service): RedirectResponse
    {
        $service->deleteRealization($realization);

        return redirect()->back()->with('success', 'Transaksi realisasi pagu berhasil dihapus!');
    }

    /**
     * Tandai Status Pengajuan SPJ SIPD.
     */
    public function toggleSpj(Request $request, PaguRealization $realization, PaguRealizationService $service): RedirectResponse
    {
        $isSubmitted = (bool) $request->input('is_spj_submitted', ! $realization->is_spj_submitted);
        $docNumber = $request->input('spj_document_number');
        $date = $request->input('spj_submission_date');

        $service->toggleSpjStatus($realization, $isSubmitted, $docNumber, $date);

        $statusMsg = $isSubmitted ? 'ditandai sudah diajukan ke SIPD' : 'ditandai belum diajukan ke SIPD';

        return redirect()->back()->with('success', "Status SPJ {$statusMsg}!");
    }

    /**
     * Rincian Pagu per Sub Kegiatan & Pemetaan PPTK.
     */
    public function activities(Request $request, PaguRealizationService $service): Response
    {
        $year = FiscalYearContext::resolve($request);
        $user = auth()->user();
        $filterPptkId = $request->filled('pptk_id') ? (int) $request->input('pptk_id') : null;

        $activities = $service->getActivitiesList($year, $user, $filterPptkId);
        $pptks = Pptk::where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'display_name']);

        return Inertia::render('Pagu/Activities', [
            'activities' => $activities,
            'pptks' => $pptks,
            'filters' => [
                'pptk_id' => $filterPptkId,
            ],
            'year' => $year,
            'is_pptk_user' => (bool) ($user && strtolower($user->role) === 'pptk'),
        ]);
    }

    /**
     * Tetapkan / Ubah PPTK penanggung jawab Sub Kegiatan.
     */
    public function assignPptk(Request $request, Activity $activity, PaguRealizationService $service): RedirectResponse
    {
        $validated = $request->validate([
            'pptk_id' => 'required|exists:pptks,id',
        ]);

        $service->assignActivityPptk($activity, (int) $validated['pptk_id']);

        return redirect()->back()->with('success', "Sub Kegiatan '{$activity->name}' berhasil ditugaskan ke PPTK!");
    }

    /**
     * Halaman Impor Excel SIPD Fungsional.
     */
    public function importView(Request $request): Response
    {
        $year = FiscalYearContext::resolve($request);
        $totalActivities = Activity::where('period_year', $year)->count();
        $totalPagu = (float) Activity::where('period_year', $year)->sum('pagu_budget');
        $totalSpj = (float) Activity::where('period_year', $year)->sum('spj_sipd_total');

        return Inertia::render('Pagu/Import', [
            'year' => $year,
            'total_activities' => $totalActivities,
            'total_pagu' => $totalPagu,
            'total_spj' => $totalSpj,
        ]);
    }

    /**
     * Proses Impor File Excel SIPD Fungsional.
     */
    public function import(Request $request, PaguRealizationService $service): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        $year = FiscalYearContext::resolve($request);
        $file = $request->file('file');
        $path = $file->getRealPath();

        try {
            $result = $service->importSipdFungsional($path, $year);

            return redirect()->route('pagu.index')->with('success', "Berhasil menyinkronkan {$result['imported_count']} subkegiatan dari SIPD! Total Pagu: Rp ".number_format($result['total_pagu'], 0, ',', '.'));
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors(['file' => 'Gagal membaca file Excel SIPD: '.$e->getMessage()]);
        }
    }
}
