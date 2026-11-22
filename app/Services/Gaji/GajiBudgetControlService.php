<?php

namespace App\Services\Gaji;

use App\Models\Gaji\GajiBudgetAllocation;
use App\Models\Gaji\GajiRecipient;

class GajiBudgetControlService
{
    /**
     * Jalankan analisis kontrol anggaran dan diagnosa penyebab selisih.
     */
    public function analyzeBudgetFeasibility(
        int $year,
        int $month,
        float $asnGrossRequirement,
        float $dprdGrossRequirement,
        array $payrollItems
    ): array {
        // Ambil data alokasi pagu tahun anggaran terkait
        $dprdAlloc = GajiBudgetAllocation::where('fiscal_year', $year)
            ->where('category', 'dprd')
            ->first();

        $asnAlloc = GajiBudgetAllocation::where('fiscal_year', $year)
            ->where('category', 'asn')
            ->first();

        // Default alokasi APBD 2026 jika belum terkonfigurasi manual
        $dprdAnnualPagu = $dprdAlloc?->annual_pagu ?? 36955949123.0; // Rp 36,95 Miliar APBD 2026
        $asnAnnualPagu = $asnAlloc?->annual_pagu ?? 8500000000.0;     // Estimasi Pagu ASN Setwan 2026

        $dprdMonthlyBudget = $dprdAlloc?->monthly_allocation ?: ($dprdAnnualPagu / 12);
        $asnMonthlyBudget = $asnAlloc?->monthly_allocation ?: ($asnAnnualPagu / 12);

        $totalMonthlyBudget = $dprdMonthlyBudget + $asnMonthlyBudget;
        $totalGrossRequirement = $asnGrossRequirement + $dprdGrossRequirement;
        $budgetDiff = $totalMonthlyBudget - $totalGrossRequirement;
        $isSufficient = $budgetDiff >= 0;

        // Diagnosa Mendalam Penyebab Selisih & Anomali
        $diagnostics = [];

        // 1. Cek defisit anggaran keseluruhan
        if (! $isSufficient) {
            $diagnostics[] = [
                'type' => 'deficit_warning',
                'severity' => 'danger',
                'title' => 'Kekurangan Saldo Anggaran (Defisit)',
                'message' => 'Total kebutuhan pembayaran (Rp '.number_format($totalGrossRequirement, 0, ',', '.').
                             ') melebihi alokasi plafon bulanan (Rp '.number_format($totalMonthlyBudget, 0, ',', '.').
                             ') sebesar Rp '.number_format(abs($budgetDiff), 0, ',', '.').'.',
                'recommendation' => 'Ajukan penyesuaian pergeseran anggaran kas subkegiatan belanja gaji atau verifikasi ulang komponen belanja.',
            ];
        }

        // 2. Cek DPRD: Dobel klaim fasilitas perumahan / transportasi
        $doubleClaims = 0;
        foreach ($payrollItems as $item) {
            if ($item['category'] === 'dprd') {
                $meta = $item['calculation_meta'] ?? [];
                if (! empty($meta['has_residence']) && ($item['housing_allowance'] ?? 0) > 0) {
                    $doubleClaims++;
                    $diagnostics[] = [
                        'type' => 'double_claim_residence',
                        'severity' => 'warning',
                        'title' => 'Indikasi Perhitungan Ganda: Tunjangan Perumahan',
                        'message' => 'Penerima atas nama '.($item['recipient_name'] ?? 'DPRD').' tercatat telah menempati Rumah Negara, namun tunjangan perumahan tetap terhitung.',
                        'recommendation' => 'Nonaktifkan komponen tunjangan perumahan untuk penerima yang telah menempati rumah dinas negara.',
                    ];
                }
                if (! empty($meta['has_car']) && ($item['transport_allowance'] ?? 0) > 0) {
                    $doubleClaims++;
                    $diagnostics[] = [
                        'type' => 'double_claim_car',
                        'severity' => 'warning',
                        'title' => 'Indikasi Perhitungan Ganda: Tunjangan Transportasi',
                        'message' => 'Penerima atas nama '.($item['recipient_name'] ?? 'DPRD').' tercatat telah mendapatkan kendaraan dinas jabatan, namun tunjangan transportasi tetap terhitung.',
                        'recommendation' => 'Sesuaikan hak tunjangan transportasi pimpinan/anggota dewan.',
                    ];
                }
            }
        }

        // 3. Cek Anggota DPRD yang belum terpetakan AKD
        $missingAkd = 0;
        foreach ($payrollItems as $item) {
            if ($item['category'] === 'dprd' && ($item['sub_category'] === 'anggota_dprd')) {
                if (($item['akd_allowance'] ?? 0) == 0) {
                    $missingAkd++;
                }
            }
        }
        if ($missingAkd > 0) {
            $diagnostics[] = [
                'type' => 'missing_akd',
                'severity' => 'info',
                'title' => "Tunjangan AKD Belum Terpasang ($missingAkd Anggota)",
                'message' => "Terdapat $missingAkd anggota DPRD yang tunjangan Alat Kelengkapan Dewan (AKD)-nya bernilai Rp 0.",
                'recommendation' => 'Periksa SK penempatan pimpinan/anggota pada Komisi, Bamus, Banggar, Bapemperda, atau BK untuk memasang tunjangan AKD.',
            ];
        }

        // 4. Cek ASN: Verifikasi PPPK Paruh Waktu
        $pppkPwCount = GajiRecipient::where('sub_category', 'pppk_paruh_waktu')->where('is_active', true)->count();
        if ($pppkPwCount > 0) {
            $diagnostics[] = [
                'type' => 'pppk_pw_notice',
                'severity' => 'info',
                'title' => "Kepatuhan Regulasi PPPK Paruh Waktu ($pppkPwCount Orang)",
                'message' => 'Sesuai Pasal 18 huruf l Perbup Banyumas 104/2025, PPPK Paruh Waktu dikecualikan dari TPP. Sistem telah memastikan TPP bernilai Rp 0.',
                'recommendation' => 'Pastikan besaran kompensasi/upah bulanan telah disesuaikan dengan surat perjanjian kerja aktif.',
            ];
        }

        if (empty($diagnostics) && $isSufficient) {
            $diagnostics[] = [
                'type' => 'all_clear',
                'severity' => 'success',
                'title' => 'Seluruh Parameter Valid & Anggaran Mencukupi',
                'message' => 'Tidak ditemukan anomali atau perhitungan ganda. Saldo plafon kas bulanan mencukupi untuk memproses pembayaran.',
                'recommendation' => 'Dapat dilanjutkan ke proses penerbitan Surat Permintaan Pembayaran (SPP) / SP2D penggajian.',
            ];
        }

        return [
            'is_sufficient' => $isSufficient,
            'status' => $isSufficient ? 'sufficient' : 'deficit',
            'status_label' => $isSufficient ? 'Anggaran Mencukupi' : 'Anggaran Tidak Mencukupi (Defisit)',
            'annual_pagu' => [
                'dprd' => $dprdAnnualPagu,
                'asn' => $asnAnnualPagu,
                'total' => $dprdAnnualPagu + $asnAnnualPagu,
            ],
            'monthly_allocation' => [
                'dprd' => $dprdMonthlyBudget,
                'asn' => $asnMonthlyBudget,
                'total' => $totalMonthlyBudget,
            ],
            'gross_requirement' => [
                'dprd' => $dprdGrossRequirement,
                'asn' => $asnGrossRequirement,
                'total' => $totalGrossRequirement,
            ],
            'budget_difference' => $budgetDiff,
            'burn_rate_pct' => $totalMonthlyBudget > 0 ? round(($totalGrossRequirement / $totalMonthlyBudget) * 100, 2) : 0.0,
            'diagnostics' => $diagnostics,
        ];
    }
}
