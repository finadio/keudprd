<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Buku Bank - {{ $bankData['meta']['month_name'] }} - Setwan Banyumas</title>
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
            margin-bottom: 12px;
        }

        .doc-header h2 {
            margin: 0;
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .doc-header h1 {
            margin: 2px 0 4px 0;
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

        .doc-header .doc-period {
            font-size: 10pt;
            font-weight: bold;
            margin-top: 2px;
            text-transform: uppercase;
        }

        /* Metadata Box */
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
            width: 220px;
            white-space: nowrap;
        }

        .meta-colon {
            width: 12px;
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

        .subtotal-row {
            background-color: #f8fafc;
            font-weight: bold;
        }

        .total-row {
            background-color: #f1f5f9;
            font-weight: bold;
            border-top: 2px solid #000000 !important;
            border-bottom: 2px solid #000000 !important;
        }

        /* Summary & Signatures Section */
        .bottom-section {
            width: 100%;
            margin-top: 15px;
            page-break-inside: avoid;
        }

        .summary-box {
            border: 1px solid #333333;
            padding: 8px 12px;
            background: #fafafa;
            font-size: 8pt;
        }

        .summary-box table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-box td {
            padding: 2px 4px;
        }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-top: 15px;
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
            📄 Dokumen Cetak Buku Bank: <b>{{ $bankData['meta']['month_name'] }}</b> (TA {{ $year }})
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

    <!-- Official Document Header -->
    <div class="doc-header">
        @if(!empty($withNotes))
            <div style="display: inline-block; background-color: #fef3c7; color: #92400e; border: 1px solid #f59e0b; padding: 2px 10px; font-size: 8pt; font-weight: bold; border-radius: 4px; margin-bottom: 6px; letter-spacing: 0.5px;">
                📝 DRAFT / LEMBAR KERJA RAPAT INTERNAL (DENGAN MEMO & CATATAN)
            </div>
        @endif
        <h2>PEMERINTAH KABUPATEN BANYUMAS</h2>
        <h1>SEKRETARIAT DEWAN PERWAKILAN RAKYAT DAERAH</h1>
        <div class="doc-title">BUKU BANK</div>
        <div class="doc-period">BULAN: {{ strtoupper($bankData['meta']['month_name']) }}</div>
    </div>

    <!-- Metadata Box -->
    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td class="meta-label">SKPD</td>
                <td class="meta-colon">:</td>
                <td class="meta-value">{{ $bankData['meta']['skpd'] }}</td>

                <td style="width: 40px;"></td>

                <td class="meta-label">BENDAHARA PENGELUARAN</td>
                <td class="meta-colon">:</td>
                <td class="meta-value">{{ $bankData['meta']['bendahara'] }}</td>
            </tr>
            <tr>
                <td class="meta-label">PENGGUNA ANGGARAN / KPA</td>
                <td class="meta-colon">:</td>
                <td class="meta-value">{{ $bankData['meta']['pa_kpa'] }}</td>

                <td></td>

                <td class="meta-label">BULAN / PERIODE</td>
                <td class="meta-colon">:</td>
                <td class="meta-value"><b>{{ strtoupper($bankData['meta']['month_name']) }}</b></td>
            </tr>
        </table>
    </div>

    <!-- Main Data Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 30px;">NO. URUT</th>
                <th rowspan="2" style="width: 75px;">TANGGAL</th>
                <th rowspan="2" style="text-align: left;">URAIAN</th>
                <th style="width: 110px;">PENERIMAAN</th>
                <th style="width: 110px;">PENGELUARAN</th>
                <th style="width: 110px;">SALDO</th>
                <th style="width: 160px; text-align: left;">KETERANGAN / TAG</th>
                <th style="width: 110px; text-align: left;">PPTK</th>
                @if(!empty($withNotes))
                <th style="width: 150px; text-align: left; background-color: #fef9c3; color: #854d0e;">MEMO / CATATAN INTERNAL</th>
                @endif
            </tr>
            <tr class="subhead">
                <th>1</th>
                <th>2</th>
                <th>3</th>
                <th>5</th>
                <th>6</th>
                <th>7</th>
                <th>8</th>
                <th>9</th>
                @if(!empty($withNotes))
                <th>10</th>
                @endif
            </tr>
        </thead>
        <tbody>
            <!-- Row: Carryover Saldo Bulan Lalu -->
            <tr style="background-color: #f8fafc; font-weight: 600;">
                <td class="text-center">-</td>
                <td class="text-center" style="white-space: nowrap;">01/{{ sprintf('%02d', $month) }}/{{ $year }}</td>
                <td style="font-style: italic;">Saldo bulan lalu</td>
                <td class="num">{{ $month > 1 ? number_format($bankData['saldo_awal'], 0, ',', '.') : '0,00' }}</td>
                <td class="num">0,00</td>
                <td class="num bold">{{ number_format($bankData['saldo_awal'], 0, ',', '.') }}</td>
                <td style="font-style: italic; color: #555;">Bawaan saldo akhir bulan lalu</td>
                <td>-</td>
                @if(!empty($withNotes))
                <td>-</td>
                @endif
            </tr>

            <!-- Monthly Transaction Rows -->
            @foreach($bankData['rows'] as $r)
            <tr>
                <td class="text-center">{{ $r['no'] }}</td>
                <td class="text-center" style="white-space: nowrap;">{{ $r['tanggal'] }}</td>
                <td>{{ $r['uraian'] }}</td>
                <td class="num">{{ $r['penerimaan'] > 0 ? number_format($r['penerimaan'], 0, ',', '.') : '-' }}</td>
                <td class="num">{{ $r['pengeluaran'] > 0 ? number_format($r['pengeluaran'], 0, ',', '.') : '-' }}</td>
                <td class="num bold">{{ number_format($r['saldo'], 0, ',', '.') }}</td>
                <td>{{ $r['keterangan'] ?: '-' }}</td>
                <td style="font-weight: 500;">{{ $r['pptk_name'] ?: '-' }}</td>
                @if(!empty($withNotes))
                <td style="background-color: #fffbeb; font-size: 7pt; color: #78350f;">{{ $r['notes'] ?: '-' }}</td>
                @endif
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <!-- 1. Periode Ini -->
            <tr class="subtotal-row">
                <td colspan="3" style="text-align: left; padding-left: 10px; font-weight: bold;">JUMLAH PERIODE INI</td>
                <td class="num bold">{{ number_format($bankData['summary']['periode_ini']['penerimaan'], 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($bankData['summary']['periode_ini']['pengeluaran'], 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($bankData['summary']['periode_ini']['saldo'], 0, ',', '.') }}</td>
                <td colspan="{{ !empty($withNotes) ? 3 : 2 }}"></td>
            </tr>

            <!-- 2. Sampai Periode Lalu -->
            <tr class="subtotal-row">
                <td colspan="3" style="text-align: left; padding-left: 10px; font-weight: bold;">JUMLAH SAMPAI PERIODE LALU</td>
                <td class="num bold">{{ number_format($bankData['summary']['sampai_periode_lalu']['penerimaan'], 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($bankData['summary']['sampai_periode_lalu']['pengeluaran'], 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($bankData['summary']['sampai_periode_lalu']['saldo'], 0, ',', '.') }}</td>
                <td colspan="{{ !empty($withNotes) ? 3 : 2 }}"></td>
            </tr>

            <!-- 3. Sampai Periode Ini -->
            <tr class="total-row">
                <td colspan="3" style="text-align: left; padding-left: 10px; font-weight: bold;">JUMLAH SAMPAI PERIODE INI</td>
                <td class="num bold">{{ number_format($bankData['summary']['sampai_periode_ini']['penerimaan'], 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($bankData['summary']['sampai_periode_ini']['pengeluaran'], 0, ',', '.') }}</td>
                <td class="num bold">{{ number_format($bankData['summary']['sampai_periode_ini']['saldo'], 0, ',', '.') }}</td>
                <td colspan="{{ !empty($withNotes) ? 3 : 2 }}"></td>
            </tr>

            <!-- 4. Sisa Kas Akhir -->
            <tr style="background-color: #eff6ff; font-weight: bold; border-bottom: 2px solid #000000;">
                <td colspan="3" style="text-align: left; padding-left: 10px;">SISA KAS (SALDO BANK AKHIR)</td>
                <td colspan="2"></td>
                <td class="num bold" style="font-size: 8.5pt;">{{ number_format($bankData['summary']['sisa_kas'], 0, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <!-- Bottom Section: Cash Breakdown & Signatures -->
    <div class="bottom-section">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <!-- Left: Terdiri Dari (Pecahan Kas) -->
                <td style="width: 45%; vertical-align: top;">
                    <div class="summary-box">
                        <div style="font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: 3px; margin-bottom: 4px;">
                            TERDIRI DARI:
                        </div>
                        <table>
                            <tr>
                                <td>A. TUNAI</td>
                                <td style="width: 10px;">:</td>
                                <td class="num">Rp 0,00</td>
                            </tr>
                            <tr>
                                <td>B. SALDO BANK</td>
                                <td>:</td>
                                <td class="num bold">Rp {{ number_format($bankData['summary']['komponen_kas']['saldo_bank'] ?? $bankData['summary']['sisa_kas'], 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td>C. SURAT BERHARGA</td>
                                <td>:</td>
                                <td class="num">Rp 0,00</td>
                            </tr>
                            <tr style="border-top: 1px solid #999; font-weight: bold;">
                                <td>TOTAL SISA KAS</td>
                                <td>:</td>
                                <td class="num bold">Rp {{ number_format($bankData['summary']['sisa_kas'], 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </div>
                </td>

                <td style="width: 10%;"></td>

                <!-- Right: Information / Notes -->
                <td style="width: 45%; vertical-align: top;">
                    <div style="font-size: 8pt; color: #444; border: 1px dashed #ccc; padding: 8px 12px; background: #fff;">
                        <b>Catatan Penutupan Kas Bank:</b><br>
                        Mutasi kas rekening giro resmi Sekretariat DPRD Kabupaten Banyumas Periode Bulan {{ $bankData['meta']['month_name'] }}.
                        Dicocokkan dan direkonsiliasi dengan rekening koran perbankan.
                    </div>
                </td>
            </tr>
        </table>

        <!-- Signatures Table -->
        <table class="signatures-table">
            <tr>
                <td>
                    Mengetahui,<br>
                    <b>Pengguna Anggaran</b>
                    <br><br><br><br>
                    <u><b>{{ $bankData['meta']['pa_kpa'] }}</b></u><br>
                    NIP. {{ $setting?->pa_kpa_nip ?? '19740510 199903 1 005' }}
                </td>
                <td style="text-align: right;">
                    Purwokerto, {{ date('d F Y') }}<br>
                    <b>Bendahara Pengeluaran</b>
                    <br><br><br><br>
                    <u><b>{{ $bankData['meta']['bendahara'] }}</b></u><br>
                    NIP. {{ $bankData['meta']['bendahara_nip'] ?: ($setting?->treasurer_nip ?? '19840915 200801 2 006') }}
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

