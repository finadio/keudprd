<?php

namespace Database\Seeders;

use App\Models\AccountingPeriod;
use App\Models\ManualAdjustment;
use App\Models\MasterSetting;
use App\Models\Pptk;
use App\Models\Subunit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Users
        $admin = User::firstOrCreate(
            ['email' => 'admin@dprd.banyumaskab.go.id'],
            [
                'name' => 'Administrator Keuangan',
                'password' => Hash::make('password'),
            ]
        );

        $bendahara = User::firstOrCreate(
            ['email' => 'bendahara@dprd.banyumaskab.go.id'],
            [
                'name' => 'INDAH SETIYOWATI, A.Md.AB.',
                'password' => Hash::make('password'),
            ]
        );

        $operator = User::firstOrCreate(
            ['email' => 'operator@dprd.banyumaskab.go.id'],
            [
                'name' => 'Operator Pembukuan',
                'password' => Hash::make('password'),
            ]
        );

        // 2. Master Settings
        MasterSetting::firstOrCreate(
            ['fiscal_year' => 2026],
            [
                'skpd_name' => 'SETWAN KABUPATEN BANYUMAS',
                'pa_kpa_name' => 'ARIF TRIYANTO, S.Sos. M.Eng.',
                'pa_kpa_nip' => '19680430 199002 1 003',
                'treasurer_name' => 'INDAH SETIYOWATI, A.Md.AB.',
                'treasurer_nip' => '19850615 201001 2 021',
                'effective_start_date' => '2026-01-01',
                'effective_end_date' => '2026-12-31',
                'is_active' => true,
            ]
        );

        // 3. Subunits
        $subUmum = Subunit::firstOrCreate(
            ['code' => 'BAG_UMUM'],
            ['name' => 'Bagian Umum dan Keuangan', 'description' => 'Sekretariat DPRD Kab. Banyumas']
        );

        $subSidang = Subunit::firstOrCreate(
            ['code' => 'BAG_SIDANG'],
            ['name' => 'Bagian Persidangan dan Perundang-undangan', 'description' => 'Sekretariat DPRD Kab. Banyumas']
        );

        $subFasilitasi = Subunit::firstOrCreate(
            ['code' => 'BAG_FASILITASI'],
            ['name' => 'Bagian Fasilitasi Penganggaran dan Pengawasan', 'description' => 'Sekretariat DPRD Kab. Banyumas']
        );

        // 4. Master 9 PPTK
        $pptks = [
            [
                'code' => 'AUL_ETY',
                'name' => 'CHAZANAH AULIA NADIYANTI, S.T. (ETY)',
                'display_name' => 'B Aulia/Ety',
                'sheet_name' => 'Aul-Ety',
                'subunit_id' => $subUmum->id,
                'color_tag' => '#3b82f6',
                'sort_order' => 1,
            ],
            [
                'code' => 'GAYA',
                'name' => 'GAYATRI',
                'display_name' => 'B Gaya',
                'sheet_name' => 'Gaya',
                'subunit_id' => $subUmum->id,
                'color_tag' => '#ec4899',
                'sort_order' => 2,
            ],
            [
                'code' => 'RATMAN',
                'name' => 'SURATMAN, S.E.',
                'display_name' => 'P Ratman',
                'sheet_name' => 'Ratman',
                'subunit_id' => $subUmum->id,
                'color_tag' => '#10b981',
                'sort_order' => 3,
            ],
            [
                'code' => 'AUL_RINA',
                'name' => 'CHAZANAH AULIA NADIYANTI, S.T. (RINA)',
                'display_name' => 'B Aulia / Rina',
                'sheet_name' => 'Aul-Rina',
                'subunit_id' => $subUmum->id,
                'color_tag' => '#6366f1',
                'sort_order' => 4,
            ],
            [
                'code' => 'SIGIT',
                'name' => 'SIGIT DWI YUNIANTO, S.H.',
                'display_name' => 'P Sigit',
                'sheet_name' => 'Sigit',
                'subunit_id' => $subSidang->id,
                'color_tag' => '#f59e0b',
                'sort_order' => 5,
            ],
            [
                'code' => 'SGNG_SYESA',
                'name' => 'SUGENG BUDI W. / SYESARIA',
                'display_name' => 'P Sugeng/Syesa',
                'sheet_name' => 'SgngSyesa',
                'subunit_id' => $subFasilitasi->id,
                'color_tag' => '#8b5cf6',
                'sort_order' => 6,
            ],
            [
                'code' => 'SGNG_EMI',
                'name' => 'SUGENG BUDI W. / EMI',
                'display_name' => 'P Sugeng/Emi',
                'sheet_name' => 'SgngEmi',
                'subunit_id' => $subFasilitasi->id,
                'color_tag' => '#14b8a6',
                'sort_order' => 7,
            ],
            [
                'code' => 'SRI_L',
                'name' => 'SRI LESTARI',
                'display_name' => 'B Sri L',
                'sheet_name' => 'Sri L',
                'subunit_id' => $subSidang->id,
                'color_tag' => '#f97316',
                'sort_order' => 8,
            ],
            [
                'code' => 'IRNA',
                'name' => 'IRNAWATINGRUM, S.S.TP., M.Si.',
                'display_name' => 'Irna',
                'sheet_name' => 'Irna',
                'subunit_id' => $subUmum->id,
                'color_tag' => '#06b6d4',
                'sort_order' => 9,
            ],
        ];

        foreach ($pptks as $p) {
            Pptk::firstOrCreate(['code' => $p['code']], $p);
        }

        // 5. Accounting Periods (12 Months)
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        foreach ($monthNames as $mNum => $mName) {
            AccountingPeriod::firstOrCreate(
                ['fiscal_year' => 2026, 'month' => $mNum],
                ['name' => "{$mName} 2026", 'is_closed' => false]
            );
        }

        // 6. Seed Known Manual Adjustments from Workbook analysis
        $pptkEty = Pptk::where('code', 'AUL_ETY')->first();
        if ($pptkEty) {
            ManualAdjustment::firstOrCreate(
                [
                    'pptk_id' => $pptkEty->id,
                    'period_month' => 2,
                    'period_year' => 2026,
                    'amount' => -500000.00,
                ],
                [
                    'adjustment_date' => '2026-02-28',
                    'adjustment_type' => 'debet_reduction',
                    'reason' => 'Catatan Ety: Penyesuaian debet rekening Februari',
                    'authorized_by' => $bendahara->id,
                ]
            );
        }

        $pptkRatman = Pptk::where('code', 'RATMAN')->first();
        if ($pptkRatman) {
            ManualAdjustment::firstOrCreate(
                [
                    'pptk_id' => $pptkRatman->id,
                    'period_month' => 2,
                    'period_year' => 2026,
                    'amount' => -3410751.00,
                ],
                [
                    'adjustment_date' => '2026-02-28',
                    'adjustment_type' => 'debet_reduction',
                    'reason' => 'Catatan Genta: Penyesuaian belanja debet pemeliharaan/logistik Februari',
                    'authorized_by' => $bendahara->id,
                ]
            );
        }
    }
}
