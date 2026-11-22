<?php

namespace App\Services\Gaji;

use App\Models\Gaji\GajiConfig;
use App\Models\Gaji\GajiRecipient;

class GajiCalculatorService
{
    protected ?array $dprdRates = null;

    protected ?array $tppRates = null;

    protected ?array $asnRates = null;

    public function __construct()
    {
        $this->loadConfigs();
    }

    public function loadConfigs(): void
    {
        $this->dprdRates = GajiConfig::getValue('dprd_rates_2026') ?? [];
        $this->tppRates = GajiConfig::getValue('tpp_banyumas_rates_2026') ?? [];
        $this->asnRates = GajiConfig::getValue('asn_allowance_rates_2026') ?? [];
    }

    public function getDprdRate(string $key, float $default): float
    {
        return isset($this->dprdRates[$key]) ? (float) $this->dprdRates[$key] : $default;
    }

    public function getAsnRate(string $key, float $default): float
    {
        return isset($this->asnRates[$key]) ? (float) $this->asnRates[$key] : $default;
    }

    public function getTppClassRate(int|string $class, float $default): float
    {
        $k = 'class_'.$class;
        if (isset($this->tppRates[$k])) {
            return (float) $this->tppRates[$k];
        }

        return $default;
    }

    /**
     * Tabel Gaji Pokok PNS (PP Nomor 5 Tahun 2024) [Sample grid Golongan & MKG Min-Max].
     */
    protected array $pnsScales = [
        'I/a' => ['min' => 1685700, 'max' => 2522600, 'step' => 32000],
        'I/b' => ['min' => 1840800, 'max' => 2670700, 'step' => 33000],
        'I/c' => ['min' => 1918700, 'max' => 2783700, 'step' => 34000],
        'I/d' => ['min' => 1999900, 'max' => 2901400, 'step' => 35000],
        'II/a' => ['min' => 2184000, 'max' => 3643400, 'step' => 45000],
        'II/b' => ['min' => 2385000, 'max' => 3797500, 'step' => 46000],
        'II/c' => ['min' => 2485900, 'max' => 3958200, 'step' => 47000],
        'II/d' => ['min' => 2591100, 'max' => 4125600, 'step' => 48000],
        'III/a' => ['min' => 2785700, 'max' => 4575200, 'step' => 56000],
        'III/b' => ['min' => 2903600, 'max' => 4768800, 'step' => 58000],
        'III/c' => ['min' => 3026400, 'max' => 4970500, 'step' => 60000],
        'III/d' => ['min' => 3154400, 'max' => 5180700, 'step' => 63000],
        'IV/a' => ['min' => 3287800, 'max' => 5399900, 'step' => 66000],
        'IV/b' => ['min' => 3426900, 'max' => 5628300, 'step' => 69000],
        'IV/c' => ['min' => 3571900, 'max' => 5866400, 'step' => 71000],
        'IV/d' => ['min' => 3723000, 'max' => 6114500, 'step' => 74000],
        'IV/e' => ['min' => 3880400, 'max' => 6373200, 'step' => 77000],
    ];

    /**
     * Tabel Gaji Pokok PPPK (Perpres Nomor 11 Tahun 2024).
     */
    protected array $pppkScales = [
        'I' => ['min' => 1938500, 'max' => 2900900, 'step' => 32000],
        'II' => ['min' => 2116900, 'max' => 3071200, 'step' => 35000],
        'III' => ['min' => 2206500, 'max' => 3201200, 'step' => 37000],
        'IV' => ['min' => 2299800, 'max' => 3336600, 'step' => 39000],
        'V' => ['min' => 2511500, 'max' => 4189900, 'step' => 52000],
        'VI' => ['min' => 2742800, 'max' => 4367100, 'step' => 54000],
        'VII' => ['min' => 2858800, 'max' => 4551800, 'step' => 56000],
        'VIII' => ['min' => 2979700, 'max' => 4744400, 'step' => 58000],
        'IX' => ['min' => 3203600, 'max' => 5261500, 'step' => 64000],
        'X' => ['min' => 3339100, 'max' => 5484000, 'step' => 67000],
        'XI' => ['min' => 3480300, 'max' => 5716000, 'step' => 70000],
        'XII' => ['min' => 3627500, 'max' => 5957800, 'step' => 73000],
    ];

    /**
     * Standar TPP Dasar Setwan Banyumas (Perbup 104/2025 Lampiran II).
     */
    protected array $tppClassRates = [
        14 => 15000000.0, // Sekwan (Eselon II.a)
        12 => 8500000.0,  // Kabag (Eselon III.a)
        9 => 5250000.0,   // Kasubbag (Eselon IV.a)
        8 => 4000000.0,   // Fungsional Pertama
        7 => 3300000.0,   // Pelaksana / Fungsional Mahir
        6 => 2800000.0,   // Pelaksana / Fungsional Terampil
        5 => 2700000.0,   // Pelaksana / Fungsional Pemula
        3 => 2600000.0,   // Pelaksana Kelas 3
        1 => 2100000.0,   // Pelaksana Kelas 1
    ];

    /**
     * Hitung rincian penghasilan lengkap untuk 1 orang penerima.
     */
    public function calculateForRecipient(
        GajiRecipient $r,
        float $prodPct = 95.0,
        float $dispPct = 100.0,
        bool $isResesMonth = false
    ): array {
        if ($r->category === 'dprd') {
            return $this->calculateDprd($r, $isResesMonth);
        }

        return $this->calculateAsn($r, $prodPct, $dispPct);
    }

    /**
     * Perhitungan Hak Keuangan DPRD (PP 18/2017 jo PP 1/2023 & Perbup 9/2024).
     */
    protected function calculateDprd(GajiRecipient $r, bool $isResesMonth): array
    {
        // 1. Uang Representasi
        $repGajiBupati = $this->getDprdRate('uang_representasi_ketua', 2100000.0);
        $pctWaka = $this->getDprdRate('pct_wakil_ketua', 80.0) / 100;
        $pctAnggota = $this->getDprdRate('pct_anggota', 75.0) / 100;

        $uangRep = match ($r->sub_category) {
            'ketua_dprd' => $repGajiBupati,
            'wakil_ketua_dprd' => $repGajiBupati * $pctWaka,
            default => $repGajiBupati * $pctAnggota,
        };

        // 2. Uang Paket = % dari Uang Representasi (default 10%)
        $pctUangPaket = $this->getDprdRate('pct_uang_paket', 10.0) / 100;
        $uangPaket = $uangRep * $pctUangPaket;

        // 3. Tunjangan Jabatan = % dari Uang Representasi (default 145%)
        $pctTunjJabatan = $this->getDprdRate('pct_tunj_jabatan', 145.0) / 100;
        $tunjJabatan = $uangRep * $pctTunjJabatan;

        // 4. Tunjangan Alat Kelengkapan Dewan (AKD) - dihitung dari Tunjangan Jabatan Ketua
        $tunjJabatanKetua = $repGajiBupati * $pctTunjJabatan;
        $pctAkdKetua = $this->getDprdRate('pct_akd_ketua', 7.5) / 100;
        $pctAkdWakil = $this->getDprdRate('pct_akd_wakil', 5.0) / 100;
        $pctAkdSekr = $this->getDprdRate('pct_akd_sekretaris', 4.0) / 100;
        $pctAkdAnggota = $this->getDprdRate('pct_akd_anggota', 3.0) / 100;

        $tunjAkd = match ($r->akd_role) {
            'ketua' => $tunjJabatanKetua * $pctAkdKetua,
            'wakil_ketua' => $tunjJabatanKetua * $pctAkdWakil,
            'sekretaris' => $tunjJabatanKetua * $pctAkdSekr,
            'anggota' => $tunjJabatanKetua * $pctAkdAnggota,
            default => 0.0,
        };

        // 5. Tunjangan Komunikasi Intensif (TKI) = multiplier x Uang Representasi Ketua
        $tkiMultiplier = (float) $this->getDprdRate('tki_multiplier', 7.0);
        $tunjTki = $tkiMultiplier * $repGajiBupati;

        // 6. Tunjangan Perumahan (Tidak diberikan jika sudah dapat Rumah Negara)
        $tunjPerumahan = 0.0;
        if (! $r->has_official_residence) {
            $tunjPerumahan = match ($r->sub_category) {
                'ketua_dprd' => $this->getDprdRate('housing_ketua', 42625000.0),
                'wakil_ketua_dprd' => $this->getDprdRate('housing_wakil_ketua', 34650000.0),
                default => $this->getDprdRate('housing_anggota', 23650000.0),
            };
        }

        // 7. Tunjangan Transportasi (Pimpinan disediakan mobil dinas, Anggota tunjangan)
        $tunjTransport = 0.0;
        if (! $r->has_official_car) {
            $tunjTransport = match ($r->sub_category) {
                'ketua_dprd', 'wakil_ketua_dprd' => $this->getDprdRate('transport_pimpinan', 14500000.0),
                default => $this->getDprdRate('transport_anggota', 13500000.0),
            };
        }

        // 8. Tunjangan Keluarga & Beras (Mengikuti ketentuan PNS)
        $pctSpouse = $this->getAsnRate('pct_spouse', 10.0) / 100;
        $pctChild = $this->getAsnRate('pct_child', 2.0) / 100;
        $ricePerPerson = $this->getAsnRate('rice_per_person', 72420.0);

        $tunjKeluarga = 0.0;
        if ($r->marital_status === 'married') {
            $tunjKeluarga += ($uangRep * $pctSpouse);
        }
        $childCount = min(2, max(0, $r->children_count));
        $tunjKeluarga += ($uangRep * $pctChild * $childCount);

        $jiwa = 1 + ($r->marital_status === 'married' ? 1 : 0) + $childCount;
        $tunjBeras = min(4, $jiwa) * $ricePerPerson;

        // 9. Tunjangan Reses (jika periode bulan reses)
        $resesPerEvent = $this->getDprdRate('reses_per_event', 14700000.0);
        $tunjReses = $isResesMonth ? $resesPerEvent : 0.0;

        // Total Bruto
        $gross = $uangRep + $uangPaket + $tunjJabatan + $tunjAkd + $tunjTki +
                 $tunjPerumahan + $tunjTransport + $tunjKeluarga + $tunjBeras + $tunjReses;

        // Potongan Pajak PPh 21 & BPJS
        $pctPph21 = $this->getDprdRate('pct_pph21', 15.0) / 100;
        $pctBpjs = $this->getDprdRate('pct_bpjs', 1.0) / 100;

        $pph21 = ($uangRep + $tunjJabatan) * $pctPph21;
        $bpjsKes = min(400000.0, $uangRep * $pctBpjs);
        $totalDeductions = $pph21 + $bpjsKes;
        $netto = $gross - $totalDeductions;

        return [
            'category' => 'dprd',
            'sub_category' => $r->sub_category,
            'basic_salary_or_rep' => $uangRep,
            'package_allowance' => $uangPaket,
            'position_allowance' => $tunjJabatan,
            'family_allowance' => $tunjKeluarga,
            'rice_allowance' => $tunjBeras,
            'akd_allowance' => $tunjAkd,
            'tki_allowance' => $tunjTki,
            'housing_allowance' => $tunjPerumahan,
            'transport_allowance' => $tunjTransport,
            'reses_allowance' => $tunjReses,
            'tpp_amount' => 0.0,
            'productivity_pct' => 100.0,
            'discipline_pct' => 100.0,
            'other_allowance' => 0.0,
            'gross_amount' => $gross,
            'deduction_iwp' => 0.0,
            'deduction_bpjs' => $bpjsKes,
            'deduction_pph21' => $pph21,
            'deduction_other' => 0.0,
            'total_deductions' => $totalDeductions,
            'net_amount' => $netto,
            'calculation_meta' => [
                'legal' => 'PP 18/2017 jo PP 1/2023, Perda Banyumas 6/2017, Perbup 9/2024',
                'uang_representasi_basis' => 'Gaji Pokok Bupati Rp '.number_format($repGajiBupati, 0, ',', '.'),
                'has_residence' => $r->has_official_residence,
                'has_car' => $r->has_official_car,
                'akd_role' => $r->akd_role,
            ],
        ];
    }

    /**
     * Perhitungan Penghasilan ASN (PNS, PPPK Penuh Waktu, PPPK Paruh Waktu).
     */
    protected function calculateAsn(GajiRecipient $r, float $prodPct, float $dispPct): array
    {
        // 1. Gaji Pokok
        $defaultPppkParuhWaktu = $this->getAsnRate('pppk_paruh_waktu_default', 1500000.0);

        $gajiPokok = 0.0;
        if ($r->fixed_salary && $r->fixed_salary > 0) {
            $gajiPokok = (float) $r->fixed_salary;
        } elseif ($r->sub_category === 'pns') {
            $gajiPokok = $this->getPnsBasicSalary($r->grade, $r->mkg_years);
        } elseif ($r->sub_category === 'pppk_penuh_waktu') {
            $gajiPokok = $this->getPppkBasicSalary($r->grade, $r->mkg_years);
        } else {
            // PPPK Paruh Waktu: Berdasarkan kesepakatan perjanjian kerja
            $gajiPokok = $r->fixed_salary ?: $defaultPppkParuhWaktu;
        }

        // 2. Tunjangan Keluarga (PNS & PPPK Penuh Waktu)
        $pctSpouse = $this->getAsnRate('pct_spouse', 10.0) / 100;
        $pctChild = $this->getAsnRate('pct_child', 2.0) / 100;
        $ricePerPerson = $this->getAsnRate('rice_per_person', 72420.0);

        $tunjKeluarga = 0.0;
        if ($r->sub_category !== 'pppk_paruh_waktu') {
            if ($r->marital_status === 'married') {
                $tunjKeluarga += ($gajiPokok * $pctSpouse);
            }
            $childCount = min(2, max(0, $r->children_count));
            $tunjKeluarga += ($gajiPokok * $pctChild * $childCount);
        }

        // 3. Tunjangan Beras/Pangan
        $childCount = min(2, max(0, $r->children_count));
        $jiwa = 1 + ($r->marital_status === 'married' ? 1 : 0) + $childCount;
        $tunjBeras = ($r->sub_category !== 'pppk_paruh_waktu') ? (min(4, $jiwa) * $ricePerPerson) : 0.0;

        // 4. Tunjangan Jabatan / Fungsional
        $tunjJabatan = 0.0;
        if ($r->sub_category === 'pns') {
            $tunjJabatan = match ($r->job_class) {
                14 => 3250000.0, // Sekwan (Eselon II)
                12 => 1260000.0, // Kabag (Eselon III)
                9 => 540000.0,   // Kasubbag (Eselon IV)
                8 => 540000.0,   // Ahli Pertama
                7 => 360000.0,   // Mahir
                default => 180000.0, // Pelaksana Umum
            };
        } elseif ($r->sub_category === 'pppk_penuh_waktu') {
            $tunjJabatan = match ($r->grade) {
                'IX' => 540000.0,
                'VII' => 360000.0,
                default => 180000.0,
            };
        }

        // 5. TPP Kabupaten Banyumas (Perbup 5/2025 jo Perbup 104/2025)
        $tppAmount = 0.0;
        if ($r->sub_category === 'pppk_paruh_waktu') {
            // Pasal 18 huruf l Perbup 104/2025: PPPK Paruh Waktu DIKECUALIKAN dari TPP
            $tppAmount = 0.0;
        } else {
            $tppBase = $r->tpp_base_amount;
            if ($tppBase <= 0) {
                if ($r->sub_category === 'pppk_penuh_waktu') {
                    $tppBase = isset($this->tppRates['pppk_penuh_waktu'])
                        ? (float) $this->tppRates['pppk_penuh_waktu']
                        : 1600000.0;
                } else {
                    $defaultClassRate = $this->tppClassRates[$r->job_class ?? 7] ?? 3300000.0;
                    $tppBase = $this->getTppClassRate($r->job_class ?? 7, $defaultClassRate);
                }
            }

            // Rumus Konversi Produktivitas & Disiplin
            $tppProdWeight = $this->getAsnRate('tpp_prod_weight', 70.0) / 100;
            $tppDispWeight = $this->getAsnRate('tpp_disp_weight', 30.0) / 100;

            $konversiProd = 95.0; // Jika capaian 81 - 90%
            if ($prodPct > 90) {
                $konversiProd = 100.0;
            } elseif ($prodPct > 80) {
                $konversiProd = 95.0;
            } elseif ($prodPct > 70) {
                $konversiProd = 85.0;
            } elseif ($prodPct > 60) {
                $konversiProd = 75.0;
            } else {
                $konversiProd = 50.0;
            }

            $faktorPengali = (($tppProdWeight * ($konversiProd / 100)) + ($tppDispWeight * ($dispPct / 100)));
            $tppAmount = round($tppBase * $faktorPengali, 0);
        }

        // Subtotal Bruto
        $gross = $gajiPokok + $tunjKeluarga + $tunjBeras + $tunjJabatan + $tppAmount;

        // Potongan Wajib
        $iwpPct = $this->getAsnRate('iwp_pct', 8.0) / 100;
        $bpjsPct = $this->getAsnRate('bpjs_pct', 1.0) / 100;

        $iwp = ($r->sub_category !== 'pppk_paruh_waktu') ? ($gajiPokok + $tunjKeluarga) * 0.01 : 0.0;
        $bpjs = ($gajiPokok + $tunjKeluarga) * 0.04;
        $pph21 = 0.0;
        if (str_starts_with($r->grade ?? '', 'IV')) {
            $pph21 = ($gajiPokok + $tppAmount) * 0.15;
        } elseif (str_starts_with($r->grade ?? '', 'III') || $r->grade === 'IX') {
            $pph21 = ($gajiPokok + $tppAmount) * 0.05;
        }

        $totalDeductions = $iwp + $pph21;
        $netto = $gross - $totalDeductions;

        return [
            'category' => 'asn',
            'sub_category' => $r->sub_category,
            'basic_salary_or_rep' => $gajiPokok,
            'package_allowance' => 0.0,
            'position_allowance' => $tunjJabatan,
            'family_allowance' => $tunjKeluarga,
            'rice_allowance' => $tunjBeras,
            'akd_allowance' => 0.0,
            'tki_allowance' => 0.0,
            'housing_allowance' => 0.0,
            'transport_allowance' => 0.0,
            'reses_allowance' => 0.0,
            'tpp_amount' => $tppAmount,
            'productivity_pct' => $prodPct,
            'discipline_pct' => $dispPct,
            'other_allowance' => 0.0,
            'gross_amount' => $gross,
            'deduction_iwp' => $iwp,
            'deduction_bpjs' => $bpjs,
            'deduction_pph21' => $pph21,
            'deduction_other' => 0.0,
            'total_deductions' => $totalDeductions,
            'net_amount' => $netto,
            'calculation_meta' => [
                'legal' => 'PP 5/2024 (PNS), Perpres 11/2024 (PPPK), Perbup Banyumas 104/2025 (TPP)',
                'job_class' => $r->job_class,
                'tpp_status' => ($r->sub_category === 'pppk_paruh_waktu') ? 'Dikecualikan (Pasal 18 huruf l Perbup 104/2025)' : 'Diberikan',
            ],
        ];
    }

    /**
     * Hitung gaji pokok PNS berdasarkan Golongan dan MKG.
     */
    public function getPnsBasicSalary(?string $grade, int $mkgYears): float
    {
        $scale = $this->pnsScales[$grade ?? 'III/a'] ?? $this->pnsScales['III/a'];
        $val = $scale['min'] + (floor($mkgYears / 2) * $scale['step']);

        return min((float) $scale['max'], (float) $val);
    }

    /**
     * Hitung gaji pokok PPPK berdasarkan Golongan dan MKG.
     */
    public function getPppkBasicSalary(?string $grade, int $mkgYears): float
    {
        $scale = $this->pppkScales[$grade ?? 'IX'] ?? $this->pppkScales['IX'];
        $val = $scale['min'] + (floor($mkgYears / 2) * $scale['step']);

        return min((float) $scale['max'], (float) $val);
    }
}
