<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\PaguRealization;
use App\Models\Pptk;
use App\Services\FiscalYearContext;
use App\Services\PaguRealizationService;
use App\Services\SmartSipdParser;
use Illuminate\Http\JsonResponse;
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
        $this->ensureNotPptk();

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
        $this->ensureNotPptk();

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
        $this->ensureNotPptk();

        $service->deleteRealization($realization);

        return redirect()->back()->with('success', 'Transaksi realisasi pagu berhasil dihapus!');
    }

    /**
     * Tandai Status Pengajuan SPJ SIPD.
     */
    public function toggleSpj(Request $request, PaguRealization $realization, PaguRealizationService $service): RedirectResponse
    {
        $this->ensureNotPptk();

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
        $this->ensureNotPptk();

        $validated = $request->validate([
            'pptk_id' => 'required|exists:pptks,id',
        ]);

        $service->assignActivityPptk($activity, (int) $validated['pptk_id']);

        return redirect()->back()->with('success', "Sub Kegiatan '{$activity->name}' berhasil ditugaskan ke PPTK!");
    }

    /**
     * Halaman Impor Excel SIPD Fungsional.
     */
    public function importView(Request $request): Response|RedirectResponse
    {
        if (auth()->user() && strtolower(auth()->user()->role) === 'pptk') {
            return redirect()->route('pagu.index')->with('warning', 'Pejabat PPTK hanya memiliki hak peninjauan (View-Only). Fitur impor SIPD dikelola oleh Bendahara atau Operator.');
        }

        $year = FiscalYearContext::resolve($request);
        $totalActivities = Activity::where('period_year', $year)->count();
        $totalPagu = (float) Activity::where('period_year', $year)->sum('pagu_budget');
        $totalSpj = (float) Activity::where('period_year', $year)->sum('spj_sipd_total');
        $pptks = Pptk::where('is_active', true)->orderBy('sort_order')->get();

        return Inertia::render('Pagu/Import', [
            'year' => $year,
            'total_activities' => $totalActivities,
            'total_pagu' => $totalPagu,
            'total_spj' => $totalSpj,
            'pptks' => $pptks,
        ]);
    }

    /**
     * Preview / Parse File Dokumen atau Tempel Teks SIPD Fungsional.
     */
    public function parse(Request $request, SmartSipdParser $parser): JsonResponse
    {
        $this->ensureNotPptk();

        $mode = $request->input('mode', 'file'); // 'file' or 'text'
        $year = FiscalYearContext::resolve($request);
        $items = [];

        if ($mode === 'text') {
            $request->validate([
                'raw_text' => 'required|string|min:5',
            ]);
            $items = $parser->parseText($request->input('raw_text'), $year);
        } else {
            $request->validate([
                'document_file' => 'nullable|file|max:25600',
                'file' => 'nullable|file|max:25600',
            ]);
            $uploaded = $request->file('document_file') ?? $request->file('file');
            if (! $uploaded) {
                return response()->json([
                    'success' => false,
                    'message' => 'File dokumen tidak ditemukan atau melebihi batas 25 MB.',
                ], 422);
            }

            $items = $parser->parseFile(
                $uploaded->getRealPath(),
                $uploaded->getClientOriginalName(),
                $uploaded->getMimeType(),
                $year
            );
        }

        $totalPagu = array_sum(array_column($items, 'pagu_budget'));
        $totalSpj = array_sum(array_column($items, 'spj_sipd_total'));

        return response()->json([
            'success' => true,
            'total_parsed' => count($items),
            'items' => $items,
            'total_pagu' => $totalPagu,
            'total_spj' => $totalSpj,
        ]);
    }

    /**
     * Commit Verified Subkegiatan Data from SIPD into database.
     */
    public function commit(Request $request): RedirectResponse
    {
        $this->ensureNotPptk();

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.code' => 'required|string',
            'items.*.name' => 'required|string',
            'items.*.program_code' => 'nullable|string',
            'items.*.program_name' => 'nullable|string',
            'items.*.pagu_budget' => 'required|numeric|min:0',
            'items.*.spj_sipd_total' => 'nullable|numeric|min:0',
            'items.*.sisa_pagu_sipd' => 'nullable|numeric',
            'items.*.pptk_id' => 'nullable|exists:pptks,id',
        ]);

        $year = FiscalYearContext::resolve($request);
        $importedCount = 0;
        $totalPagu = 0.0;
        $totalSpj = 0.0;

        foreach ($validated['items'] as $item) {
            $activity = Activity::where('code', $item['code'])
                ->where('period_year', $year)
                ->first();

            if (! $activity) {
                $activity = new Activity;
                $activity->code = $item['code'];
                $activity->period_year = $year;
            }

            $activity->name = $item['name'];
            $activity->program_code = $item['program_code'] ?? '4.02.01';
            $activity->program_name = $item['program_name'] ?? 'PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA';
            $activity->pagu_budget = $item['pagu_budget'];
            $activity->spj_sipd_total = $item['spj_sipd_total'] ?? 0;
            $activity->sisa_pagu_sipd = $item['sisa_pagu_sipd'] ?? ($item['pagu_budget'] - ($item['spj_sipd_total'] ?? 0));
            if (! empty($item['pptk_id'])) {
                $activity->pptk_id = $item['pptk_id'];
            }
            $activity->save();

            $importedCount++;
            $totalPagu += (float) $item['pagu_budget'];
            $totalSpj += (float) ($item['spj_sipd_total'] ?? 0);
        }

        return redirect()->route('pagu.index')->with(
            'success',
            "Berhasil menyinkronkan {$importedCount} subkegiatan dari SIPD! Total Pagu: Rp ".number_format($totalPagu, 0, ',', '.')
        );
    }

    /**
     * Proses Impor File Excel SIPD Fungsional (Legacy Form Support).
     */
    public function import(Request $request, PaguRealizationService $service): RedirectResponse
    {
        $this->ensureNotPptk();

        $request->validate([
            'file' => 'required|file|max:25600',
        ]);

        $year = FiscalYearContext::resolve($request);
        $file = $request->file('file');
        $path = $file->getRealPath();

        try {
            $result = $service->importSipdFungsional($path, $year);

            return redirect()->route('pagu.index')->with('success', "Berhasil menyinkronkan {$result['imported_count']} subkegiatan dari SIPD! Total Pagu: Rp ".number_format($result['total_pagu'], 0, ',', '.'));
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors(['file' => 'Gagal membaca file SIPD: '.$e->getMessage()]);
        }
    }

    /**
     * Pastikan pengguna saat ini bukan ber-role PPTK (PPTK bersifat View-Only).
     */
    private function ensureNotPptk(): void
    {
        $user = auth()->user();
        if ($user && strtolower($user->role) === 'pptk') {
            abort(403, 'Akses dibatasi. Pejabat PPTK hanya memiliki hak akses peninjauan (View-Only). Pencatatan kas riil dilakukan oleh Bendahara Pengeluaran atau Operator.');
        }
    }
}
