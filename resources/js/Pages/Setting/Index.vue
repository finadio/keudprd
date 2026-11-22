<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
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
    AlertTriangle,
    CheckCircle2,
    FolderOpen,
    UserCheck,
    UserPlus,
    Shield,
    Key,
    UserCog
} from 'lucide-vue-next';

const props = defineProps({
    setting: Object,
    pptks: Array,
    subunits: Array,
    periods: Array,
    audit_logs: Array,
    users: Array,
    roles: Array,
    active_year: Number,
    default_year: Number,
    available_years: Array,
    year_summaries: Array,
    storage_stats: Object,
});

const page = usePage();
const routeTab = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('tab') : null;
const activeTab = ref(routeTab || 'users');

const tabs = computed(() => [
    { id: 'users', label: 'Pengguna & Role', icon: UserCog, badge: props.users?.length || 0, desc: 'Kelola akun login staf, role hak akses, dan reset sandi' },
    { id: 'pptk', label: 'Master Data PPTK', icon: Users, badge: props.pptks?.length || 0, desc: 'Data master 9 PPTK, subunit, dan akun login terkait' },
    { id: 'skpd', label: 'Identitas SKPD & Periode', icon: Building2, desc: 'Nama instansi, Sekwan, Bendahara, NIP, dan penguncian buku' },
    { id: 'database', label: 'Tahun Anggaran & Data', icon: Database, badge: `TA ${props.active_year || props.setting?.fiscal_year || 2026}`, desc: 'Manajemen multi-tahun anggaran dan kapasitas database' },
    { id: 'audit', label: 'Audit Trail', icon: History, badge: props.audit_logs?.length || 0, desc: 'Rekam jejak aktivitas dan log audit sistem' },
]);

const setActiveTab = (tabId) => {
    activeTab.value = tabId;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabId);
        window.history.replaceState({}, '', url.toString());
    }
};

watch(() => page.url, (newUrl) => {
    if (newUrl.includes('/pengaturan')) {
        const search = newUrl.includes('?') ? newUrl.split('?')[1] : '';
        const params = new URLSearchParams(search);
        activeTab.value = params.get('tab') || 'users';
    }
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

const showDeleteYearModal = ref(false);
const yearToDelete = ref(null);
const confirmYearInput = ref('');
const isDeletingYear = ref(false);

const openDeleteYearModal = (ys) => {
    yearToDelete.value = ys;
    confirmYearInput.value = '';
    showDeleteYearModal.value = true;
};

const executeDeleteYear = () => {
    if (!yearToDelete.value) return;
    if (confirmYearInput.value !== String(yearToDelete.value.year)) return;

    isDeletingYear.value = true;
    router.delete(`/pengaturan/tahun/${yearToDelete.value.year}`, {
        preserveScroll: false,
        onSuccess: () => {
            showDeleteYearModal.value = false;
            yearToDelete.value = null;
            confirmYearInput.value = '';
            isDeletingYear.value = false;
        },
        onError: () => {
            isDeletingYear.value = false;
        },
        onFinish: () => {
            isDeletingYear.value = false;
        },
    });
};

const makeYearActive = (yr) => {
    router.post(`/pengaturan/tahun/${yr}/jadikan-aktif`, {}, {
        preserveScroll: false,
    });
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

// Manajemen Akun Pengguna & Hak Akses (Role)
const showUserModal = ref(false);
const isEditingUser = ref(false);
const showResetUserPasswordModal = ref(false);
const showDeleteUserModal = ref(false);
const userToDelete = ref(null);

const userForm = useForm({
    id: null,
    name: '',
    username: '',
    email: '',
    role: 'pptk',
    pptk_id: '',
    password: '',
});

const resetUserPasswordForm = useForm({
    id: null,
    username: '',
    name: '',
    password: '',
    password_confirmation: '',
});

const openCreateUserModal = (defaultPptkId = null) => {
    isEditingUser.value = false;
    userForm.reset();
    userForm.clearErrors();
    userForm.id = null;
    userForm.role = defaultPptkId ? 'pptk' : 'operator';
    userForm.pptk_id = defaultPptkId || '';
    if (defaultPptkId) {
        const p = props.pptks?.find(item => item.id === defaultPptkId);
        if (p) {
            userForm.name = p.display_name || p.name;
            const clean = (p.display_name || p.name).toLowerCase().replace(/[^a-z0-9]/g, '');
            userForm.username = clean;
            userForm.password = 'Pptk' + (clean ? clean.charAt(0).toUpperCase() + clean.slice(1) : 'Setwan') + '#2026';
        }
    }
    showUserModal.value = true;
};

const openEditUserModal = (u) => {
    isEditingUser.value = true;
    userForm.clearErrors();
    userForm.id = u.id;
    userForm.name = u.name;
    userForm.username = u.username || '';
    userForm.email = u.email;
    const r = (u.role || '').toLowerCase().replace(/\s+/g, '');
    userForm.role = r === 'superadmin' || r === 'admin' ? 'superadmin' : (r === 'bendahara' ? 'bendahara' : (r === 'pptk' ? 'pptk' : 'operator'));
    userForm.pptk_id = u.pptk_id || '';
    userForm.password = '';
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

const openResetUserPasswordModal = (u) => {
    resetUserPasswordForm.reset();
    resetUserPasswordForm.clearErrors();
    resetUserPasswordForm.id = u.id;
    resetUserPasswordForm.username = u.username || u.email;
    resetUserPasswordForm.name = u.name;
    resetUserPasswordForm.password = '';
    resetUserPasswordForm.password_confirmation = '';
    showResetUserPasswordModal.value = true;
};

const submitResetUserPassword = () => {
    if (!resetUserPasswordForm.id) return;
    resetUserPasswordForm.post(`/pengaturan/users/${resetUserPasswordForm.id}/reset-password`, {
        preserveScroll: true,
        onSuccess: () => {
            showResetUserPasswordModal.value = false;
        },
    });
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
    create_login_account: true,
    username: '',
    password: '',
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
    pptkForm.create_login_account = true;
    pptkForm.username = '';
    pptkForm.password = '';
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
    pptkForm.create_login_account = false;
    pptkForm.username = '';
    pptkForm.password = '';
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
        if (pptkForm.create_login_account) {
            const clean = pptkForm.display_name.toLowerCase().replace(/[^a-z0-9]/g, '');
            pptkForm.username = clean;
            pptkForm.password = 'Pptk' + (clean ? clean.charAt(0).toUpperCase() + clean.slice(1) : 'Setwan') + '#2026';
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
</script>

<template>
    <AppLayout>
        <Head title="Pengaturan & Master Data" />

        <div class="space-y-5">
            
            <!-- Page Title (Harmonized KeuDPRD Card) -->
            <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
                        <Settings class="w-6 h-6 text-slate-700" />
                        Pengaturan Sistem &amp; Data Master Keuangan
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1">
                        Kelola identitas instansi, pejabat berwenang (PA/Bendahara), master 9 PPTK, penguncian periode buku, dan rekam jejak audit (*audit trail*).
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a 
                        :href="`/laporan/backup-zip?year=${active_year || form.fiscal_year || 2026}`"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#04331a] hover:bg-emerald-800 text-white rounded-xl text-xs font-bold transition-colors shadow-xs"
                        title="Unduh seluruh arsip pembukuan Excel & database dalam bentuk ZIP"
                    >
                        <Archive class="w-4 h-4 text-emerald-300" />
                        <span>Cadangkan ZIP (TA {{ active_year || form.fiscal_year || 2026 }})</span>
                    </a>
                </div>
            </div>

            <!-- Sub-Menu Nav Tabs (Segmented Sub-Menu) -->
            <div class="bg-white border border-slate-200/90 rounded-2xl p-1.5 shadow-xs">
                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                    <button
                        v-for="t in tabs"
                        :key="t.id"
                        type="button"
                        @click="setActiveTab(t.id)"
                        :class="[
                            'inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap shrink-0',
                            activeTab === t.id
                                ? 'bg-[#04331a] text-white shadow-xs'
                                : 'bg-transparent hover:bg-slate-100 text-slate-700 hover:text-slate-900'
                        ]"
                    >
                        <component :is="t.icon" class="w-4 h-4 shrink-0" :class="activeTab === t.id ? 'text-emerald-300' : 'text-slate-500'" />
                        <span>{{ t.label }}</span>
                        <span 
                            v-if="t.badge !== undefined && t.badge !== null"
                            :class="[
                                'text-[10px] px-1.5 py-0.2 rounded-full font-mono font-bold shrink-0',
                                activeTab === t.id ? 'bg-emerald-800 text-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200'
                            ]"
                        >
                            {{ t.badge }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Tab 4: Multi-Year Fiscal Switcher & Storage Capacity Guard -->
            <div v-show="activeTab === 'database'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Card: Pergantian Tahun Anggaran & Arsip Tahun Lama -->
                <div class="bg-white border border-slate-200/90 rounded-xl shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 bg-emerald-950 text-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <CalendarPlus class="w-4 h-4 text-emerald-300" />
                            <h2 class="text-xs sm:text-sm font-bold uppercase tracking-wide">Manajemen Multi-Tahun Anggaran &amp; Arsip</h2>
                        </div>
                        <span class="px-2.5 py-0.5 bg-emerald-800 text-emerald-200 rounded-md text-[10px] font-mono font-bold">
                            Aktif: TA {{ default_year || 2026 }}
                        </span>
                    </div>

                    <div class="p-4 sm:p-5 space-y-4 text-xs">
                        <!-- Daftar Tahun Tersimpan -->
                        <div class="overflow-x-auto border border-slate-200 rounded-xl">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Tahun</th>
                                        <th class="px-3 py-2 text-right">Transaksi</th>
                                        <th class="px-3 py-2 text-center">Bulan Dikunci</th>
                                        <th class="px-3 py-2 text-center whitespace-nowrap">Status</th>
                                        <th class="px-3 py-2 text-center whitespace-nowrap">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="ys in (year_summaries || [])" :key="ys.year" class="hover:bg-slate-50">
                                        <td class="px-3 py-2 font-mono font-bold text-slate-900 whitespace-nowrap">TA {{ ys.year }}</td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-700 whitespace-nowrap">{{ ys.tx_count.toLocaleString('id-ID') }} baris</td>
                                        <td class="px-3 py-2 text-center font-mono whitespace-nowrap">
                                            <span :class="ys.closed_months === 12 ? 'text-amber-700 font-bold' : 'text-slate-600'">
                                                {{ ys.closed_months }} / {{ ys.total_months || 12 }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center whitespace-nowrap">
                                            <span v-if="ys.is_default" class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-bold">
                                                TA Aktif
                                            </span>
                                            <span v-else class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-[10px] font-semibold">
                                                Arsip
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-center whitespace-nowrap">
                                            <div class="flex items-center justify-center gap-1">
                                                <!-- Buka Data -->
                                                <button
                                                    type="button"
                                                    @click="switchToYear(ys.year)"
                                                    :disabled="ys.year === active_year"
                                                    :class="[
                                                        'p-1.5 rounded-lg transition-colors cursor-pointer',
                                                        ys.year === active_year
                                                            ? 'text-emerald-700 bg-emerald-50 cursor-default'
                                                            : 'text-slate-500 hover:text-emerald-700 hover:bg-emerald-50'
                                                    ]"
                                                    :title="ys.year === active_year ? 'Tahun ini sedang dibuka di layar' : 'Buka & lihat data pembukuan TA ' + ys.year"
                                                >
                                                    <FolderOpen class="w-3.5 h-3.5" />
                                                </button>

                                                <!-- Jadikan TA Aktif Utama -->
                                                <button
                                                    v-if="!ys.is_default"
                                                    type="button"
                                                    @click="makeYearActive(ys.year)"
                                                    class="p-1.5 rounded-lg text-slate-400 hover:text-amber-700 hover:bg-amber-50 transition-colors cursor-pointer"
                                                    :title="'Jadikan TA ' + ys.year + ' sebagai Tahun Anggaran Aktif Utama sistem'"
                                                >
                                                    <CheckCircle2 class="w-3.5 h-3.5" />
                                                </button>

                                                <!-- Hapus TA Permanen -->
                                                <button
                                                    v-if="!ys.is_default"
                                                    type="button"
                                                    @click="openDeleteYearModal(ys)"
                                                    class="p-1.5 rounded-lg text-slate-400 hover:text-red-700 hover:bg-red-50 transition-colors cursor-pointer"
                                                    :title="'Hapus permanen TA ' + ys.year + ' beserta seluruh datanya'"
                                                >
                                                    <Trash2 class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Form Buka Tahun Anggaran Baru -->
                        <form @submit.prevent="openNewFiscalYear" class="p-4 bg-emerald-50/70 border border-emerald-200 rounded-xl space-y-3">
                            <div class="font-bold text-emerald-950 text-xs sm:text-sm">Buka Tahun Anggaran Baru (Data Tahun Lama Tetap Tersimpan Aman)</div>
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="w-32">
                                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">Tahun Baru</label>
                                    <input
                                        v-model.number="newYearForm.new_year"
                                        type="number"
                                        min="2020"
                                        max="2100"
                                        required
                                        class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs font-mono font-bold focus:ring-2 focus:ring-emerald-500"
                                    />
                                </div>
                                <label class="flex items-center gap-2 text-[11px] text-slate-700 cursor-pointer pb-2 flex-1 min-w-[220px]">
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
                                    class="px-4 py-2 bg-[#107c41] hover:bg-[#0c6334] disabled:opacity-50 text-white rounded-xl font-bold text-xs shadow-xs transition-colors cursor-pointer"
                                >
                                    {{ isOpeningNewYear ? 'Memproses...' : 'Buka Tahun Baru' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Card: Pemantauan Kapasitas Penyimpanan Jangka Panjang -->
                <div class="bg-white border border-slate-200/90 rounded-xl shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Database class="w-4 h-4 text-blue-600" />
                            <h2 class="text-xs sm:text-sm font-bold text-slate-800 uppercase tracking-wide">Kapasitas Penyimpanan Jangka Panjang</h2>
                        </div>
                        <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-md border border-emerald-200">
                            Status: Aman &amp; Ringan
                        </span>
                    </div>

                    <div class="p-4 sm:p-5 space-y-3.5 text-xs">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div class="p-3.5 bg-slate-50 border border-slate-200/90 rounded-xl">
                                <div class="text-[11px] text-slate-500 font-medium">Total Transaksi (Semua Tahun)</div>
                                <div class="text-base font-black text-slate-900 font-mono mt-0.5">
                                    {{ (storage_stats?.total_transactions || 0).toLocaleString('id-ID') }}
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">
                                    TA {{ active_year }}: {{ (storage_stats?.active_year_transactions || 0).toLocaleString('id-ID') }} baris
                                </div>
                            </div>

                            <div class="p-3.5 bg-slate-50 border border-slate-200/90 rounded-xl">
                                <div class="text-[11px] text-slate-500 font-medium">Ukuran Database Aktif</div>
                                <div class="text-base font-black text-emerald-800 font-mono mt-0.5">
                                    {{ storage_stats?.db_size_mb !== null && storage_stats?.db_size_mb !== undefined ? storage_stats.db_size_mb + ' MB' : 'Tersedia' }}
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">
                                    Estimasi ~2-3 MB per tahun
                                </div>
                            </div>

                            <div class="p-3.5 bg-slate-50 border border-slate-200/90 rounded-xl col-span-2 sm:col-span-1">
                                <div class="text-[11px] text-slate-500 font-medium">File ZIP di Server</div>
                                <div class="text-base font-black text-blue-800 font-mono mt-0.5">
                                    {{ storage_stats?.backup_files_count || 0 }} File ({{ storage_stats?.backup_size_mb || 0 }} MB)
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">
                                    Auto-simpan maks {{ storage_stats?.backup_keep_limit || 5 }} terbaru
                                </div>
                            </div>
                        </div>

                        <div class="p-3.5 bg-blue-50/70 border border-blue-200 rounded-xl text-[11px] text-blue-950 leading-relaxed">
                            <strong>Mengapa aman jangka panjang?</strong> Tabel transaksi telah diindeks berdasarkan tahun anggaran (`period_year`), sehingga menyimpan 10–20 tahun data hanya memakan ±30–60 MB dan tidak memperlambat aplikasi. File cadangan ZIP otomatis dipangkas agar hanya menyimpan <b>5 arsip terbaru</b> di server.
                        </div>

                        <div class="flex justify-end pt-1">
                            <button
                                type="button"
                                @click="pruneOldBackups"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 rounded-xl text-xs font-semibold transition-colors cursor-pointer shadow-2xs"
                            >
                                <Trash2 class="w-3.5 h-3.5 text-slate-500" />
                                <span>Bersihkan File ZIP Lama Sekarang</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Identitas SKPD & Penguncian Periode -->
            <div v-show="activeTab === 'skpd'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                
                <!-- Card 1: Konfigurasi Identitas SKPD & Pejabat -->
                <div class="bg-white border border-slate-200/90 rounded-xl shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center gap-2">
                        <Building2 class="w-4 h-4 text-emerald-600" />
                        <h2 class="text-xs sm:text-sm font-bold text-slate-800 uppercase tracking-wide">Identitas SKPD &amp; Pejabat Berwenang</h2>
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
                            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer text-xs">
                                <Save class="w-4 h-4" /> Simpan Konfigurasi
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Card 2: Penguncian Periode Buku (Lock Periods) -->
                <div class="bg-white border border-slate-200/90 rounded-xl shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Lock class="w-4 h-4 text-amber-600" />
                            <h2 class="text-xs sm:text-sm font-bold text-slate-800 uppercase tracking-wide">Penguncian Periode Pembukuan (TA {{ active_year || form.fiscal_year }})</h2>
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
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-rose-100 text-rose-800 font-mono"
                                        >
                                            <Lock class="w-3 h-3" /> DIKUNCI
                                        </span>
                                        <span 
                                            v-else 
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800 font-mono"
                                        >
                                            <Unlock class="w-3 h-3" /> TERBUKA
                                        </span>
                                    </td>
                                    <td class="px-3 py-1.5 text-center">
                                        <button 
                                            @click="toggleLock(p.id)"
                                            :class="[
                                                'px-3 py-1 rounded-lg text-[11px] font-semibold transition-colors cursor-pointer',
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

            <!-- Tab 1: Manajemen Akun Pengguna & Hak Akses (Role) -->
            <div v-show="activeTab === 'users'" class="space-y-4">
                <!-- Card: Manajemen Akun Pengguna & Hak Akses (Role) -->
                <div class="bg-white border border-slate-200/90 rounded-xl shadow-xs overflow-hidden">
                    <div class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <UserCog class="w-5 h-5 text-emerald-800" />
                            <div>
                                <h2 class="text-xs sm:text-sm font-bold text-slate-800 uppercase tracking-wide">
                                    Manajemen Akun Pengguna &amp; Hak Akses (Role)
                                </h2>
                                <p class="text-[11px] text-slate-500">
                                    Kelola username login singkat, hak akses modul, ubah role pengguna, dan reset password login staf.
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-[11px] text-slate-500 font-medium">
                                Total: <strong class="text-slate-800">{{ users?.length || 0 }}</strong> Akun Terdaftar
                            </span>
                            <button 
                                type="button"
                                @click="openCreateUserModal()"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#04331a] hover:bg-emerald-800 text-white rounded-xl text-xs font-bold transition-colors cursor-pointer shadow-xs"
                            >
                                <UserPlus class="w-3.5 h-3.5" />
                                <span>Tambah Pengguna Baru</span>
                            </button>
                        </div>
                    </div>

                    <!-- Role Guide Cards -->
                    <div class="p-4 bg-slate-50/60 border-b border-slate-200 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div v-for="r in roles" :key="r.value" class="p-3 bg-white border border-slate-200 rounded-xl shadow-2xs space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border" :class="r.color">
                                    {{ r.label }}
                                </span>
                                <span class="text-[10px] font-mono text-slate-400">role: {{ r.value }}</span>
                            </div>
                            <p class="text-[11px] text-slate-600 leading-snug pt-1">{{ r.description }}</p>
                        </div>
                    </div>

                    <!-- Users Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-slate-100 text-slate-700 font-semibold border-b border-slate-200">
                                <tr>
                                    <th class="px-3 py-2 text-center w-10">No</th>
                                    <th class="px-3 py-2 text-left">Nama Pengguna</th>
                                    <th class="px-3 py-2 text-left">Username Login</th>
                                    <th class="px-3 py-2 text-left">Role / Hak Akses</th>
                                    <th class="px-3 py-2 text-left">Terhubung PPTK</th>
                                    <th class="px-3 py-2 text-left">Email Resmi</th>
                                    <th class="px-3 py-2 text-center w-36">Aksi &amp; Sandi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(u, idx) in users" :key="u.id" class="hover:bg-slate-50 transition-colors">
                                    <td class="px-3 py-2 text-center font-mono text-slate-500">{{ idx + 1 }}</td>
                                    <td class="px-3 py-2 font-bold text-slate-900">
                                        {{ u.name }}
                                        <span v-if="u.id === $page.props.auth?.user?.id" class="ml-1.5 px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            Akun Anda
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex items-center gap-1 font-mono font-bold text-emerald-900 px-2 py-0.5 rounded bg-emerald-50 border border-emerald-200">
                                            @{{ u.username || '-' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span 
                                            class="inline-block px-2 py-0.5 rounded text-[10px] font-bold border uppercase"
                                            :class="[
                                                u.role === 'superadmin' || u.role === 'admin' ? 'bg-emerald-100 text-emerald-800 border-emerald-300' :
                                                (u.role === 'bendahara' ? 'bg-blue-100 text-blue-800 border-blue-300' :
                                                (u.role === 'pptk' ? 'bg-amber-100 text-amber-800 border-amber-300' :
                                                'bg-purple-100 text-purple-800 border-purple-300'))
                                            ]"
                                        >
                                            {{ u.role }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span v-if="u.pptk" class="inline-flex items-center gap-1 font-medium text-amber-900 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                            <Users class="w-3 h-3 text-amber-700" />
                                            {{ u.pptk.display_name || u.pptk.name }}
                                        </span>
                                        <span v-else class="text-slate-400 font-mono text-[11px]">- (Seluruh SKPD)</span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600 font-mono text-[11px]">{{ u.email }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button 
                                                type="button"
                                                @click="openResetUserPasswordModal(u)"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 transition-colors cursor-pointer"
                                                title="Reset Password Pengguna"
                                            >
                                                <Key class="w-3 h-3 text-slate-500" />
                                                <span>Reset Sandi</span>
                                            </button>
                                            <button 
                                                type="button"
                                                @click="openEditUserModal(u)"
                                                class="p-1 rounded text-slate-600 hover:text-blue-700 hover:bg-blue-50 transition-colors cursor-pointer"
                                                title="Edit Profil & Role"
                                            >
                                                <Pencil class="w-3.5 h-3.5" />
                                            </button>
                                            <button 
                                                v-if="u.id !== 1 && u.id !== $page.props.auth?.user?.id"
                                                type="button"
                                                @click="confirmDeleteUser(u)"
                                                class="p-1 rounded text-slate-400 hover:text-red-700 hover:bg-red-50 transition-colors cursor-pointer"
                                                title="Hapus Akun Pengguna"
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

                <!-- Card: Keamanan Akun & Ganti Password Sendiri -->
                <div class="bg-white border border-slate-200/90 rounded-xl shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <KeyRound class="w-4 h-4 text-rose-600" />
                            <h2 class="text-xs sm:text-sm font-bold text-slate-800 uppercase tracking-wide">Ubah Password Akun Anda Sendiri</h2>
                        </div>
                        <span class="text-[11px] text-slate-500">Perbarui kata sandi akun login Anda yang sedang aktif</span>
                    </div>

                    <form @submit.prevent="submitPasswordChange" class="p-4 sm:p-5 grid grid-cols-1 md:grid-cols-4 gap-3.5 items-end text-xs">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Password Saat Ini</label>
                            <input
                                v-model="passwordForm.current_password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full px-3 py-1.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500"
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
                                class="w-full px-3 py-1.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500"
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
                                class="w-full px-3 py-1.5 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500"
                            />
                        </div>
                        <div>
                            <button
                                type="submit"
                                :disabled="passwordForm.processing"
                                class="w-full px-4 py-2 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white rounded-xl font-bold text-xs shadow-xs transition-colors cursor-pointer"
                            >
                                {{ passwordForm.processing ? 'Menyimpan...' : 'Simpan Password Baru' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tab 2: Master PPTK Management -->
            <div v-show="activeTab === 'pptk'" class="space-y-4">
                <!-- Card 4: Master PPTK Management -->
                <div class="bg-white border border-slate-200/90 rounded-xl shadow-xs overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Users class="w-4 h-4 text-emerald-700" />
                        <div>
                            <h2 class="text-xs sm:text-sm font-bold text-slate-800 uppercase tracking-wide">Daftar PPTK Sekretariat DPRD Banyumas</h2>
                            <p class="text-[11px] text-slate-500">
                                Tambah, ubah identitas pejabat/gelar, akun login, atau nonaktifkan PPTK ketika pergantian periode anggaran baru.
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
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#04331a] hover:bg-emerald-800 text-white rounded-xl text-xs font-bold transition-colors cursor-pointer shadow-xs"
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
                                <th class="px-3 py-2 text-left">Akun Login</th>
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
                                <td class="px-3 py-2">
                                    <span v-if="p.user?.username" class="inline-flex items-center gap-1 font-mono font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                        @{{ p.user.username }}
                                    </span>
                                    <button 
                                        v-else 
                                        type="button" 
                                        @click="openCreateUserModal(p.id)"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-300 cursor-pointer transition-colors"
                                        title="Buat Akun Login untuk PPTK ini"
                                    >
                                        <UserPlus class="w-3 h-3" />
                                        <span>+ Buat Akun</span>
                                    </button>
                                </td>
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
                                <td colspan="10" class="px-3 py-6 text-center text-slate-500">
                                    Belum ada data PPTK tersimpan. Klik tombol "+ Tambah PPTK Baru" di atas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>

            <!-- Tab 5: Rekam Jejak Audit Keamanan (Audit Trail) -->
            <div v-show="activeTab === 'audit'" class="space-y-4">
                <!-- Card 5: Audit Trail -->
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

                            <!-- Akun Login Otomatis PPTK -->
                            <div v-if="!isEditingPptk" class="sm:col-span-2 p-3 bg-emerald-50/70 border border-emerald-200 rounded-xl space-y-2.5">
                                <label class="flex items-center gap-2 cursor-pointer font-bold text-emerald-950 text-xs">
                                    <input 
                                        v-model="pptkForm.create_login_account" 
                                        type="checkbox" 
                                        class="rounded border-emerald-400 text-emerald-700 focus:ring-emerald-500 w-4 h-4" 
                                    />
                                    <span>Otomatis Buat Akun Login untuk PPTK ini</span>
                                </label>
                                <p class="text-[11px] text-emerald-800">
                                    Pejabat PPTK dapat langsung login ke sistem untuk memantau pagu dan sheet BKU kegiatannya.
                                </p>

                                <div v-if="pptkForm.create_login_account" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                    <div>
                                        <label class="block font-semibold text-emerald-950 mb-1 text-[11px]">
                                            Username Login Singkat <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-2.5 top-1.5 text-slate-400 font-mono text-xs">@</span>
                                            <input 
                                                v-model="pptkForm.username" 
                                                type="text" 
                                                placeholder="mis: sugeng" 
                                                class="w-full pl-6 pr-2.5 py-1.5 font-mono text-xs border border-emerald-300 rounded bg-white focus:ring-1 focus:ring-emerald-700 outline-none" 
                                            />
                                        </div>
                                        <span class="text-[10px] text-slate-500">Username singkat untuk login</span>
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-emerald-950 mb-1 text-[11px]">
                                            Password Login Awal
                                        </label>
                                        <input 
                                            v-model="pptkForm.password" 
                                            type="text" 
                                            placeholder="PptkSugeng#2026" 
                                            class="w-full px-2.5 py-1.5 font-mono text-xs border border-emerald-300 rounded bg-white focus:ring-1 focus:ring-emerald-700 outline-none" 
                                        />
                                        <span class="text-[10px] text-slate-500">Password default aman</span>
                                    </div>
                                </div>
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
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150">
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
                        
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-900 space-y-1">
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
                                class="px-3 py-1.5 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl font-semibold transition-colors cursor-pointer"
                            >
                                Batal
                            </button>
                            <button 
                                type="button" 
                                @click="executeDeletePptk"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-red-700 hover:bg-red-800 text-white rounded-xl font-bold transition-colors cursor-pointer shadow-xs"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                                <span>Lanjutkan Hapus</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Konfirmasi Hapus Permanen Tahun Anggaran -->
            <div 
                v-if="showDeleteYearModal && yearToDelete" 
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            >
                <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-5 py-4 bg-red-900 text-white flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <AlertTriangle class="w-5 h-5 text-amber-300 shrink-0" />
                            <h3 class="text-xs sm:text-sm font-bold uppercase tracking-wide">Hapus Permanen TA {{ yearToDelete.year }}</h3>
                        </div>
                        <button 
                            type="button" 
                            @click="showDeleteYearModal = false"
                            class="text-red-200 hover:text-white transition-colors cursor-pointer"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-5 space-y-4 text-xs">
                        <p class="text-slate-700 leading-relaxed">
                            Tindakan ini akan <strong class="text-red-700 font-bold">menghapus secara permanen</strong> seluruh data pembukuan Tahun Anggaran <strong class="text-slate-900 font-bold font-mono">TA {{ yearToDelete.year }}</strong> dan tidak dapat dipulihkan:
                        </p>
                        
                        <div class="p-3.5 bg-red-50 border border-red-200 rounded-xl text-red-900 space-y-2">
                            <div class="font-bold flex items-center gap-1.5 text-xs text-red-950">
                                <Trash2 class="w-4 h-4 text-red-700 shrink-0" />
                                <span>Data yang akan dibersihkan tuntas:</span>
                            </div>
                            <ul class="list-disc pl-5 space-y-1 text-[11px] text-red-800">
                                <li><strong class="font-semibold">{{ yearToDelete.tx_count.toLocaleString('id-ID') }} baris</strong> transaksi BKU &amp; Buku Bank</li>
                                <li>Seluruh data realisasi pencairan pagu TA {{ yearToDelete.year }}</li>
                                <li>Penguncian 12 periode bulan TA {{ yearToDelete.year }}</li>
                                <li>Pengaturan master &amp; penyesuaian manual TA {{ yearToDelete.year }}</li>
                            </ul>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-800">
                                Ketik angka <span class="font-mono text-red-700 bg-red-50 px-1.5 py-0.5 rounded border border-red-200">{{ yearToDelete.year }}</span> untuk konfirmasi:
                            </label>
                            <input 
                                v-model="confirmYearInput"
                                type="text"
                                :placeholder="`Ketik ${yearToDelete.year}`"
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-xs font-mono font-bold focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                @keyup.enter="confirmYearInput === String(yearToDelete.year) && executeDeleteYear()"
                            />
                        </div>

                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button 
                                type="button" 
                                @click="showDeleteYearModal = false"
                                class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl font-semibold transition-colors cursor-pointer text-xs"
                            >
                                Batal
                            </button>
                            <button 
                                type="button" 
                                @click="executeDeleteYear"
                                :disabled="confirmYearInput !== String(yearToDelete.year) || isDeletingYear"
                                :class="[
                                    'inline-flex items-center gap-1.5 px-4 py-2 text-white rounded-xl font-bold transition-all text-xs shadow-xs',
                                    confirmYearInput === String(yearToDelete.year) && !isDeletingYear
                                        ? 'bg-red-700 hover:bg-red-800 cursor-pointer'
                                        : 'bg-slate-300 text-slate-500 cursor-not-allowed opacity-70'
                                ]"
                            >
                                <Trash2 class="w-4 h-4" />
                                <span>{{ isDeletingYear ? 'Menghapus Data...' : 'Hapus Permanen TA ' + yearToDelete.year }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Tambah / Edit Pengguna & Role -->
            <div 
                v-if="showUserModal" 
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            >
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-5 py-3.5 bg-[#04331a] text-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <UserPlus v-if="!isEditingUser" class="w-4 h-4 text-emerald-300" />
                            <UserCog v-else class="w-4 h-4 text-emerald-300" />
                            <h3 class="text-xs sm:text-sm font-bold uppercase tracking-wide">
                                {{ isEditingUser ? 'Ubah Pengguna & Hak Akses (Role)' : 'Tambah Pengguna Baru' }}
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

                    <form @submit.prevent="submitUserForm" class="p-5 space-y-3.5 text-xs">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">
                                Nama Lengkap Pengguna <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="userForm.name" 
                                type="text" 
                                required
                                placeholder="misal: Irna Setiawati / Budi Santoso"
                                class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 outline-none"
                            />
                            <div v-if="userForm.errors.name" class="text-red-600 text-[10px] mt-0.5">{{ userForm.errors.name }}</div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">
                                    Username Login <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-slate-400 font-mono text-xs">@</span>
                                    <input 
                                        v-model="userForm.username" 
                                        type="text" 
                                        required
                                        placeholder="mis: irna / bendahara"
                                        class="w-full pl-7 pr-3 py-2 font-mono border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 outline-none"
                                    />
                                </div>
                                <span class="text-[10px] text-slate-400">Username singkat untuk login</span>
                                <div v-if="userForm.errors.username" class="text-red-600 text-[10px] mt-0.5">{{ userForm.errors.username }}</div>
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">
                                    Email Resmi
                                </label>
                                <input 
                                    v-model="userForm.email" 
                                    type="email" 
                                    placeholder="nama@dprd.banyumaskab.go.id"
                                    class="w-full px-3 py-2 font-mono border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 outline-none"
                                />
                                <span class="text-[10px] text-slate-400">Opsional (otomatis jika kosong)</span>
                                <div v-if="userForm.errors.email" class="text-red-600 text-[10px] mt-0.5">{{ userForm.errors.email }}</div>
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">
                                Hak Akses / Role <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="userForm.role"
                                required
                                class="w-full px-3 py-2 border border-slate-300 rounded-xl text-xs bg-white focus:ring-2 focus:ring-emerald-500 outline-none font-semibold text-slate-800"
                            >
                                <option value="superadmin">Super Admin (Akses Penuh Seluruh Modul &amp; Pengaturan)</option>
                                <option value="bendahara">Bendahara Pengeluaran (BKU, Bank, Rekonsiliasi, Gaji, Pagu)</option>
                                <option value="operator">Operator Pembukuan (Input Harian BKU &amp; Rekonsiliasi)</option>
                                <option value="pptk">PPTK (Khusus Pantauan Realisasi Pagu &amp; BKU Terkait)</option>
                            </select>
                            <div v-if="userForm.errors.role" class="text-red-600 text-[10px] mt-0.5">{{ userForm.errors.role }}</div>
                        </div>

                        <!-- Jika role PPTK, pilih PPTK terhubung -->
                        <div v-if="userForm.role === 'pptk'" class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl space-y-1.5">
                            <label class="block font-bold text-amber-950 text-xs">
                                Terhubung dengan PPTK Siapa? <span class="text-red-500">*</span>
                            </label>
                            <select 
                                v-model="userForm.pptk_id"
                                class="w-full px-3 py-2 border border-amber-300 rounded-xl text-xs bg-white focus:ring-2 focus:ring-amber-500 outline-none font-medium"
                            >
                                <option value="">-- Pilih Nama PPTK --</option>
                                <option v-for="p in pptks" :key="p.id" :value="p.id">
                                    {{ p.display_name || p.name }} ({{ p.code }}) - {{ p.subunit?.name || 'Sekretariat' }}
                                </option>
                            </select>
                            <p class="text-[10px] text-amber-800">
                                Akun ini hanya akan melihat dan memfilter data pagu &amp; transaksi BKU milik PPTK ini.
                            </p>
                        </div>

                        <div v-if="!isEditingUser">
                            <label class="block font-semibold text-slate-700 mb-1">
                                Password Login Awal <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="userForm.password" 
                                type="text" 
                                required
                                minlength="8"
                                placeholder="Minimal 8 karakter, misal: PptkIrna#2026"
                                class="w-full px-3 py-2 font-mono border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 outline-none"
                            />
                            <div v-if="userForm.errors.password" class="text-red-600 text-[10px] mt-0.5">{{ userForm.errors.password }}</div>
                        </div>

                        <div v-else>
                            <label class="block font-semibold text-slate-700 mb-1">
                                Password Baru (Kosongkan jika tidak ingin mengubah)
                            </label>
                            <input 
                                v-model="userForm.password" 
                                type="text" 
                                minlength="8"
                                placeholder="Biarkan kosong jika tidak diubah"
                                class="w-full px-3 py-2 font-mono border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 outline-none"
                            />
                            <div v-if="userForm.errors.password" class="text-red-600 text-[10px] mt-0.5">{{ userForm.errors.password }}</div>
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                            <button 
                                type="button" 
                                @click="showUserModal = false"
                                class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl font-semibold transition-colors cursor-pointer text-xs"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="userForm.processing"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#04331a] hover:bg-emerald-800 disabled:opacity-50 text-white rounded-xl font-bold transition-colors cursor-pointer shadow-xs text-xs"
                            >
                                <Save class="w-3.5 h-3.5" />
                                <span>{{ userForm.processing ? 'Menyimpan...' : (isEditingUser ? 'Perbarui Pengguna' : 'Simpan Pengguna') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal Reset Password Pengguna oleh Admin -->
            <div 
                v-if="showResetUserPasswordModal" 
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            >
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-5 py-3.5 bg-slate-900 text-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Key class="w-4 h-4 text-amber-300" />
                            <h3 class="text-xs sm:text-sm font-bold uppercase tracking-wide">
                                Reset Password @{{ resetUserPasswordForm.username }}
                            </h3>
                        </div>
                        <button 
                            type="button" 
                            @click="showResetUserPasswordModal = false"
                            class="text-slate-300 hover:text-white transition-colors cursor-pointer"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form @submit.prevent="submitResetUserPassword" class="p-5 space-y-3.5 text-xs">
                        <p class="text-slate-600 text-xs">
                            Masukkan password baru untuk akun pengguna <strong>{{ resetUserPasswordForm.name }}</strong> (<code>@{{ resetUserPasswordForm.username }}</code>):
                        </p>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">
                                Password Baru (Min. 8 Karakter) <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="resetUserPasswordForm.password" 
                                type="text" 
                                required
                                minlength="8"
                                placeholder="Masukkan password baru"
                                class="w-full px-3 py-2 font-mono border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 outline-none"
                            />
                            <div v-if="resetUserPasswordForm.errors.password" class="text-red-600 text-[10px] mt-0.5">{{ resetUserPasswordForm.errors.password }}</div>
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">
                                Konfirmasi Password Baru <span class="text-red-500">*</span>
                            </label>
                            <input 
                                v-model="resetUserPasswordForm.password_confirmation" 
                                type="text" 
                                required
                                minlength="8"
                                placeholder="Ulangi password baru"
                                class="w-full px-3 py-2 font-mono border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 outline-none"
                            />
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                            <button 
                                type="button" 
                                @click="showResetUserPasswordModal = false"
                                class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl font-semibold transition-colors cursor-pointer text-xs"
                            >
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="resetUserPasswordForm.processing"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white rounded-xl font-bold transition-colors cursor-pointer shadow-xs text-xs"
                            >
                                <Check class="w-3.5 h-3.5" />
                                <span>{{ resetUserPasswordForm.processing ? 'Menyimpan...' : 'Perbarui Password' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal Konfirmasi Hapus Akun Pengguna -->
            <div 
                v-if="showDeleteUserModal && userToDelete" 
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            >
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                    <div class="px-5 py-3.5 bg-red-800 text-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="w-4 h-4 text-amber-300" />
                            <h3 class="text-xs sm:text-sm font-bold uppercase tracking-wide">Konfirmasi Hapus Akun</h3>
                        </div>
                        <button 
                            type="button" 
                            @click="showDeleteUserModal = false"
                            class="text-red-200 hover:text-white transition-colors cursor-pointer"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="p-5 space-y-3.5 text-xs">
                        <p class="text-slate-700 leading-relaxed">
                            Apakah Anda yakin ingin menghapus akun pengguna <strong class="text-slate-900 font-bold">"{{ userToDelete.name }}"</strong> (<code>@{{ userToDelete.username }}</code>) dengan role <span class="uppercase font-bold text-red-700">{{ userToDelete.role }}</span>?
                        </p>
                        <p class="text-slate-500 text-[11px]">
                            Pengguna ini tidak akan dapat login lagi ke sistem. Riwayat pembukuan dan audit trail sebelumnya tetap tersimpan rapi di sistem.
                        </p>

                        <div class="pt-2 flex items-center justify-end gap-2">
                            <button 
                                type="button" 
                                @click="showDeleteUserModal = false"
                                class="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl font-semibold transition-colors cursor-pointer text-xs"
                            >
                                Batal
                            </button>
                            <button 
                                type="button" 
                                @click="executeDeleteUser"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-700 hover:bg-red-800 text-white rounded-xl font-bold transition-colors cursor-pointer shadow-xs text-xs"
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
