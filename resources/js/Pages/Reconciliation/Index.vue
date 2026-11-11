<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    ArrowLeftRight, 
    CheckCircle2, 
    AlertTriangle, 
    Search, 
    Filter, 
    Check, 
    UserCheck,
    Calendar,
    HelpCircle
} from 'lucide-vue-next';

const props = defineProps({
    year: Number,
    current_month: Number,
    stats: Object,
    items: Array,
    pptks: Array,
});

const searchQuery = ref('');
const statusFilter = ref('all');

const switchMonth = (m) => {
    router.get('/rekonsiliasi', { month: m, year: props.year }, { preserveState: true, preserveScroll: true });
};

const assignPptk = (txId, pptkId) => {
    router.post(`/rekonsiliasi/assign/${txId}`, { pptk_id: pptkId }, { preserveScroll: true });
};

const formatRupiah = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '0';
    return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

const filteredItems = computed(() => {
    return props.items.filter(item => {
        const matchesSearch = !searchQuery.value || 
            item.uraian.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            item.tanggal.includes(searchQuery.value) ||
            item.pptk_name.toLowerCase().includes(searchQuery.value.toLowerCase());

        const matchesStatus = statusFilter.value === 'all' || 
            (statusFilter.value === 'cocok' && item.status_rekonsiliasi === 'cocok') ||
            (statusFilter.value === 'perlu_periksa' && item.status_rekonsiliasi === 'perlu_periksa');

        return matchesSearch && matchesStatus;
    });
});
</script>

<script>
import { computed } from 'vue';
export default {
    computed: {
        filteredItems() {
            return this.items.filter(item => {
                const matchesSearch = !this.searchQuery || 
                    item.uraian.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    item.tanggal.includes(this.searchQuery) ||
                    item.pptk_name.toLowerCase().includes(this.searchQuery.toLowerCase());

                const matchesStatus = this.statusFilter === 'all' || 
                    (this.statusFilter === 'cocok' && item.status_rekonsiliasi === 'cocok') ||
                    (this.statusFilter === 'perlu_periksa' && item.status_rekonsiliasi === 'perlu_periksa');

                return matchesSearch && matchesStatus;
            });
        }
    }
}
</script>

<template>
    <AppLayout>
        <Head title="Rekonsiliasi Buku Bank & BKU" />

        <div class="space-y-4">
            
            <!-- Page Title -->
            <div class="bg-white border border-slate-200 rounded p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                    <h1 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <ArrowLeftRight class="w-5 h-5 text-indigo-600" />
                        Rekonsiliasi Buku Bank vs BKU Per PPTK
                    </h1>
                    <p class="text-xs text-slate-500">
                        Pencocokan mutasi bank giro dengan bukti pertanggungjawaban BKU untuk memastikan tidak ada transaksi yang terlewat atau selisih alokasi.
                    </p>
                </div>

                <!-- Month Selector -->
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600">Bulan:</span>
                    <select :value="current_month" @change="switchMonth($event.target.value)" class="px-2.5 py-1 text-xs border border-slate-300 rounded bg-white font-medium">
                        <option v-for="m in 12" :key="m" :value="m">Bulan {{ m }}</option>
                    </select>
                </div>
            </div>

            <!-- Stats KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div class="bg-white border border-slate-200 rounded p-3 shadow-sm">
                    <div class="text-xs text-slate-500 font-medium">Total Mutasi Bank</div>
                    <div class="text-xl font-bold font-mono text-slate-900 mt-1">{{ stats.total_bank }} <span class="text-xs font-normal text-slate-500">Transaksi</span></div>
                </div>

                <div class="bg-white border border-emerald-200 rounded p-3 shadow-sm bg-emerald-50/30">
                    <div class="text-xs text-emerald-800 font-medium flex items-center justify-between">
                        <span>Sudah Cocok</span>
                        <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                    </div>
                    <div class="text-xl font-bold font-mono text-emerald-800 mt-1">{{ stats.matched }}</div>
                </div>

                <div class="bg-white border border-amber-200 rounded p-3 shadow-sm bg-amber-50/30">
                    <div class="text-xs text-amber-800 font-medium flex items-center justify-between">
                        <span>Perlu Pemeriksaan</span>
                        <AlertTriangle class="w-4 h-4 text-amber-600" />
                    </div>
                    <div class="text-xl font-bold font-mono text-amber-800 mt-1">{{ stats.unmatched }}</div>
                </div>

                <div class="bg-white border border-rose-200 rounded p-3 shadow-sm bg-rose-50/30">
                    <div class="text-xs text-rose-800 font-medium flex items-center justify-between">
                        <span>Belum Ada PPTK</span>
                        <HelpCircle class="w-4 h-4 text-rose-600" />
                    </div>
                    <div class="text-xl font-bold font-mono text-rose-800 mt-1">{{ stats.missing_pptk }}</div>
                </div>
            </div>

            <!-- Filter & Search Toolbar -->
            <div class="bg-white border border-slate-200 rounded p-3 shadow-sm flex flex-wrap items-center justify-between gap-2 text-xs">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-slate-700">Filter Status:</span>
                    <button
                        @click="statusFilter = 'all'"
                        :class="[
                            'px-2.5 py-1 rounded text-xs font-medium border transition-colors',
                            statusFilter === 'all' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-300'
                        ]"
                    >
                        Semua
                    </button>
                    <button
                        @click="statusFilter = 'perlu_periksa'"
                        :class="[
                            'px-2.5 py-1 rounded text-xs font-medium border transition-colors',
                            statusFilter === 'perlu_periksa' ? 'bg-amber-600 text-white border-amber-600' : 'bg-white text-slate-600 border-slate-300'
                        ]"
                    >
                        Perlu Periksa
                    </button>
                    <button
                        @click="statusFilter = 'cocok'"
                        :class="[
                            'px-2.5 py-1 rounded text-xs font-medium border transition-colors',
                            statusFilter === 'cocok' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-300'
                        ]"
                    >
                        Cocok
                    </button>
                </div>

                <div class="relative">
                    <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari transaksi / PPTK..."
                        class="pl-8 pr-3 py-1 bg-white border border-slate-300 rounded text-xs w-60"
                    />
                </div>
            </div>

            <!-- Reconciliation Table -->
            <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                <div class="overflow-x-auto excel-scroll">
                    <table class="w-full excel-table text-xs">
                        <thead>
                            <tr class="excel-header-row">
                                <th class="w-10">No</th>
                                <th class="w-24">Tanggal</th>
                                <th class="min-w-[300px] text-left">Uraian Mutasi Bank</th>
                                <th class="min-w-[120px] text-right">Nominal (Rp)</th>
                                <th class="min-w-[100px] text-center">Arah Kas</th>
                                <th class="min-w-[180px] text-left">Alokasi PPTK</th>
                                <th class="min-w-[120px] text-center">Status Rekonsiliasi</th>
                                <th class="min-w-[200px] text-left">Keterangan / Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, idx) in filteredItems"
                                :key="item.bank_id"
                                :class="[
                                    'hover:bg-slate-50',
                                    item.status_rekonsiliasi === 'perlu_periksa' ? 'bg-amber-50/40' : 'bg-white'
                                ]"
                            >
                                <td class="text-center font-mono text-slate-500">{{ idx + 1 }}</td>
                                <td class="text-center font-mono text-slate-700 whitespace-nowrap">{{ item.tanggal }}</td>
                                <td class="text-slate-900 font-medium">{{ item.uraian }}</td>
                                <td 
                                    :class="[
                                        'text-right font-mono font-bold',
                                        item.arah === 'receipt' ? 'text-emerald-700' : 'text-slate-900'
                                    ]"
                                >
                                    {{ formatRupiah(item.nominal) }}
                                </td>
                                <td class="text-center font-semibold text-[11px]">
                                    <span v-if="item.arah === 'receipt'" class="text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">Penerimaan</span>
                                    <span v-else class="text-rose-700 bg-rose-100 px-1.5 py-0.5 rounded">Pengeluaran</span>
                                </td>
                                <td>
                                    <select
                                        :value="item.pptk_id || ''"
                                        @change="assignPptk(item.bank_id, $event.target.value)"
                                        class="px-2 py-0.5 border border-slate-300 rounded text-xs w-full bg-white font-medium focus:ring-1 focus:ring-indigo-500"
                                    >
                                        <option value="">-- Pilih PPTK Penanggung Jawab --</option>
                                        <option v-for="p in pptks" :key="p.id" :value="p.id">{{ p.display_name }}</option>
                                    </select>
                                </td>
                                <td class="text-center whitespace-nowrap">
                                    <span v-if="item.status_rekonsiliasi === 'cocok'" class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded">
                                        <CheckCircle2 class="w-3 h-3" /> Cocok
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-800 bg-amber-100 px-2 py-0.5 rounded">
                                        <AlertTriangle class="w-3 h-3" /> Perlu Periksa
                                    </span>
                                </td>
                                <td class="text-slate-600 text-[11px]">{{ item.catatan }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
