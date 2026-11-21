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
        <Head title="Impor Transaksi & Pembukuan Otomatis" />

        <div class="space-y-5 max-w-6xl mx-auto">
            
            <!-- 1. Header Banner with Joglo / DPRD Silhouette -->
            <div class="bg-gradient-to-r from-emerald-50 via-green-50/70 to-emerald-100/50 border border-emerald-200/80 rounded-2xl p-6 relative overflow-hidden shadow-xs flex items-center justify-between">
                <div class="relative z-10 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 border border-emerald-200 flex items-center justify-center text-emerald-800 shadow-xs shrink-0">
                        <UploadCloud class="w-7 h-7 text-emerald-700" />
                    </div>
                    <div>
                        <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight">
                            Impor Transaksi
                        </h1>
                        <p class="text-xs md:text-sm text-slate-600 mt-0.5">
                            Unggah file transaksi Anda, dan sistem akan membacanya secara otomatis.
                        </p>
                    </div>
                </div>

                <!-- Subtle DPRD / Joglo Silhouette Vector on Right -->
                <div class="absolute right-0 top-0 bottom-0 w-80 pointer-events-none opacity-20 flex items-center justify-end pr-4 text-emerald-800">
                    <svg viewBox="0 0 200 120" fill="currentColor" class="h-32 w-auto">
                        <!-- Crown / Genteng joglo -->
                        <polygon points="100,5 106,16 94,16" />
                        <polygon points="100,16 160,50 40,50" />
                        <polygon points="100,45 185,75 15,75" />
                        <!-- Pillars / Tiang Pendopo -->
                        <rect x="35" y="75" width="8" height="40" />
                        <rect x="70" y="75" width="8" height="40" />
                        <rect x="96" y="75" width="8" height="40" />
                        <rect x="122" y="75" width="8" height="40" />
                        <rect x="157" y="75" width="8" height="40" />
                        <!-- Base / Lantai -->
                        <rect x="20" y="115" width="160" height="5" rx="2" />
                    </svg>
                </div>
            </div>

            <!-- 2. Stepper Card (Tahapan 1 - 2 - 3) -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
                <div class="flex items-center justify-center max-w-2xl mx-auto gap-2 md:gap-4 text-xs">
                    
                    <!-- Step 1: Pilih Metode -->
                    <div class="flex items-center gap-2">
                        <span 
                            :class="[
                                'w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs transition-all',
                                currentStep >= 1 ? 'bg-emerald-700 text-white shadow-xs' : 'bg-slate-200 text-slate-500'
                            ]"
                        >
                            1
                        </span>
                        <span 
                            :class="[
                                'px-3 py-1 rounded-full font-bold transition-all',
                                currentStep === 1 
                                    ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' 
                                    : 'text-slate-700 font-medium'
                            ]"
                        >
                            Pilih Metode
                        </span>
                    </div>

                    <!-- Connector Line 1 -->
                    <div :class="['h-0.5 w-10 md:w-20 transition-colors', currentStep >= 2 ? 'bg-emerald-600' : 'bg-slate-200']"></div>

                    <!-- Step 2: Unggah File -->
                    <div class="flex items-center gap-2">
                        <span 
                            :class="[
                                'w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs transition-all',
                                currentStep >= 2 
                                    ? 'bg-emerald-700 text-white shadow-xs' 
                                    : 'border-2 border-slate-300 text-slate-500 bg-white'
                            ]"
                        >
                            2
                        </span>
                        <span 
                            :class="[
                                'px-3 py-1 rounded-full font-bold transition-all',
                                currentStep === 2 
                                    ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' 
                                    : 'text-slate-500 font-medium'
                            ]"
                        >
                            {{ activeInputTab === 'file' ? 'Unggah File' : 'Tempel Teks' }}
                        </span>
                    </div>

                    <!-- Connector Line 2 -->
                    <div :class="['h-0.5 w-10 md:w-20 transition-colors', currentStep >= 3 ? 'bg-emerald-600' : 'bg-slate-200']"></div>

                    <!-- Step 3: Periksa & Simpan -->
                    <div class="flex items-center gap-2">
                        <span 
                            :class="[
                                'w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs transition-all',
                                currentStep >= 3 
                                    ? 'bg-emerald-700 text-white shadow-xs' 
                                    : 'border-2 border-slate-300 text-slate-400 bg-white'
                            ]"
                        >
                            3
                        </span>
                        <span 
                            :class="[
                                'px-3 py-1 rounded-full font-bold transition-all',
                                currentStep === 3 
                                    ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' 
                                    : 'text-slate-400 font-medium'
                            ]"
                        >
                            Periksa & Simpan
                        </span>
                    </div>

                </div>
            </div>

            <!-- 3. MAIN WIZARD BODY -->

            <!-- STAGE A: SELECTION & UPLOAD (Step 1 & 2) -->
            <div v-if="currentStep < 3" class="space-y-4">
                
                <!-- Method Selection Cards (2 Column Side-by-Side) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    <!-- Card 1: Unggah File (Disarankan) -->
                    <div 
                        @click="activeInputTab = 'file'"
                        :class="[
                            'p-4 rounded-2xl border-2 transition-all cursor-pointer flex items-center justify-between gap-3 shadow-xs',
                            activeInputTab === 'file'
                                ? 'border-emerald-600 bg-emerald-50/50 ring-2 ring-emerald-500/20'
                                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50'
                        ]"
                    >
                        <div class="flex items-center gap-3.5">
                            <div 
                                :class="[
                                    'w-11 h-11 rounded-xl flex items-center justify-center shrink-0 border transition-colors',
                                    activeInputTab === 'file' 
                                        ? 'bg-emerald-100 border-emerald-300 text-emerald-800' 
                                        : 'bg-slate-100 border-slate-200 text-slate-500'
                                ]"
                            >
                                <Upload class="w-5 h-5 text-emerald-700" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-bold text-slate-900 text-sm">Unggah File</h3>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        Disarankan
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Pilih file transaksi dari komputer Anda.
                                </p>
                            </div>
                        </div>

                        <!-- Checkmark Indicator -->
                        <div class="shrink-0">
                            <div 
                                v-if="activeInputTab === 'file'"
                                class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center shadow-xs"
                            >
                                <Check class="w-3.5 h-3.5 stroke-[3]" />
                            </div>
                            <div 
                                v-else
                                class="w-6 h-6 rounded-full border-2 border-slate-300 bg-white"
                            ></div>
                        </div>
                    </div>

                    <!-- Card 2: Tempel Data -->
                    <div 
                        @click="activeInputTab = 'paste'"
                        :class="[
                            'p-4 rounded-2xl border-2 transition-all cursor-pointer flex items-center justify-between gap-3 shadow-xs',
                            activeInputTab === 'paste'
                                ? 'border-emerald-600 bg-emerald-50/50 ring-2 ring-emerald-500/20'
                                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50'
                        ]"
                    >
                        <div class="flex items-center gap-3.5">
                            <div 
                                :class="[
                                    'w-11 h-11 rounded-xl flex items-center justify-center shrink-0 border transition-colors',
                                    activeInputTab === 'paste' 
                                        ? 'bg-emerald-100 border-emerald-300 text-emerald-800' 
                                        : 'bg-slate-100 border-slate-200 text-slate-500'
                                ]"
                            >
                                <Clipboard class="w-5 h-5 text-slate-600" />
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900 text-sm">Tempel Data</h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Salin dan tempel data transaksi dari Excel atau tabel.
                                </p>
                            </div>
                        </div>

                        <!-- Checkmark Indicator -->
                        <div class="shrink-0">
                            <div 
                                v-if="activeInputTab === 'paste'"
                                class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center shadow-xs"
                            >
                                <Check class="w-3.5 h-3.5 stroke-[3]" />
                            </div>
                            <div 
                                v-else
                                class="w-6 h-6 rounded-full border-2 border-slate-300 bg-white"
                            ></div>
                        </div>
                    </div>

                </div>

                <!-- Input Box Method 1: Unggah File -->
                <div v-if="activeInputTab === 'file'" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-5">
                    
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
                        class="border-2 border-dashed border-emerald-400/80 rounded-2xl p-8 md:p-10 text-center bg-emerald-50/10 hover:bg-emerald-50/30 transition-all cursor-pointer group"
                    >
                        <div class="w-14 h-14 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center justify-center mx-auto mb-3 group-hover:scale-105 transition-transform">
                            <UploadCloud class="w-8 h-8 text-emerald-600" />
                        </div>
                        
                        <p class="text-sm md:text-base font-bold text-slate-800">
                            Pilih file transaksi dari komputer Anda
                        </p>

                        <div class="mt-3">
                            <button 
                                type="button"
                                @click.stop="triggerFileInput"
                                class="px-6 py-2.5 bg-emerald-800 hover:bg-emerald-900 text-white font-bold rounded-xl text-xs shadow-xs inline-flex items-center gap-2 transition-all cursor-pointer"
                            >
                                <Upload class="w-3.5 h-3.5" />
                                Pilih File
                            </button>
                        </div>

                        <!-- Selected File Preview if chosen -->
                        <div v-if="selectedFile" class="mt-4 inline-flex items-center gap-3 px-4 py-2 bg-white border border-emerald-300 rounded-xl shadow-xs">
                            <span :class="['px-2 py-0.5 rounded text-[10px] font-bold border', getFileBadgeColor(selectedFile.name)]">
                                {{ selectedFile.name.split('.').pop().toUpperCase() }}
                            </span>
                            <span class="text-xs font-bold text-slate-800 font-mono">{{ selectedFile.name }}</span>
                            <span class="text-[11px] text-slate-500">({{ (selectedFile.size / 1024).toFixed(1) }} KB)</span>
                            <button 
                                type="button" 
                                @click.stop="removeSelectedFile" 
                                class="text-slate-400 hover:text-rose-600 p-0.5 rounded-full hover:bg-slate-100 transition-colors"
                                title="Batal pilih file"
                            >
                                <X class="w-4 h-4" />
                            </button>
                        </div>

                        <p v-else class="text-[11px] text-slate-400 mt-3 font-medium">
                            Format: PDF, Excel, Word, CSV, Foto | Maks. 25 MB
                        </p>
                    </div>

                    <!-- CTA Action Button: Mulai Baca File -->
                    <div class="text-center pt-1">
                        <button
                            @click="processFileUpload"
                            :disabled="isProcessing || !selectedFile"
                            class="w-full max-w-md mx-auto py-3.5 px-8 bg-emerald-800 hover:bg-emerald-900 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl text-sm shadow-md flex items-center justify-center gap-2 transition-all cursor-pointer"
                        >
                            <span v-if="isProcessing" class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
                            <ArrowRight v-else class="w-4 h-4" />
                            <span>{{ isProcessing ? processingMessage : 'Mulai Baca File' }}</span>
                        </button>
                    </div>

                </div>

                <!-- Input Box Method 2: Tempel Data -->
                <div v-else-if="activeInputTab === 'paste'" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4">
                    
                    <div class="p-3 bg-emerald-50/70 border border-emerald-200 rounded-xl text-xs text-emerald-900 flex items-start gap-2.5">
                        <Sparkles class="w-4 h-4 text-emerald-700 shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold">Algoritma Auto-Converter Aktif:</span>
                            Mendukung teks acak, tabel web SIPD RI, mutasi CMS Bank Jateng, atau chat WhatsApp. Sistem secara cerdas menyaring kop dinas, menyatukan baris terpotong, serta mengekstrak nomor dokumen, tanggal, uraian, PPTK, dan nominal.
                        </div>
                    </div>

                    <textarea
                        v-model="rawPasteText"
                        rows="7"
                        placeholder="Tempel teks tabel transaksi di sini (Ctrl+V)... Parser akan otomatis membersihkan header, menyatukan baris berantakan, mendeteksi nominal, dan memetakan PPTK."
                        class="w-full p-3 font-mono text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-slate-50/50"
                    ></textarea>

                    <div class="flex items-center justify-between text-[11px] text-slate-500">
                        <span>Mendukung multi-transaksi, tab-delimited, format tanggal Indonesia (15 Oktober 2026), dan mutasi debet/kredit.</span>
                    </div>

                    <!-- CTA Action Button: Mulai Ekstraksi Teks -->
                    <div class="text-center pt-2">
                        <button
                            @click="processPasteText"
                            :disabled="isProcessing || !rawPasteText.trim()"
                            class="w-full max-w-md mx-auto py-3.5 px-8 bg-emerald-800 hover:bg-emerald-900 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl text-sm shadow-md flex items-center justify-center gap-2 transition-all cursor-pointer"
                        >
                            <span v-if="isProcessing" class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
                            <ArrowRight v-else class="w-4 h-4" />
                            <span>{{ isProcessing ? processingMessage : 'Mulai Ekstraksi Teks' }}</span>
                        </button>
                    </div>

                </div>

            </div>

            <!-- STAGE B: STEP 3 - VERIFIKASI & SIMPAN (When Transactions Parsed) -->
            <div v-else class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden space-y-0">
                
                <!-- Step 3 Action Header -->
                <div class="px-5 py-4 bg-slate-900 text-white flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <button 
                                @click="resetToStepOne"
                                class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg text-xs font-semibold flex items-center gap-1 transition-colors cursor-pointer"
                            >
                                <ArrowLeft class="w-3.5 h-3.5" /> Unggah File Lain
                            </button>
                            <h2 class="text-sm font-bold uppercase tracking-wider flex items-center gap-1.5 text-emerald-400">
                                <CheckCircle2 class="w-4 h-4 text-emerald-400" />
                                Hasil Ekstraksi Transaksi ({{ parsedResults.length }} Transaksi Ditemukan)
                            </h2>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">
                            Periksa hasil ekstraksi sebelum dibukukan. Anda dapat mengubah PPTK, arah arus kas, nominal, atau mengecualikan transaksi.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="parsedResults.some(r => r.is_duplicate)"
                            @click="removeDuplicateItems"
                            type="button"
                            class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                            title="Hapus semua baris yang terdeteksi dobel dari daftar impor"
                        >
                            <Trash2 class="w-3.5 h-3.5" /> Hapus {{ parsedResults.filter(r => r.is_duplicate).length }} Baris Dobel
                        </button>
                        <button
                            @click="commitTransactions"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm flex items-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <Check class="w-4 h-4" /> Setujui & Simpan ke Pembukuan Aktif
                        </button>
                    </div>
                </div>

                <!-- Preview Table -->
                <div class="overflow-x-auto excel-scroll p-3">
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

            <!-- 4. Riwayat Impor Terakhir (Matching Mockup Design) -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                <div class="px-5 py-3.5 bg-white border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Clock class="w-5 h-5 text-emerald-600" />
                        <h2 class="text-sm md:text-base font-bold text-slate-900">
                            Riwayat Impor Terakhir
                        </h2>
                    </div>
                    <span class="text-xs font-semibold text-slate-500 hover:text-emerald-700 cursor-pointer flex items-center gap-1 transition-colors">
                        Lihat Semua <ChevronRight class="w-3.5 h-3.5" />
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-emerald-50/60 text-slate-700 font-semibold border-b border-emerald-100/80">
                                <th class="px-4 py-2.5 text-left">Nama File</th>
                                <th class="px-4 py-2.5 text-left">Tanggal Impor</th>
                                <th class="px-4 py-2.5 text-center">Jumlah Transaksi</th>
                                <th class="px-4 py-2.5 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="recent_batches.length === 0">
                                <td colspan="4" class="px-4 py-6 text-center text-slate-400 text-xs">
                                    Belum ada riwayat impor transaksi sebelumnya.
                                </td>
                            </tr>
                            <tr v-for="b in recent_batches" :key="b.id" class="hover:bg-slate-50/80 transition-colors">
                                <!-- Nama File with Format Badge -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <span 
                                            :class="[
                                                'w-6 h-6 rounded flex items-center justify-center font-bold text-[10px] font-mono shrink-0',
                                                b.original_filename?.endsWith('.xlsx') || b.original_filename?.endsWith('.xls') || b.import_type === 'excel_historical'
                                                    ? 'bg-emerald-600 text-white'
                                                    : (b.original_filename?.endsWith('.pdf') ? 'bg-rose-600 text-white' : 'bg-blue-600 text-white')
                                            ]"
                                        >
                                            {{ getFileIconType(b.original_filename) }}
                                        </span>
                                        <span class="font-bold text-slate-800">
                                            {{ b.original_filename || (b.import_type === 'paste_text' ? 'Tempel Teks SIPD' : 'Dokumen Impor') }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Tanggal Impor -->
                                <td class="px-4 py-3 text-slate-600 font-medium">
                                    {{ formatDateIndo(b.created_at) }}
                                </td>

                                <!-- Jumlah Transaksi -->
                                <td class="px-4 py-3 text-center font-mono font-bold text-slate-800 text-sm">
                                    {{ Number(b.parsed_count || 0).toLocaleString('id-ID') }}
                                </td>

                                <!-- Status Badge -->
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center gap-1 px-3 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        <Check class="w-3 h-3 stroke-[2.5]" /> Berhasil
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
