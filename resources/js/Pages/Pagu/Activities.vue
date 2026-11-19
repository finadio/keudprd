<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    FolderTree,
    Search,
    Filter,
    UserCheck,
    Layers,
    ChevronRight,
    ArrowUpRight,
    ArrowDownLeft,
    CheckCircle2,
    Clock,
    FileSpreadsheet
} from 'lucide-vue-next';

const props = defineProps({
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

const formatPercent = (val) => {
    return Number(val || 0).toLocaleString('id-ID', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    }) + '%';
};

const searchQuery = ref('');
const selectedPptkFilter = ref(props.filters.pptk_id || '');

const filteredActivities = computed(() => {
    return props.activities.filter((act) => {
        const matchesSearch = !searchQuery.value ||
            act.code.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            act.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            act.pptk_name.toLowerCase().includes(searchQuery.value.toLowerCase());

        const matchesPptk = !selectedPptkFilter.value || act.pptk_id === Number(selectedPptkFilter.value);

        return matchesSearch && matchesPptk;
    });
});

const totals = computed(() => {
    const list = filteredActivities.value;
    return {
        pagu: list.reduce((acc, a) => acc + (a.pagu_budget || 0), 0),
        pencairan: list.reduce((acc, a) => acc + (a.pencairan || 0), 0),
        pengembalian: list.reduce((acc, a) => acc + (a.pengembalian || 0), 0),
        net_riil: list.reduce((acc, a) => acc + (a.net_realisasi_riil || 0), 0),
        sisa_riil: list.reduce((acc, a) => acc + (a.sisa_pagu_riil || 0), 0),
        spj_sipd: list.reduce((acc, a) => acc + (a.spj_sipd_total || 0), 0),
        belum_spj: list.reduce((acc, a) => acc + (a.belum_spj || 0), 0),
    };
});

const updatePptkAssignment = (activityId, newPptkId) => {
    router.post(`/pagu/subkegiatan/${activityId}/assign-pptk`, {
        pptk_id: newPptkId,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Pagu Anggaran per Sub Kegiatan & Pemetaan PPTK" />

        <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
            <!-- Header (White Card) -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-emerald-600">
                        <FolderTree class="w-4 h-4" />
                        <span>Master Pagu & Pemetaan Tugas PPTK</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 mt-1">
                        Pagu per Sub Kegiatan & Pemetaan PPTK
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                        Daftar 61 subkegiatan dari SIPD Fungsional dengan alokasi PPTK, serapan riil, dan sisa pagu anggaran.
                    </p>
                </div>

                <div class="flex items-center gap-2.5">
                    <Link
                        href="/pagu/impor"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition-colors"
                    >
                        <FileSpreadsheet class="w-4 h-4 text-emerald-600" />
                        <span>Perbarui dari SIPD</span>
                    </Link>
                </div>
            </div>

            <!-- Filter & Search Bar (White Card) -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="relative w-full sm:w-80">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari kode atau nama subkegiatan..."
                        class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:ring-2 focus:ring-emerald-500"
                    />
                    <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                </div>

                <div v-if="!is_pptk_user" class="relative w-full sm:w-64">
                    <select
                        v-model="selectedPptkFilter"
                        class="w-full appearance-none pl-3 pr-8 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500 cursor-pointer"
                    >
                        <option value="">Semua PPTK ({{ activities.length }} Subkegiatan)</option>
                        <option v-for="p in pptks" :key="p.id" :value="p.id">
                            {{ p.display_name }}
                        </option>
                    </select>
                    <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-slate-400">▼</span>
                </div>
            </div>

            <!-- Table of Activities (White Card) -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left min-w-[1150px]">
                        <thead class="bg-slate-50 text-slate-700 font-bold uppercase tracking-wider text-[11px] border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3.5 text-center w-12">No</th>
                                <th class="px-4 py-3.5 min-w-[280px]">Kode & Sub Kegiatan</th>
                                <th class="px-4 py-3.5 min-w-[190px]">PPTK Penanggung Jawab</th>
                                <th class="px-4 py-3.5 text-right whitespace-nowrap">Pagu SIPD</th>
                                <th class="px-4 py-3.5 text-right whitespace-nowrap">Realisasi Riil</th>
                                <th class="px-4 py-3.5 text-right whitespace-nowrap">Sisa Pagu Riil</th>
                                <th class="px-4 py-3.5 text-right whitespace-nowrap">SPJ SIPD</th>
                                <th class="px-4 py-3.5 text-right whitespace-nowrap">Belum SPJ</th>
                                <th class="px-4 py-3.5 text-center whitespace-nowrap w-24">% Serapan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="filteredActivities.length === 0">
                                <td colspan="9" class="px-4 py-12 text-center text-slate-400 text-xs italic">
                                    Tidak ada sub kegiatan yang cocok dengan kriteria pencarian.
                                </td>
                            </tr>
                            <tr
                                v-for="(act, idx) in filteredActivities"
                                :key="act.id"
                                class="hover:bg-slate-50/80 transition-colors"
                            >
                                <td class="px-4 py-3.5 text-center font-sans text-slate-400 font-bold">{{ idx + 1 }}</td>
                                <td class="px-4 py-3.5 min-w-[280px]">
                                    <div class="font-sans font-bold text-[11px] text-slate-700">
                                        {{ act.code }}
                                    </div>
                                    <div class="font-semibold text-slate-900 text-xs mt-0.5 leading-snug">
                                        {{ act.name }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 leading-tight">
                                        {{ act.program_code }} - {{ act.program_name }}
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap min-w-[190px]">
                                    <div v-if="is_pptk_user" class="font-bold text-slate-900">
                                        {{ act.pptk_name }}
                                    </div>
                                    <div v-else class="relative w-48">
                                        <select
                                            :value="act.pptk_id"
                                            @change="updatePptkAssignment(act.id, $event.target.value)"
                                            class="w-full appearance-none pl-2.5 pr-6 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-900 focus:ring-1 focus:ring-emerald-500 cursor-pointer"
                                        >
                                            <option v-for="p in pptks" :key="p.id" :value="p.id">
                                                {{ p.display_name }}
                                            </option>
                                        </select>
                                        <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-slate-400">▼</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">
                                    {{ formatCurrency(act.pagu_budget) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">
                                    {{ formatCurrency(act.net_realisasi_riil) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">
                                    {{ formatCurrency(act.sisa_pagu_riil) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">
                                    {{ formatCurrency(act.spj_sipd_total) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">
                                    {{ formatCurrency(act.belum_spj) }}
                                </td>
                                <td class="px-4 py-3.5 text-center font-sans tabular-nums font-bold whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-[10px] bg-slate-100 text-slate-800 font-bold">
                                        {{ formatPercent(act.persen_serapan) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-slate-50 font-bold border-t-2 border-slate-200 text-slate-900">
                            <tr>
                                <td colspan="3" class="px-4 py-4 text-center uppercase tracking-wider font-bold">
                                    TOTAL ({{ filteredActivities.length }} SUBKEGIATAN)
                                </td>
                                <td class="px-4 py-4 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">{{ formatCurrency(totals.pagu) }}</td>
                                <td class="px-4 py-4 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">{{ formatCurrency(totals.net_riil) }}</td>
                                <td class="px-4 py-4 text-right font-sans tabular-nums font-black text-slate-900 whitespace-nowrap">{{ formatCurrency(totals.sisa_riil) }}</td>
                                <td class="px-4 py-4 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">{{ formatCurrency(totals.spj_sipd) }}</td>
                                <td class="px-4 py-4 text-right font-sans tabular-nums font-black text-slate-900 whitespace-nowrap">{{ formatCurrency(totals.belum_spj) }}</td>
                                <td class="px-4 py-4 text-center font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">
                                    {{ totals.pagu > 0 ? formatPercent((totals.net_riil / totals.pagu) * 100) : '0%' }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
