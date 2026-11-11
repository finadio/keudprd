<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    UploadCloud, 
    Clipboard, 
    FileText, 
    CheckCircle2, 
    AlertTriangle, 
    Plus, 
    Trash2, 
    Check, 
    X, 
    ArrowRight,
    Search,
    ShieldAlert,
    History
} from 'lucide-vue-next';

const props = defineProps({
    recent_batches: Array,
    pptks: Array,
});

const activeInputTab = ref('paste'); // 'paste', 'pdf', 'manual'
const rawPasteText = ref('');
const selectedPdfFile = ref(null);
const isProcessing = ref(false);
const parsedResults = ref([]);
const selectAll = ref(true);

// Process Copy-Paste Text
const processPasteText = async () => {
    if (!rawPasteText.value.trim()) {
        alert('Silakan tempel teks transaksi terlebih dahulu.');
        return;
    }

    isProcessing.value = true;
    try {
        const response = await fetch('/impor/parse', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({
                mode: 'text',
                raw_text: rawPasteText.value,
            }),
        });

        const data = await response.json();
        if (data.success) {
            parsedResults.value = data.items.map(item => ({
                ...item,
                selected: item.selected !== false,
            }));
        } else {
            alert('Gagal membaca transaksi dari teks.');
        }
    } catch (e) {
        alert('Terjadi kesalahan saat memproses teks.');
    } finally {
        isProcessing.value = false;
    }
};

// Process Upload PDF
const handleFileChange = (e) => {
    selectedPdfFile.value = e.target.files[0];
};

const processPdfUpload = async () => {
    if (!selectedPdfFile.value) {
        alert('Pilih file PDF terlebih dahulu.');
        return;
    }

    isProcessing.value = true;
    const formData = new FormData();
    formData.append('mode', 'pdf');
    formData.append('pdf_file', selectedPdfFile.value);

    try {
        const response = await fetch('/impor/parse', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: formData,
        });

        const data = await response.json();
        if (data.success) {
            parsedResults.value = data.items.map(item => ({
                ...item,
                selected: item.selected !== false,
            }));
        } else {
            alert('Gagal mengekstraksi transaksi dari PDF.');
        }
    } catch (e) {
        alert('Terjadi kesalahan saat memproses file PDF.');
    } finally {
        isProcessing.value = false;
    }
};

const toggleSelectAll = () => {
    parsedResults.value.forEach(item => {
        item.selected = selectAll.value;
    });
};

const removeResultItem = (index) => {
    parsedResults.value.splice(index, 1);
};

const removeDuplicateItems = () => {
    parsedResults.value = parsedResults.value.filter(item => !item.is_duplicate);
};

// Commit Verified Transactions to DB
const commitTransactions = () => {
    const selectedItems = parsedResults.value.filter(item => item.selected);
    if (selectedItems.length === 0) {
        alert('Pilih minimal satu transaksi untuk dibukukan.');
        return;
    }

    router.post('/impor/commit', {
        import_type: activeInputTab.value === 'paste' ? 'paste_text' : 'pdf_upload',
        items: selectedItems,
    }, {
        onSuccess: () => {
            parsedResults.value = [];
            rawPasteText.value = '';
            selectedPdfFile.value = null;
        }
    });
};

const formatRupiah = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '0';
    return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};
</script>

<template>
    <AppLayout>
        <Head title="Smart Transaction Import" />

        <div class="space-y-4">
            
            <!-- Page Title -->
            <div class="bg-white border border-slate-200 rounded p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                    <h1 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <UploadCloud class="w-5 h-5 text-emerald-600" />
                        Smart Transaction Import & Verifikasi Pembukuan
                    </h1>
                    <p class="text-xs text-slate-500">
                        Otomatisasi pemindahan transaksi dari aplikasi keuangan resmi (SIPD RI / CMS Bank Jateng / SP2D) tanpa perlu mengetik ulang satu per satu.
                    </p>
                </div>
            </div>

            <!-- Input Method Selector & Container -->
            <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                <div class="border-b border-slate-200 bg-slate-50 px-4 flex items-center gap-2 text-xs">
                    <button
                        @click="activeInputTab = 'paste'"
                        :class="[
                            'py-2.5 px-4 font-semibold border-b-2 flex items-center gap-1.5 transition-colors',
                            activeInputTab === 'paste'
                                ? 'border-emerald-600 text-emerald-800 bg-white'
                                : 'border-transparent text-slate-600 hover:text-slate-900'
                        ]"
                    >
                        <Clipboard class="w-4 h-4 text-emerald-600" />
                        METODE A — Tempel Teks (Copy-Paste)
                    </button>

                    <button
                        @click="activeInputTab = 'pdf'"
                        :class="[
                            'py-2.5 px-4 font-semibold border-b-2 flex items-center gap-1.5 transition-colors',
                            activeInputTab === 'pdf'
                                ? 'border-emerald-600 text-emerald-800 bg-white'
                                : 'border-transparent text-slate-600 hover:text-slate-900'
                        ]"
                    >
                        <FileText class="w-4 h-4 text-blue-600" />
                        METODE B — Upload PDF (Print to PDF)
                    </button>
                </div>

                <!-- Method A: Copy-Paste Area -->
                <div v-if="activeInputTab === 'paste'" class="p-4 space-y-3">
                    <div class="text-xs text-slate-600">
                        <span class="font-semibold text-slate-800">Petunjuk Penggunaan:</span>
                        Buka halaman daftar transaksi pada aplikasi SIPD atau mutasi rekening CMS Bank Jateng, sorot (blok) seluruh tabel transaksi, salin (<kbd class="px-1 py-0.5 bg-slate-100 border border-slate-300 rounded font-mono text-[10px]">Ctrl+C</kbd>), lalu tempelkan (<kbd class="px-1 py-0.5 bg-slate-100 border border-slate-300 rounded font-mono text-[10px]">Ctrl+V</kbd>) ke dalam kotak di bawah ini.
                    </div>

                    <textarea
                        v-model="rawPasteText"
                        rows="6"
                        placeholder="Tempel teks tabel transaksi di sini... Parser akan memisahkan baris, nomor dokumen, tanggal, uraian, potongan pajak, dan nominal secara otomatis."
                        class="w-full p-3 font-mono text-xs border border-slate-300 rounded focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-slate-50/50"
                    ></textarea>

                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-slate-500">Mendukung multi-transaksi, format tabel tab-delimited, baris SP2D, dan mutasi giro.</span>
                        <button
                            @click="processPasteText"
                            :disabled="isProcessing || !rawPasteText.trim()"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded text-xs font-semibold shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <span v-if="isProcessing" class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
                            <Check v-else class="w-4 h-4" />
                            Proses & Ekstraksi Transaksi
                        </button>
                    </div>
                </div>

                <!-- Method B: Upload PDF Area -->
                <div v-else-if="activeInputTab === 'pdf'" class="p-4 space-y-3">
                    <div class="text-xs text-slate-600">
                        <span class="font-semibold text-slate-800">Petunjuk Penggunaan:</span>
                        Pada browser saat membuka laporan keuangan resmi, gunakan menu <kbd class="px-1 py-0.5 bg-slate-100 border border-slate-300 rounded font-mono text-[10px]">Cetak (Ctrl+P) &rarr; Simpan sebagai PDF</kbd>, kemudian unggah file PDF tersebut di bawah ini.
                    </div>

                    <div class="border-2 border-dashed border-slate-300 rounded-lg p-6 text-center hover:bg-slate-50 transition-colors">
                        <FileText class="w-8 h-8 text-blue-500 mx-auto mb-2" />
                        <label class="cursor-pointer">
                            <span class="text-xs font-semibold text-blue-600 hover:underline">Pilih file PDF dari komputer</span>
                            <input type="file" accept=".pdf" @change="handleFileChange" class="hidden" />
                        </label>
                        <p v-if="selectedPdfFile" class="text-xs font-bold text-slate-800 mt-2 font-mono">
                            File terpilih: {{ selectedPdfFile.name }} ({{ (selectedPdfFile.size / 1024).toFixed(1) }} KB)
                        </p>
                        <p v-else class="text-[11px] text-slate-400 mt-1">Maksimal ukuran 10 MB. PDF teks asli (bukan gambar scan foto).</p>
                    </div>

                    <div class="flex items-center justify-end">
                        <button
                            @click="processPdfUpload"
                            :disabled="isProcessing || !selectedPdfFile"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded text-xs font-semibold shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <span v-if="isProcessing" class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
                            <Check v-else class="w-4 h-4" />
                            Baca & Ekstraksi Teks PDF
                        </button>
                    </div>
                </div>
            </div>

            <!-- Preview & Verification Table (Section 6 Requirements) -->
            <div v-if="parsedResults.length > 0" class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-900 text-white flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider flex items-center gap-1.5">
                            <CheckCircle2 class="w-4 h-4 text-emerald-400" />
                            Tabel Preview & Verifikasi Hasil Ekstraksi ({{ parsedResults.length }} Transaksi Terdeteksi)
                        </h2>
                        <p class="text-[11px] text-slate-400">
                            Periksa hasil ekstraksi sebelum dibukukan. Anda dapat mengubah PPTK, arah arus kas, nominal, atau mengecualikan transaksi.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="parsedResults.some(r => r.is_duplicate)"
                            @click="removeDuplicateItems"
                            type="button"
                            class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded text-xs font-bold shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                            title="Hapus semua baris yang terdeteksi dobel dari daftar impor"
                        >
                            <Trash2 class="w-3.5 h-3.5" /> Hapus {{ parsedResults.filter(r => r.is_duplicate).length }} Baris Dobel
                        </button>
                        <button
                            @click="commitTransactions"
                            class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-bold shadow-sm flex items-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <Check class="w-4 h-4" /> Setujui & Simpan ke Pembukuan Aktif
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto excel-scroll p-2">
                    <table class="w-full excel-table text-xs">
                        <thead>
                            <tr class="excel-header-row">
                                <th class="w-8 text-center">
                                    <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                </th>
                                <th class="w-10">No</th>
                                <th class="w-24">Tanggal</th>
                                <th class="min-w-[130px] text-left">No Dokumen</th>
                                <th class="min-w-[280px] text-left">Uraian Transaksi</th>
                                <th class="min-w-[140px] text-left">PPTK Penanggung Jawab</th>
                                <th class="min-w-[110px] text-center">Arah Kas</th>
                                <th class="min-w-[110px] text-right">Nominal Netto (Rp)</th>
                                <th class="min-w-[100px] text-center">Pemeriksaan</th>
                                <th class="w-10 text-center">Hapus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, idx) in parsedResults"
                                :key="item.temp_id"
                                :class="[
                                    'hover:bg-slate-50',
                                    item.is_duplicate ? 'bg-amber-50/70' : 'bg-white'
                                ]"
                            >
                                <td class="text-center">
                                    <input type="checkbox" v-model="item.selected" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                </td>
                                <td class="text-center font-mono text-slate-500">{{ idx + 1 }}</td>
                                <td class="text-center">
                                    <input type="date" v-model="item.transaction_date" class="px-1.5 py-0.5 border border-slate-300 rounded text-xs font-mono w-28" />
                                </td>
                                <td>
                                    <input type="text" v-model="item.document_number" placeholder="No Dokumen..." class="px-1.5 py-0.5 border border-slate-300 rounded text-xs w-full font-mono" />
                                </td>
                                <td>
                                    <input type="text" v-model="item.description" class="px-1.5 py-0.5 border border-slate-300 rounded text-xs w-full text-slate-900" />
                                </td>
                                <td>
                                    <select v-model="item.pptk_id" class="px-1.5 py-0.5 border border-slate-300 rounded text-xs w-full">
                                        <option value="">-- Tanpa PPTK --</option>
                                        <option v-for="p in pptks" :key="p.id" :value="p.id">{{ p.display_name }}</option>
                                    </select>
                                </td>
                                <td class="text-center">
                                    <select v-model="item.flow_direction" class="px-1.5 py-0.5 border border-slate-300 rounded text-xs font-semibold">
                                        <option value="disbursement" class="text-rose-700">Pengeluaran</option>
                                        <option value="receipt" class="text-emerald-700">Penerimaan</option>
                                    </select>
                                </td>
                                <td class="text-right">
                                    <input type="number" v-model="item.amount_net" class="px-1.5 py-0.5 border border-slate-300 rounded text-xs text-right font-mono font-bold text-slate-900 w-28" />
                                </td>
                                <td class="text-center whitespace-nowrap">
                                    <span 
                                        v-if="item.is_duplicate" 
                                        :title="item.duplicate_reason || 'Potensi Duplikat'"
                                        class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-800 bg-amber-100 border border-amber-300 px-2 py-0.5 rounded cursor-help"
                                    >
                                        <AlertTriangle class="w-3 h-3 text-amber-600" /> Potensi Dobel
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">
                                        <CheckCircle2 class="w-3 h-3" /> Siap
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button @click="removeResultItem(idx)" class="text-slate-400 hover:text-rose-600 transition-colors">
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Import Batches Audit History -->
            <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                        <History class="w-4 h-4 text-slate-500" /> Riwayat Batch Impor Transaksi
                    </h2>
                    <span class="text-[11px] text-slate-500">Mencegah transaksi diimpor berulang kali</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-100 text-slate-700 font-semibold border-b border-slate-200">
                            <tr>
                                <th class="px-3 py-2 text-left">No Batch</th>
                                <th class="px-3 py-2 text-left">Metode</th>
                                <th class="px-3 py-2 text-left">File / Sumber</th>
                                <th class="px-3 py-2 text-center">Jumlah Record</th>
                                <th class="px-3 py-2 text-center">Status</th>
                                <th class="px-3 py-2 text-left">Waktu Impor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="b in recent_batches" :key="b.id" class="hover:bg-slate-50">
                                <td class="px-3 py-2 font-mono font-semibold text-slate-800">{{ b.batch_number }}</td>
                                <td class="px-3 py-2">
                                    <span v-if="b.import_type === 'excel_historical'" class="px-2 py-0.5 bg-purple-100 text-purple-800 rounded font-medium text-[10px]">Excel Historis</span>
                                    <span v-else-if="b.import_type === 'paste_text'" class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded font-medium text-[10px]">Tempel Teks</span>
                                    <span v-else class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded font-medium text-[10px]">Upload PDF</span>
                                </td>
                                <td class="px-3 py-2 text-slate-600">{{ b.original_filename || 'Tempel Teks SIPD' }}</td>
                                <td class="px-3 py-2 text-center font-mono font-bold">{{ b.parsed_count }} Record</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded font-medium text-[10px] uppercase font-mono">Dibukukan</span>
                                </td>
                                <td class="px-3 py-2 text-slate-500 font-mono text-[11px]">{{ new Date(b.created_at).toLocaleString('id-ID') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
