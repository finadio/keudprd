<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    PieChart,
    Wallet,
    TrendingDown,
    Clock,
    CheckCircle2,
    AlertCircle,
    ArrowUpRight,
    ArrowDownLeft,
    Plus,
    FileSpreadsheet,
    Layers,
    Receipt,
    Filter,
    ChevronRight,
    Building2,
    Users
} from 'lucide-vue-next';

const props = defineProps({
    overview: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const formatCurrency = (val) => {
    return 'Rp ' + Number(val || 0).toLocaleString('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
};

const formatNumber = (val) => {
    return Number(val || 0).toLocaleString('id-ID', {
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

const selectedPptk = ref(props.filters.pptk_id || '');

const applyPptkFilter = (val) => {
    router.get('/pagu', {
        pptk_id: val || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Pantauan Realisasi Pagu Anggaran" />

        <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
            <!-- Header Section (White Card) -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-emerald-600">
                        <PieChart class="w-4 h-4" />
                        <span>Sistem Monitoring Real-Time Kas Lapangan vs SIPD</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 mt-1">
                        Pantauan Realisasi Pagu Anggaran
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                        Melacak pencairan dana kas riil ke PPTK, pengembalian sisa, dan memantau selisih dana yang belum di-SPJ-kan ke SIPD.
                    </p>
                </div>

                <div class="flex items-center gap-2.5 flex-wrap">
                    <!-- PPTK Filter -->
                    <div v-if="!overview.is_pptk_user" class="relative">
                        <select
                            :value="selectedPptk"
                            @change="applyPptkFilter($event.target.value)"
                            class="appearance-none pl-3 pr-8 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500 cursor-pointer"
                        >
                            <option value="">Semua PPTK (Total)</option>
                            <option v-for="p in overview.all_pptks" :key="p.id" :value="p.id">
                                {{ p.display_name }}
                            </option>
                        </select>
                        <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-slate-400">▼</span>
                    </div>

                    <!-- Quick Action: Catat Transaksi Realisasi -->
                    <Link
                        href="/pagu/transaksi"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Catat Pencairan / Sisa</span>
                    </Link>

                    <!-- Import SIPD button -->
                    <Link
                        href="/pagu/impor"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition-colors"
                        title="Perbarui Pagu dari Excel SIPD"
                    >
                        <FileSpreadsheet class="w-4 h-4 text-emerald-600" />
                        <span>Sinkron SIPD</span>
                    </Link>
                </div>
            </div>

            <!-- Notice / Banner Info Alur Kerja -->
            <div class="bg-emerald-50/80 border border-emerald-200/90 rounded-2xl p-4 flex items-start gap-3">
                <div class="p-2 bg-emerald-500/10 text-emerald-700 rounded-xl shrink-0">
                    <AlertCircle class="w-5 h-5" />
                </div>
                <div class="text-xs text-emerald-900 leading-relaxed">
                    <span class="font-bold">Alur Pengawasan Kas Riil:</span> Dana kegiatan dicatat langsung saat kas didistribusikan ke PPTK/dewan sebelum kegiatan. Kolom <strong>Belum SPJ</strong> menunjukkan uang muka kegiatan yang belum di-acc/diajukan ke SIPD. Fitur <strong>Pengembalian Sisa</strong> digunakan jika ada kegiatan yang tidak menghabiskan seluruh dana yang dicairkan.
                </div>
            </div>

            <!-- KPI Cards Grid (4 White Cards) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <!-- Card 1: Total Pagu Anggaran -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Pagu (SIPD)</span>
                            <div class="p-2 bg-blue-50 text-blue-600 rounded-xl">
                                <Wallet class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-1 font-sans tabular-nums whitespace-nowrap overflow-hidden">
                            <span class="text-xs font-bold text-slate-400">Rp</span>
                            <span class="text-lg sm:text-xl xl:text-2xl font-black text-slate-900 tracking-tight">
                                {{ formatNumber(overview.kpi.total_pagu) }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 flex flex-col gap-1 text-[11px]">
                        <div class="flex items-center justify-between text-slate-500">
                            <span>Sumber:</span>
                            <span class="font-bold text-slate-800">SIPD Fungsional</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-500">
                            <span>Tahun Anggaran:</span>
                            <span class="font-bold text-slate-800">TA {{ overview.year }} Murni</span>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Realisasi Riil Lapangan (Netto) -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Realisasi Kas Riil</span>
                            <div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl">
                                <TrendingDown class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-1 font-sans tabular-nums whitespace-nowrap overflow-hidden">
                            <span class="text-xs font-bold text-slate-400">Rp</span>
                            <span class="text-lg sm:text-xl xl:text-2xl font-black text-slate-900 tracking-tight">
                                {{ formatNumber(overview.kpi.net_realisasi_riil) }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 flex flex-col gap-1 text-[11px]">
                        <div class="flex items-center justify-between text-slate-500">
                            <span>Pencairan Kas:</span>
                            <span class="font-bold text-slate-800 font-sans tabular-nums whitespace-nowrap">{{ formatCurrency(overview.kpi.total_pencairan) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-500">
                            <span>Pengembalian:</span>
                            <span class="font-bold text-slate-800 font-sans tabular-nums whitespace-nowrap">-{{ formatCurrency(overview.kpi.total_pengembalian) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Sisa Pagu Anggaran Riil -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Sisa Pagu Riil</span>
                            <div class="p-2 bg-purple-50 text-purple-600 rounded-xl">
                                <Layers class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-1 font-sans tabular-nums whitespace-nowrap overflow-hidden">
                            <span class="text-xs font-bold text-slate-400">Rp</span>
                            <span class="text-lg sm:text-xl xl:text-2xl font-black text-slate-900 tracking-tight">
                                {{ formatNumber(overview.kpi.sisa_pagu_riil) }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 flex flex-col gap-1 text-[11px]">
                        <div class="flex items-center justify-between text-slate-500">
                            <span>Serapan Anggaran:</span>
                            <span class="font-bold text-slate-800 font-sans tabular-nums whitespace-nowrap">{{ formatPercent(overview.kpi.persen_realisasi_riil) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-500">
                            <span>Sisa di SIPD:</span>
                            <span class="font-bold text-slate-800 font-sans tabular-nums whitespace-nowrap">{{ formatCurrency(overview.kpi.sisa_pagu_sipd) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Uang Kas Beredar Belum SPJ SIPD -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Kas Belum SPJ SIPD</span>
                            <div class="p-2 bg-slate-100 text-slate-600 rounded-xl">
                                <Clock class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-1 font-sans tabular-nums whitespace-nowrap overflow-hidden">
                            <span class="text-xs font-bold text-slate-400">Rp</span>
                            <span class="text-lg sm:text-xl xl:text-2xl font-black text-slate-900 tracking-tight">
                                {{ formatNumber(overview.kpi.outstanding_belum_spj) }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 flex flex-col gap-1 text-[11px]">
                        <div class="flex items-center justify-between text-slate-500">
                            <span>Status Kas:</span>
                            <span class="font-bold text-slate-800">Uang Muka di PPTK</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-500">
                            <span>Keterangan:</span>
                            <span class="font-bold text-slate-800">Belum SPJ ke SIPD</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comparison Bar: Serapan Riil vs SPJ SIPD Resmi (White Card) -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                    <span class="font-bold text-slate-900 uppercase tracking-wider">Perbandingan Progres Penyerapan Anggaran</span>
                    <div class="flex items-center gap-4 text-xs">
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span>
                            <span class="text-slate-600">Realisasi Riil ({{ formatPercent(overview.kpi.persen_realisasi_riil) }})</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-blue-500 inline-block"></span>
                            <span class="text-slate-600">SPJ SIPD Resmi ({{ formatPercent(overview.kpi.persen_spj_sipd) }})</span>
                        </div>
                    </div>
                </div>

                <!-- Dual Progress Bar -->
                <div class="space-y-2">
                    <div>
                        <div class="flex justify-between text-[11px] text-slate-500 mb-1">
                            <span>Realisasi Kas Lapangan (Uang Keluar)</span>
                            <span class="font-bold font-sans tabular-nums text-slate-900">{{ formatCurrency(overview.kpi.net_realisasi_riil) }}</span>
                        </div>
                        <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                            <div
                                class="bg-emerald-500 h-2.5 rounded-full transition-all duration-500"
                                :style="{ width: Math.min(100, overview.kpi.persen_realisasi_riil) + '%' }"
                            ></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between text-[11px] text-slate-500 mb-1">
                            <span>SPJ Masuk SIPD (Pertanggungjawaban Resmi)</span>
                            <span class="font-bold font-sans tabular-nums text-slate-900">{{ formatCurrency(overview.kpi.total_spj_sipd) }}</span>
                        </div>
                        <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                            <div
                                class="bg-blue-500 h-2.5 rounded-full transition-all duration-500"
                                :style="{ width: Math.min(100, overview.kpi.persen_spj_sipd) + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table: Rincian Realisasi Pagu per PPTK (White Card) -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
                <div class="p-5 border-b border-slate-200/90 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <Users class="w-4 h-4 text-emerald-600" />
                            <span>Rekapitulasi Pagu & Sisa Kas per PPTK</span>
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Pantauan real-time saldo sisa pagu dan tanggungan SPJ masing-masing PPTK.
                        </p>
                    </div>

                    <Link
                        href="/pagu/subkegiatan"
                        class="text-xs font-bold text-emerald-600 hover:text-emerald-700 inline-flex items-center gap-1"
                    >
                        <span>Lihat Rincian Subkegiatan</span>
                        <ChevronRight class="w-3.5 h-3.5" />
                    </Link>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left min-w-[1100px]">
                        <thead class="bg-slate-50 text-slate-700 font-bold uppercase tracking-wider text-[11px] border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3.5 text-center w-12">No</th>
                                <th class="px-4 py-3.5 min-w-[220px]">PPTK / Bagian</th>
                                <th class="px-4 py-3.5 text-right whitespace-nowrap">Pagu SIPD</th>
                                <th class="px-4 py-3.5 text-right whitespace-nowrap">Pencairan Kas</th>
                                <th class="px-4 py-3.5 text-right whitespace-nowrap">Pengembalian</th>
                                <th class="px-4 py-3.5 text-right whitespace-nowrap">Realisasi Riil</th>
                                <th class="px-4 py-3.5 text-right whitespace-nowrap">Sisa Pagu Riil</th>
                                <th class="px-4 py-3.5 text-right whitespace-nowrap">SPJ SIPD</th>
                                <th class="px-4 py-3.5 text-right whitespace-nowrap">Belum SPJ</th>
                                <th class="px-4 py-3.5 text-center whitespace-nowrap w-20">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="(item, idx) in overview.pptk_breakdown"
                                :key="item.id"
                                class="hover:bg-slate-50/80 transition-colors"
                            >
                                <td class="px-4 py-3.5 text-center font-sans text-slate-400 font-bold">{{ idx + 1 }}</td>
                                <td class="px-4 py-3.5 min-w-[220px]">
                                    <div class="font-bold text-slate-900 text-xs sm:text-sm">{{ item.display_name }}</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">{{ item.subunit_name }} • {{ item.subkegiatan_count }} subkegiatan</div>
                                </td>
                                <td class="px-4 py-3.5 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">
                                    {{ formatCurrency(item.pagu) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">
                                    {{ formatCurrency(item.pencairan) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">
                                    {{ item.pengembalian > 0 ? '-' + formatCurrency(item.pengembalian) : 'Rp 0' }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-sans tabular-nums font-black text-slate-900 whitespace-nowrap">
                                    {{ formatCurrency(item.net_realisasi_riil) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-sans tabular-nums font-black text-slate-900 whitespace-nowrap">
                                    {{ formatCurrency(item.sisa_pagu_riil) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">
                                    {{ formatCurrency(item.spj_sipd) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-sans tabular-nums font-black text-slate-900 whitespace-nowrap">
                                    {{ formatCurrency(item.belum_spj) }}
                                </td>
                                <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                    <Link
                                        :href="`/pagu/transaksi?pptk_id=${item.id}`"
                                        class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition-colors inline-block"
                                    >
                                        Detail
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-slate-50 font-bold border-t-2 border-slate-200 text-slate-900">
                            <tr>
                                <td colspan="2" class="px-4 py-4 text-center uppercase tracking-wider font-bold">TOTAL KESELURUHAN</td>
                                <td class="px-4 py-4 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">{{ formatCurrency(overview.kpi.total_pagu) }}</td>
                                <td class="px-4 py-4 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">{{ formatCurrency(overview.kpi.total_pencairan) }}</td>
                                <td class="px-4 py-4 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">-{{ formatCurrency(overview.kpi.total_pengembalian) }}</td>
                                <td class="px-4 py-4 text-right font-sans tabular-nums font-black text-slate-900 whitespace-nowrap">{{ formatCurrency(overview.kpi.net_realisasi_riil) }}</td>
                                <td class="px-4 py-4 text-right font-sans tabular-nums font-black text-slate-900 whitespace-nowrap">{{ formatCurrency(overview.kpi.sisa_pagu_riil) }}</td>
                                <td class="px-4 py-4 text-right font-sans tabular-nums font-bold text-slate-900 whitespace-nowrap">{{ formatCurrency(overview.kpi.total_spj_sipd) }}</td>
                                <td class="px-4 py-4 text-right font-sans tabular-nums font-black text-slate-900 whitespace-nowrap">{{ formatCurrency(overview.kpi.outstanding_belum_spj) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Two Column Section: Program Breakdown & Recent Transactions -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Program Breakdown Cards (White Card) -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <Building2 class="w-4 h-4 text-emerald-600" />
                            <span>Pagu Berdasarkan Program APBD</span>
                        </h3>
                        <span class="text-xs text-slate-400">SIPD Fungsional</span>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="prg in overview.programs"
                            :key="prg.code"
                            class="p-4 bg-slate-50/70 rounded-xl border border-slate-200/80 space-y-2"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <span class="text-[10px] font-sans px-1.5 py-0.5 bg-emerald-100 text-emerald-800 rounded font-bold">
                                        {{ prg.code }}
                                    </span>
                                    <h4 class="text-xs font-bold text-slate-900 mt-1">{{ prg.name }}</h4>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs font-bold font-sans tabular-nums text-slate-900">{{ formatCurrency(prg.pagu) }}</div>
                                    <span class="text-[10px] text-slate-500">Pagu Total</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-[11px] pt-1 border-t border-slate-200/70">
                                <div>
                                    <span class="text-slate-500">Realisasi Riil:</span>
                                    <span class="ml-1 font-bold font-sans tabular-nums text-slate-900">{{ formatCurrency(prg.net_realisasi_riil) }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-slate-500">Sisa Pagu:</span>
                                    <span class="ml-1 font-bold font-sans tabular-nums text-slate-900">{{ formatCurrency(prg.sisa_pagu_riil) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions (White Card) -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <Receipt class="w-4 h-4 text-emerald-600" />
                            <span>Aktivitas Realisasi & Pengembalian Kas Terbaru</span>
                        </h3>
                        <Link
                            href="/pagu/transaksi"
                            class="text-xs font-bold text-emerald-600 hover:text-emerald-700 inline-flex items-center gap-1"
                        >
                            <span>Semua Transaksi</span>
                            <ChevronRight class="w-3.5 h-3.5" />
                        </Link>
                    </div>

                    <div v-if="overview.recent_transactions.length === 0" class="text-center py-10 text-slate-400 text-xs italic">
                        Belum ada catatan pencairan dana atau pengembalian sisa kas.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="tx in overview.recent_transactions"
                            :key="tx.id"
                            class="p-3 bg-slate-50/70 rounded-xl border border-slate-200/80 flex items-center justify-between gap-3 text-xs"
                        >
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div
                                    class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                    :class="tx.type === 'pencairan' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                >
                                    <ArrowUpRight v-if="tx.type === 'pencairan'" class="w-4 h-4" />
                                    <ArrowDownLeft v-else class="w-4 h-4" />
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-900 truncate">{{ tx.description }}</div>
                                    <div class="text-[10px] text-slate-500">
                                        {{ tx.pptk?.display_name }} • {{ formatDate(tx.transaction_date) }}
                                    </div>
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <div class="font-sans tabular-nums font-bold text-slate-900">
                                    {{ tx.type === 'pengembalian' ? '-' : '' }}{{ formatCurrency(tx.amount) }}
                                </div>
                                <span
                                    class="text-[9px] px-1.5 py-0.2 rounded font-bold"
                                    :class="tx.is_spj_submitted ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ tx.is_spj_submitted ? 'SPJ Terkirim' : 'Belum SPJ' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
