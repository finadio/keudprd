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
    KeyRound,
    Plus,
    Pencil,
    X,
    AlertTriangle
} from 'lucide-vue-next';

const props = defineProps({
    setting: Object,
    pptks: Array,
    subunits: Array,
    users: Array,
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

// Manajemen PPTK (Tambah, Edit, Hapus, Toggle Aktif)
const showPptkModal = ref(false);
const isEditingPptk = ref(false);
const showDeletePptkModal = ref(false);
const pptkToDelete = ref(null);

const pptkForm = useForm({
    id: null,
    code: '',
    name: '',
    display_name: '',
    nip: '',
    subunit_id: '',
    sheet_name: '',
    color_tag: '#04331a',
    sort_order: 1,
    is_active: true,
});

const openCreatePptkModal = () => {
    isEditingPptk.value = false;
    pptkForm.reset();
    pptkForm.clearErrors();
    pptkForm.id = null;
    pptkForm.code = '';
    pptkForm.name = '';
    pptkForm.display_name = '';
    pptkForm.nip = '';
    pptkForm.sheet_name = '';
    pptkForm.color_tag = '#04331a';
    pptkForm.sort_order = (props.pptks?.length || 0) + 1;
    pptkForm.is_active = true;
    pptkForm.subunit_id = props.subunits?.[0]?.id || '';
    showPptkModal.value = true;
};

const openEditPptkModal = (pptk) => {
    isEditingPptk.value = true;
    pptkForm.clearErrors();
    pptkForm.id = pptk.id;
    pptkForm.code = pptk.code || '';
    pptkForm.display_name = pptk.display_name || pptk.name || '';
    pptkForm.name = pptkForm.display_name;
    pptkForm.nip = pptk.nip || '';
    pptkForm.subunit_id = pptk.subunit_id || '';
    pptkForm.sheet_name = pptk.sheet_name || '';
    pptkForm.color_tag = pptk.color_tag || '#04331a';
    pptkForm.sort_order = pptk.sort_order ?? 0;
    pptkForm.is_active = Boolean(pptk.is_active);
    showPptkModal.value = true;
};

const onDisplayNameInput = () => {
    pptkForm.name = pptkForm.display_name;
    if (!isEditingPptk.value) {
        if (!pptkForm.sheet_name || pptkForm.sheet_name === pptkForm.display_name.slice(0, -1)) {
            pptkForm.sheet_name = pptkForm.display_name;
        }
        if (!pptkForm.code) {
            pptkForm.code = pptkForm.display_name.toUpperCase().replace(/[^A-Z0-9]/g, '_');
        }
    }
};

const submitPptkForm = () => {
    if (isEditingPptk.value && pptkForm.id) {
        pptkForm.put(`/pengaturan/pptk/${pptkForm.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showPptkModal.value = false;
            },
        });
    } else {
        pptkForm.post('/pengaturan/pptk', {
            preserveScroll: true,
            onSuccess: () => {
                showPptkModal.value = false;
            },
        });
    }
};

const confirmDeletePptk = (pptk) => {
    pptkToDelete.value = pptk;
    showDeletePptkModal.value = true;
};

const executeDeletePptk = () => {
    if (!pptkToDelete.value) return;
    router.delete(`/pengaturan/pptk/${pptkToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeletePptkModal.value = false;
            pptkToDelete.value = null;
        },
    });
};

const togglePptkActive = (pptk) => {
    router.post(`/pengaturan/pptk/${pptk.id}/toggle-active`, {}, {
        preserveScroll: true,
    });
};

// Manajemen Pengguna & Hak Akses (Role)
const showUserModal = ref(false);
const isEditingUser = ref(false);
const showDeleteUserModal = ref(false);
const userToDelete = ref(null);

const userForm = useForm({
    id: null,
    name: '',
    email: '',
    password: '',
    role: 'pptk',
    pptk_id: '',
});

const openCreateUserModal = () => {
    isEditingUser.value = false;
    userForm.reset();
    userForm.clearErrors();
    userForm.id = null;
    userForm.name = '';
    userForm.email = '';
    userForm.password = '';
    userForm.role = 'pptk';
    userForm.pptk_id = props.pptks?.[0]?.id || '';
    showUserModal.value = true;
};

const openEditUserModal = (u) => {
    isEditingUser.value = true;
    userForm.clearErrors();
    userForm.id = u.id;
    userForm.name = u.name || '';
    userForm.email = u.email || '';
    userForm.password = '';
    userForm.role = u.role || 'operator';
    userForm.pptk_id = u.pptk_id || (props.pptks?.[0]?.id || '');
    showUserModal.value = true;
};

const submitUserForm = () => {
    if (isEditingUser.value && userForm.id) {
        userForm.put(`/pengaturan/users/${userForm.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showUserModal.value = false;
            },
        });
    } else {
        userForm.post('/pengaturan/users', {
            preserveScroll: true,
            onSuccess: () => {
                showUserModal.value = false;
            },
        });
    }
};

const confirmDeleteUser = (u) => {
    userToDelete.value = u;
    showDeleteUserModal.value = true;
};

const executeDeleteUser = () => {
    if (!userToDelete.value) return;
    router.delete(`/pengaturan/users/${userToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteUserModal.value = false;
            userToDelete.value = null;
        },
    });
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

            <!-- Card 4: Master PPTK Management -->
            <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Users class="w-4 h-4 text-emerald-700" />
                        <div>
                            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wide">Daftar PPTK Sekretariat DPRD Banyumas</h2>
                            <p class="text-[11px] text-slate-500">
                                Tambah, ubah identitas pejabat/gelar, atau nonaktifkan PPTK ketika pergantian periode anggaran baru.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-[11px] text-slate-500 font-medium">
                            Total: <strong class="text-slate-800">{{ pptks?.length || 0 }}</strong> ({{ pptks?.filter(p => p.is_active)?.length || 0 }} Aktif)
                        </span>
                        <button 
                            type="button"
                            @click="openCreatePptkModal"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#04331a] hover:bg-emerald-800 text-white rounded text-xs font-bold transition-colors cursor-pointer shadow-xs"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>Tambah PPTK Baru</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-100 text-slate-700 font-semibold border-b border-slate-200">
                            <tr>
                                <th class="px-3 py-2 text-center w-10">No</th>
                                <th class="px-3 py-2 text-left">Kode</th>
                                <th class="px-3 py-2 text-left">Nama PPTK</th>
                                <th class="px-3 py-2 text-left">NIP</th>
                                <th class="px-3 py-2 text-left">Bagian / Subunit</th>
                                <th class="px-3 py-2 text-left">Sheet BKU</th>
                                <th class="px-3 py-2 text-center w-16">Urut</th>
                                <th class="px-3 py-2 text-center w-28">Status</th>
                                <th class="px-3 py-2 text-center w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(p, idx) in pptks" :key="p.id" :class="['hover:bg-slate-50 transition-colors', !p.is_active ? 'bg-slate-50/60 opacity-60' : '']">
                                <td class="px-3 py-2 text-center font-mono text-slate-500">{{ idx + 1 }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex items-center gap-1.5 font-mono font-bold text-slate-800 px-2 py-0.5 rounded bg-slate-100 border border-slate-200">
                                        <span class="w-2.5 h-2.5 rounded-full inline-block shrink-0" :style="{ backgroundColor: p.color_tag || '#04331a' }"></span>
                                        {{ p.code }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-bold text-emerald-950">{{ p.display_name || p.name }}</td>
                                <td class="px-3 py-2 font-mono text-slate-600">{{ p.nip || '-' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ p.subunit?.name || '-' }}</td>
                                <td class="px-3 py-2 font-mono text-blue-700 underline">
                                    <Link v-if="p.is_active" :href="`/bku?tab=${p.sheet_name}`">{{ p.sheet_name }}</Link>
                                    <span v-else class="text-slate-400 no-underline">{{ p.sheet_name }}</span>
                                </td>
                                <td class="px-3 py-2 text-center font-mono text-slate-600">{{ p.sort_order }}</td>
                                <td class="px-3 py-2 text-center">
                                    <button 
                                        type="button" 
                                        @click="togglePptkActive(p)"
                                        :title="p.is_active ? 'Klik untuk nonaktifkan' : 'Klik untuk aktifkan'"
                                        class="px-2 py-0.5 rounded text-[10px] font-bold cursor-pointer transition-colors border"
                                        :class="p.is_active ? 'bg-emerald-100 text-emerald-800 border-emerald-200 hover:bg-emerald-200' : 'bg-slate-200 text-slate-700 border-slate-300 hover:bg-slate-300'"
                                    >
                                        {{ p.is_active ? '● Aktif' : '○ Nonaktif' }}
                                    </button>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button 
                                            type="button"
                                            @click="openEditPptkModal(p)"
                                            class="p-1 rounded text-slate-600 hover:text-blue-700 hover:bg-blue-50 transition-colors cursor-pointer"
                                            title="Edit PPTK"
                                        >
                                            <Pencil class="w-3.5 h-3.5" />
                                        </button>
                                        <button 
                                            type="button"
                                            @click="confirmDeletePptk(p)"
                                            class="p-1 rounded text-slate-400 hover:text-red-700 hover:bg-red-50 transition-colors cursor-pointer"
                                            title="Hapus / Nonaktifkan PPTK"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!pptks || pptks.length === 0">
                                <td colspan="9" class="px-3 py-6 text-center text-slate-500">
                                    Belum ada data PPTK tersimpan. Klik tombol "+ Tambah PPTK Baru" di atas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card 5: User Management & Roles -->
            <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <KeyRound class="w-4 h-4 text-emerald-700" />
                        <div>
                            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wide">Manajemen Akun Pengguna &amp; Hak Akses (Role)</h2>
                            <p class="text-[11px] text-slate-500">
                                Atur akun login: Super Admin, Bendahara, Operator, dan Akun PPTK untuk memantau dashboard pagu kegiatan secara mandiri.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-[11px] text-slate-500 font-medium">
                            Total Pengguna: <strong class="text-slate-800">{{ users?.length || 0 }}</strong>
                        </span>
                        <button 
                            type="button"
                            @click="openCreateUserModal"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#04331a] hover:bg-emerald-800 text-white rounded text-xs font-bold transition-colors cursor-pointer shadow-xs"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>Tambah Akun Pengguna</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-100 text-slate-700 font-semibold border-b border-slate-200">
                            <tr>
                                <th class="px-3 py-2 text-center w-10">No</th>
                                <th class="px-3 py-2 text-left">Nama Pengguna</th>
                                <th class="px-3 py-2 text-left">Email Login</th>
                                <th class="px-3 py-2 text-center w-36">Hak Akses (Role)</th>
                                <th class="px-3 py-2 text-left">Pejabat PPTK Terkait</th>
                                <th class="px-3 py-2 text-center w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(u, idx) in users" :key="u.id" class="hover:bg-slate-50 transition-colors">
                                <td class="px-3 py-2 text-center font-mono text-slate-500">{{ idx + 1 }}</td>
                                <td class="px-3 py-2 font-bold text-slate-900">{{ u.name }}</td>
                                <td class="px-3 py-2 font-mono text-slate-700">{{ u.email }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span 
                                        v-if="u.role === 'superadmin' || u.role === 'Super Admin'" 
                                        class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200"
                                    >
                                        Super Admin
                                    </span>
                                    <span 
                                        v-else-if="u.role === 'bendahara'" 
                                        class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200"
                                    >
                                        Bendahara
                                    </span>
                                    <span 
                                        v-else-if="u.role === 'pptk'" 
                                        class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200"
                                    >
                                        PPTK
                                    </span>
                                    <span 
                                        v-else 
                                        class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200"
                                    >
                                        Operator
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <span v-if="u.role === 'pptk' && u.pptk" class="inline-flex items-center gap-1.5 font-semibold text-emerald-900">
                                        <span class="w-2 h-2 rounded-full inline-block shrink-0" :style="{ backgroundColor: u.pptk.color_tag || '#04331a' }"></span>
                                        {{ u.pptk.display_name }} ({{ u.pptk.name }})
                                    </span>
                                    <span v-else-if="u.role === 'pptk'" class="text-amber-600 font-semibold italic text-[11px]">
                                        Belum dihubungkan ke data PPTK
                                    </span>
                                    <span v-else class="text-slate-400 font-mono text-[11px]">-</span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button 
                                            type="button"
                                            @click="openEditUserModal(u)"
                                            class="p-1 rounded text-slate-600 hover:text-blue-700 hover:bg-blue-50 transition-colors cursor-pointer"
                                            title="Edit Pengguna"
                                        >
                                            <Pencil class="w-3.5 h-3.5" />
                                        </button>
                                        <button 
                                            type="button"
                                            @click="confirmDeleteUser(u)"
                                            class="p-1 rounded text-slate-400 hover:text-red-700 hover:bg-red-50 transition-colors cursor-pointer"
                                            title="Hapus Pengguna"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card 6: Audit Trail -->
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

            <!-- Modal Tambah / Edit PPTK -->
            <div 
                v-if="showPptkModal" 
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            >
                <div class="bg-white rounded border border-slate-200 shadow-xl w-full max-w-xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-4 py-3 bg-[#04331a] text-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Users class="w-4 h-4 text-emerald-300" />
                            <h3 class="text-xs font-bold uppercase tracking-wide">
                                {{ isEditingPptk ? 'Ubah Data PPTK' : 'Tambah PPTK Baru (Periode Baru)' }}
                            </h3>
                        </div>
                        <button 
                            type="button" 
                            @click="showPptkModal = false"
                            class="text-emerald-200 hover:text-white transition-colors cursor-pointer"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form @submit.prevent="submitPptkForm" class="p-4 space-y-3 text-xs">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Nama PPTK -->
                            <div class="sm:col-span-2">
                                <label class="block font-semibold text-slate-700 mb-1">
                                    Nama PPTK <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    v-model="pptkForm.display_name" 
                                    @input="onDisplayNameInput"
                                    type="text" 
                                    required
                                    placeholder="contoh: Pak Sugeng/Emi, Irna, atau B Aulia/Ety"
                                    class="w-full px-2.5 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-emerald-700 focus:border-emerald-700 outline-none text-xs"
                                />
                                <span class="text-[10px] text-slate-400">Nama/label PPTK yang tampil di tabel &amp; dropdown</span>
                                <div v-if="pptkForm.errors.display_name" class="text-red-600 text-[10px] mt-0.5">{{ pptkForm.errors.display_name }}</div>
                            </div>

                            <!-- Kode Singkat Unik -->
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">
                                    Kode Singkat PPTK <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    v-model="pptkForm.code" 
                                    type="text" 
                                    required
                                    placeholder="contoh: AHMAD"
                                    class="w-full px-2.5 py-1.5 font-mono uppercase border border-slate-300 rounded focus:ring-1 focus:ring-emerald-700 focus:border-emerald-700 outline-none text-xs"
                                />
                                <span class="text-[10px] text-slate-400">Harus unik (tanpa spasi)</span>
                                <div v-if="pptkForm.errors.code" class="text-red-600 text-[10px] mt-0.5">{{ pptkForm.errors.code }}</div>
                            </div>

                            <!-- NIP -->
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">
                                    NIP (Nomor Induk Pegawai)
                                </label>
                                <input 
                                    v-model="pptkForm.nip" 
                                    type="text" 
                                    placeholder="contoh: 19780512 200212 1 004"
                                    class="w-full px-2.5 py-1.5 font-mono border border-slate-300 rounded focus:ring-1 focus:ring-emerald-700 focus:border-emerald-700 outline-none text-xs"
                                />
                                <div v-if="pptkForm.errors.nip" class="text-red-600 text-[10px] mt-0.5">{{ pptkForm.errors.nip }}</div>
                            </div>

                            <!-- Bagian / Subunit -->
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">
                                    Bagian / Subunit
                                </label>
                                <select 
                                    v-model="pptkForm.subunit_id"
                                    class="w-full px-2.5 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-emerald-700 focus:border-emerald-700 outline-none text-xs bg-white"
                                >
                                    <option value="">-- Pilih Bagian (Opsional) --</option>
                                    <option v-for="sub in subunits" :key="sub.id" :value="sub.id">
                                        {{ sub.name }}
                                    </option>
                                </select>
                                <div v-if="pptkForm.errors.subunit_id" class="text-red-600 text-[10px] mt-0.5">{{ pptkForm.errors.subunit_id }}</div>
                            </div>

                            <!-- Nama Tab Sheet BKU -->
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">
                                    Nama Sheet BKU
                                </label>
                                <input 
                                    v-model="pptkForm.sheet_name" 
                                    type="text" 
                                    placeholder="contoh: Ahmad"
                                    class="w-full px-2.5 py-1.5 font-mono border border-slate-300 rounded focus:ring-1 focus:ring-emerald-700 focus:border-emerald-700 outline-none text-xs"
                                />
                                <span class="text-[10px] text-slate-400">Nama tab buku kas per PPTK</span>
                                <div v-if="pptkForm.errors.sheet_name" class="text-red-600 text-[10px] mt-0.5">{{ pptkForm.errors.sheet_name }}</div>
                            </div>

                            <!-- Tag Warna Identitas -->
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">
                                    Warna Label Tag
                                </label>
                                <div class="flex items-center gap-2">
                                    <input 
                                        v-model="pptkForm.color_tag" 
                                        type="color" 
                                        class="w-8 h-8 p-0 border border-slate-300 rounded cursor-pointer"
                                    />
                                    <input 
                                        v-model="pptkForm.color_tag" 
                                        type="text" 
                                        placeholder="#04331a"
                                        class="flex-1 px-2.5 py-1.5 font-mono border border-slate-300 rounded focus:ring-1 focus:ring-emerald-700 focus:border-emerald-700 outline-none text-xs uppercase"
                                    />
                                </div>
                            </div>

                            <!-- Nomor Urut -->
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">
                                    Nomor Urutan Tampilan
                                </label>
                                <input 
                                    v-model.number="pptkForm.sort_order" 
                                    type="number" 
                                    min="0"
                                    class="w-full px-2.5 py-1.5 font-mono border border-slate-300 rounded focus:ring-1 focus:ring-emerald-700 focus:border-emerald-700 outline-none text-xs"
                                />
                            </div>

                            <!-- Status Aktif -->
                            <div class="flex items-center gap-2 pt-6">
                                <label class="relative flex items-center gap-2 cursor-pointer select-none">
                                    <input 
                                        v-model="pptkForm.is_active" 
                                        type="checkbox" 
                                        class="w-4 h-4 text-emerald-700 rounded border-slate-300 focus:ring-emerald-600"
                                    />
                                    <span class="font-semibold text-slate-700">Status Aktif (Tampil di Periode Berjalan)</span>
                                </label>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                            <button 
                                type="button" 
                                @click="showPptkModal = false"
                                class="px-3 py-1.5 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded font-semibold transition-colors cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="pptkForm.processing"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-[#04331a] hover:bg-emerald-800 disabled:opacity-50 text-white rounded font-bold transition-colors cursor-pointer shadow-xs"
                            >
                                <Save class="w-3.5 h-3.5" />
                                <span>{{ pptkForm.processing ? 'Menyimpan...' : (isEditingPptk ? 'Perbarui PPTK' : 'Simpan PPTK') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal Konfirmasi Hapus / Nonaktifkan PPTK -->
            <div 
                v-if="showDeletePptkModal && pptkToDelete" 
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            >
                <div class="bg-white rounded border border-slate-200 shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-4 py-3 bg-red-800 text-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="w-4 h-4 text-amber-300" />
                            <h3 class="text-xs font-bold uppercase tracking-wide">Konfirmasi Hapus / Nonaktifkan PPTK</h3>
                        </div>
                        <button 
                            type="button" 
                            @click="showDeletePptkModal = false"
                            class="text-red-200 hover:text-white transition-colors cursor-pointer"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="p-4 space-y-3 text-xs">
                        <p class="text-slate-700 leading-relaxed">
                            Apakah Anda yakin ingin menghapus PPTK <strong class="text-slate-900 font-bold">"{{ pptkToDelete.display_name }}"</strong> ({{ pptkToDelete.name }})?
                        </p>
                        
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded text-amber-900 space-y-1">
                            <p class="font-bold flex items-center gap-1 text-[11px]">
                                <ShieldCheck class="w-3.5 h-3.5 text-amber-700 shrink-0" />
                                <span>Perlindungan Riwayat Pembukuan:</span>
                            </p>
                            <p class="text-[11px] leading-relaxed">
                                Jika PPTK ini sudah memiliki riwayat transaksi BKU atau pagu anggaran, sistem secara otomatis akan <strong>menonaktifkannya</strong> (tidak menghapus permanen) agar laporan pembukuan tahun sebelumnya tetap utuh dan valid.
                            </p>
                        </div>

                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button 
                                type="button" 
                                @click="showDeletePptkModal = false"
                                class="px-3 py-1.5 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded font-semibold transition-colors cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="button" 
                                @click="executeDeletePptk"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-red-700 hover:bg-red-800 text-white rounded font-bold transition-colors cursor-pointer shadow-xs"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                                <span>Lanjutkan Hapus</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Tambah / Edit Pengguna -->
            <div 
                v-if="showUserModal" 
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            >
                <div class="bg-white rounded border border-slate-200 shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-4 py-3 bg-[#04331a] text-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <KeyRound class="w-4 h-4 text-emerald-300" />
                            <h3 class="text-xs font-bold uppercase tracking-wide">
                                {{ isEditingUser ? 'Ubah Data Pengguna' : 'Tambah Akun Pengguna Baru' }}
                            </h3>
                        </div>
                        <button 
                            type="button" 
                            @click="showUserModal = false"
                            class="text-emerald-200 hover:text-white transition-colors cursor-pointer"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form @submit.prevent="submitUserForm" class="p-4 space-y-3 text-xs">
                        <!-- Nama Lengkap -->
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">
                                Nama Lengkap Pengguna <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="userForm.name" 
                                type="text" 
                                required
                                placeholder="contoh: IRNAWATINGRUM, S.S.TP., M.Si."
                                class="w-full px-2.5 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-emerald-700 focus:border-emerald-700 outline-none text-xs"
                            />
                            <div v-if="userForm.errors.name" class="text-red-600 text-[10px] mt-0.5">{{ userForm.errors.name }}</div>
                        </div>

                        <!-- Email Login -->
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">
                                Email Login <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="userForm.email" 
                                type="email" 
                                required
                                placeholder="contoh: pptk.irna@dprd.banyumaskab.go.id"
                                class="w-full px-2.5 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-emerald-700 focus:border-emerald-700 outline-none text-xs"
                            />
                            <div v-if="userForm.errors.email" class="text-red-600 text-[10px] mt-0.5">{{ userForm.errors.email }}</div>
                        </div>

                        <!-- Password -->
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">
                                Password <span v-if="!isEditingUser" class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="userForm.password" 
                                type="password" 
                                :required="!isEditingUser"
                                :placeholder="isEditingUser ? '(Kosongkan jika tidak ingin mengubah password)' : 'Minimal 6 karakter'"
                                class="w-full px-2.5 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-emerald-700 focus:border-emerald-700 outline-none text-xs"
                            />
                            <div v-if="userForm.errors.password" class="text-red-600 text-[10px] mt-0.5">{{ userForm.errors.password }}</div>
                        </div>

                        <!-- Role / Hak Akses -->
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">
                                Hak Akses (Role) <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="userForm.role"
                                required
                                class="w-full px-2.5 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-emerald-700 focus:border-emerald-700 outline-none text-xs bg-white"
                            >
                                <option value="superadmin">Super Admin (Akses Penuh)</option>
                                <option value="bendahara">Bendahara Pengeluaran</option>
                                <option value="operator">Operator Pembukuan</option>
                                <option value="pptk">PPTK (Pemantau Dashboard Pagu Kegiatan)</option>
                            </select>
                            <div v-if="userForm.errors.role" class="text-red-600 text-[10px] mt-0.5">{{ userForm.errors.role }}</div>
                        </div>

                        <!-- Hubungkan ke PPTK (khusus role PPTK) -->
                        <div v-if="userForm.role === 'pptk'" class="p-3 bg-emerald-50 border border-emerald-200 rounded space-y-1.5 animate-in fade-in duration-150">
                            <label class="block font-bold text-emerald-900">
                                Pilih Pejabat PPTK Terkait <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="userForm.pptk_id"
                                required
                                class="w-full px-2.5 py-1.5 border border-emerald-300 rounded focus:ring-1 focus:ring-emerald-700 focus:border-emerald-700 outline-none text-xs bg-white font-medium"
                            >
                                <option value="">-- Pilih PPTK --</option>
                                <option v-for="p in pptks" :key="p.id" :value="p.id">
                                    {{ p.display_name }} ({{ p.code }}) - {{ p.name }}
                                </option>
                            </select>
                            <p class="text-[10px] text-emerald-700 leading-normal">
                                💡 Akun ini akan otomatis terhubung dan terkunci hanya untuk melihat dan mengelola dashboard pagu dari PPTK yang dipilih.
                            </p>
                            <div v-if="userForm.errors.pptk_id" class="text-red-600 text-[10px] mt-0.5">{{ userForm.errors.pptk_id }}</div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                            <button 
                                type="button" 
                                @click="showUserModal = false"
                                class="px-3 py-1.5 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded font-semibold transition-colors cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="userForm.processing"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-[#04331a] hover:bg-emerald-800 disabled:opacity-50 text-white rounded font-bold transition-colors cursor-pointer shadow-xs"
                            >
                                <Save class="w-3.5 h-3.5" />
                                <span>{{ userForm.processing ? 'Menyimpan...' : (isEditingUser ? 'Perbarui Pengguna' : 'Simpan Pengguna') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal Konfirmasi Hapus Pengguna -->
            <div 
                v-if="showDeleteUserModal && userToDelete" 
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            >
                <div class="bg-white rounded border border-slate-200 shadow-xl w-full max-w-sm overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-4 py-3 bg-red-800 text-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="w-4 h-4 text-amber-300" />
                            <h3 class="text-xs font-bold uppercase tracking-wide">Konfirmasi Hapus Pengguna</h3>
                        </div>
                        <button 
                            type="button" 
                            @click="showDeleteUserModal = false"
                            class="text-red-200 hover:text-white transition-colors cursor-pointer"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="p-4 space-y-3 text-xs">
                        <p class="text-slate-700 leading-relaxed">
                            Apakah Anda yakin ingin menghapus akun pengguna <strong class="text-slate-900 font-bold">"{{ userToDelete.name }}"</strong> ({{ userToDelete.email }})?
                        </p>

                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button 
                                type="button" 
                                @click="showDeleteUserModal = false"
                                class="px-3 py-1.5 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded font-semibold transition-colors cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="button" 
                                @click="executeDeleteUser"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-red-700 hover:bg-red-800 text-white rounded font-bold transition-colors cursor-pointer shadow-xs"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                                <span>Hapus Akun</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
