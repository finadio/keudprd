<?php

namespace Database\Seeders;

use App\Models\Gaji\GajiBudgetAllocation;
use App\Models\Gaji\GajiConfig;
use App\Models\Gaji\GajiRecipient;
use Illuminate\Database\Seeder;

class GajiInitialDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Inisialisasi Alokasi Kontrol Anggaran (Pagu APBD 2026)
        GajiBudgetAllocation::firstOrCreate(
            ['fiscal_year' => 2026, 'category' => 'dprd'],
            [
                'budget_name' => 'Belanja Gaji dan Tunjangan DPRD Kabupaten Banyumas',
                'account_code' => '5.1.01.01.01.0001',
                'annual_pagu' => 36955949123.0, // Alokasi APBD 2026 Resmi
                'monthly_allocation' => 36955949123.0 / 12,
                'realized_amount' => 0.0,
                'notes' => 'Kepgub Jateng 100.3.3.1/462/2025 jo Perbup Banyumas 6/2026',
            ]
        );

        GajiBudgetAllocation::firstOrCreate(
            ['fiscal_year' => 2026, 'category' => 'asn'],
            [
                'budget_name' => 'Belanja Gaji, Tunjangan, dan TPP ASN Sekretariat DPRD',
                'account_code' => '5.1.01.01.01.0002',
                'annual_pagu' => 8500000000.0, // Alokasi Pagu Gaji & TPP Setwan 2026
                'monthly_allocation' => 8500000000.0 / 12,
                'realized_amount' => 0.0,
                'notes' => 'Alokasi Pagu Gaji Pokok, Tunjangan Keluarga/Beras & TPP ASN Setwan',
            ]
        );

        // 2. Inisialisasi Konfigurasi Dasar
        GajiConfig::firstOrCreate(
            ['config_key' => 'dprd_rates_2026'],
            [
                'category' => 'dprd',
                'name' => 'Tarif Dasar Hak Keuangan DPRD 2026',
                'description' => 'Besaran uang representasi, tunjangan perumahan, transportasi, TKI',
                'config_value' => [
                    'uang_representasi_ketua' => 2100000.0,
                    'pct_wakil_ketua' => 80.0,
                    'pct_anggota' => 75.0,
                    'pct_uang_paket' => 10.0,
                    'pct_tunj_jabatan' => 145.0,
                    'tki_multiplier' => 7,
                    'housing_ketua' => 42625000.0,
                    'housing_wakil_ketua' => 34650000.0,
                    'housing_anggota' => 23650000.0,
                    'transport_anggota' => 13500000.0,
                    'transport_pimpinan' => 14500000.0,
                    'reses_per_event' => 14700000.0,
                    'pct_akd_ketua' => 7.5,
                    'pct_akd_wakil' => 5.0,
                    'pct_akd_sekretaris' => 4.0,
                    'pct_akd_anggota' => 3.0,
                    'pct_pph21' => 15.0,
                    'pct_bpjs' => 1.0,
                ],
                'legal_reference' => 'PP 18/2017 jo PP 1/2023, Perda Banyumas 6/2017, Perbup 9/2024',
                'is_active' => true,
            ]
        );

        GajiConfig::firstOrCreate(
            ['config_key' => 'asn_allowance_rates_2026'],
            [
                'category' => 'asn',
                'name' => 'Formula Komposisi Tunjangan & TPP ASN 2026',
                'description' => 'Persentase tunjangan keluarga, beras, bobot produktivitas/disiplin TPP, dan standar PPPK Paruh Waktu',
                'config_value' => [
                    'pct_spouse' => 10.0,
                    'pct_child' => 2.0,
                    'rice_per_person' => 72420.0,
                    'tpp_prod_weight' => 70.0,
                    'tpp_disp_weight' => 30.0,
                    'pppk_paruh_waktu_default' => 1500000.0,
                    'iwp_pct' => 8.0,
                    'bpjs_pct' => 1.0,
                ],
                'legal_reference' => 'PP 5/2024, Perpres 11/2024, Perbup Banyumas 104/2025',
                'is_active' => true,
            ]
        );

        GajiConfig::firstOrCreate(
            ['config_key' => 'tpp_banyumas_rates_2026'],
            [
                'category' => 'asn',
                'name' => 'Pagu Dasar TPP ASN Setwan Banyumas 2026',
                'description' => 'Besaran TPP Dasar per kelas jabatan di Sekretariat DPRD',
                'config_value' => [
                    'class_14' => 15000000.0,
                    'class_12' => 8500000.0,
                    'class_9' => 5250000.0,
                    'class_8' => 4000000.0,
                    'class_7' => 3300000.0,
                    'class_6' => 2800000.0,
                    'class_5' => 2700000.0,
                    'class_3' => 2600000.0,
                    'class_1' => 2100000.0,
                    'pppk_penuh_waktu' => 1600000.0,
                    'pppk_paruh_waktu' => 0.0,
                ],
                'legal_reference' => 'Perbup Banyumas Nomor 5 Tahun 2025 jo Perbup Nomor 104 Tahun 2025',
                'is_active' => true,
            ]
        );

        // 3. SEEDING 20 PNS SEKRETARIAT DPRD (Lampiran A.1)
        $pnsList = [
            ['Arif Triyanto, S.Sos., M.Eng', 'Sekretaris DPRD', 'IV/c', 14, 20],
            ['Sri Heriyati, S.H', 'Kepala Bagian Persidangan dan Perundang-undangan', 'IV/a', 12, 16],
            ['Sri Utami, S.H', 'Kepala Bagian Fasilitasi Penganggaran dan Pengawasan', 'IV/b', 12, 18],
            ['Efi Irmawanti, S.E., M.M.', 'Kepala Bagian Perencanaan dan Keuangan', 'IV/b', 12, 18],
            ['Fendy Rudianto, S.E.', 'Kepala Bagian Umum', 'IV/a', 12, 16],
            ['Suratman, S.E., M.M.', 'Kasubbag Rumah Tangga dan Perlengkapan', 'IV/a', 9, 14],
            ['Chazanah Aulia, S.T.', 'Kasubbag TU dan Kepegawaian', 'III/d', 9, 12],
            ['Nangimatul Purwaningsih, S.Sos', 'Kasubbag Keuangan', 'III/d', 9, 12],
            ['Sugeng Budi Wurianto, S.T., M.Si.', 'Analis Kebijakan Ahli Muda', 'IV/a', 9, 14],
            ['Gayatri, S.Sos', 'Perisalah Legislatif Ahli Muda', 'III/d', 9, 12],
            ['Sigit Dwi Yunianto, S.H.', 'Perancang Peraturan Perundang-undangan Ahli Muda', 'III/d', 9, 12],
            ['Prastiwi Widjajanti', 'Pengadministrasi Perkantoran', 'III/b', 7, 10],
            ['Sri Lestari', 'Pengadministrasi Perkantoran', 'III/b', 7, 10],
            ['Kariyanto', 'Pengadministrasi Perkantoran', 'III/b', 7, 10],
            ['Tri Purwanti, S.Sos', 'Penelaah Teknis Kebijakan pada Subbagian Keuangan', 'III/a', 7, 6],
            ['Agus Sutrisno', 'Penata Layanan Operasional', 'I/d', 3, 4],
            ['Indah Setiowati, A.Md.AB', 'Pengolah Data dan Informasi', 'II/c', 6, 4],
            ['Irnawatin Ingrum, S.STP., M.A.P.', 'Penelaah Teknis Kebijakan', 'II/c', 6, 4],
            ['Amin Nuruddin', 'Pengadministrasi Perkantoran', 'III/b', 7, 10],
            ['Fadel Muamar Rozika, S.Tr.IP', 'Penelaah Teknis Kebijakan', 'III/a', 7, 2],
        ];

        foreach ($pnsList as $p) {
            GajiRecipient::firstOrCreate(
                ['name' => $p[0], 'category' => 'asn'],
                [
                    'category' => 'asn',
                    'sub_category' => 'pns',
                    'position_name' => $p[1],
                    'grade' => $p[2],
                    'job_class' => $p[3],
                    'mkg_years' => $p[4],
                    'marital_status' => 'married',
                    'children_count' => 2,
                    'is_active' => true,
                ]
            );
        }

        // 4. SEEDING 24 PPPK PENUH WAKTU (Lampiran A.2)
        $pppkPenuhList = [
            ['Milla Yans Julies Rismet, S.E.', 'Perisalah Legislatif Ahli Pertama', 'IX'],
            ['Ixora Adhitama, S.H.', 'Analis Hukum Ahli Pertama', 'IX'],
            ['Dian Anggraeni Kumala D., S.E.', 'Perencana Ahli Pertama', 'IX'],
            ['Mohandas Nur Alfan, S.Kom.', 'Pranata Komputer Ahli Pertama', 'IX'],
            ['Linatus Shofiyah, S.Si.', 'Penata Layanan Operasional', 'IX'],
            ['Syesaria Ayuning Pangastuti, S.Psi.', 'Penata Layanan Operasional', 'IX'],
            ['Tarkim', 'Operator Layanan Operasional', 'V'],
            ['Sasmita Wulansih, S.P.', 'Penata Layanan Operasional', 'IX'],
            ['Emi Andriastanti, A.Md.', 'Pengelola Layanan Operasional', 'VII'],
            ['Randi Setiyono, A.Md.', 'Pengelola Layanan Operasional', 'VII'],
            ['Dwika Leksana Putra, S.Sos', 'Penata Layanan Operasional', 'IX'],
            ['Muchammad Ikhsanuddin', 'Operator Layanan Operasional', 'V'],
            ['Zaenal Asikin', 'Pengelola Umum Operasional', 'I'],
            ['Sugeng Tinggal', 'Pengelola Umum Operasional', 'I'],
            ['Eti Nurul Ardiati, A.Md.', 'Pengelola Layanan Operasional', 'VII'],
            ['Salsadilla Vania Oktavianti', 'Operator Layanan Operasional', 'V'],
            ['Rina Widyawati, S.Pd.', 'Penata Layanan Operasional', 'IX'],
            ['Jarwoto Ariyono, A.Md.', 'Pengelola Layanan Operasional', 'VII'],
            ['Liya Ulfah Palupi, A.Md.Keb.', 'Pengelola Layanan Operasional', 'VII'],
            ['Priyatno', 'Pengelola Umum Operasional', 'I'],
            ['Gilang Abdul R.', 'Operator Layanan Operasional', 'V'],
            ['Dita Ibnu Ariandana', 'Operator Layanan Operasional', 'V'],
            ['Indra Hari Suseno', 'Operator Layanan Operasional', 'V'],
            ['Yanuar Siswanto', 'Operator Layanan Operasional', 'V'],
        ];

        foreach ($pppkPenuhList as $pw) {
            GajiRecipient::firstOrCreate(
                ['name' => $pw[0], 'category' => 'asn'],
                [
                    'category' => 'asn',
                    'sub_category' => 'pppk_penuh_waktu',
                    'position_name' => $pw[1],
                    'grade' => $pw[2],
                    'mkg_years' => 2,
                    'job_class' => 7,
                    'tpp_base_amount' => 1600000.0, // Perbup 104/2025
                    'marital_status' => 'married',
                    'children_count' => 1,
                    'is_active' => true,
                ]
            );
        }

        // 5. SEEDING 17 PPPK PARUH WAKTU (Lampiran A.3)
        $pppkParuhList = [
            ['Abdiel Savero Rahadian Janitra', 'Operator Layanan Operasional'],
            ['Agnes Setiawan', 'Operator Layanan Operasional'],
            ['Dias Gigih Wicaksono', 'Operator Layanan Operasional'],
            ['Bambang Hermanto', 'Operator Layanan Operasional'],
            ['Yohanes Hendri Pranowo', 'Operator Layanan Operasional'],
            ['Ardhy Wicaksono', 'Operator Layanan Operasional'],
            ['Ricky Junaedi', 'Operator Layanan Operasional'],
            ['Solekhan', 'Operator Layanan Operasional'],
            ['Jihan Nur Ariqoh, S.A.P.', 'Penata Layanan Operasional'],
            ['Ayu Nur Ariantina, S.H.', 'Penata Layanan Operasional'],
            ['Velly Fatmawati, S.Kep.', 'Penata Layanan Operasional'],
            ['Wanda Genta Dwifebrian, S.Sos.', 'Penata Layanan Operasional'],
            ['Cristyana Puspita Sari, S.A.P.', 'Penata Layanan Operasional'],
            ['Kristanto Aji Nugroho, A.Md.', 'Pengelola Layanan Operasional'],
            ['Zaenur Adi', 'Pengelola Umum Operasional'],
            ['Urip Budi Priyatno', 'Pengelola Umum Operasional'],
            ['Reliyanto', 'Pengelola Umum Operasional'],
        ];

        foreach ($pppkParuhList as $pr) {
            GajiRecipient::firstOrCreate(
                ['name' => $pr[0], 'category' => 'asn'],
                [
                    'category' => 'asn',
                    'sub_category' => 'pppk_paruh_waktu',
                    'position_name' => $pr[1],
                    'grade' => 'Paruh Waktu',
                    'fixed_salary' => 2200000.0,
                    'tpp_base_amount' => 0.0, // Dikecualikan dari TPP
                    'is_active' => true,
                ]
            );
        }

        // 6. SEEDING 4 PIMPINAN DPRD (Lampiran B.1)
        $pimpinanDprd = [
            ['Agus Priyanggodo', 'ketua_dprd', 'Ketua DPRD', 'PDI Perjuangan', true, true],
            ['Imam Ahfas, S.Pd., M.Pd.', 'wakil_ketua_dprd', 'Wakil Ketua DPRD', 'PKB', true, true],
            ['Muh. Erlangga Adinugraha, S.M.', 'wakil_ketua_dprd', 'Wakil Ketua DPRD', 'Partai Gerindra', true, true],
            ['Joko Pramono, S.E.', 'wakil_ketua_dprd', 'Wakil Ketua DPRD', 'PKS', true, true],
        ];

        foreach ($pimpinanDprd as $pim) {
            GajiRecipient::firstOrCreate(
                ['name' => $pim[0], 'category' => 'dprd'],
                [
                    'category' => 'dprd',
                    'sub_category' => $pim[1],
                    'position_name' => $pim[2],
                    'political_party' => $pim[3],
                    'has_official_residence' => false, // Mendapat tunjangan perumahan
                    'has_official_car' => true, // Disediakan kendaraan dinas jabatan pimpinan
                    'akd_role' => 'ketua',
                    'marital_status' => 'married',
                    'children_count' => 2,
                    'is_active' => true,
                ]
            );
        }

        // 7. SEEDING 46 ANGGOTA DPRD (Lampiran B.2)
        $anggotaDprd = [
            ['Iwan Supriyanto, S.H., M.H.', 'Partai Demokrat'],
            ['Abdullah Arif Budiman, S.E.', 'Partai Demokrat'],
            ['Ir. Budiyono', 'Partai Gerindra'],
            ['Bobby Listyo Widjatmoko, S.H.', 'Partai Gerindra'],
            ['Hj. Yuningsih', 'Partai Gerindra'],
            ['Siswondo', 'Partai Gerindra'],
            ['Alfiatun Khasanah, S.Tr., Keb.', 'Partai Gerindra'],
            ['H. Rachmat Imanda, S.E.Ak.', 'Partai Gerindra'],
            ['Dodet Suryondaru Maduranto, S.TP.', 'Partai Golkar'],
            ['Dr. H. Supangkat, S.H., M.H.', 'Partai Golkar'],
            ['Setia Budiyanto, S.Hut., S.H.', 'Partai Golkar'],
            ['Arief Dwi Kusuma Wardhana, S.E.', 'Partai Golkar'],
            ['Andik Pegiarto, S.KM.', 'Partai Golkar'],
            ['Nartam Andrea Nusa', 'Partai NasDem'],
            ['Cipto Pujiharso, S.E.', 'PAN'],
            ['Muchtar Nasir, S.Pd.', 'PAN'],
            ['Subagyo, S.Pd., M.Si.', 'PDI Perjuangan'],
            ['Didi Rudianto, S.Par., M.M.', 'PDI Perjuangan'],
            ['Jasmin, S.H.', 'PDI Perjuangan'],
            ['Andri Kusmayadi, S.T.', 'PDI Perjuangan'],
            ['Merakarno Ra\'husna Taruno, S.T.', 'PDI Perjuangan'],
            ['Agus Supriyanto', 'PDI Perjuangan'],
            ['Sandung Wijoko', 'PDI Perjuangan'],
            ['H. Anang Agus Kostrad Diharto', 'PDI Perjuangan'],
            ['H. Samsudin Tirta, S.E., M.M.', 'PDI Perjuangan'],
            ['Wawan Yuwandha, S.P.', 'PDI Perjuangan'],
            ['drg. Andrias Kartikosari', 'PDI Perjuangan'],
            ['dr. Henry Christanto', 'PDI Perjuangan'],
            ['Ofan Sofiyan, S.Sos.', 'PDI Perjuangan'],
            ['Ito Anjarini, S.Sos.', 'PDI Perjuangan'],
            ['Rellya Venny Octalina', 'PDI Perjuangan'],
            ['Trisno Sudarso, S.Sos.', 'PDI Perjuangan'],
            ['H. Woro Sulistiyono, S.H.', 'PKB'],
            ['H. Ahmad Darisun', 'PKB'],
            ['Dr. Mugiarti, S.Pd., M.M.', 'PKB'],
            ['Imam Santosa, S.H.', 'PKB'],
            ['Tati Irawati, A.Md.', 'PKB'],
            ['Dukha Ngabdul Wasih', 'PKB'],
            ['Mustofa, S.Ag.', 'PKB'],
            ['Danan Setianto, S.E.', 'PKB'],
            ['Atik Luthfiyah', 'PKS'],
            ['Dedi Supriyanto', 'PKS'],
            ['Eko Pramono', 'PKS'],
            ['Slamet Sukoco, S.TP.', 'PKS'],
            ['Sutrisno Rudiyanto', 'PKS'],
            ['Balqis Fadillah, S.H.I., M.Pd.', 'PPP'],
        ];

        foreach ($anggotaDprd as $ang) {
            GajiRecipient::firstOrCreate(
                ['name' => $ang[0], 'category' => 'dprd'],
                [
                    'category' => 'dprd',
                    'sub_category' => 'anggota_dprd',
                    'position_name' => 'Anggota DPRD',
                    'political_party' => $ang[1],
                    'has_official_residence' => false,
                    'has_official_car' => false,
                    'akd_role' => 'anggota',
                    'marital_status' => 'married',
                    'children_count' => 2,
                    'is_active' => true,
                ]
            );
        }
    }
}
