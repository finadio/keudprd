<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    FileSpreadsheet,
    UploadCloud,
    CheckCircle2,
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    Loader2,
    Clipboard,
    Upload,
    Check,
    X,
    Trash2,
    Sparkles,
    FileText
} from 'lucide-vue-next';

const props = defineProps({
    year: Number,
    total_activities: Number,
    total_pagu: Number,
    total_spj: Number,
    pptks: {
        type: Array,
        default: () => [],
    },
});

const formatCurrency = (val) => {
    return 'Rp ' + Number(val || 0).toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
};

const activeInputTab = ref('file'); // 'file' or 'paste'
const selectedFile = ref(null);
const fileInputRef = ref(null);
const rawPasteText = ref('');
const isProcessing = ref(false);
const processingMessage = ref('');
const parsedResults = ref([]);
const selectAll = ref(true);

// Determine active step (1: Pilih Metode, 2: Unggah Dokumen / Tempel Teks, 3: Periksa & Simpan)
const currentStep = computed(() => {
    if (parsedResults.value.length > 0) return 3;
    if (selectedFile.value || rawPasteText.value.trim().length > 0) return 2;
    return 1;
});

const triggerFileInput = () => {
    if (fileInputRef.value) {
        fileInputRef.value.click();
    }
};

const onFileChange = (e) => {
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

// Process Copy-Paste Text
const processPasteText = async () => {
    if (!rawPasteText.value.trim()) {
        alert('Silakan tempel teks tabel SIPD terlebih dahulu.');
        return;
    }

    isProcessing.value = true;
    processingMessage.value = 'Menganalisis teks & merapikan struktur data subkegiatan SIPD...';
    try {
        const response = await fetch('/pagu/impor/parse', {
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
                alert('Tidak ada subkegiatan yang terdeteksi dari teks yang ditempel. Pastikan teks memuat kode atau nama subkegiatan serta nominal pagu.');
            }
        } else {
            alert(data.message || 'Gagal membaca subkegiatan dari teks.');
        }
    } catch (e) {
        alert('Terjadi kesalahan saat memproses teks.');
    } finally {
        isProcessing.value = false;
        processingMessage.value = '';
    }
};

// Process File Upload (Excel, PDF, Word, CSV, Images/Scan with RapidOCR)
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
    formData.append('file', selectedFile.value); // backward compatibility

    try {
        const response = await fetch('/pagu/impor/parse', {
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
                alert('Dokumen berhasil dibaca namun tidak ada baris subkegiatan valid yang ditemukan.');
            }
        } else {
            alert(data.message || 'Gagal mengekstraksi subkegiatan dari file dokumen.');
        }
    } catch (e) {
        alert('Terjadi kesalahan saat memproses file dokumen.');
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

const sumParsedPagu = computed(() => {
    return parsedResults.value
        .filter(i => i.selected)
        .reduce((sum, item) => sum + Number(item.pagu_budget || 0), 0);
});

const sumParsedSpj = computed(() => {
    return parsedResults.value
        .filter(i => i.selected)
        .reduce((sum, item) => sum + Number(item.spj_sipd_total || 0), 0);
});

// Commit Verified Activities to Database
const commitActivities = () => {
    const selectedItems = parsedResults.value.filter(item => item.selected);
    if (selectedItems.length === 0) {
        alert('Pilih minimal satu subkegiatan untuk disinkronkan.');
        return;
    }

    router.post('/pagu/impor/commit', {
        items: selectedItems,
    }, {
        onSuccess: () => {
            parsedResults.value = [];
            rawPasteText.value = '';
            selectedFile.value = null;
        }
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Sinkronisasi Data Pagu dari SIPD" />

        <div class="space-y-4 max-w-5xl mx-auto">
            <!-- Header Card (Matching KeuDPRD Style) -->
            <div class="bg-white border border-slate-200 rounded p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                    <Link
                        href="/pagu"
                        class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-emerald-600 font-bold mb-1.5 transition-colors"
                    >
                        <ArrowLeft class="w-3.5 h-3.5" />
                        <span>Kembali ke Pantauan Pagu</span>
                    </Link>
                    <h1 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <UploadCloud class="w-5 h-5 text-emerald-600" />
                        Sinkronisasi Data Pagu SIPD Fungsional
                    </h1>
                    <p class="text-xs text-slate-500">
                        Unggah file dokumen resmi atau tempel teks ekspor SPJ Fungsional dari SIPD Penatausahaan dengan Deep Learning RapidOCR.
                    </p>
                </div>
            </div>

            <!-- Current Status Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="p-3.5 bg-white rounded border border-slate-200 shadow-sm">
                    <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Subkegiatan Aktif</span>
                    <div class="text-xl font-bold font-mono text-slate-900 mt-1">
                        {{ total_activities }} <span class="text-xs font-normal text-slate-500">Subkegiatan</span>
                    </div>
                </div>

                <div class="p-3.5 bg-white rounded border border-slate-200 shadow-sm">
                    <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Pagu Terdaftar</span>
                    <div class="text-xl font-bold font-mono text-slate-900 mt-1">
                        {{ formatCurrency(total_pagu) }}
                    </div>
                </div>

                <div class="p-3.5 bg-white rounded border border-slate-200 shadow-sm">
                    <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">SPJ Resmi SIPD</span>
                    <div class="text-xl font-bold font-mono text-emerald-700 mt-1">
                        {{ formatCurrency(total_spj) }}
                    </div>
                </div>
            </div>

            <!-- Stepper Indicator (Harmonized with KeuDPRD) -->
            <div class="bg-white border border-slate-200 rounded p-3 shadow-sm">
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
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    
                    <!-- Card 1: Unggah Dokumen (Disarankan) -->
                    <div 
                        @click="activeInputTab = 'file'"
                        :class="[
                            'p-3.5 rounded border transition-all cursor-pointer flex items-center justify-between gap-3 shadow-sm',
                            activeInputTab === 'file'
                                ? 'border-emerald-600 bg-emerald-50/40 ring-1 ring-emerald-500/20'
                                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/60'
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <div 
                                :class="[
                                    'w-9 h-9 rounded flex items-center justify-center shrink-0 border transition-colors',
                                    activeInputTab === 'file' 
                                        ? 'bg-emerald-100 border-emerald-300 text-emerald-800' 
                                        : 'bg-slate-100 border-slate-200 text-slate-500'
                                ]"
                            >
                                <Upload class="w-4 h-4 text-emerald-700" />
                            </div>
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <h3 class="font-bold text-slate-900 text-xs">Unggah Dokumen SIPD</h3>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        Disarankan
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    Pilih file Excel (.xlsx/.xls), PDF (Digital/Scan), Word, atau Foto Scan Kwitansi.
                                </p>
                            </div>
                        </div>

                        <!-- Checkmark Indicator -->
                        <div class="shrink-0">
                            <div 
                                v-if="activeInputTab === 'file'"
                                class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center shadow-xs"
                            >
                                <Check class="w-3.5 h-3.5 stroke-[3]" />
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
                            'p-3.5 rounded border transition-all cursor-pointer flex items-center justify-between gap-3 shadow-sm',
                            activeInputTab === 'paste'
                                ? 'border-emerald-600 bg-emerald-50/40 ring-1 ring-emerald-500/20'
                                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/60'
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <div 
                                :class="[
                                    'w-9 h-9 rounded flex items-center justify-center shrink-0 border transition-colors',
                                    activeInputTab === 'paste' 
                                        ? 'bg-emerald-100 border-emerald-300 text-emerald-800' 
                                        : 'bg-slate-100 border-slate-200 text-slate-500'
                                ]"
                            >
                                <Clipboard class="w-4 h-4 text-slate-600" />
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900 text-xs">Tempel Data (Copy-Paste)</h3>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    Salin dan tempel data tabel subkegiatan dari SIPD atau tabel lain.
                                </p>
                            </div>
                        </div>

                        <!-- Checkmark Indicator -->
                        <div class="shrink-0">
                            <div 
                                v-if="activeInputTab === 'paste'"
                                class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center shadow-xs"
                            >
                                <Check class="w-3.5 h-3.5 stroke-[3]" />
                            </div>
                            <div 
                                v-else
                                class="w-5 h-5 rounded-full border border-slate-300 bg-white"
                            ></div>
                        </div>
                    </div>

                </div>

                <!-- Input Box Method 1: Unggah Dokumen -->
                <div v-if="activeInputTab === 'file'" class="bg-white border border-slate-200 rounded p-4 shadow-sm space-y-4">
                    
                    <input 
                        ref="fileInputRef"
                        type="file" 
                        accept=".xlsx,.xls,.pdf,.docx,.doc,.csv,.txt,.png,.jpg,.jpeg,.webp,.bmp,.tiff" 
                        @change="onFileChange" 
                        class="hidden" 
                    />

                    <!-- Dashed Dropzone Box -->
                    <div 
                        @click="triggerFileInput"
                        class="border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-lg p-6 text-center hover:bg-slate-50/60 transition-colors cursor-pointer group"
                    >
                        <UploadCloud class="w-8 h-8 text-emerald-600 mx-auto mb-2 group-hover:scale-105 transition-transform" />
                        
                        <p class="text-xs font-bold text-slate-800">
                            Klik atau geser file SPJ Fungsional SIPD ke sini
                        </p>

                        <div class="mt-2.5">
                            <button 
                                type="button"
                                @click.stop="triggerFileInput"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded text-xs shadow-xs inline-flex items-center gap-1.5 transition-colors cursor-pointer"
                            >
                                <Upload class="w-3.5 h-3.5" />
                                Pilih File Dokumen
                            </button>
                        </div>

                        <!-- Selected File Preview if chosen -->
                        <div v-if="selectedFile" class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 bg-white border border-emerald-300 rounded shadow-xs">
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

                        <p v-else class="text-[11px] text-slate-400 mt-2">
                            Format didukung: Excel (.xlsx/.xls), PDF (Digital & Scan OCR), Word, CSV, Foto/Scan | Maks. 25 MB
                        </p>
                    </div>

                    <!-- Action Button Row -->
                    <div class="flex items-center justify-between pt-1">
                        <div class="text-[11px] text-slate-500">
                            <span v-if="isProcessing" class="font-medium text-emerald-700 flex items-center gap-1.5">
                                <span class="animate-spin w-3.5 h-3.5 border-2 border-emerald-600 border-t-transparent rounded-full"></span>
                                {{ processingMessage }}
                            </span>
                            <span v-else>RapidOCR otomatis membaca tabel subkegiatan dari PDF hasil scan fisik maupun foto kwitansi/laporan.</span>
                        </div>

                        <button
                            @click="processFileUpload"
                            :disabled="isProcessing || !selectedFile"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded text-xs shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <span v-if="isProcessing" class="animate-spin w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                            <ArrowRight v-else class="w-3.5 h-3.5" />
                            <span>{{ isProcessing ? 'Sedang Membaca...' : 'Mulai Baca Dokumen' }}</span>
                        </button>
                    </div>

                </div>

                <!-- Input Box Method 2: Tempel Data -->
                <div v-else-if="activeInputTab === 'paste'" class="bg-white border border-slate-200 rounded p-4 shadow-sm space-y-3">
                    
                    <div class="p-2.5 bg-emerald-50/70 border border-emerald-200 rounded text-xs text-emerald-900 flex items-start gap-2">
                        <Sparkles class="w-4 h-4 text-emerald-700 shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold">Algoritma Auto-Converter Aktif:</span>
                            Mendukung teks acak, tabel web SIPD Penatausahaan, atau chat. Sistem secara cerdas menyaring kop dinas, mendeteksi kode subkegiatan (misal <code>4.02.01.2.01.0001</code>), anggaran pagu, realisasi SPJ, dan mencocokkan PPTK secara otomatis.
                        </div>
                    </div>

                    <textarea
                        v-model="rawPasteText"
                        rows="7"
                        placeholder="Tempel teks tabel subkegiatan SIPD di sini (Ctrl+V)... Parser otomatis memisahkan kode subkegiatan, nama kegiatan, nominal pagu, dan realisasi SPJ."
                        class="w-full p-2.5 font-mono text-xs border border-slate-300 rounded focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-slate-50/50"
                    ></textarea>

                    <!-- Action Button Row -->
                    <div class="flex items-center justify-between pt-1">
                        <span class="text-[11px] text-slate-500">Mendukung format angka ribuan Indonesia (Rp 1.500.000) dan teks multi-kolom tab-delimited.</span>

                        <button
                            @click="processPasteText"
                            :disabled="isProcessing || !rawPasteText.trim()"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded text-xs shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <span v-if="isProcessing" class="animate-spin w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                            <ArrowRight v-else class="w-3.5 h-3.5" />
                            <span>{{ isProcessing ? 'Sedang Memproses...' : 'Mulai Ekstraksi Teks SIPD' }}</span>
                        </button>
                    </div>

                </div>

            </div>

            <!-- STAGE 3: PERIKSA & SIMPAN (Preview Table) -->
            <div v-else class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden space-y-0">
                
                <!-- Step 3 Action Header -->
                <div class="px-4 py-3 bg-slate-900 text-white flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <button 
                                @click="resetToStepOne"
                                class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded text-xs font-semibold flex items-center gap-1 transition-colors cursor-pointer"
                            >
                                <ArrowLeft class="w-3 h-3" /> Unggah Dokumen Lain
                            </button>
                            <h2 class="text-xs font-bold uppercase tracking-wider flex items-center gap-1.5 text-emerald-400">
                                <CheckCircle2 class="w-4 h-4 text-emerald-400" />
                                Hasil Ekstraksi SIPD ({{ parsedResults.length }} Subkegiatan Terdeteksi)
                            </h2>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">
                            Total Pagu Terdeteksi: <b class="text-white font-mono">{{ formatCurrency(sumParsedPagu) }}</b> &bull; Total SPJ: <b class="text-emerald-400 font-mono">{{ formatCurrency(sumParsedSpj) }}</b>. Periksa dan ubah PPTK atau nominal jika diperlukan.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            @click="commitActivities"
                            class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-bold shadow-sm flex items-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <Check class="w-4 h-4" /> Setujui &amp; Sinkronkan ke Data Pagu
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
                                <th class="min-w-[150px] text-left">Kode Subkegiatan</th>
                                <th class="min-w-[300px] text-left">Nama Subkegiatan</th>
                                <th class="min-w-[150px] text-left">PPTK Penanggung Jawab</th>
                                <th class="min-w-[130px] text-right">Pagu Anggaran (Rp)</th>
                                <th class="min-w-[130px] text-right">Realisasi SPJ SIPD (Rp)</th>
                                <th class="min-w-[130px] text-right">Sisa Pagu (Rp)</th>
                                <th class="w-10 text-center">Hapus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, idx) in parsedResults"
                                :key="item.temp_id"
                                class="hover:bg-slate-50 bg-white"
                            >
                                <td class="text-center">
                                    <input type="checkbox" v-model="item.selected" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                </td>
                                <td class="text-center font-mono text-slate-500">{{ idx + 1 }}</td>
                                <td>
                                    <input type="text" v-model="item.code" class="px-1.5 py-0.5 border border-slate-300 rounded text-xs font-mono w-full font-bold text-slate-900" />
                                </td>
                                <td>
                                    <input type="text" v-model="item.name" class="px-1.5 py-0.5 border border-slate-300 rounded text-xs w-full text-slate-900" />
                                </td>
                                <td>
                                    <select v-model="item.pptk_id" class="px-1.5 py-0.5 border border-slate-300 rounded text-xs w-full">
                                        <option value="">-- Tanpa PPTK --</option>
                                        <option v-for="p in pptks" :key="p.id" :value="p.id">{{ p.display_name }}</option>
                                    </select>
                                </td>
                                <td class="text-right">
                                    <input type="number" v-model="item.pagu_budget" class="px-1.5 py-0.5 border border-slate-300 rounded text-xs text-right font-mono font-bold text-slate-900 w-32" />
                                </td>
                                <td class="text-right">
                                    <input type="number" v-model="item.spj_sipd_total" class="px-1.5 py-0.5 border border-slate-300 rounded text-xs text-right font-mono font-bold text-emerald-700 w-32" />
                                </td>
                                <td class="text-right font-mono font-bold text-slate-700 px-2">
                                    {{ formatCurrency(Number(item.pagu_budget || 0) - Number(item.spj_sipd_total || 0)) }}
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

            <!-- Petunjuk Penggunaan (Harmonized Card) -->
            <div class="p-4 bg-white rounded border border-slate-200 shadow-sm space-y-2 text-xs text-slate-600 leading-relaxed">
                <div class="font-bold text-slate-900 flex items-center gap-1.5">
                    <AlertCircle class="w-4 h-4 text-emerald-600" />
                    <span>Petunjuk Sinkronisasi Pagu dari SIPD:</span>
                </div>
                <ol class="list-decimal pl-5 space-y-1">
                    <li>Buka menu <strong>Laporan Pertanggungjawaban SKPD (SPJ Fungsional)</strong> pada aplikasi <strong>SIPD Penatausahaan</strong> Sekretariat DPRD Kabupaten Banyumas.</li>
                    <li><strong>Metode Dokumen:</strong> Anda dapat mengunggah file hasil ekspor <strong>Excel (.xlsx/.xls)</strong>, file cetak <strong>PDF (Digital / Hasil Scan)</strong>, dokumen Word, atau bahkan <strong>Foto/Scan Kwitansi/Laporan Fisik</strong>. Deep Learning RapidOCR akan mengekstrak tabel otomatis.</li>
                    <li><strong>Metode Tempel Teks:</strong> Cukup sorot (blok) seluruh tabel subkegiatan di layar browser SIPD, salin (<kbd class="px-1 py-0.5 bg-slate-100 border border-slate-300 rounded font-mono text-[10px]">Ctrl+C</kbd>), lalu tempel (<kbd class="px-1 py-0.5 bg-slate-100 border border-slate-300 rounded font-mono text-[10px]">Ctrl+V</kbd>) pada kotak teks.</li>
                </ol>
            </div>
        </div>
    </AppLayout>
</template>
