<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    FileSpreadsheet,
    UploadCloud,
    CheckCircle2,
    AlertCircle,
    ArrowLeft,
    Loader2
} from 'lucide-vue-next';

const props = defineProps({
    year: Number,
    total_activities: Number,
    total_pagu: Number,
    total_spj: Number,
});

const formatCurrency = (val) => {
    return 'Rp ' + Number(val || 0).toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
};

const form = useForm({
    file: null,
});

const isDragging = ref(false);
const fileName = ref('');

const onFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.file = file;
        fileName.value = file.name;
    }
};

const onDrop = (e) => {
    isDragging.value = false;
    const file = e.dataTransfer.files[0];
    if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.xls'))) {
        form.file = file;
        fileName.value = file.name;
    }
};

const submit = () => {
    if (!form.file) return;
    form.post('/pagu/impor');
};
</script>

<template>
    <AppLayout>
        <Head title="Sinkronisasi Data Pagu dari SIPD" />

        <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <Link
                        href="/pagu"
                        class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-emerald-600 font-bold mb-2 transition-colors"
                    >
                        <ArrowLeft class="w-4 h-4" />
                        <span>Kembali ke Pantauan Pagu</span>
                    </Link>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900">
                        Sinkronisasi Data Pagu SIPD Fungsional
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                        Unggah file Excel ekspor SPJ Fungsional dari SIPD Penatausahaan untuk memperbarui data pagu anggaran dan SPJ resmi.
                    </p>
                </div>
            </div>

            <!-- Current Status Cards (White Cards) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs">
                    <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Subkegiatan Aktif</span>
                    <div class="text-2xl font-black text-slate-900 font-sans tracking-tight mt-1.5">
                        {{ total_activities }} Subkegiatan
                    </div>
                </div>

                <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs">
                    <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Pagu Terdaftar</span>
                    <div class="text-2xl font-black text-slate-900 font-sans tabular-nums tracking-tight mt-1.5">
                        {{ formatCurrency(total_pagu) }}
                    </div>
                </div>

                <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs">
                    <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">SPJ Resmi SIPD</span>
                    <div class="text-2xl font-black text-slate-900 font-sans tabular-nums tracking-tight mt-1.5">
                        {{ formatCurrency(total_spj) }}
                    </div>
                </div>
            </div>

            <!-- Upload Box Card (White Card) -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-xs space-y-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="onDrop"
                        class="border-2 border-dashed rounded-3xl p-8 text-center transition-all cursor-pointer flex flex-col items-center justify-center gap-3"
                        :class="isDragging ? 'border-emerald-500 bg-emerald-50/50' : 'border-slate-300 hover:border-emerald-500 bg-slate-50/60 hover:bg-slate-50'"
                        @click="$refs.fileInput.click()"
                    >
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".xlsx,.xls"
                            class="hidden"
                            @change="onFileChange"
                        />

                        <div class="p-4 bg-emerald-100 text-emerald-700 rounded-2xl">
                            <UploadCloud class="w-8 h-8" />
                        </div>

                        <div>
                            <div class="text-sm font-bold text-slate-800">
                                {{ fileName || 'Klik atau geser file Excel SPJ Fungsional ke sini' }}
                            </div>
                            <div class="text-xs text-slate-400 mt-1">
                                Format file didukung: .xlsx atau .xls (Maksimal 20 MB)
                            </div>
                        </div>

                        <div v-if="fileName" class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold">
                            <CheckCircle2 class="w-3.5 h-3.5" />
                            <span>File siap disinkronkan</span>
                        </div>
                    </div>

                    <div v-if="form.errors.file" class="p-3 bg-red-50 text-red-600 rounded-xl text-xs font-medium">
                        {{ form.errors.file }}
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <Link
                            href="/pagu"
                            class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-colors"
                        >
                            Batal
                        </Link>
                        <button
                            type="submit"
                            :disabled="!form.file || form.processing"
                            class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-xs transition-colors inline-flex items-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                            <span>{{ form.processing ? 'Menyinkronkan...' : 'Mulai Sinkronisasi SIPD' }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Petunjuk Penggunaan (White Card) -->
            <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-2 text-xs text-slate-600 leading-relaxed">
                <div class="font-bold text-slate-900 flex items-center gap-1.5">
                    <AlertCircle class="w-4 h-4 text-emerald-600" />
                    <span>Petunjuk Ekspor dari SIPD:</span>
                </div>
                <ol class="list-decimal pl-5 space-y-1">
                    <li>Buka aplikasi <strong>SIPD Penatausahaan</strong> Sekretariat DPRD Kabupaten Banyumas.</li>
                    <li>Masuk ke menu <strong>Laporan Pertanggungjawaban SKPD (SPJ Fungsional)</strong>.</li>
                    <li>Pilih periode bulan berjalan lalu unduh/ekspor dalam format <strong>Excel (.xlsx)</strong> (contoh file: <code>SPJ_Fungsional_November_2026.xlsx</code>).</li>
                    <li>Unggah file tersebut di form atas. Sistem akan membaca Sheet <code>2 Program dan Subkegiatan</code> dan otomatis mengupdate pagu serta realisasi SPJ resmi.</li>
                </ol>
            </div>
        </div>
    </AppLayout>
</template>
