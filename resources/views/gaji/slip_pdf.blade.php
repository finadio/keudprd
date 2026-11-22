<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Slip Gaji & Tunjangan</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1e293b; margin: 15px; }
        .header { text-align: center; border-bottom: 2px solid #047857; padding-bottom: 8px; margin-bottom: 12px; }
        .header h2 { margin: 0; font-size: 13px; color: #065f46; text-transform: uppercase; }
        .header p { margin: 2px 0 0; font-size: 10px; color: #64748b; }
        .info-table { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .info-table td { padding: 2px 4px; }
        .info-label { width: 22%; font-weight: bold; color: #475569; }
        .info-val { width: 28%; }
        .main-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .main-table th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 5px; text-align: left; font-size: 10px; }
        .main-table td { border: 1px solid #e2e8f0; padding: 4px 6px; }
        .amount { text-align: right; font-family: monospace; font-size: 11px; }
        .total-row { background: #ecfdf5; font-weight: bold; }
        .signatures { width: 100%; margin-top: 20px; }
        .sign-col { width: 50%; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Sekretariat DPRD Kabupaten Banyumas</h2>
        <p>SLIP PENGHASILAN & HAK KEUANGAN BULAN {{ strtoupper(\Carbon\Carbon::createFromDate($period->period_year, $period->period_month, 1)->locale('id')->isoFormat('MMMM Y')) }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td class="info-label">Nama Penerima</td>
            <td class="info-val">: {{ $recipient->name }}</td>
            <td class="info-label">Kategori / Status</td>
            <td class="info-val">: {{ $recipient->sub_category_label }}</td>
        </tr>
        <tr>
            <td class="info-label">Jabatan</td>
            <td class="info-val">: {{ $recipient->position_name }}</td>
            <td class="info-label">Golongan / MKG</td>
            <td class="info-val">: {{ $recipient->grade ?: '-' }} ({{ $recipient->mkg_years }} Thn)</td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
            <tr>
                <th style="width: 50%;">KOMPONEN PENGHASILAN (BRUTO)</th>
                <th style="width: 50%;">KOMPONEN POTONGAN</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="vertical-align: top;">
                    <table style="width: 100%;">
                        <tr>
                            <td>{{ $item->category === 'dprd' ? 'Uang Representasi' : 'Gaji Pokok' }}</td>
                            <td class="amount">Rp {{ number_format($item->basic_salary_or_rep, 0, ',', '.') }}</td>
                        </tr>
                        @if($item->package_allowance > 0)
                        <tr>
                            <td>Uang Paket (10%)</td>
                            <td class="amount">Rp {{ number_format($item->package_allowance, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        @if($item->position_allowance > 0)
                        <tr>
                            <td>Tunjangan Jabatan</td>
                            <td class="amount">Rp {{ number_format($item->position_allowance, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        @if($item->akd_allowance > 0)
                        <tr>
                            <td>Tunjangan AKD ({{ strtoupper($recipient->akd_role) }})</td>
                            <td class="amount">Rp {{ number_format($item->akd_allowance, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        @if($item->tki_allowance > 0)
                        <tr>
                            <td>Tunjangan Komunikasi Intensif</td>
                            <td class="amount">Rp {{ number_format($item->tki_allowance, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        @if($item->housing_allowance > 0)
                        <tr>
                            <td>Tunjangan Perumahan</td>
                            <td class="amount">Rp {{ number_format($item->housing_allowance, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        @if($item->transport_allowance > 0)
                        <tr>
                            <td>Tunjangan Transportasi</td>
                            <td class="amount">Rp {{ number_format($item->transport_allowance, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        @if($item->tpp_amount > 0)
                        <tr>
                            <td>TPP Daerah (Kinerja & Disiplin)</td>
                            <td class="amount">Rp {{ number_format($item->tpp_amount, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        @if($item->family_allowance > 0)
                        <tr>
                            <td>Tunjangan Keluarga</td>
                            <td class="amount">Rp {{ number_format($item->family_allowance, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        @if($item->rice_allowance > 0)
                        <tr>
                            <td>Tunjangan Pangan / Beras</td>
                            <td class="amount">Rp {{ number_format($item->rice_allowance, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
                <td style="vertical-align: top;">
                    <table style="width: 100%;">
                        @if($item->deduction_iwp > 0)
                        <tr>
                            <td>Iuran Wajib Pegawai (IWP 1%)</td>
                            <td class="amount">Rp {{ number_format($item->deduction_iwp, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        @if($item->deduction_bpjs > 0)
                        <tr>
                            <td>Iuran BPJS Kesehatan</td>
                            <td class="amount">Rp {{ number_format($item->deduction_bpjs, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        @if($item->deduction_pph21 > 0)
                        <tr>
                            <td>Pajak Penghasilan (PPh 21)</td>
                            <td class="amount">Rp {{ number_format($item->deduction_pph21, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        @if($item->deduction_other > 0)
                        <tr>
                            <td>Potongan Lainnya / Koperasi</td>
                            <td class="amount">Rp {{ number_format($item->deduction_other, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
            <tr class="total-row">
                <td>
                    <div style="display: flex; justify-content: space-between;">
                        <span>TOTAL PENGHASILAN BRUTO:</span>
                        <span class="amount" style="float: right;">Rp {{ number_format($item->gross_amount, 0, ',', '.') }}</span>
                    </div>
                </td>
                <td>
                    <div style="display: flex; justify-content: space-between;">
                        <span>TOTAL POTONGAN:</span>
                        <span class="amount" style="float: right;">Rp {{ number_format($item->total_deductions, 0, ',', '.') }}</span>
                    </div>
                </td>
            </tr>
            <tr style="background: #107c41; color: #ffffff; font-weight: bold; font-size: 12px;">
                <td colspan="2" style="padding: 8px;">
                    <span style="letter-spacing: 0.5px;">TAKE HOME PAY (PENGHASILAN BERSIH DITERIMA):</span>
                    <span style="float: right; font-family: monospace;">Rp {{ number_format($item->net_amount, 0, ',', '.') }}</span>
                </td>
            </tr>
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td class="sign-col">
                <p>Penerima,</p>
                <br><br><br>
                <p><strong>{{ $recipient->name }}</strong></p>
            </td>
            <td class="sign-col">
                <p>Bendahara Pengeluaran,</p>
                <br><br><br>
                <p><strong>INDAH SETIYOWATI, A.Md.AB.</strong><br><span style="font-size: 9px; color: #64748b;">NIP. 19990419 202203 2 018</span></p>
            </td>
        </tr>
    </table>
</body>
</html>
