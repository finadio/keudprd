<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak - {{ $title }} - Setwan Banyumas</title>
    <style>
        @page {
            size: landscape;
            margin: 10mm 8mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.5pt;
            color: #000000;
            background: #ffffff;
            margin: 0;
            padding: 15px;
        }

        /* Top Screen Toolbar */
        .print-toolbar {
            position: sticky;
            top: 0;
            left: 0;
            right: 0;
            background: #04331a;
            color: #ffffff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
            z-index: 9999;
            margin: -15px -15px 20px -15px;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .btn-print {
            background: #107c41;
            color: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }

        .btn-print:hover {
            background: #0d6334;
        }

        .btn-close {
            background: rgba(255,255,255,0.15);
            color: white;
        }

        .btn-close:hover {
            background: rgba(255,255,255,0.25);
        }

        @media print {
            .print-toolbar {
                display: none !important;
            }
            body {
                padding: 0 !important;
                margin: 0 !important;
            }
        }

        /* Document Header */
        .doc-header {
            text-align: center;
            margin-bottom: 15px;
        }

        .doc-header h2 {
            margin: 0;
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .doc-header h1 {
            margin: 2px 0 6px 0;
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .doc-header .doc-title {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin-top: 4px;
        }

        .doc-header .doc-year {
            font-size: 9.5pt;
            font-weight: bold;
            margin-top: 2px;
        }

        /* Document Metadata Box */
        .meta-box {
            width: 100%;
            margin-bottom: 12px;
            border-top: 1px solid #333333;
            border-bottom: 1px solid #333333;
            padding: 6px 4px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }

        .meta-table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .meta-label {
            font-weight: bold;
            width: 150px;
            white-space: nowrap;
        }

        .meta-colon {
            width: 10px;
            text-align: center;
            font-weight: bold;
        }

        .meta-value {
            font-weight: 500;
        }

        /* Main Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
            table-layout: auto;
            margin-bottom: 20px;
        }

        .data-table th, 
        .data-table td {
            border: 1px solid #111111;
            padding: 3px 4px;
            vertical-align: middle;
        }

        .data-table thead {
            display: table-header-group;
        }

        .data-table tfoot {
            display: table-footer-group;
        }

        .data-table tr {
            page-break-inside: avoid;
        }

        .data-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }

        .data-table .subhead th {
            background-color: #f8fafc;
            font-size: 6.5pt;
            font-weight: normal;
            font-family: monospace;
            padding: 1px;
        }

        .num {
            text-align: right;
            font-family: 'Courier New', Courier, monospace;
            font-size: 7.5pt;
            white-space: nowrap;
        }

        .text-center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .total-row {
            background-color: #f8fafc;
            font-weight: bold;
            border-top: 2px solid #000000 !important;
            border-bottom: 2px solid #000000 !important;
        }

        /* Signatures block */
        .signatures {
            width: 100%;
            margin-top: 25px;
            page-break-inside: avoid;
        }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }

        .signatures-table td {
            vertical-align: top;
            width: 50%;
            padding: 0 20px;
        }
    </style>
</head>
<body>

    <!-- Floating Top Toolbar on Screen (Hidden on Print) -->
    <div class="print-toolbar">
        <div style="font-size: 13px; font-weight: 600;">
            📄 Dokumen Cetak BKU: <b>{{ $title }}</b> (TA {{ $year }})
        </div>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()" class="btn btn-print">
                🖨️ Cetak Dokumen / Simpan PDF
            </button>
            <button onclick="window.close()" class="btn btn-close">
                ✖️ Tutup Tab
            </button>
        </div>
    </div>

    <!-- Official Header -->
    <div class="doc-header">
        @if(!empty($withNotes))
            <div style="display: inline-block; background-color: #fef3c7; color: #92400e; border: 1px solid #f59e0b; padding: 2px 10px; font-size: 8pt; font-weight: bold; border-radius: 4px; margin-bottom: 6px; letter-spacing: 0.5px;">
                📝 DRAFT / LEMBAR KERJA RAPAT INTERNAL (DENGAN MEMO & CATATAN)
            </div>
        @endif
        <h2>PEMERINTAH KABUPATEN BANYUMAS</h2>
        <h1>SEKRETARIAT DEWAN PERWAKILAN RAKYAT DAERAH</h1>
        <div class="doc-title">{{ $title }}</div>
        <div class="doc-year">TAHUN ANGGARAN {{ $year }}</div>
    </div>

    <!-- Metadata Box -->
    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td class="meta-label">SKPD</td>
                <td class="meta-colon">:</td>
                <td class="meta-value">{{ $sheetData['meta']['skpd'] ?? $setting?->skpd_name ?? 'SETWAN KABUPATEN BANYUMAS' }}</td>
                
                <td style="width: 30px;"></td>

                <td class="meta-label">Bendahara Pengeluaran</td>
                <td class="meta-colon">:</td>
                <td class="meta-value">{{ $sheetData['meta']['bendahara'] ?? $setting?->treasurer_name ?? 'INDAH SETIYOWATI, A.Md.AB.' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Pengguna Anggaran</td>
                <td class="meta-colon">:</td>
                <td class="meta-value">{{ $sheetData['meta']['pa_kpa'] ?? $setting?->pa_kpa_name ?? 'ARIF TRIYANTO, S.Sos. M.Eng.' }}</td>

                <td></td>

                <td class="meta-label">Periode</td>
                <td class="meta-colon">:</td>
                <td class="meta-value">{{ $sheetData['meta']['periode'] ?? ('JANUARI s/d DESEMBER ' . $year) }}</td>
            </tr>
            @if(!empty($sheetData['meta']['pptk']))
            <tr>
                <td class="meta-label">PPTK</td>
                <td class="meta-colon">:</td>
                <td class="meta-value" colspan="5"><b>{{ $sheetData['meta']['pptk'] }}</b></td>
            </tr>
            @endif
        </table>
    </div>

    <!-- ================= 1. VIEW HITUNGAN (REKAPITULASI BKU PER PPTK) ================= -->
    @if($activeTab === 'Hitungan')
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 25px;">No</th>
                <th rowspan="2" style="text-align: left; min-width: 140px;">Nama PPTK</th>
                <th>Debet (Rp)</th>
                <th>Panjar (Rp)</th>
                <th>Jml (Rp)</th>
                <th>SPJ (Rp)</th>
                <th>Hutang Panjar (Rp)</th>
                <th>Bank (Rp)</th>
                <th>Tunai (Rp)</th>
                <th>SPJ Panjar Feb</th>
                <th>Sisa Panjar</th>
                <th>Total Sisa Riil</th>
                <th>Kontrol Selisih</th>
            </tr>
            <tr class="subhead">
                <th>2</th>
                <th>3</th>
                <th>4 (2+3)</th>
                <th>5</th>
                <th>6 (4-5)</th>
                <th>7 (ket 6)</th>
                <th>8 (ket 6)</th>
                <th>9</th>
                <th>10 (8-9)</th>
                <th>11 (7+10)</th>
                <th>G - L (=0)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sheetData['rows'] ?? [] as $r)
            <tr>
                <td class="text-center">{{ $r['no'] }}</td>
                <td style="font-weight: 600;">{{ $r['pptk_name'] }}</td>
                <td class="num">{{ number_format($r['debet'] ?? 0, 0, ',', '.') }}</td>
                <td class="num">{{ number_format($r['panjar'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($r['jml'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($r['spj'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($r['total_hutang_panjar'] ?? 0, 0, ',', '.') }}</td>
                <td class="num">{{ number_format($r['bank'] ?? 0, 0, ',', '.') }}</td>
                <td class="num">{{ number_format($r['tunai'] ?? 0, 0, ',', '.') }}</td>
                <td class="num">{{ number_format($r['spj_lanjutan'] ?? 0, 0, ',', '.') }}</td>
                <td class="num">{{ number_format($r['sisa_panjar_jan'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($r['total_sisa_riil'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold text-center">{{ number_format($r['kontrol_selisih'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" class="text-center bold">TOTAL KESELURUHAN</td>
                <td class="num bold">{{ number_format($sheetData['totals']['total_debet'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['totals']['total_panjar'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['totals']['total_kewajiban'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['totals']['total_spj'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['totals']['total_hutang_panjar'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['totals']['bank'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['totals']['tunai'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">-</td>
                <td class="num bold">-</td>
                <td class="num bold">{{ number_format($sheetData['totals']['total_sisa_riil'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold text-center">{{ number_format($sheetData['totals']['kontrol_selisih'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <!-- ================= 2. VIEW STANDARD BKU (UP / KK / PPTK SHEETS) ================= -->
    @elseif(isset($sheetData['rows']) && $activeTab !== 'Jml Deb Rek' && $activeTab !== 'Deb Rek' && !str_starts_with($activeTab, 'Deb'))
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 30px;">NO. URUT</th>
                <th rowspan="2" style="width: 70px;">TANGGAL</th>
                <th rowspan="2" style="text-align: left;">URAIAN</th>
                <th style="width: 100px;">PENERIMAAN</th>
                <th style="width: 100px;">PENGELUARAN</th>
                <th style="width: 100px;">SALDO</th>
                
                @if(isset($sheetData['rows'][0]['debet']))
                <th style="width: 85px;">Debet</th>
                <th style="width: 85px;">Panjar</th>
                <th style="width: 85px;">SPJ</th>
                <th style="width: 85px;">Jml</th>
                @endif
                @if(!empty($withNotes))
                <th style="width: 140px; text-align: left; background-color: #fef9c3; color: #854d0e;">MEMO / CATATAN</th>
                @endif
            </tr>
            <tr class="subhead">
                <th>4</th>
                <th>5</th>
                <th>6</th>
                @if(isset($sheetData['rows'][0]['debet']))
                <th>H</th>
                <th>I</th>
                <th>J</th>
                <th>K</th>
                @endif
                @if(!empty($withNotes))
                <th>L</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($sheetData['rows'] ?? [] as $r)
            <tr>
                <td class="text-center">{{ $r['no'] }}</td>
                <td class="text-center" style="white-space: nowrap;">{{ $r['tanggal'] }}</td>
                <td>{{ $r['uraian'] }}</td>
                <td class="num">{{ ($r['penerimaan'] ?? 0) > 0 ? number_format($r['penerimaan'], 0, ',', '.') : '-' }}</td>
                <td class="num">{{ ($r['pengeluaran'] ?? 0) > 0 ? number_format($r['pengeluaran'], 0, ',', '.') : '-' }}</td>
                <td class="num bold">{{ number_format($r['saldo'] ?? 0, 0, ',', '.') }}</td>

                @if(isset($r['debet']))
                <td class="num">{{ ($r['debet'] ?? 0) > 0 ? number_format($r['debet'], 0, ',', '.') : '-' }}</td>
                <td class="num">{{ ($r['panjar'] ?? 0) != 0 ? number_format($r['panjar'], 0, ',', '.') : '-' }}</td>
                <td class="num">{{ ($r['spj'] ?? 0) > 0 ? number_format($r['spj'], 0, ',', '.') : '-' }}</td>
                <td class="num bold">{{ number_format($r['jml'] ?? 0, 0, ',', '.') }}</td>
                @endif
                @if(!empty($withNotes))
                <td style="background-color: #fffbeb; font-size: 7pt; color: #78350f;">{{ $r['notes'] ?? ($r['keterangan'] ?? '-') }}</td>
                @endif
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" class="text-center bold">TOTAL MUTASI</td>
                <td class="num bold">{{ number_format($sheetData['total_penerimaan'] ?? ($sheetData['summary']['total_spj'] ?? 0), 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['total_pengeluaran'] ?? ($sheetData['summary']['total_panjar'] ?? 0), 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['saldo_akhir'] ?? ($sheetData['summary']['sisa_kewajiban'] ?? 0), 0, ',', '.') }}</td>

                @if(isset($sheetData['rows'][0]['debet']))
                <td class="num bold">{{ number_format($sheetData['summary']['total_debet'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['summary']['total_panjar'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['summary']['total_spj'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['summary']['sisa_kewajiban'] ?? 0, 0, ',', '.') }}</td>
                @endif
                @if(!empty($withNotes))
                <td></td>
                @endif
            </tr>
        </tfoot>
    </table>

    <!-- ================= 3. VIEW MATRIKS JML DEB REK ================= -->
    @elseif($activeTab === 'Jml Deb Rek')
    <div style="font-weight: bold; font-size: 9pt; margin-bottom: 6px;">1. BAGIAN DEBET REKENING (TOKO)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="text-align: left; width: 150px;">PPTK</th>
                @for($m = 1; $m <= 12; $m++)
                <th>{{ $sheetData['month_names'][$m] ?? $m }}</th>
                @endfor
                <th style="width: 100px;">Total Setahun</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sheetData['debet'] ?? [] as $row)
            <tr>
                <td style="font-weight: 600;">{{ $row['pptk_name'] }}</td>
                @for($m = 1; $m <= 12; $m++)
                <td class="num">{{ ($row[$m] ?? 0) != 0 ? number_format($row[$m], 0, ',', '.') : '-' }}</td>
                @endfor
                <td class="num bold">{{ number_format($row['total'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="font-weight: bold; font-size: 9pt; margin-top: 15px; margin-bottom: 6px;">2. BAGIAN PANJAR TUNAI</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="text-align: left; width: 150px;">PPTK</th>
                @for($m = 1; $m <= 12; $m++)
                <th>{{ $sheetData['month_names'][$m] ?? $m }}</th>
                @endfor
                <th style="width: 100px;">Total Setahun</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sheetData['panjar'] ?? [] as $row)
            <tr>
                <td style="font-weight: 600;">{{ $row['pptk_name'] }}</td>
                @for($m = 1; $m <= 12; $m++)
                <td class="num">{{ ($row[$m] ?? 0) != 0 ? number_format($row[$m], 0, ',', '.') : '-' }}</td>
                @endfor
                <td class="num bold">{{ number_format($row['total'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- ================= 4. VIEW DEB REK INDUK ================= -->
    @elseif($activeTab === 'Deb Rek')
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 30px;">NO</th>
                <th rowspan="2" style="width: 70px;">TANGGAL</th>
                <th rowspan="2" style="text-align: left;">URAIAN</th>
                <th rowspan="2" style="width: 90px;">PENERIMAAN</th>
                <th rowspan="2" style="width: 90px;">PENGELUARAN</th>
                <th rowspan="2" style="width: 90px;">SALDO</th>
                <th colspan="{{ count($sheetData['pptks'] ?? []) }}">ALOKASI PPTK</th>
            </tr>
            <tr class="subhead">
                @foreach($sheetData['pptks'] ?? [] as $p)
                <th>{{ $p->display_name }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($sheetData['rows'] ?? [] as $r)
            <tr style="{{ ($r['is_contra'] ?? false) ? 'background-color: #f8fafc; font-style: italic;' : '' }}">
                <td class="text-center">{{ $r['no'] }}</td>
                <td class="text-center">{{ $r['tanggal'] }}</td>
                <td style="{{ ($r['is_contra'] ?? false) ? 'padding-left: 15px;' : '' }}">{{ $r['uraian'] }}</td>
                <td class="num">{{ ($r['penerimaan'] ?? 0) > 0 ? number_format($r['penerimaan'], 0, ',', '.') : '-' }}</td>
                <td class="num">{{ ($r['pengeluaran'] ?? 0) > 0 ? number_format($r['pengeluaran'], 0, ',', '.') : '-' }}</td>
                <td class="num bold">{{ number_format($r['saldo'] ?? 0, 0, ',', '.') }}</td>
                @foreach($sheetData['pptks'] ?? [] as $p)
                <td class="num">{{ ($r['pptk_cols'][$p->id] ?? 0) > 0 ? number_format($r['pptk_cols'][$p->id], 0, ',', '.') : '-' }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- ================= 5. VIEW BKU RESES KAS UMUM ================= -->
    @elseif(($sheetData['type'] ?? '') === 'bku_reses' || str_starts_with($activeTab, 'Reses'))
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 35px;">NO. URUT</th>
                <th style="width: 80px;">TANGGAL</th>
                <th style="text-align: left;">URAIAN TRANSAKSI</th>
                <th style="width: 120px;">PENERIMAAN (Rp)</th>
                <th style="width: 120px;">PENGELUARAN (Rp)</th>
                <th style="width: 120px;">SALDO (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sheetData['rows'] ?? [] as $r)
            <tr>
                <td class="text-center">{{ $r['no'] }}</td>
                <td class="text-center">{{ $r['tanggal'] }}</td>
                <td>{{ $r['uraian'] }}</td>
                <td class="num">{{ !empty($r['penerimaan']) && $r['penerimaan'] > 0 ? number_format($r['penerimaan'], 0, ',', '.') : '-' }}</td>
                <td class="num">{{ !empty($r['pengeluaran']) && $r['pengeluaran'] > 0 ? number_format($r['pengeluaran'], 0, ',', '.') : '-' }}</td>
                <td class="num bold">{{ number_format($r['saldo'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" class="text-center bold">JUMLAH PERIODE INI</td>
                <td class="num bold">{{ number_format($sheetData['summary']['total_penerimaan'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['summary']['total_pengeluaran'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['summary']['saldo_akhir'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    @if(!empty($sheetData['rekap_kasda']))
    <div style="margin-top: 15px;">
        <h4 style="font-size: 11px; margin-bottom: 5px; text-transform: uppercase;">REKAPITULASI ALOKASI DANA &amp; SISA KASDA RESES</h4>
        <table class="data-table" style="max-width: 600px;">
            <thead>
                <tr>
                    <th style="text-align: left;">KATEGORI BELANJA</th>
                    <th style="width: 120px;">PAGU SP2D</th>
                    <th style="width: 120px;">REALISASI SPJ</th>
                    <th style="width: 120px;">SISA KASDA</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sheetData['rekap_kasda'] as $k)
                @if(!empty($k['kategori']) || !empty($k['sp2d']))
                <tr>
                    <td><b>{{ $k['kategori'] ?: '-' }}</b></td>
                    <td class="num">{{ !empty($k['sp2d']) ? number_format($k['sp2d'], 0, ',', '.') : '-' }}</td>
                    <td class="num">{{ !empty($k['spj']) ? number_format($k['spj'], 0, ',', '.') : '-' }}</td>
                    <td class="num bold" style="color: #b91c1c;">{{ !empty($k['kasda']) ? number_format($k['kasda'], 0, ',', '.') : '-' }}</td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- ================= 6. VIEW DEBET RESES & PAJAK PPH 23 / 21 ================= -->
    @elseif(($sheetData['type'] ?? '') === 'deb_reses' || str_starts_with($activeTab, 'Deb'))
    <h4 style="font-size: 11px; margin-bottom: 5px; text-transform: uppercase;">BAGIAN 1: RINCIAN SEWA TEMPAT &amp; PAJAK PPH PASAL 23 (2%)</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 30px;">NO</th>
                <th style="text-align: left;">NAMA ANGGOTA DEWAN / URAIAN</th>
                <th style="width: 80px;">BRUTO</th>
                <th style="width: 75px;">PPH 23 (2%)</th>
                <th style="width: 80px;">NETTO</th>
                <th style="width: 80px;">SPJ BRUTO</th>
                <th style="width: 75px;">PPH SPJ</th>
                <th style="width: 80px;">NETTO SPJ</th>
                <th style="width: 75px;">SETOR KASDA</th>
                <th style="width: 70px;">PPH SETOR</th>
                <th style="width: 75px;">NETTO SETOR</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sheetData['sewa']['rows'] ?? ($sheetData['rows'] ?? []) as $r)
            <tr>
                <td class="text-center">{{ $r['no'] }}</td>
                <td>{{ $r['uraian'] }}</td>
                <td class="num">{{ number_format($r['bruto'] ?? 0, 0, ',', '.') }}</td>
                <td class="num" style="color: #b91c1c;">{{ number_format($r['pajak'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold" style="color: #047857;">{{ number_format($r['netto'] ?? 0, 0, ',', '.') }}</td>
                <td class="num">{{ number_format($r['spj'] ?? 0, 0, ',', '.') }}</td>
                <td class="num">{{ number_format($r['pph_spj'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($r['netto_spj'] ?? 0, 0, ',', '.') }}</td>
                <td class="num">{{ number_format($r['setor'] ?? ($r['setor_kasda'] ?? 0), 0, ',', '.') }}</td>
                <td class="num">{{ number_format($r['pph_setor'] ?? 0, 0, ',', '.') }}</td>
                <td class="num">{{ number_format($r['netto_setor'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" class="text-center bold">TOTAL SEWA TEMPAT</td>
                <td class="num bold">{{ number_format($sheetData['sewa']['totals']['bruto'] ?? ($sheetData['totals']['bruto'] ?? 0), 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['sewa']['totals']['pajak'] ?? ($sheetData['totals']['pajak'] ?? 0), 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['sewa']['totals']['netto'] ?? ($sheetData['totals']['netto'] ?? 0), 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['sewa']['totals']['spj'] ?? ($sheetData['totals']['spj'] ?? 0), 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['sewa']['totals']['pph_spj'] ?? ($sheetData['totals']['pph_spj'] ?? 0), 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['sewa']['totals']['netto_spj'] ?? ($sheetData['totals']['netto_spj'] ?? 0), 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['sewa']['totals']['setor'] ?? ($sheetData['totals']['setor_kasda'] ?? 0), 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['sewa']['totals']['pph_setor'] ?? 0, 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($sheetData['sewa']['totals']['netto_setor'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    @if(!empty($sheetData['tunjangan']['rows']))
    <div style="margin-top: 20px; page-break-before: always;">
        <h4 style="font-size: 11px; margin-bottom: 5px; text-transform: uppercase;">BAGIAN 2: RINCIAN TUNJANGAN RESES &amp; POTONGAN PPH PASAL 21</h4>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 35px;">NO</th>
                    <th style="text-align: left;">NAMA ANGGOTA DEWAN</th>
                    <th style="width: 120px;">TUNJANGAN BRUTO (Rp)</th>
                    <th style="width: 120px;">PAJAK PPH 21 (Rp)</th>
                    <th style="width: 120px;">NETTO DITERIMA (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sheetData['tunjangan']['rows'] as $t)
                <tr>
                    <td class="text-center">{{ $t['no'] }}</td>
                    <td>{{ $t['uraian'] }}</td>
                    <td class="num">{{ number_format($t['bruto'] ?? 0, 0, ',', '.') }}</td>
                    <td class="num" style="color: #b91c1c;">{{ number_format($t['pajak'] ?? 0, 0, ',', '.') }}</td>
                    <td class="num bold" style="color: #047857;">{{ number_format($t['netto'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2" class="text-center bold">TOTAL TUNJANGAN RESES</td>
                    <td class="num bold">{{ number_format($sheetData['tunjangan']['totals']['bruto'] ?? 0, 0, ',', '.') }}</td>
                    <td class="num bold">{{ number_format($sheetData['tunjangan']['totals']['pajak'] ?? 0, 0, ',', '.') }}</td>
                    <td class="num bold">{{ number_format($sheetData['tunjangan']['totals']['netto'] ?? 0, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
    @endif

    <!-- Signatures Section -->
    <div class="signatures">
        <table class="signatures-table">
            <tr>
                <td>
                    Mengetahui,<br>
                    <b>Pengguna Anggaran</b>
                    <br><br><br><br>
                    <u><b>{{ $sheetData['meta']['pa_kpa'] ?? $setting?->pa_kpa_name ?? 'ARIF TRIYANTO, S.Sos. M.Eng.' }}</b></u><br>
                    NIP. {{ $setting?->pa_kpa_nip ?? '19740510 199903 1 005' }}
                </td>
                <td style="text-align: right;">
                    Purwokerto, {{ date('d F Y') }}<br>
                    <b>Bendahara Pengeluaran</b>
                    <br><br><br><br>
                    <u><b>{{ $sheetData['meta']['bendahara'] ?? $setting?->treasurer_name ?? 'INDAH SETIYOWATI, A.Md.AB.' }}</b></u><br>
                    NIP. {{ $setting?->treasurer_nip ?? '19840915 200801 2 006' }}
                </td>
            </tr>
        </table>
    </div>

    <script>
        // Auto trigger print dialog when opened
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                window.print();
            }, 400);
        });
    </script>
</body>
</html>

