<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    UploadCloud, 
    Clipboard, 
    FileText, 
    FileSpreadsheet,
    CheckCircle2, 
    AlertTriangle, 
    Plus, 
    Trash2, 
    Check, 
    X, 
    ArrowRight,
    Search,
    ShieldAlert,
    History,
    Sparkles,
    FileCheck,
    Image as ImageIcon
} from 'lucide-vue-next';

const props = defineProps({
    recent_batches: Array,
    pptks: Array,
});

const activeInputTab = ref('paste'); // 'paste', 'file'
const rawPasteText = ref('');
const selectedFile = ref(null);
const isProcessing = ref(false);
const processingMessage = ref('');
const parsedResults = ref([]);
const selectAll = ref(true);

// Process Copy-Paste Text
const processPasteText = async () => {
    if (!rawPasteText.value.trim()) {
        alert('Silakan tempel teks transaksi terlebih dahulu.');
        return;
    }

    isProcessing.value = true;
    processingMessage.value = 'Menganalisis teks & merapikan struktur data berantakan...';
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
            if (data.items.length === 0) {
                alert('Tidak ada transaksi yang terdeteksi dari teks yang ditempel. Pastikan teks memuat tanggal dan nominal.');
            }
        } else {
            alert(data.message || 'Gagal membaca transaksi dari teks.');
        }
    } catch (e) {
        alert('Terjadi kesalahan saat memproses teks.');
    } finally {
        isProcessing.value = false;
        processingMessage.value = '';
    }
};

// Process Upload File (All types: PDF, Excel, Word, CSV, Images/Scan with RapidOCR)
const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        selectedFile.value = file;
    }
};

const getFileBadgeColor = (filename) => {
    if (!filename) return 'bg-slate-100 text-slate-700';
    const ext = filename.split('.').pop().toLowerCase();
    if (['xlsx', 'xls', 'csv'].includes(ext)) return 'bg-emerald-100 text-emerald-800 border-emerald-300';
    if (['pdf'].includes(ext)) return 'bg-rose-100 text-rose-800 border-rose-300';
    if (['docx', 'doc'].includes(ext)) return 'bg-blue-100 text-blue-800 border-blue-300';
    if (['png', 'jpg', 'jpeg', 'webp'].includes(ext)) return 'bg-amber-100 text-amber-800 border-amber-300';
    return 'bg-slate-100 text-slate-800 border-slate-300';
};

const processFileUpload = async () => {
    if (!selectedFile.value) {
        alert('Pilih file dokumen terlebih dahulu.');
        return;
    }

    isProcessing.value = true;
    processingMessage.value = 'Membaca dokumen & menjalankan RapidOCR jika diperlukan...';

    const formData = new FormData();
    formData.append('mode', 'file');
    formData.append('document_file', selectedFile.value);
    formData.append('pdf_file', selectedFile.value); // backward compatibility

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
            if (data.items.length === 0) {
                alert('Dokumen berhasil dibaca namun tidak ada baris transaksi valid (tanggal & nominal) yang ditemukan.');
            }
        } else {
            alert(data.message || 'Gagal mengekstraksi transaksi dari file dokumen.');
        }
    } catch (e) {
        alert('Terjadi kesalahan saat memproses file dokumen.');
    } finally {
        isProcessing.value = false;
        processingMessage.value = '';
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
        import_type: activeInputTab.value === 'paste' ? 'paste_text' : 'file_ocr',
        items: selectedItems,
    }, {
        onSuccess: () => {
            parsedResults.value = [];
            rawPasteText.value = '';
            selectedFile.value = null;
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
        <Head title="Smart Transaction Import & OCR" />

        <div class="space-y-4">
            
            <!-- Page Title -->
            <div class="bg-white border border-slate-200 rounded p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                    <h1 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <UploadCloud class="w-5 h-5 text-emerald-600" />
                        Smart Transaction Import & Verifikasi Pembukuan
                    </h1>
                    <p class="text-xs text-slate-500">
                        Otomatisasi pemindahan transaksi dari aplikasi resmi (SIPD RI / CMS Bank Jateng / SP2D / Excel / Foto Bukti Kas) dengan Deep Learning RapidOCR.
                    </p>
                </div>
            </div>

            <!-- Input Method Selector & Container -->
            <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                <div class="border-b border-slate-200 bg-slate-50 px-4 flex items-center gap-2 text-xs">
                    <button
                        @click="activeInputTab = 'paste'"
                        :class="[
                            'py-2.5 px-4 font-semibold border-b-2 flex items-center gap-1.5 transition-colors cursor-pointer',
                            activeInputTab === 'paste'
                                ? 'border-emerald-600 text-emerald-800 bg-white shadow-xs'
                                : 'border-transparent text-slate-600 hover:text-slate-900'
                        ]"
                    >
                        <Clipboard class="w-4 h-4 text-emerald-600" />
                        METODE A — Tempel Teks Cerdas (Copy-Paste Berantakan / Tabel / WA)
                    </button>

                    <button
                        @click="activeInputTab = 'file'"
                        :class="[
                            'py-2.5 px-4 font-semibold border-b-2 flex items-center gap-1.5 transition-colors cursor-pointer',
                            activeInputTab === 'file'
                                ? 'border-emerald-600 text-emerald-800 bg-white shadow-xs'
                                : 'border-transparent text-slate-600 hover:text-slate-900'
                        ]"
                    >
                        <Sparkles class="w-4 h-4 text-blue-600" />
                        METODE B — Unggah Dokumen & OCR AI (PDF, Excel, Word, Scan/Foto Kwitansi)
                    </button>
                </div>

                <!-- Method A: Copy-Paste Area -->
                <div v-if="activeInputTab === 'paste'" class="p-4 space-y-3">
                    <div class="p-3 bg-emerald-50/60 border border-emerald-200 rounded text-xs text-emerald-900 flex items-start gap-2">
                        <Sparkles class="w-4 h-4 text-emerald-700 shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold">Algoritma Auto-Converter Aktif:</span>
                            Tidak masalah jika hasil copy-paste berantakan, terpotong banyak baris, atau berasal dari tabel SIPD, chat WhatsApp, maupun mutasi CMS Bank Jateng. Sistem secara cerdas akan menyaring kop laporan, menyatukan baris terpotong, serta mendeteksi tanggal, nomor dokumen, uraian, PPTK, dan nominal.
                        </div>
                    </div>

                    <textarea
                        v-model="rawPasteText"
                        rows="7"
                        placeholder="Tempel teks tabel transaksi di sini (Ctrl+V)... Parser akan otomatis membersihkan header, menyatukan baris berantakan, mendeteksi nominal, dan memetakan PPTK."
                        class="w-full p-3 font-mono text-xs border border-slate-300 rounded focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-slate-50/50"
                    ></textarea>

                    <div class="flex items-center justify-between">
                        <span class="text-[11px] text-slate-500">Mendukung multi-transaksi, tabel tab-delimited, format tanggal Indonesia (15 Oktober 2026 / 15-Okt-26), dan mutasi debet/kredit.</span>
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

                <!-- Method B: Upload Universal Document & OCR Area -->
                <div v-else-if="activeInputTab === 'file'" class="p-4 space-y-3">
                    <div class="p-3 bg-blue-50/70 border border-blue-200 rounded text-xs text-blue-900 flex items-start gap-2">
                        <Sparkles class="w-4 h-4 text-blue-700 shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold">Didukung Mesin Pembaca Multi-Format & Deep Learning RapidOCR:</span>
                            Unggah berbagai tipe dokumen tanpa perlu mengubah format secara manual. Mesin akan membaca file digital maupun gambar hasil foto/scan kwitansi, SP2D, rekening koran koran bank, dokumen Word, dan spreadsheet Excel.
                        </div>
                    </div>

                    <!-- Supported Format Badges -->
                    <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                        <span class="text-slate-500 font-semibold mr-1">Format Didukung:</span>
                        <span class="px-2 py-0.5 rounded bg-rose-50 border border-rose-200 text-rose-700 font-medium">PDF (Digital & Scan OCR)</span>
                        <span class="px-2 py-0.5 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 font-medium">Excel (.xlsx, .xls)</span>
                        <span class="px-2 py-0.5 rounded bg-blue-50 border border-blue-200 text-blue-700 font-medium">Word (.docx)</span>
                        <span class="px-2 py-0.5 rounded bg-cyan-50 border border-cyan-200 text-cyan-700 font-medium">CSV / TXT</span>
                        <span class="px-2 py-0.5 rounded bg-amber-50 border border-amber-200 text-amber-700 font-medium">Foto & Scan (.png, .jpg, .webp)</span>
                    </div>

                    <!-- Dropzone -->
                    <div class="border-2 border-dashed border-slate-300 rounded-lg p-6 text-center hover:bg-slate-50 transition-colors">
                        <UploadCloud class="w-9 h-9 text-blue-500 mx-auto mb-2" />
                        <label class="cursor-pointer">
                            <span class="text-xs font-bold text-blue-600 hover:underline">Pilih file dokumen atau gambar dari komputer</span>
                            <input 
                                type="file" 
                                accept=".pdf,.xlsx,.xls,.csv,.txt,.docx,.png,.jpg,.jpeg,.webp,.bmp,.tiff" 
                                @change="handleFileChange" 
                                class="hidden" 
                            />
                        </label>
                        <p v-if="selectedFile" class="text-xs font-bold mt-2 font-mono flex items-center justify-center gap-2">
                            <span :class="['px-2 py-0.5 border rounded text-[11px]', getFileBadgeColor(selectedFile.name)]">
                                {{ selectedFile.name.split('.').pop().toUpperCase() }}
                            </span>
                            <span class="text-slate-800">{{ selectedFile.name }}</span>
                            <span class="text-slate-400 font-normal">({{ (selectedFile.size / 1024).toFixed(1) }} KB)</span>
                        </p>
                        <p v-else class="text-[11px] text-slate-400 mt-1">Maksimal ukuran 25 MB. Termasuk hasil scan foto kamera HP atau screenshot CMS.</p>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="text-[11px] text-slate-500">
                            <span v-if="isProcessing" class="font-medium text-blue-700 flex items-center gap-1.5">
                                <span class="animate-spin w-3.5 h-3.5 border-2 border-blue-600 border-t-transparent rounded-full"></span>
                                {{ processingMessage }}
                            </span>
                        </div>
                        <button
                            @click="processFileUpload"
                            :disabled="isProcessing || !selectedFile"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded text-xs font-semibold shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <span v-if="isProcessing" class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
                            <Check v-else class="w-4 h-4" />
                            Baca & Ekstraksi Dokumen / OCR
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
                                    <span v-else-if="b.import_type === 'paste_text'" class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded font-medium text-[10px]">Tempel Teks Cerdas</span>
                                    <span v-else-if="b.import_type === 'file_ocr'" class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded font-medium text-[10px]">Dokumen & OCR</span>
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
