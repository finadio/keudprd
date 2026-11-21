<script setup>
import { ref, computed } from 'vue';
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
    ArrowLeft,
    Search,
    ShieldAlert,
    History,
    Sparkles,
    FileCheck,
    ChevronRight,
    Clock,
    Upload,
    Image as ImageIcon
} from 'lucide-vue-next';

const props = defineProps({
    recent_batches: Array,
    pptks: Array,
});

const activeInputTab = ref('file'); // 'file' (Unggah File - Disarankan) or 'paste' (Tempel Data)
const rawPasteText = ref('');
const selectedFile = ref(null);
const fileInputRef = ref(null);
const isProcessing = ref(false);
const processingMessage = ref('');
const parsedResults = ref([]);
const selectAll = ref(true);

// Determine active step (1: Pilih Metode, 2: Unggah File / Tempel Data, 3: Periksa & Simpan)
const currentStep = computed(() => {
    if (parsedResults.value.length > 0) return 3;
    if (selectedFile.value || rawPasteText.value.trim().length > 0) return 2;
    return 1;
});

// Trigger file input click
const triggerFileInput = () => {
    if (fileInputRef.value) {
        fileInputRef.value.click();
    }
};

const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        selectedFile.value = file;
    }
};

const removeSelectedFile = () => {
    selectedFile.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
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

const getFileIconType = (filename) => {
    if (!filename) return 'DOC';
    const ext = filename.split('.').pop().toLowerCase();
    if (['xlsx', 'xls'].includes(ext)) return 'X';
    if (ext === 'pdf') return 'PDF';
    if (['docx', 'doc'].includes(ext)) return 'W';
    if (['png', 'jpg', 'jpeg', 'webp'].includes(ext)) return 'IMG';
    return 'TXT';
};

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

// Process Upload File (PDF, Excel, Word, CSV, Images/Scan with RapidOCR)
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
                'Accept': 'application/json',
            },
            body: formData,
        });

        if (!response.ok) {
            const errData = await response.json().catch(() => ({}));
            alert(errData.message || `Gagal membaca berkas (HTTP ${response.status}: ${response.statusText}).`);
            return;
        }

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
        alert('Terjadi kesalahan saat memproses file dokumen: ' + (e.message || e));
    } finally {
        isProcessing.value = false;
        processingMessage.value = '';
    }
};

const resetToStepOne = () => {
    parsedResults.value = [];
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

const formatDateIndo = (dateStr) => {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).replace(/\./g, ':');
    } catch (e) {
        return dateStr;
    }
};
</script>

<template>
    <AppLayout>
        <Head title="Smart Transaction Import & OCR" />

        <div class="space-y-5">
            
            <!-- Standard KeuDPRD Page Title Card (Harmonized with Dashboard & Pagu) -->
            <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
                        <UploadCloud class="w-6 h-6 text-emerald-600" />
                        Smart Transaction Import &amp; Verifikasi Pembukuan
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1">
                        Otomatisasi pemindahan transaksi dari aplikasi resmi (SIPD RI / CMS Bank Jateng / SP2D / Excel / Foto Bukti Kas) dengan Deep Learning RapidOCR.
                    </p>
                </div>
            </div>

            <!-- Stepper Indicator (Harmonized with KeuDPRD) -->
            <div class="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-xs">
                <div class="flex items-center justify-center max-w-xl mx-auto text-xs">
                    
                    <!-- Step 1: Pilih Metode -->
                    <div class="flex items-center gap-2">
                        <span 
                            :class="[
                                'w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs transition-colors',
                                currentStep >= 1 ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500 border border-slate-300'
                            ]"
                        >
                            1
                        </span>
                        <span 
                            :class="[
                                'font-semibold transition-colors',
                                currentStep === 1 ? 'text-emerald-800' : 'text-slate-700'
                            ]"
                        >
                            Pilih Metode
                        </span>
                    </div>

                    <!-- Connector Line 1 -->
                    <div :class="['h-0.5 w-8 sm:w-16 mx-2 transition-colors', currentStep >= 2 ? 'bg-emerald-600' : 'bg-slate-200']"></div>

                    <!-- Step 2: Unggah Dokumen / Teks -->
                    <div class="flex items-center gap-2">
                        <span 
                            :class="[
                                'w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs transition-colors',
                                currentStep >= 2 ? 'bg-emerald-600 text-white' : 'bg-white text-slate-400 border border-slate-300'
                            ]"
                        >
                            2
                        </span>
                        <span 
                            :class="[
                                'font-semibold transition-colors',
                                currentStep === 2 ? 'text-emerald-800' : 'text-slate-500'
                            ]"
                        >
                            {{ activeInputTab === 'file' ? 'Unggah Dokumen' : 'Tempel Teks' }}
                        </span>
                    </div>

                    <!-- Connector Line 2 -->
                    <div :class="['h-0.5 w-8 sm:w-16 mx-2 transition-colors', currentStep >= 3 ? 'bg-emerald-600' : 'bg-slate-200']"></div>

                    <!-- Step 3: Periksa & Simpan -->
                    <div class="flex items-center gap-2">
                        <span 
                            :class="[
                                'w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs transition-colors',
                                currentStep >= 3 ? 'bg-emerald-600 text-white' : 'bg-white text-slate-400 border border-slate-300'
                            ]"
                        >
                            3
                        </span>
                        <span 
                            :class="[
                                'font-semibold transition-colors',
                                currentStep === 3 ? 'text-emerald-800' : 'text-slate-400'
                            ]"
                        >
                            Periksa & Simpan
                        </span>
                    </div>

                </div>
            </div>

            <!-- STAGE 1 & 2: INPUT SELECTION & PROCESS -->
            <div v-if="currentStep < 3" class="space-y-4">
                
                <!-- Method Selection Cards (Harmonious with KeuDPRD Cards) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    
                    <!-- Card 1: Unggah File (Disarankan) -->
                    <div 
                        @click="activeInputTab = 'file'"
                        :class="[
                            'p-4 rounded-xl border transition-all cursor-pointer flex items-center justify-between gap-3 shadow-xs',
                            activeInputTab === 'file'
                                ? 'border-emerald-600 bg-emerald-50/50 ring-2 ring-emerald-500/20'
                                : 'border-slate-200/90 bg-white hover:border-slate-300 hover:bg-slate-50/70'
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <div 
                                :class="[
                                    'w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border transition-colors',
                                    activeInputTab === 'file' 
                                        ? 'bg-emerald-100 border-emerald-300 text-emerald-800' 
                                        : 'bg-slate-100 border-slate-200 text-slate-500'
                                ]"
                            >
                                <Upload class="w-4 h-4 text-emerald-700" />
                            </div>
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <h3 class="font-bold text-slate-900 text-xs sm:text-sm">Unggah Dokumen</h3>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        Disarankan
                                    </span>
                                </div>
                                <p class="text-[11px] sm:text-xs text-slate-500 mt-0.5">
                                    Pilih file PDF, Excel, Word, CSV, atau Foto Scan Kwitansi.
                                </p>
                            </div>
                        </div>

                        <!-- Checkmark Indicator -->
                        <div class="shrink-0">
                            <div 
                                v-if="activeInputTab === 'file'"
                                class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center shadow-xs"
                            >
                                <Check class="w-3 h-3 stroke-[3]" />
                            </div>
                            <div 
                                v-else
                                class="w-5 h-5 rounded-full border border-slate-300 bg-white"
                            ></div>
                        </div>
                    </div>

                    <!-- Card 2: Tempel Data -->
                    <div 
                        @click="activeInputTab = 'paste'"
                        :class="[
                            'p-4 rounded-xl border transition-all cursor-pointer flex items-center justify-between gap-3 shadow-xs',
                            activeInputTab === 'paste'
                                ? 'border-emerald-600 bg-emerald-50/50 ring-2 ring-emerald-500/20'
                                : 'border-slate-200/90 bg-white hover:border-slate-300 hover:bg-slate-50/70'
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <div 
                                :class="[
                                    'w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border transition-colors',
                                    activeInputTab === 'paste' 
                                        ? 'bg-emerald-100 border-emerald-300 text-emerald-800' 
                                        : 'bg-slate-100 border-slate-200 text-slate-500'
                                ]"
                            >
                                <Clipboard class="w-4 h-4 text-slate-600" />
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900 text-xs sm:text-sm">Tempel Data (Copy-Paste)</h3>
                                <p class="text-[11px] sm:text-xs text-slate-500 mt-0.5">
                                    Salin dan tempel data tabel transaksi dari SIPD, Bank, atau WA.
                                </p>
                            </div>
                        </div>

                        <!-- Checkmark Indicator -->
                        <div class="shrink-0">
                            <div 
                                v-if="activeInputTab === 'paste'"
                                class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center shadow-xs"
                            >
                                <Check class="w-3 h-3 stroke-[3]" />
                            </div>
                            <div 
                                v-else
                                class="w-5 h-5 rounded-full border border-slate-300 bg-white"
                            ></div>
                        </div>
                    </div>

                </div>

                <!-- Input Box Method 1: Unggah Dokumen -->
                <div v-if="activeInputTab === 'file'" class="bg-white border border-slate-200/90 rounded-xl p-5 shadow-xs space-y-4">
                    
                    <!-- Hidden native file input -->
                    <input 
                        ref="fileInputRef"
                        type="file" 
                        accept=".pdf,.xlsx,.xls,.csv,.txt,.docx,.png,.jpg,.jpeg,.webp,.bmp,.tiff" 
                        @change="handleFileChange" 
                        class="hidden" 
                    />

                    <!-- Dashed Dropzone Box -->
                    <div 
                        @click="triggerFileInput"
                        class="border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-xl p-8 text-center hover:bg-slate-50/60 transition-colors cursor-pointer group"
                    >
                        <UploadCloud class="w-10 h-10 text-emerald-600 mx-auto mb-2.5 group-hover:scale-105 transition-transform" />
                        
                        <p class="text-xs sm:text-sm font-bold text-slate-800">
                            Pilih file transaksi dari komputer Anda
                        </p>

                        <div class="mt-3">
                            <button 
                                type="button"
                                @click.stop="triggerFileInput"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-xs inline-flex items-center gap-1.5 transition-colors cursor-pointer"
                            >
                                <Upload class="w-3.5 h-3.5" />
                                Pilih File Dokumen
                            </button>
                        </div>

                        <!-- Selected File Preview if chosen -->
                        <div v-if="selectedFile" class="mt-3.5 inline-flex items-center gap-2 px-3 py-1.5 bg-white border border-emerald-300 rounded-lg shadow-xs">
                            <span :class="['px-1.5 py-0.5 rounded text-[10px] font-bold border', getFileBadgeColor(selectedFile.name)]">
                                {{ selectedFile.name.split('.').pop().toUpperCase() }}
                            </span>
                            <span class="text-xs font-bold text-slate-800 font-mono">{{ selectedFile.name }}</span>
                            <span class="text-[11px] text-slate-500">({{ (selectedFile.size / 1024).toFixed(1) }} KB)</span>
                            <button 
                                type="button" 
                                @click.stop="removeSelectedFile" 
                                class="text-slate-400 hover:text-rose-600 p-0.5 rounded transition-colors"
                                title="Batal pilih file"
                            >
                                <X class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <p v-else class="text-[11px] sm:text-xs text-slate-400 mt-2.5">
                            Format didukung: PDF, Excel, Word, CSV, Foto Scan | Maks. 25 MB
                        </p>
                    </div>

                    <!-- Action Button Row -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                        <div class="text-[11px] sm:text-xs text-slate-500">
                            <span v-if="isProcessing" class="font-medium text-emerald-700 flex items-center gap-1.5">
                                <span class="animate-spin w-3.5 h-3.5 border-2 border-emerald-600 border-t-transparent rounded-full"></span>
                                {{ processingMessage }}
                            </span>
                            <span v-else>Mesin OCR RapidOCR otomatis aktif jika mengunggah file foto atau dokumen hasil scan.</span>
                        </div>

                        <button
                            @click="processFileUpload"
                            :disabled="isProcessing || !selectedFile"
                            class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl text-xs shadow-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <span v-if="isProcessing" class="animate-spin w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                            <ArrowRight v-else class="w-3.5 h-3.5" />
                            <span>{{ isProcessing ? 'Sedang Membaca...' : 'Mulai Baca File' }}</span>
                        </button>
                    </div>

                </div>

                <!-- Input Box Method 2: Tempel Data -->
                <div v-else-if="activeInputTab === 'paste'" class="bg-white border border-slate-200/90 rounded-xl p-5 shadow-xs space-y-3.5">
                    
                    <div class="p-3 bg-emerald-50/70 border border-emerald-200 rounded-xl text-xs sm:text-sm text-emerald-900 flex items-start gap-2.5">
                        <Sparkles class="w-4 h-4 text-emerald-700 shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold">Algoritma Auto-Converter Aktif:</span>
                            Mendukung teks acak, tabel web SIPD RI, mutasi CMS Bank Jateng, atau chat WhatsApp. Sistem secara cerdas menyaring kop dinas, menyatukan baris terpotong, serta mengekstrak nomor dokumen, tanggal, uraian, PPTK, dan nominal.
                        </div>
                    </div>

                    <textarea
                        v-model="rawPasteText"
                        rows="7"
                        placeholder="Tempel teks tabel transaksi di sini (Ctrl+V)... Parser otomatis membersihkan header, menyatukan baris berantakan, mendeteksi nominal, dan memetakan PPTK."
                        class="w-full p-3 font-mono text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-slate-50/50"
                    ></textarea>

                    <!-- Action Button Row -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                        <span class="text-[11px] sm:text-xs text-slate-500">Mendukung format tanggal Indonesia (15 Oktober 2026), tab-delimited, dan mutasi debet/kredit.</span>

                        <button
                            @click="processPasteText"
                            :disabled="isProcessing || !rawPasteText.trim()"
                            class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl text-xs shadow-xs flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <span v-if="isProcessing" class="animate-spin w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                            <ArrowRight v-else class="w-3.5 h-3.5" />
                            <span>{{ isProcessing ? 'Sedang Memproses...' : 'Mulai Ekstraksi Teks' }}</span>
                        </button>
                    </div>

                </div>

            </div>

            <!-- STAGE 3: PERIKSA & SIMPAN (Preview Table) -->
            <div v-else class="bg-white border border-slate-200/90 rounded-xl shadow-xs overflow-hidden space-y-0">
                
                <!-- Step 3 Action Header -->
                <div class="px-5 py-3.5 bg-slate-900 text-white flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <button 
                                @click="resetToStepOne"
                                class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg text-xs font-semibold flex items-center gap-1 transition-colors cursor-pointer"
                            >
                                <ArrowLeft class="w-3 h-3" /> Unggah Dokumen Lain
                            </button>
                            <h2 class="text-xs sm:text-sm font-bold uppercase tracking-wider flex items-center gap-1.5 text-emerald-400">
                                <CheckCircle2 class="w-4 h-4 text-emerald-400" />
                                Hasil Ekstraksi Transaksi ({{ parsedResults.length }} Transaksi Terdeteksi)
                            </h2>
                        </div>
                        <p class="text-[11px] sm:text-xs text-slate-400 mt-1">
                            Periksa hasil ekstraksi sebelum dibukukan. Anda dapat mengubah PPTK, arah arus kas, nominal, atau mengecualikan transaksi.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="parsedResults.some(r => r.is_duplicate)"
                            @click="removeDuplicateItems"
                            type="button"
                            class="px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                            title="Hapus semua baris yang terdeteksi dobel dari daftar impor"
                        >
                            <Trash2 class="w-3.5 h-3.5" /> Hapus {{ parsedResults.filter(r => r.is_duplicate).length }} Baris Dobel
                        </button>
                        <button
                            @click="commitTransactions"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <Check class="w-4 h-4" /> Setujui &amp; Simpan ke Pembukuan Aktif
                        </button>
                    </div>
                </div>

                <!-- Preview Table -->
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

            <!-- Riwayat Batch Impor Transaksi (Harmonized KeuDPRD Card) -->
            <div class="bg-white border border-slate-200/90 rounded-xl shadow-xs overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
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
                            <tr v-if="recent_batches.length === 0">
                                <td colspan="6" class="px-3 py-4 text-center text-slate-400 text-xs">
                                    Belum ada transaksi yang pernah diimpor.
                                </td>
                            </tr>
                            <tr v-for="b in recent_batches" :key="b.id" class="hover:bg-slate-50">
                                <td class="px-3 py-2 font-mono font-semibold text-slate-800">{{ b.batch_number }}</td>
                                <td class="px-3 py-2">
                                    <span v-if="b.import_type === 'excel_historical'" class="px-2 py-0.5 bg-purple-100 text-purple-800 rounded font-medium text-[10px]">Excel Historis</span>
                                    <span v-else-if="b.import_type === 'paste_text'" class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded font-medium text-[10px]">Tempel Teks Cerdas</span>
                                    <span v-else-if="b.import_type === 'file_ocr'" class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded font-medium text-[10px]">Dokumen & OCR</span>
                                    <span v-else class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded font-medium text-[10px]">Upload PDF</span>
                                </td>
                                <td class="px-3 py-2 text-slate-600 font-medium">{{ b.original_filename || (b.import_type === 'paste_text' ? 'Tempel Teks SIPD' : 'Dokumen Impor') }}</td>
                                <td class="px-3 py-2 text-center font-mono font-bold">{{ Number(b.parsed_count || 0).toLocaleString('id-ID') }} Record</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded font-medium text-[10px] uppercase font-mono">Dibukukan</span>
                                </td>
                                <td class="px-3 py-2 text-slate-500 font-mono text-[11px]">{{ formatDateIndo(b.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
