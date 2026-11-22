<?php

namespace App\Http\Controllers\Gaji;

use App\Http\Controllers\Controller;
use App\Models\Gaji\GajiRecipient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GajiRecipientController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $category = $request->input('category');
        $subCategory = $request->input('sub_category');

        $query = GajiRecipient::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('position_name', 'like', "%{$search}%")
                    ->orWhere('identifier_number', 'like', "%{$search}%")
                    ->orWhere('political_party', 'like', "%{$search}%");
            });
        }

        if ($category && in_array($category, ['asn', 'dprd'])) {
            $query->where('category', $category);
        }

        if ($subCategory) {
            $query->where('sub_category', $subCategory);
        }

        $recipients = $query->orderBy('category')
            ->orderBy('sub_category')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Gaji/Penerima/Index', [
            'recipients' => $recipients,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'sub_category' => $subCategory,
            ],
            'stats' => [
                'total' => GajiRecipient::count(),
                'asn_count' => GajiRecipient::where('category', 'asn')->count(),
                'dprd_count' => GajiRecipient::where('category', 'dprd')->count(),
                'active_count' => GajiRecipient::where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'identifier_number' => 'nullable|string|max:50',
            'category' => 'required|in:asn,dprd',
            'sub_category' => 'required|in:pns,pppk_penuh_waktu,pppk_paruh_waktu,ketua_dprd,wakil_ketua_dprd,anggota_dprd',
            'position_name' => 'required|string|max:150',
            'grade' => 'nullable|string|max:20',
            'mkg_years' => 'nullable|integer|min:0|max:40',
            'job_class' => 'nullable|integer|min:1|max:14',
            'political_party' => 'nullable|string|max:100',
            'marital_status' => 'required|in:single,married,widowed',
            'children_count' => 'required|integer|min:0|max:10',
            'akd_role' => 'nullable|in:none,ketua,wakil_ketua,sekretaris,anggota',
            'akd_name' => 'nullable|string|max:100',
            'has_official_residence' => 'boolean',
            'has_official_car' => 'boolean',
            'tpp_base_amount' => 'nullable|numeric|min:0',
            'fixed_salary' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        GajiRecipient::create($validated);

        return back()->with('success', 'Data penerima berhasil ditambahkan.');
    }

    public function update(Request $request, GajiRecipient $recipient): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'identifier_number' => 'nullable|string|max:50',
            'category' => 'required|in:asn,dprd',
            'sub_category' => 'required|in:pns,pppk_penuh_waktu,pppk_paruh_waktu,ketua_dprd,wakil_ketua_dprd,anggota_dprd',
            'position_name' => 'required|string|max:150',
            'grade' => 'nullable|string|max:20',
            'mkg_years' => 'nullable|integer|min:0|max:40',
            'job_class' => 'nullable|integer|min:1|max:14',
            'political_party' => 'nullable|string|max:100',
            'marital_status' => 'required|in:single,married,widowed',
            'children_count' => 'required|integer|min:0|max:10',
            'akd_role' => 'nullable|in:none,ketua,wakil_ketua,sekretaris,anggota',
            'akd_name' => 'nullable|string|max:100',
            'has_official_residence' => 'boolean',
            'has_official_car' => 'boolean',
            'tpp_base_amount' => 'nullable|numeric|min:0',
            'fixed_salary' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $recipient->update($validated);

        return back()->with('success', 'Data penerima berhasil diperbarui.');
    }

    public function toggleActive(GajiRecipient $recipient): RedirectResponse
    {
        $recipient->update(['is_active' => ! $recipient->is_active]);

        return back()->with('success', 'Status keaktifan penerima berhasil diubah.');
    }

    public function destroy(GajiRecipient $recipient): RedirectResponse
    {
        $recipient->delete();

        return back()->with('success', 'Data penerima berhasil dihapus.');
    }

    /**
     * Impor data penerima dari berkas Excel.
     */
    public function importExcel(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        if (count($rows) < 2) {
            return back()->withErrors(['file' => 'Berkas Excel kosong atau tidak memiliki baris data.']);
        }

        $imported = 0;
        // Asumsi baris 1 adalah header: Nama, NIP/NIK, Kategori (ASN/DPRD), Sub Kategori, Jabatan, Golongan, MKG, Kelas Jabatan, Partai
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $name = trim((string) ($row[0] ?? ''));
            if (! $name) {
                continue;
            }

            $cat = strtolower(trim((string) ($row[2] ?? 'asn')));
            if (! in_array($cat, ['asn', 'dprd'])) {
                $cat = 'asn';
            }

            $subCat = strtolower(trim(str_replace(' ', '_', (string) ($row[3] ?? 'pns'))));
            if (! in_array($subCat, ['pns', 'pppk_penuh_waktu', 'pppk_paruh_waktu', 'ketua_dprd', 'wakil_ketua_dprd', 'anggota_dprd'])) {
                $subCat = ($cat === 'dprd') ? 'anggota_dprd' : 'pns';
            }

            GajiRecipient::updateOrCreate(
                ['name' => $name, 'category' => $cat],
                [
                    'identifier_number' => trim((string) ($row[1] ?? '')) ?: null,
                    'sub_category' => $subCat,
                    'position_name' => trim((string) ($row[4] ?? 'Pegawai/Anggota')),
                    'grade' => trim((string) ($row[5] ?? '')) ?: null,
                    'mkg_years' => (int) ($row[6] ?? 0),
                    'job_class' => ! empty($row[7]) ? (int) $row[7] : null,
                    'political_party' => trim((string) ($row[8] ?? '')) ?: null,
                    'is_active' => true,
                ]
            );
            $imported++;
        }

        return back()->with('success', "Berhasil mengimpor $imported data penerima dari Excel.");
    }

    /**
     * Download format template Excel untuk impor penerima.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Penerima');

        $headers = [
            'Nama Penerima', 'NIP / NIK', 'Kategori (asn / dprd)', 'Sub Kategori (pns / pppk_penuh_waktu / pppk_paruh_waktu / ketua_dprd / wakil_ketua_dprd / anggota_dprd)',
            'Jabatan', 'Golongan (mis. IV/c, IX)', 'MKG Tahun', 'Kelas Jabatan (1-14)', 'Partai Politik (khusus DPRD)',
        ];

        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue("{$col}1", $h);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        // Contoh Baris
        $sheet->setCellValue('A2', 'Contoh Pegawai PNS, S.Sos');
        $sheet->setCellValue('B2', '19850101 201001 1 001');
        $sheet->setCellValue('C2', 'asn');
        $sheet->setCellValue('D2', 'pns');
        $sheet->setCellValue('E2', 'Kepala Subbagian');
        $sheet->setCellValue('F2', 'III/d');
        $sheet->setCellValue('G2', 12);
        $sheet->setCellValue('H2', 9);
        $sheet->setCellValue('I2', '');

        $sheet->setCellValue('A3', 'Contoh Anggota Dewan');
        $sheet->setCellValue('B3', '');
        $sheet->setCellValue('C3', 'dprd');
        $sheet->setCellValue('D3', 'anggota_dprd');
        $sheet->setCellValue('E3', 'Anggota DPRD');
        $sheet->setCellValue('F3', '');
        $sheet->setCellValue('G3', 0);
        $sheet->setCellValue('H3', '');
        $sheet->setCellValue('I3', 'PDI Perjuangan');

        $tempPath = storage_path('app/template_penerima_gaji.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, 'Template_Impor_Penerima_Gaji.xlsx')->deleteFileAfterSend(true);
    }
}
