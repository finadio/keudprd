<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    Settings, 
    Building2, 
    Users, 
    Lock, 
    Unlock, 
    ShieldCheck, 
    Check, 
    History,
    Save,
    Archive,
    CalendarPlus,
    Database,
    Trash2,
    ArrowRight,
    KeyRound
} from 'lucide-vue-next';

const props = defineProps({
    setting: Object,
    pptks: Array,
    periods: Array,
    audit_logs: Array,
    active_year: Number,
    default_year: Number,
    available_years: Array,
    year_summaries: Array,
    storage_stats: Object,
});

const form = ref({
    skpd_name: props.setting?.skpd_name || 'SETWAN KABUPATEN BANYUMAS',
    pa_kpa_name: props.setting?.pa_kpa_name || 'ARIF TRIYANTO, S.Sos. M.Eng.',
    pa_kpa_nip: props.setting?.pa_kpa_nip || '19680430 199002 1 003',
    treasurer_name: props.setting?.treasurer_name || 'INDAH SETIYOWATI, A.Md.AB.',
    treasurer_nip: props.setting?.treasurer_nip || '19850615 201001 2 021',
    fiscal_year: props.setting?.fiscal_year || props.active_year || 2026,
});

const newYearForm = ref({
    new_year: (props.default_year || 2026) + 1,
    lock_previous_year: true,
});

const isOpeningNewYear = ref(false);

const saveSettings = () => {
    router.post('/pengaturan/master', form.value);
};

const toggleLock = (periodId) => {
    router.post(`/pengaturan/period/${periodId}/toggle-lock`, {}, { preserveScroll: true });
};

const switchToYear = (yr) => {
    router.post('/tahun-anggaran/pilih', {
        year: yr,
        current_url: '/pengaturan',
    });
};

const openNewFiscalYear = () => {
    const targetYr = Number(newYearForm.value.new_year);
    if (!targetYr || targetYr < 2020 || targetYr > 2100) return;

    isOpeningNewYear.value = true;
    router.post('/pengaturan/tahun-baru', newYearForm.value, {
        onFinish: () => {
            isOpeningNewYear.value = false;
        },
    });
};

const pruneOldBackups = () => {
    router.post('/pengaturan/bersihkan-backup', {}, { preserveScroll: true });
};

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submitPasswordChange = () => {
    passwordForm.post('/pengaturan/ganti-password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};

const formatRupiah = (val) => {
    return 'Rp ' + Number(val || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};
</script>

<template>
    <AppLayout>
        <Head title="Pengaturan & Master Data" />

        <div class="space-y-4">
            
            <!-- Page Title -->
            <div class="bg-white border border-slate-200 rounded p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                    <h1 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <Settings class="w-5 h-5 text-slate-700" />
                        Pengaturan Sistem & Data Master Keuangan
                    </h1>
                    <p class="text-xs text-slate-500">
                        Kelola identitas instansi, pejabat berwenang (PA/Bendahara), master 9 PPTK, penguncian periode buku, dan rekam jejak audit (*audit trail*).
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a 
                        :href="`/laporan/backup-zip?year=${active_year || form.fiscal_year || 2026}`"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#04331a] hover:bg-emerald-800 text-white rounded text-xs font-bold transition-colors shadow-2xs"
                        title="Unduh seluruh arsip pembukuan Excel & database dalam bentuk ZIP"
                    >
                        <Archive class="w-4 h-4 text-emerald-300" />
                        <span>Cadangkan ZIP (TA {{ active_year || form.fiscal_year || 2026 }})</span>
                    </a>
                </div>
            </div>

            <!-- Multi-Year Fiscal Switcher & Storage Capacity Guard -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Card: Pergantian Tahun Anggaran & Arsip Tahun Lama -->
                <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-emerald-950 text-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <CalendarPlus class="w-4 h-4 text-emerald-300" />
                            <h2 class="text-xs font-bold uppercase tracking-wide">Manajemen Multi-Tahun Anggaran &amp; Arsip</h2>
                        </div>
                        <span class="px-2 py-0.5 bg-emerald-800 text-emerald-200 rounded text-[10px] font-mono font-bold">
                            Aktif: TA {{ default_year || 2026 }}
                        </span>
                    </div>

                    <div class="p-4 space-y-4 text-xs">
                        <!-- Daftar Tahun Tersimpan -->
                        <div class="overflow-x-auto border border-slate-200 rounded">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Tahun</th>
                                        <th class="px-3 py-2 text-right">Transaksi</th>
                                        <th class="px-3 py-2 text-center">Bulan Dikunci</th>
                                        <th class="px-3 py-2 text-center">Status</th>
                                        <th class="px-3 py-2 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="ys in (year_summaries || [])" :key="ys.year" class="hover:bg-slate-50">
                                        <td class="px-3 py-2 font-mono font-bold text-slate-900">TA {{ ys.year }}</td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-700">{{ ys.tx_count.toLocaleString('id-ID') }} baris</td>
                                        <td class="px-3 py-2 text-center font-mono">
                                            <span :class="ys.closed_months === 12 ? 'text-amber-700 font-bold' : 'text-slate-600'">
                                                {{ ys.closed_months }} / {{ ys.total_months || 12 }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span v-if="ys.is_default" class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-bold">
                                                TA Aktif
                                            </span>
                                            <span v-else class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-[10px] font-semibold">
                                                Arsip
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <button
                                                type="button"
                                                @click="switchToYear(ys.year)"
                                                :disabled="ys.year === active_year"
                                                :class="[
                                                    'inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-bold transition-colors',
                                                    ys.year === active_year
                                                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 cursor-default'
                                                        : 'bg-white hover:bg-emerald-600 hover:text-white text-slate-700 border border-slate-300 cursor-pointer'
                                                ]"
                                            >
                                                <span>{{ ys.year === active_year ? 'Sedang Dibuka' : 'Buka Data' }}</span>
                                                <ArrowRight v-if="ys.year !== active_year" class="w-3 h-3" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Form Buka Tahun Anggaran Baru -->
                        <form @submit.prevent="openNewFiscalYear" class="p-3 bg-emerald-50/70 border border-emerald-200 rounded-lg space-y-2.5">
                            <div class="font-bold text-emerald-950">Buka Tahun Anggaran Baru (Data Tahun Lama Tetap Tersimpan Aman)</div>
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="w-32">
                                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">Tahun Baru</label>
                                    <input
                                        v-model.number="newYearForm.new_year"
                                        type="number"
                                        min="2020"
                                        max="2100"
                                        required
                                        class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded text-xs font-mono font-bold"
                                    />
                                </div>
                                <label class="flex items-center gap-2 text-[11px] text-slate-700 cursor-pointer pb-1.5 flex-1 min-w-[220px]">
                                    <input
                                        v-model="newYearForm.lock_previous_year"
                                        type="checkbox"
                                        class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                    />
                                    <span>Otomatis kunci 12 bulan TA {{ default_year || 2026 }} sebagai arsip <i>read-only</i></span>
                                </label>
                                <button
                                    type="submit"
                                    :disabled="isOpeningNewYear"
                                    class="px-3.5 py-1.5 bg-[#107c41] hover:bg-[#0c6334] disabled:opacity-50 text-white rounded font-bold text-xs shadow-2xs transition-colors cursor-pointer"
                                >
                                    {{ isOpeningNewYear ? 'Memproses...' : 'Buka Tahun Baru' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Card: Pemantauan Kapasitas Penyimpanan Jangka Panjang -->
                <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Database class="w-4 h-4 text-blue-600" />
                            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wide">Kapasitas Penyimpanan Jangka Panjang</h2>
                        </div>
                        <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                            Status: Aman &amp; Ringan
                        </span>
                    </div>

                    <div class="p-4 space-y-3 text-xs">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg">
                                <div class="text-[11px] text-slate-500 font-medium">Total Transaksi (Semua Tahun)</div>
                                <div class="text-base font-black text-slate-900 font-mono mt-0.5">
                                    {{ (storage_stats?.total_transactions || 0).toLocaleString('id-ID') }}
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">
                                    TA {{ active_year }}: {{ (storage_stats?.active_year_transactions || 0).toLocaleString('id-ID') }} baris
                                </div>
                            </div>

                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg">
                                <div class="text-[11px] text-slate-500 font-medium">Ukuran Database Aktif</div>
                                <div class="text-base font-black text-emerald-800 font-mono mt-0.5">
                                    {{ storage_stats?.db_size_mb !== null && storage_stats?.db_size_mb !== undefined ? storage_stats.db_size_mb + ' MB' : 'Tersedia' }}
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">
                                    Estimasi ~2-3 MB per tahun
                                </div>
                            </div>

                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg col-span-2 sm:col-span-1">
                                <div class="text-[11px] text-slate-500 font-medium">File ZIP di Server</div>
                                <div class="text-base font-black text-blue-800 font-mono mt-0.5">
                                    {{ storage_stats?.backup_files_count || 0 }} File ({{ storage_stats?.backup_size_mb || 0 }} MB)
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">
                                    Auto-simpan maks {{ storage_stats?.backup_keep_limit || 5 }} terbaru
                                </div>
                            </div>
                        </div>

                        <div class="p-3 bg-blue-50/70 border border-blue-200 rounded-lg text-[11px] text-blue-950 leading-relaxed">
                            <strong>Mengapa aman jangka panjang?</strong> Tabel transaksi telah diindeks berdasarkan tahun anggaran (`period_year`), sehingga menyimpan 10–20 tahun data hanya memakan ±30–60 MB dan tidak memperlambat aplikasi. File cadangan ZIP otomatis dipangkas agar hanya menyimpan <b>5 arsip terbaru</b> di server.
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="button"
                                @click="pruneOldBackups"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 rounded text-xs font-semibold transition-colors cursor-pointer"
                            >
                                <Trash2 class="w-3.5 h-3.5 text-slate-500" />
                                <span>Bersihkan File ZIP Lama Sekarang</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab / Cards Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                
                <!-- Card 1: Konfigurasi Identitas SKPD & Pejabat -->
                <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center gap-2">
                        <Building2 class="w-4 h-4 text-emerald-600" />
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wide">Identitas SKPD & Pejabat Berwenang</h2>
                    </div>

                    <form @submit.prevent="saveSettings" class="p-4 space-y-3 text-xs">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Nama SKPD</label>
                            <input v-model="form.skpd_name" type="text" required class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs uppercase" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Tahun Anggaran</label>
                                <input v-model="form.fiscal_year" type="number" required class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs font-mono" />
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Status Pembukuan</label>
                                <div class="px-2.5 py-1.5 bg-emerald-50 border border-emerald-300 text-emerald-800 rounded font-semibold text-xs flex items-center gap-1.5">
                                    <ShieldCheck class="w-4 h-4" /> Aktif
                                </div>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-slate-100">
                            <label class="block font-semibold text-slate-700 mb-1">Pengguna Anggaran (PA / Sekwan) Aktif</label>
                            <input v-model="form.pa_kpa_name" type="text" required class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs" />
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">NIP Pengguna Anggaran</label>
                            <input v-model="form.pa_kpa_nip" type="text" class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs font-mono" />
                        </div>

                        <div class="pt-2 border-t border-slate-100">
                            <label class="block font-semibold text-slate-700 mb-1">Bendahara Pengeluaran</label>
                            <input v-model="form.treasurer_name" type="text" required class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs" />
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">NIP Bendahara Pengeluaran</label>
                            <input v-model="form.treasurer_nip" type="text" class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs font-mono" />
                        </div>

                        <div class="pt-3 flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-bold shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer">
                                <Save class="w-4 h-4" /> Simpan Konfigurasi
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Card 2: Penguncian Periode Buku (Lock Periods) -->
                <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Lock class="w-4 h-4 text-amber-600" />
                            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wide">Penguncian Periode Pembukuan (TA {{ active_year || form.fiscal_year }})</h2>
                        </div>
                        <span class="text-[11px] text-slate-500">Mencegah perubahan data pada bulan yang sudah ditutup</span>
                    </div>

                    <div class="p-3 overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-slate-100 text-slate-700 font-semibold border-b border-slate-200">
                                <tr>
                                    <th class="px-3 py-1.5 text-left">Bulan</th>
                                    <th class="px-3 py-1.5 text-center">Status Periode</th>
                                    <th class="px-3 py-1.5 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="p in periods" :key="p.id" class="hover:bg-slate-50">
                                    <td class="px-3 py-1.5 font-medium text-slate-800">{{ p.name }}</td>
                                    <td class="px-3 py-1.5 text-center">
                                        <span 
                                            v-if="p.is_closed" 
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 font-mono"
                                        >
                                            <Lock class="w-3 h-3" /> DIKUNCI
                                        </span>
                                        <span 
                                            v-else 
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 font-mono"
                                        >
                                            <Unlock class="w-3 h-3" /> TERBUKA
                                        </span>
                                    </td>
                                    <td class="px-3 py-1.5 text-center">
                                        <button 
                                            @click="toggleLock(p.id)"
                                            :class="[
                                                'px-2 py-1 rounded text-[11px] font-semibold transition-colors cursor-pointer',
                                                p.is_closed
                                                    ? 'bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300'
                                                    : 'bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-300'
                                            ]"
                                        >
                                            {{ p.is_closed ? 'Buka Kunci' : 'Kunci Periode' }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Card 3: Keamanan Akun & Ganti Password -->
            <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <KeyRound class="w-4 h-4 text-rose-600" />
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wide">Keamanan Akun &amp; Ubah Password Login</h2>
                    </div>
                    <span class="text-[11px] text-rose-700 font-semibold">Wajib diganti sebelum diakses publik</span>
                </div>

                <form @submit.prevent="submitPasswordChange" class="p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end text-xs">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Password Saat Ini</label>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs"
                        />
                        <p v-if="passwordForm.errors.current_password" class="mt-1 text-[11px] text-rose-600 font-medium">
                            {{ passwordForm.errors.current_password }}
                        </p>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Password Baru (Min. 8 Karakter)</label>
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Minimal 8 karakter"
                            class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs"
                        />
                        <p v-if="passwordForm.errors.password" class="mt-1 text-[11px] text-rose-600 font-medium">
                            {{ passwordForm.errors.password }}
                        </p>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Konfirmasi Password Baru</label>
                        <input
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Ulangi password baru"
                            class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs"
                        />
                    </div>
                    <div>
                        <button
                            type="submit"
                            :disabled="passwordForm.processing"
                            class="w-full px-4 py-1.5 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white rounded font-bold text-xs shadow-xs transition-colors cursor-pointer"
                        >
                            {{ passwordForm.processing ? 'Menyimpan...' : 'Simpan Password Baru' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Card 4: Master 9 PPTK List -->
            <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Users class="w-4 h-4 text-blue-600" />
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wide">Daftar 9 PPTK Sekretariat DPRD Banyumas</h2>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-100 text-slate-700 font-semibold border-b border-slate-200">
                            <tr>
                                <th class="px-3 py-2 text-center w-12">No</th>
                                <th class="px-3 py-2 text-left">Kode</th>
                                <th class="px-3 py-2 text-left">Label Sheet Excel</th>
                                <th class="px-3 py-2 text-left">Nama Lengkap & Gelar</th>
                                <th class="px-3 py-2 text-left">Sheet BKU Terkait</th>
                                <th class="px-3 py-2 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(p, idx) in pptks" :key="p.id" class="hover:bg-slate-50">
                                <td class="px-3 py-2 text-center font-mono text-slate-500">{{ idx + 1 }}</td>
                                <td class="px-3 py-2 font-mono font-semibold text-slate-800">{{ p.code }}</td>
                                <td class="px-3 py-2 font-bold text-emerald-800">{{ p.display_name }}</td>
                                <td class="px-3 py-2 text-slate-900 font-medium">{{ p.name }}</td>
                                <td class="px-3 py-2 font-mono text-blue-700 underline">
                                    <Link :href="`/bku?tab=${p.sheet_name}`">{{ p.sheet_name }}</Link>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Aktif</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card 4: Audit Trail -->
            <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <History class="w-4 h-4 text-purple-600" />
                        <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wide">Rekam Jejak Audit Keamanan (*Audit Trail*)</h2>
                    </div>
                    <span class="text-[11px] text-slate-500">Merekam setiap aksi pembuatan, koreksi, dan impor</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-100 text-slate-700 font-semibold border-b border-slate-200">
                            <tr>
                                <th class="px-3 py-2 text-left">Waktu</th>
                                <th class="px-3 py-2 text-left">Aksi</th>
                                <th class="px-3 py-2 text-left">Entitas</th>
                                <th class="px-3 py-2 text-left">Pengguna</th>
                                <th class="px-3 py-2 text-left">IP Address</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="log in audit_logs" :key="log.id" class="hover:bg-slate-50">
                                <td class="px-3 py-2 font-mono text-slate-600">{{ new Date(log.created_at).toLocaleString('id-ID') }}</td>
                                <td class="px-3 py-2">
                                    <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold uppercase bg-slate-100 text-slate-800">
                                        {{ log.action }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-medium text-slate-800">{{ log.entity_type }} #{{ log.entity_id || '-' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ log.user?.name || 'Sistem / Operator' }}</td>
                                <td class="px-3 py-2 font-mono text-slate-500">{{ log.ip_address || '127.0.0.1' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
