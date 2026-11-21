<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Plus,
    Search,
    Filter,
    Edit2,
    Trash2,
    CheckCircle2,
    Clock,
    X,
    Receipt,
    ArrowUpRight,
    ArrowDownLeft,
    AlertCircle,
    FileText,
    Calendar,
    User,
    Tag
} from 'lucide-vue-next';

const props = defineProps({
    transactions: Object,
    activities: Array,
    pptks: Array,
    filters: Object,
    year: Number,
    is_pptk_user: Boolean,
});

const formatCurrency = (val) => {
    return 'Rp ' + Number(val || 0).toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const cleanStr = String(dateStr).includes('T') ? String(dateStr).split('T')[0] : String(dateStr).substring(0, 10);
    try {
        const parts = cleanStr.split('-');
        if (parts.length !== 3) return dateStr;
        const [year, month, day] = parts;
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const mIdx = parseInt(month, 10) - 1;
        if (mIdx < 0 || mIdx > 11) return cleanStr;
        return `${parseInt(day, 10)} ${months[mIdx]} ${year}`;
    } catch (e) {
        return cleanStr;
    }
};

// Filter State
const search = ref(props.filters.search || '');
const filterPptk = ref(props.filters.pptk_id || '');
const filterActivity = ref(props.filters.activity_id || '');
const filterType = ref(props.filters.type || '');
const filterSpj = ref(props.filters.is_spj_submitted ?? '');

const applyFilters = () => {
    router.get('/pagu/transaksi', {
        search: search.value || undefined,
        pptk_id: filterPptk.value || undefined,
        activity_id: filterActivity.value || undefined,
        type: filterType.value || undefined,
        is_spj_submitted: filterSpj.value !== '' ? filterSpj.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    search.value = '';
    filterPptk.value = '';
    filterActivity.value = '';
    filterType.value = '';
    filterSpj.value = '';
    applyFilters();
};

const currentPerPage = computed(() => {
    return Number(props.filters?.per_page || 15);
});

const changePerPage = (size) => {
    const val = size === 'all' ? 99999 : size;
    router.get('/pagu/transaksi', {
        search: search.value || undefined,
        pptk_id: filterPptk.value || undefined,
        activity_id: filterActivity.value || undefined,
        type: filterType.value || undefined,
        is_spj_submitted: filterSpj.value !== '' ? filterSpj.value : undefined,
        per_page: val,
        page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const cleanPaginationLabel = (label) => {
    if (!label) return '';
    const clean = String(label).trim();
    if (clean.includes('Previous') || clean.includes('previous') || clean.includes('&laquo;')) {
        return 'Sebelumnya';
    }
    if (clean.includes('Next') || clean.includes('next') || clean.includes('&raquo;')) {
        return 'Berikutnya';
    }
    return clean;
};

// Modal State
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
    pptk_id: '',
    activity_id: '',
    transaction_date: new Date().toISOString().split('T')[0],
    type: 'pencairan',
    amount: '',
    recipient: '',
    description: '',
    source_fund: 'up_gu',
    document_number: '',
    is_spj_submitted: false,
    spj_submission_date: '',
    spj_document_number: '',
    notes: '',
});

const openCreateModal = (type = 'pencairan') => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.type = type;
    form.transaction_date = new Date().toISOString().split('T')[0];
    if (props.pptks.length === 1) {
        form.pptk_id = props.pptks[0].id;
    }
    isModalOpen.value = true;
};

const openEditModal = (tx) => {
    isEditing.value = true;
    editingId.value = tx.id;
    form.pptk_id = tx.pptk_id;
    form.activity_id = tx.activity_id || '';
    form.transaction_date = tx.transaction_date;
    form.type = tx.type;
    form.amount = tx.amount;
    form.recipient = tx.recipient || '';
    form.description = tx.description;
    form.source_fund = tx.source_fund || 'up_gu';
    form.document_number = tx.document_number || '';
    form.is_spj_submitted = !!tx.is_spj_submitted;
    form.spj_submission_date = tx.spj_submission_date || '';
    form.spj_document_number = tx.spj_document_number || '';
    form.notes = tx.notes || '';
    isModalOpen.value = true;
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(`/pagu/transaksi/${editingId.value}`, {
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    } else {
        form.post('/pagu/transaksi', {
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    }
};

const deleteTransaction = (id) => {
    if (confirm('Yakin ingin menghapus catatan transaksi realisasi ini?')) {
        router.delete(`/pagu/transaksi/${id}`, {
            preserveScroll: true,
        });
    }
};

const toggleSpj = (tx) => {
    const newStatus = !tx.is_spj_submitted;
    router.post(`/pagu/transaksi/${tx.id}/toggle-spj`, {
        is_spj_submitted: newStatus,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Pencatatan Realisasi & Pengembalian Kas Pagu" />

        <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
            <!-- Header (White Card) -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-emerald-600">
                        <Receipt class="w-4 h-4" />
                        <span>Buku Catatan Kas Kegiatan Real-Time</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 mt-1">
                        Pencatatan Realisasi & Pengembalian Kas
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                        Catat distribusi uang kegiatan saat dicairkan ke PPTK dan catat pengembalian sisa jika ada kas yang tidak terpakai.
                    </p>
                </div>

                <div v-if="!is_pptk_user" class="flex items-center gap-2.5 flex-wrap">
                    <button
                        type="button"
                        @click="openCreateModal('pencairan')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer"
                    >
                        <Plus class="w-4 h-4" />
                        <span>+ Pencairan Dana Kegiatan</span>
                    </button>

                    <button
                        type="button"
                        @click="openCreateModal('pengembalian')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer"
                    >
                        <ArrowDownLeft class="w-4 h-4" />
                        <span>+ Pengembalian Sisa Kas</span>
                    </button>
                </div>
                <div v-else class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-800 text-xs font-bold rounded-xl border border-emerald-200">
                        <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600" />
                        <span>Mode Tinjauan PPTK (View-Only)</span>
                    </span>
                </div>
            </div>

            <!-- Filter Card (White Card) -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <!-- Search Input -->
                    <div class="relative lg:col-span-2">
                        <input
                            v-model="search"
                            @keyup.enter="applyFilters"
                            type="text"
                            placeholder="Cari uraian, penerima, nomor bukti..."
                            class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:ring-2 focus:ring-emerald-500"
                        />
                        <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    </div>

                    <!-- Filter PPTK -->
                    <div v-if="!is_pptk_user" class="relative">
                        <select
                            v-model="filterPptk"
                            @change="applyFilters"
                            class="w-full appearance-none pl-3 pr-8 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500 cursor-pointer"
                        >
                            <option value="">Semua PPTK</option>
                            <option v-for="p in pptks" :key="p.id" :value="p.id">
                                {{ p.display_name }}
                            </option>
                        </select>
                        <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-slate-400">▼</span>
                    </div>

                    <!-- Filter Tipe -->
                    <div class="relative">
                        <select
                            v-model="filterType"
                            @change="applyFilters"
                            class="w-full appearance-none pl-3 pr-8 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500 cursor-pointer"
                        >
                            <option value="">Semua Tipe Kas</option>
                            <option value="pencairan">Pencairan Dana</option>
                            <option value="pengembalian">Pengembalian Sisa</option>
                        </select>
                        <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-slate-400">▼</span>
                    </div>

                    <!-- Filter Status SPJ -->
                    <div class="relative">
                        <select
                            v-model="filterSpj"
                            @change="applyFilters"
                            class="w-full appearance-none pl-3 pr-8 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500 cursor-pointer"
                        >
                            <option value="">Semua Status SPJ</option>
                            <option value="0">Belum Di-SPJ-kan (Pending)</option>
                            <option value="1">Sudah Di-SPJ-kan ke SIPD</option>
                        </select>
                        <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-slate-400">▼</span>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs pt-1">
                    <span class="text-slate-500">
                        Menampilkan <strong>{{ transactions.total }}</strong> transaksi
                    </span>
                    <button
                        type="button"
                        @click="resetFilters"
                        class="text-emerald-600 hover:text-emerald-700 font-semibold cursor-pointer"
                    >
                        Reset Filter
                    </button>
                </div>
            </div>

            <!-- Table of Transactions (White Card) -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left min-w-[1050px]">
                        <thead class="bg-slate-50 text-slate-700 font-bold uppercase tracking-wider text-[11px] border-b border-slate-200">
                            <tr>
                                <th class="px-5 py-3.5 whitespace-nowrap">Tanggal & No Bukti</th>
                                <th class="px-5 py-3.5 whitespace-nowrap min-w-[170px]">PPTK / Penerima</th>
                                <th class="px-5 py-3.5 min-w-[320px]">Sub Kegiatan & Uraian</th>
                                <th class="px-5 py-3.5 text-center whitespace-nowrap">Tipe Kas</th>
                                <th class="px-5 py-3.5 text-right whitespace-nowrap">Nominal Kas</th>
                                <th class="px-5 py-3.5 text-center whitespace-nowrap">Status SPJ SIPD</th>
                                <th v-if="!is_pptk_user" class="px-5 py-3.5 text-center whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="transactions.data.length === 0">
                                <td :colspan="is_pptk_user ? 6 : 7" class="px-5 py-12 text-center text-slate-400 text-xs italic">
                                    Tidak ada catatan transaksi yang sesuai dengan filter.
                                </td>
                            </tr>
                            <tr
                                v-for="tx in transactions.data"
                                :key="tx.id"
                                class="hover:bg-slate-50/80 transition-colors"
                            >
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="font-bold font-sans text-slate-900 text-xs sm:text-sm">{{ formatDate(tx.transaction_date) }}</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">{{ tx.document_number || 'Tanpa No Bukti' }}</div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-900 text-xs sm:text-sm">{{ tx.pptk?.display_name }}</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">Penerima: {{ tx.recipient || '-' }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    <div v-if="tx.activity" class="text-[11px] font-sans font-semibold text-slate-700 leading-snug">
                                        <span class="inline-block px-1.5 py-0.5 bg-slate-100 text-slate-700 rounded text-[10px] font-mono mr-1">
                                            {{ tx.activity.code }}
                                        </span>
                                        <span>{{ tx.activity.name }}</span>
                                    </div>
                                    <div v-else class="text-[11px] font-semibold text-slate-500">
                                        Pencairan Umum PPTK
                                    </div>
                                    <div class="text-xs text-slate-900 mt-1 leading-relaxed">
                                        {{ tx.description }}
                                    </div>
                                    <div v-if="tx.notes" class="text-[10px] text-slate-400 italic mt-0.5">
                                        Catatan: {{ tx.notes }}
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-center whitespace-nowrap">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold inline-flex items-center gap-1"
                                        :class="tx.type === 'pencairan' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200'"
                                    >
                                        <ArrowUpRight v-if="tx.type === 'pencairan'" class="w-3 h-3 text-emerald-600" />
                                        <ArrowDownLeft v-else class="w-3 h-3 text-amber-600" />
                                        <span>{{ tx.type === 'pencairan' ? 'Pencairan' : 'Pengembalian' }}</span>
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right whitespace-nowrap font-sans tabular-nums font-bold text-slate-900 text-xs sm:text-sm">
                                    {{ tx.type === 'pengembalian' ? '-' : '' }}{{ formatCurrency(tx.amount) }}
                                </td>
                                <td class="px-5 py-4 text-center whitespace-nowrap">
                                    <button
                                        v-if="!is_pptk_user"
                                        type="button"
                                        @click="toggleSpj(tx)"
                                        class="px-2.5 py-1 rounded-lg text-[10px] font-bold cursor-pointer transition-colors inline-flex items-center gap-1.5 border"
                                        :class="tx.is_spj_submitted ? 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                                        title="Klik untuk mengubah status SPJ"
                                    >
                                        <CheckCircle2 v-if="tx.is_spj_submitted" class="w-3 h-3 text-blue-600" />
                                        <Clock v-else class="w-3 h-3 text-slate-400" />
                                        <span>{{ tx.is_spj_submitted ? 'Sudah SPJ SIPD' : 'Belum SPJ' }}</span>
                                    </button>
                                    <span
                                        v-else
                                        class="px-2.5 py-1 rounded-lg text-[10px] font-bold inline-flex items-center gap-1.5 border"
                                        :class="tx.is_spj_submitted ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-slate-50 text-slate-600 border-slate-200'"
                                    >
                                        <CheckCircle2 v-if="tx.is_spj_submitted" class="w-3 h-3 text-blue-600" />
                                        <Clock v-else class="w-3 h-3 text-slate-400" />
                                        <span>{{ tx.is_spj_submitted ? 'Sudah SPJ SIPD' : 'Belum SPJ' }}</span>
                                    </span>
                                </td>
                                <td v-if="!is_pptk_user" class="px-5 py-4 text-center whitespace-nowrap space-x-1.5">
                                    <button
                                        type="button"
                                        @click="openEditModal(tx)"
                                        class="p-1.5 text-slate-500 hover:text-emerald-600 hover:bg-slate-100 rounded-lg cursor-pointer transition-colors"
                                        title="Edit Transaksi"
                                    >
                                        <Edit2 class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="deleteTransaction(tx.id)"
                                        class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-slate-100 rounded-lg cursor-pointer transition-colors"
                                        title="Hapus Transaksi"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table Pagination Controls matching BKU & Buku Bank Standard -->
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs select-none bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 print:hidden no-print">
                    <!-- Left: Rows per page selector & Count info -->
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1.5">
                            <span class="text-slate-500 font-medium">Tampilkan:</span>
                            <div class="inline-flex rounded-md shadow-2xs">
                                <button
                                    v-for="size in [10, 20, 50, 100, 'all']"
                                    :key="size"
                                    @click="changePerPage(size)"
                                    :class="[
                                        'px-2.5 py-1 text-xs font-semibold border first:rounded-l-md last:rounded-r-md -ml-px transition-colors cursor-pointer',
                                        (size === 'all' && currentPerPage >= 9999) || currentPerPage === size
                                            ? 'bg-[#107c41] border-[#107c41] text-white z-10'
                                            : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-100'
                                    ]"
                                >
                                    {{ size === 'all' ? 'Semua' : size }}
                                </button>
                            </div>
                        </div>

                        <span class="text-slate-300">|</span>

                        <span class="text-slate-600">
                            Menampilkan 
                            <strong class="text-slate-900 font-mono">{{ transactions.total === 0 ? 0 : (transactions.from || 1) }}</strong> - 
                            <strong class="text-slate-900 font-mono">{{ transactions.to || transactions.total }}</strong> 
                            dari <strong class="text-slate-900 font-mono">{{ transactions.total }}</strong> data
                        </span>
                    </div>

                    <!-- Right: Page navigation links -->
                    <div v-if="transactions.links && transactions.links.length > 3" class="flex items-center gap-1">
                        <template v-for="(link, idx) in transactions.links" :key="idx">
                            <span v-if="link.label === '...'" class="px-2 text-slate-400">...</span>
                            <Link
                                v-else-if="link.url"
                                :href="link.url"
                                :class="[
                                    'min-w-[28px] h-7 px-2.5 flex items-center justify-center text-xs font-semibold rounded-md border transition-colors shadow-2xs cursor-pointer',
                                    link.active
                                        ? 'bg-[#107c41] border-[#107c41] text-white shadow-2xs'
                                        : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-100'
                                ]"
                            >
                                {{ cleanPaginationLabel(link.label) }}
                            </Link>
                            <span
                                v-else
                                class="min-w-[28px] h-7 px-2.5 flex items-center justify-center text-xs font-medium rounded-md border border-slate-200 bg-white text-slate-400 opacity-50 cursor-not-allowed"
                            >
                                {{ cleanPaginationLabel(link.label) }}
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Modal (White Card) -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <div
                            class="p-2 rounded-xl"
                            :class="form.type === 'pencairan' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                        >
                            <ArrowUpRight v-if="form.type === 'pencairan'" class="w-5 h-5" />
                            <ArrowDownLeft v-else class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">
                                {{ isEditing ? 'Edit Transaksi' : (form.type === 'pencairan' ? 'Catat Pencairan Kas Kegiatan' : 'Catat Pengembalian Sisa Kas') }}
                            </h3>
                            <p class="text-xs text-slate-500">
                                {{ form.type === 'pencairan' ? 'Distribusi kas ke PPTK/dewan sebelum kegiatan' : 'Pengembalian uang kegiatan yang tidak terpakai' }}
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="isModalOpen = false" class="p-1 text-slate-400 hover:text-slate-600 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4 text-xs">
                    <!-- Tipe Transaksi -->
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tipe Transaksi</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="form.type = 'pencairan'"
                                class="py-2 px-3 rounded-xl border text-xs font-bold cursor-pointer transition-colors"
                                :class="form.type === 'pencairan' ? 'bg-emerald-50 border-emerald-500 text-emerald-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                            >
                                Pencairan Kas ke PPTK
                            </button>
                            <button
                                type="button"
                                @click="form.type = 'pengembalian'"
                                class="py-2 px-3 rounded-xl border text-xs font-bold cursor-pointer transition-colors"
                                :class="form.type === 'pengembalian' ? 'bg-amber-50 border-amber-500 text-amber-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
                            >
                                Pengembalian Sisa Kas
                            </button>
                        </div>
                    </div>

                    <!-- PPTK -->
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">PPTK Penanggung Jawab *</label>
                        <select
                            v-model="form.pptk_id"
                            required
                            class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-xl text-xs font-medium text-slate-900"
                        >
                            <option value="" disabled>Pilih PPTK</option>
                            <option v-for="p in pptks" :key="p.id" :value="p.id">
                                {{ p.display_name }}
                            </option>
                        </select>
                    </div>

                    <!-- Sub Kegiatan -->
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Sub Kegiatan (Pagu Anggaran)</label>
                        <select
                            v-model="form.activity_id"
                            class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-xl text-xs font-medium text-slate-900"
                        >
                            <option value="">-- Pencairan Umum PPTK (Non-Subkegiatan) --</option>
                            <option v-for="act in activities" :key="act.id" :value="act.id">
                                {{ act.code }} - {{ act.name }} (Pagu: {{ formatCurrency(act.pagu_budget) }})
                            </option>
                        </select>
                    </div>

                    <!-- Tanggal & Nominal -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Tanggal Transaksi *</label>
                            <input
                                v-model="form.transaction_date"
                                type="date"
                                required
                                class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-xl text-xs font-medium text-slate-900"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nominal (Rupiah) *</label>
                            <input
                                v-model="form.amount"
                                type="number"
                                step="any"
                                min="1"
                                required
                                placeholder="Contoh: 15000000"
                                class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-xl text-xs font-sans tabular-nums font-bold text-slate-900"
                            />
                        </div>
                    </div>

                    <!-- Penerima & Sumber Dana -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Penerima Kas / Pelaksana</label>
                            <input
                                v-model="form.recipient"
                                type="text"
                                placeholder="Contoh: Komisi A / Panitia Rapat"
                                class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Sumber Kas</label>
                            <select
                                v-model="form.source_fund"
                                class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900"
                            >
                                <option value="up_gu">Uang Persediaan / GU (Kas UP)</option>
                                <option value="tu">Tambah Uang (TU)</option>
                                <option value="ls">Langsung (LS)</option>
                                <option value="transfer">Transfer Bank</option>
                                <option value="tunai">Kas Tunai</option>
                            </select>
                        </div>
                    </div>

                    <!-- Uraian Keperluan -->
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Uraian Keperluan Kegiatan *</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            required
                            placeholder="Tuliskan uraian jelas mengenai pencairan dana kegiatan atau pengembalian sisa..."
                            class="w-full py-2 px-3 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900"
                        ></textarea>
                    </div>

                    <!-- Status SPJ SIPD -->
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="form.is_spj_submitted"
                                class="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500"
                            />
                            <span class="font-bold text-slate-900 text-xs">
                                Sudah diajukan & di-acc SPJ di SIPD
                            </span>
                        </label>

                        <div v-if="form.is_spj_submitted" class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 border-t border-slate-200">
                            <div>
                                <label class="block text-[11px] text-slate-500">Nomor Bukti SPJ SIPD</label>
                                <input
                                    v-model="form.spj_document_number"
                                    type="text"
                                    placeholder="Contoh: 0012/SPJ/SETWAN/2026"
                                    class="w-full py-1.5 px-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900"
                                />
                            </div>
                            <div>
                                <label class="block text-[11px] text-slate-500">Tanggal Pengajuan SPJ</label>
                                <input
                                    v-model="form.spj_submission_date"
                                    type="date"
                                    class="w-full py-1.5 px-2 bg-white border border-slate-300 rounded-lg text-xs text-slate-900"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            type="button"
                            @click="isModalOpen = false"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs cursor-pointer shadow-xs disabled:opacity-50"
                        >
                            {{ isEditing ? 'Simpan Perubahan' : 'Catat Transaksi' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
