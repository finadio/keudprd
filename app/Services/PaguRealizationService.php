<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\PaguRealization;
use App\Models\Pptk;
use App\Models\Subunit;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PaguRealizationService
{
    /**
     * Import / Sync activities from SIPD SPJ Fungsional Excel workbook.
     */
    public function importSipdFungsional(string $filePath, int $year): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName('2 Program dan Subkegiatan')
            ?? $spreadsheet->getSheetByName('1 LPJ Lengkap')
            ?? $spreadsheet->getActiveSheet();

        $maxRow = $sheet->getHighestRow();
        $importedCount = 0;
        $totalPagu = 0.0;
        $totalSpj = 0.0;

        $currentProgCode = '';
        $currentProgName = '';

        // Preload PPTKs
        $pptks = Pptk::all()->keyBy('code');
        $ratman = $pptks->get('RATMAN');
        $sigit = $pptks->get('SIGIT');
        $aulEty = $pptks->get('AUL_ETY');
        $gaya = $pptks->get('GAYA');
        $aulRina = $pptks->get('AUL_RINA');
        $sgngSyesa = $pptks->get('SGNG_SYESA');
        $sgngEmi = $pptks->get('SGNG_EMI');
        $sriL = $pptks->get('SRI_L');
        $irna = $pptks->get('IRNA');

        $getVal = function ($cellCoordinate) use ($sheet) {
            try {
                $val = $sheet->getCell($cellCoordinate)->getCalculatedValue();

                return is_numeric($val) ? (float) $val : 0.0;
            } catch (\Throwable) {
                $val = $sheet->getCell($cellCoordinate)->getValue();

                return is_numeric($val) ? (float) $val : 0.0;
            }
        };

        for ($row = 6; $row <= $maxRow; $row++) {
            $col1 = trim((string) $sheet->getCell("A{$row}")->getValue());
            $col2 = trim((string) $sheet->getCell("B{$row}")->getValue());

            if (empty($col1) && empty($col2)) {
                continue;
            }

            // Program header (e.g. 4.02.01 or 4.02.02)
            if (str_starts_with($col1, '4.02') && substr_count($col1, '.') === 2) {
                $currentProgCode = $col1;
                $currentProgName = $col2;

                continue;
            }

            // Subkegiatan row (e.g. 4.02.01.2.01.0001)
            if (str_starts_with($col1, '4.02') && substr_count($col1, '.') >= 3) {
                $pagu = $getVal("C{$row}");
                $lsGaji = $getVal("F{$row}");
                $lsBarjas = $getVal("I{$row}");
                $upGuTu = $getVal("L{$row}");
                $totSpj = $getVal("M{$row}");
                $sisaPagu = $getVal("N{$row}");

                // Intelligently assign default PPTK if not yet set
                $assignedPptkId = null;
                $lowerName = strtolower($col2);

                if (str_contains($lowerName, 'gaji') || str_contains($lowerName, 'perencanaan') || str_contains($lowerName, 'keuangan dprd')) {
                    $assignedPptkId = $aulEty?->id;
                } elseif (str_contains($lowerName, 'logistik') || str_contains($lowerName, 'rumah tangga') || str_contains($lowerName, 'kunjungan tamu') || str_contains($lowerName, 'cetakan')) {
                    $assignedPptkId = $ratman?->id;
                } elseif (str_contains($lowerName, 'peralatan') || str_contains($lowerName, 'mebel') || str_contains($lowerName, 'pemeliharaan')) {
                    $assignedPptkId = $aulRina?->id;
                } elseif (str_contains($lowerName, 'publikasi') || str_contains($lowerName, 'hubungan masyarakat') || str_contains($lowerName, 'dokumentasi')) {
                    $assignedPptkId = $gaya?->id;
                } elseif (str_contains($lowerName, 'peraturan daerah') || str_contains($lowerName, 'perda') || str_contains($lowerName, 'tata tertib') || str_contains($lowerName, 'naskah')) {
                    $assignedPptkId = $sigit?->id;
                } elseif (str_contains($lowerName, 'reses') || str_contains($lowerName, 'pokok-pokok pikiran') || str_contains($lowerName, 'kua') || str_contains($lowerName, 'apbd')) {
                    $assignedPptkId = $sgngSyesa?->id;
                } elseif (str_contains($lowerName, 'pengawasan') || str_contains($lowerName, 'infrastruktur') || str_contains($lowerName, 'kesejahteraan') || str_contains($lowerName, 'perekonomian')) {
                    $assignedPptkId = $sgngEmi?->id;
                } elseif (str_contains($lowerName, 'kode etik') || str_contains($lowerName, 'laporan kinerja')) {
                    $assignedPptkId = $sriL?->id;
                } else {
                    $assignedPptkId = $irna?->id ?? $aulEty?->id;
                }

                $activity = Activity::where('code', $col1)
                    ->where('period_year', $year)
                    ->first();

                if (! $activity) {
                    $activity = new Activity;
                    $activity->code = $col1;
                    $activity->period_year = $year;
                    $activity->pptk_id = $assignedPptkId;
                }

                $activity->name = $col2;
                $activity->program_code = $currentProgCode;
                $activity->program_name = $currentProgName;
                $activity->pagu_budget = $pagu;
                $activity->spj_ls_gaji = $lsGaji;
                $activity->spj_ls_barjas = $lsBarjas;
                $activity->spj_up_gu_tu = $upGuTu;
                $activity->spj_sipd_total = $totSpj;
                $activity->sisa_pagu_sipd = $sisaPagu;
                $activity->save();

                $importedCount++;
                $totalPagu += $pagu;
                $totalSpj += $totSpj;
            }
        }

        return [
            'imported_count' => $importedCount,
            'total_pagu' => $totalPagu,
            'total_spj' => $totalSpj,
            'total_sisa' => $totalPagu - $totalSpj,
        ];
    }

    /**
     * Get overall Real-time Pagu vs Field Realization vs SIPD Overview.
     */
    public function getPaguOverview(int $year, ?User $user = null, ?int $filterPptkId = null): array
    {
        $effectivePptkId = $filterPptkId;
        if ($user && strtolower($user->role) === 'pptk' && $user->pptk_id) {
            $effectivePptkId = $user->pptk_id;
        }

        // Subunits
        $subunits = Subunit::with(['pptks' => function ($q) {
            $q->where('is_active', true)->orderBy('sort_order');
        }])->get();

        // PPTKs
        $pptksQuery = Pptk::where('is_active', true)->orderBy('sort_order');
        if ($effectivePptkId) {
            $pptksQuery->where('id', $effectivePptkId);
        }
        $pptks = $pptksQuery->get();

        // Activities query
        $activitiesQuery = Activity::where('period_year', $year);
        if ($effectivePptkId) {
            $activitiesQuery->where('pptk_id', $effectivePptkId);
        }
        $activities = $activitiesQuery->get();

        // Realizations query
        $realizationsQuery = PaguRealization::where('period_year', $year);
        if ($effectivePptkId) {
            $realizationsQuery->where('pptk_id', $effectivePptkId);
        }
        $allRealizations = $realizationsQuery->get();

        $totalPagu = (float) $activities->sum('pagu_budget');
        $totalSpjSipd = (float) $activities->sum('spj_sipd_total');

        // Field Realizations: Pencairan vs Pengembalian Sisa
        $totalPencairan = (float) $allRealizations->where('type', 'pencairan')->sum('amount');
        $totalPengembalian = (float) $allRealizations->where('type', 'pengembalian')->sum('amount');
        $netRealisasiRiil = $totalPencairan - $totalPengembalian;
        $sisaPaguRiil = $totalPagu - $netRealisasiRiil;
        $persenRealisasiRiil = $totalPagu > 0 ? round(($netRealisasiRiil / $totalPagu) * 100, 2) : 0.0;
        $persenSpjSipd = $totalPagu > 0 ? round(($totalSpjSipd / $totalPagu) * 100, 2) : 0.0;

        // Outstanding: Uang kas yang sudah dicairkan ke lapangan tapi belum di-SPJ-kan ke SIPD
        $outstandingBelumSpj = (float) $allRealizations
            ->where('type', 'pencairan')
            ->where('is_spj_submitted', false)
            ->sum('amount');

        // PPTK Breakdown Table
        $pptkBreakdown = [];
        foreach ($pptks as $p) {
            $pActivities = $activities->where('pptk_id', $p->id);
            $pRealizations = $allRealizations->where('pptk_id', $p->id);

            $pPagu = (float) $pActivities->sum('pagu_budget');
            $pSpjSipd = (float) $pActivities->sum('spj_sipd_total');
            $pPencairan = (float) $pRealizations->where('type', 'pencairan')->sum('amount');
            $pPengembalian = (float) $pRealizations->where('type', 'pengembalian')->sum('amount');
            $pNetRiil = $pPencairan - $pPengembalian;
            $pSisaRiil = $pPagu - $pNetRiil;
            $pBelumSpj = (float) $pRealizations->where('type', 'pencairan')->where('is_spj_submitted', false)->sum('amount');
            $pPersen = $pPagu > 0 ? round(($pNetRiil / $pPagu) * 100, 1) : 0.0;

            $pptkBreakdown[] = [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'display_name' => $p->display_name,
                'subunit_name' => $p->subunit?->name ?? 'Bagian Umum dan Keuangan',
                'subkegiatan_count' => $pActivities->count(),
                'pagu' => $pPagu,
                'pencairan' => $pPencairan,
                'pengembalian' => $pPengembalian,
                'net_realisasi_riil' => $pNetRiil,
                'sisa_pagu_riil' => $pSisaRiil,
                'spj_sipd' => $pSpjSipd,
                'sisa_pagu_sipd' => $pPagu - $pSpjSipd,
                'belum_spj' => $pBelumSpj,
                'persen_serapan' => $pPersen,
            ];
        }

        // Program Breakdown
        $programs = Activity::where('period_year', $year)
            ->selectRaw('program_code, program_name, SUM(pagu_budget) as total_pagu, SUM(spj_sipd_total) as total_spj')
            ->groupBy('program_code', 'program_name')
            ->get()
            ->map(function ($prg) use ($allRealizations, $year) {
                $prgActivities = Activity::where('period_year', $year)
                    ->where('program_code', $prg->program_code)
                    ->pluck('id');

                $prgRealizations = $allRealizations->whereIn('activity_id', $prgActivities);
                $pencairan = (float) $prgRealizations->where('type', 'pencairan')->sum('amount');
                $pengembalian = (float) $prgRealizations->where('type', 'pengembalian')->sum('amount');
                $netRiil = $pencairan - $pengembalian;
                $pagu = (float) $prg->total_pagu;

                return [
                    'code' => $prg->program_code ?? 'Lainnya',
                    'name' => $prg->program_name ?? 'Program Lainnya',
                    'pagu' => $pagu,
                    'spj_sipd' => (float) $prg->total_spj,
                    'net_realisasi_riil' => $netRiil,
                    'sisa_pagu_riil' => $pagu - $netRiil,
                    'persen_serapan' => $pagu > 0 ? round(($netRiil / $pagu) * 100, 1) : 0.0,
                ];
            });

        // Recent 8 Transactions
        $recentTransactions = PaguRealization::where('period_year', $year)
            ->when($effectivePptkId, fn ($q) => $q->where('pptk_id', $effectivePptkId))
            ->with(['pptk:id,name,display_name', 'activity:id,code,name'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(8)
            ->get();

        return [
            'year' => $year,
            'effective_pptk_id' => $effectivePptkId,
            'is_pptk_user' => (bool) ($user && strtolower($user->role) === 'pptk'),
            'kpi' => [
                'total_pagu' => $totalPagu,
                'total_pencairan' => $totalPencairan,
                'total_pengembalian' => $totalPengembalian,
                'net_realisasi_riil' => $netRealisasiRiil,
                'sisa_pagu_riil' => $sisaPaguRiil,
                'persen_realisasi_riil' => $persenRealisasiRiil,
                'total_spj_sipd' => $totalSpjSipd,
                'sisa_pagu_sipd' => $totalPagu - $totalSpjSipd,
                'persen_spj_sipd' => $persenSpjSipd,
                'outstanding_belum_spj' => $outstandingBelumSpj,
            ],
            'pptk_breakdown' => $pptkBreakdown,
            'programs' => $programs,
            'subunits' => $subunits,
            'recent_transactions' => $recentTransactions,
            'all_pptks' => Pptk::where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'display_name']),
        ];
    }

    /**
     * Get paginated transactions list with search and filters.
     */
    public function getTransactions(int $year, array $filters = [], ?User $user = null): LengthAwarePaginator
    {
        $query = PaguRealization::where('period_year', $year)
            ->with(['pptk:id,name,display_name,code', 'activity:id,code,name', 'creator:id,name']);

        // Role restriction
        if ($user && strtolower($user->role) === 'pptk' && $user->pptk_id) {
            $query->where('pptk_id', $user->pptk_id);
        } elseif (! empty($filters['pptk_id'])) {
            $query->where('pptk_id', $filters['pptk_id']);
        }

        if (! empty($filters['activity_id'])) {
            $query->where('activity_id', $filters['activity_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_spj_submitted']) && $filters['is_spj_submitted'] !== '') {
            $query->where('is_spj_submitted', (bool) $filters['is_spj_submitted']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('recipient', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%")
                    ->orWhere('spj_document_number', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * Record new distribution / return transaction.
     */
    public function storeRealization(array $data, User $user): PaguRealization
    {
        return PaguRealization::create([
            'period_year' => $data['period_year'] ?? 2026,
            'pptk_id' => $data['pptk_id'],
            'activity_id' => $data['activity_id'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'type' => $data['type'] ?? 'pencairan',
            'amount' => (float) $data['amount'],
            'recipient' => $data['recipient'] ?? null,
            'description' => $data['description'],
            'source_fund' => $data['source_fund'] ?? 'up_gu',
            'document_number' => $data['document_number'] ?? null,
            'is_spj_submitted' => (bool) ($data['is_spj_submitted'] ?? false),
            'spj_submission_date' => ! empty($data['is_spj_submitted']) ? ($data['spj_submission_date'] ?? $data['transaction_date']) : null,
            'spj_document_number' => $data['spj_document_number'] ?? null,
            'created_by' => $user->id,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Update existing transaction.
     */
    public function updateRealization(PaguRealization $realization, array $data): PaguRealization
    {
        $realization->update([
            'pptk_id' => $data['pptk_id'] ?? $realization->pptk_id,
            'activity_id' => $data['activity_id'] ?? $realization->activity_id,
            'transaction_date' => $data['transaction_date'] ?? $realization->transaction_date,
            'type' => $data['type'] ?? $realization->type,
            'amount' => isset($data['amount']) ? (float) $data['amount'] : $realization->amount,
            'recipient' => $data['recipient'] ?? $realization->recipient,
            'description' => $data['description'] ?? $realization->description,
            'source_fund' => $data['source_fund'] ?? $realization->source_fund,
            'document_number' => $data['document_number'] ?? $realization->document_number,
            'is_spj_submitted' => isset($data['is_spj_submitted']) ? (bool) $data['is_spj_submitted'] : $realization->is_spj_submitted,
            'spj_submission_date' => ! empty($data['is_spj_submitted']) ? ($data['spj_submission_date'] ?? $realization->spj_submission_date) : null,
            'spj_document_number' => $data['spj_document_number'] ?? $realization->spj_document_number,
            'notes' => $data['notes'] ?? $realization->notes,
        ]);

        return $realization;
    }

    /**
     * Delete transaction.
     */
    public function deleteRealization(PaguRealization $realization): bool
    {
        return $realization->delete();
    }

    /**
     * Toggle SPJ Submission status.
     */
    public function toggleSpjStatus(PaguRealization $realization, bool $isSubmitted, ?string $docNumber = null, ?string $date = null): PaguRealization
    {
        $realization->update([
            'is_spj_submitted' => $isSubmitted,
            'spj_document_number' => $isSubmitted ? ($docNumber ?? $realization->spj_document_number ?? 'SPJ-SIPD') : null,
            'spj_submission_date' => $isSubmitted ? ($date ?? now()->format('Y-m-d')) : null,
        ]);

        return $realization;
    }

    /**
     * Get activities list with their real-time remaining pagu calculation.
     */
    public function getActivitiesList(int $year, ?User $user = null, ?int $filterPptkId = null): Collection
    {
        $effectivePptkId = $filterPptkId;
        if ($user && strtolower($user->role) === 'pptk' && $user->pptk_id) {
            $effectivePptkId = $user->pptk_id;
        }

        $query = Activity::where('period_year', $year)
            ->with(['pptk:id,name,display_name,code'])
            ->orderBy('code');

        if ($effectivePptkId) {
            $query->where('pptk_id', $effectivePptkId);
        }

        $activities = $query->get();
        $realizations = PaguRealization::where('period_year', $year)->get();

        return $activities->map(function ($act) use ($realizations) {
            $actRealizations = $realizations->where('activity_id', $act->id);
            $pencairan = (float) $actRealizations->where('type', 'pencairan')->sum('amount');
            $pengembalian = (float) $actRealizations->where('type', 'pengembalian')->sum('amount');
            $netRiil = $pencairan - $pengembalian;
            $sisaPaguRiil = (float) $act->pagu_budget - $netRiil;
            $belumSpj = (float) $actRealizations->where('type', 'pencairan')->where('is_spj_submitted', false)->sum('amount');

            return [
                'id' => $act->id,
                'code' => $act->code,
                'name' => $act->name,
                'program_code' => $act->program_code,
                'program_name' => $act->program_name,
                'pptk_id' => $act->pptk_id,
                'pptk_name' => $act->pptk?->display_name ?? 'Belum Ditugaskan',
                'pagu_budget' => (float) $act->pagu_budget,
                'pencairan' => $pencairan,
                'pengembalian' => $pengembalian,
                'net_realisasi_riil' => $netRiil,
                'sisa_pagu_riil' => $sisaPaguRiil,
                'spj_sipd_total' => (float) $act->spj_sipd_total,
                'sisa_pagu_sipd' => (float) $act->sisa_pagu_sipd,
                'belum_spj' => $belumSpj,
                'persen_serapan' => $act->pagu_budget > 0 ? round(($netRiil / (float) $act->pagu_budget) * 100, 1) : 0.0,
            ];
        });
    }

    /**
     * Assign / Reassign PPTK to an Activity.
     */
    public function assignActivityPptk(Activity $activity, int $pptkId): Activity
    {
        $activity->pptk_id = $pptkId;
        $activity->save();

        return $activity;
    }
}
