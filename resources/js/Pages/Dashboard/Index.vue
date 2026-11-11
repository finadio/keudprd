<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    Landmark, 
    BookOpen, 
    UploadCloud, 
    AlertTriangle,
    ArrowRight
} from 'lucide-vue-next';

const props = defineProps({
    year: Number,
    active_month: Number,
    bank_summary: Object,
    hitungan_totals: Object,
    pptk_recap: Array,
    total_transactions: Number,
    unverified_count: Number,
    monthly_trends: Array,
});

const activeTab = ref('monthly');
const hoveredIndex = ref(null);
const displayMode = ref('full'); // 'full' (Nominal Penuh Rp) by default, or 'compact' (Juta)

const formatRupiah = (val) => {
    if (val === null || val === undefined || isNaN(val)) return 'Rp 0';
    return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

// Format nominal penuh tanpa desimal (misal: 4.374.418.800)
const formatNominalFull = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '0';
    return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

// Format selisih penuh dengan tanda + / - (misal: +418.392.098)
const formatSelisihFull = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '0';
    const num = Number(val);
    const formatted = Math.abs(num).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    if (num > 0) return `+${formatted}`;
    if (num < 0) return `-${formatted}`;
    return formatted;
};

// Format in Rp Juta with 2 decimal places (e.g. 4.374,42)
const formatJuta = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '0,00';
    const inJuta = Number(val) / 1_000_000;
    return inJuta.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// Format Selisih with + or − prefix and 2 decimal places
const formatSelisih = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '0,00';
    const inJuta = Number(val) / 1_000_000;
    const formatted = Math.abs(inJuta).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (inJuta > 0) return `+${formatted}`;
    if (inJuta < 0) return `−${formatted}`;
    return formatted;
};

// Date range label: Jan – Okt 2026
const trendDateRange = computed(() => {
    if (!props.monthly_trends || props.monthly_trends.length === 0) return `TA ${props.year}`;
    const start = props.monthly_trends[0]?.month || 'Jan';
    const end = props.monthly_trends[props.monthly_trends.length - 1]?.month || 'Okt';
    return `${start} – ${end} ${props.year}`;
});

// Chart coordinates configuration
const chartLeft = 55;
const chartRight = 865;
const chartTop = 25;
const chartBottom = 150;
const chartWidth = chartRight - chartLeft;
const chartHeight = chartBottom - chartTop;
const topTick = 7; // 7 Rp miliar
const chartTicks = [0, 3, 4, 5, 6, 7];

const getX = (index) => {
    const total = props.monthly_trends?.length || 1;
    if (total <= 1) return (chartLeft + chartRight) / 2;
    return chartLeft + (index / (total - 1)) * chartWidth;
};

const getY = (valMiliar) => {
    const val = Math.max(0, Number(valMiliar) || 0);
    return chartBottom - (val / topTick) * chartHeight;
};

// Area path under Penerimaan line
const penerimaanAreaPath = computed(() => {
    if (!props.monthly_trends || props.monthly_trends.length === 0) return '';
    const points = props.monthly_trends.map((m, i) => `${getX(i).toFixed(1)},${getY(m.penerimaan / 1_000_000_000).toFixed(1)}`);
    const firstX = getX(0).toFixed(1);
    const lastX = getX(props.monthly_trends.length - 1).toFixed(1);
    return `M ${points.join(' L ')} L ${lastX},${chartBottom} L ${firstX},${chartBottom} Z`;
});

// Line path for Penerimaan
const penerimaanLinePath = computed(() => {
    if (!props.monthly_trends || props.monthly_trends.length === 0) return '';
    return 'M ' + props.monthly_trends.map((m, i) => `${getX(i).toFixed(1)},${getY(m.penerimaan / 1_000_000_000).toFixed(1)}`).join(' L ');
});

// Line path for Pengeluaran
const pengeluaranLinePath = computed(() => {
    if (!props.monthly_trends || props.monthly_trends.length === 0) return '';
    return 'M ' + props.monthly_trends.map((m, i) => `${getX(i).toFixed(1)},${getY(m.pengeluaran / 1_000_000_000).toFixed(1)}`).join(' L ');
});
</script>

<template>
    <AppLayout>
        <Head title="Dashboard Keuangan - KeuDPRD Banyumas" />

        <div class="space-y-5">
            
            <!-- ================= PAGE TITLE & ACTION BUTTONS ================= -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Dashboard</h1>
                    <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 font-mono text-xs font-bold rounded-md border border-emerald-200">
                        TA {{ year }}
                    </span>
                </div>

                <!-- Action Buttons Toolbar -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <Link 
                        href="/impor" 
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#107c41] hover:bg-[#0e6b37] text-white rounded-lg text-xs font-bold shadow-2xs transition-colors"
                    >
                        <UploadCloud class="w-4 h-4" />
                        <span>Impor Transaksi</span>
                    </Link>

                    <Link 
                        href="/bku" 
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 rounded-lg text-xs font-bold shadow-2xs transition-colors"
                    >
                        <BookOpen class="w-4 h-4 text-slate-600" />
                        <span>Buka Lembar BKU</span>
                    </Link>

                    <Link 
                        href="/buku-bank" 
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 rounded-lg text-xs font-bold shadow-2xs transition-colors"
                    >
                        <Landmark class="w-4 h-4 text-slate-600" />
                        <span>Buka Buku Bank</span>
                    </Link>
                </div>
            </div>

            <!-- ================= 4 TOP STAT CARDS ================= -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- Card 1: Saldo Buku Bank -->
                <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs flex flex-col justify-between">
                    <div>
                        <div class="text-xs font-semibold text-slate-600 mb-1">
                            Saldo Buku Bank
                        </div>
                        <div class="text-2xl font-black font-sans text-slate-900 tracking-tight">
                            {{ formatRupiah(bank_summary.sisa_kas) }}
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-500 space-y-0.5 mt-3 pt-2 border-t border-slate-100">
                        <div>Penerimaan: <span class="font-medium text-slate-700">{{ formatRupiah(bank_summary.periode_ini.penerimaan) }}</span></div>
                        <div>Pengeluaran: <span class="font-medium text-slate-700">{{ formatRupiah(bank_summary.periode_ini.pengeluaran) }}</span></div>
                    </div>
                </div>

                <!-- Card 2: Debet Rekening (Toko) -->
                <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs flex flex-col justify-between">
                    <div>
                        <div class="text-xs font-semibold text-slate-600 mb-1">
                            Debet Rekening (Toko)
                        </div>
                        <div class="text-2xl font-black font-sans text-slate-900 tracking-tight">
                            {{ formatRupiah(hitungan_totals.debet) }}
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-500 space-y-0.5 mt-3 pt-2 border-t border-slate-100">
                        <div>Panjar: <span class="font-medium text-slate-700">{{ formatRupiah(hitungan_totals.panjar) }}</span></div>
                        <div>Total Beban: <span class="font-medium text-slate-700">{{ formatRupiah(hitungan_totals.jml) }}</span></div>
                    </div>
                </div>

                <!-- Card 3: Realisasi SPJ -->
                <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs flex flex-col justify-between">
                    <div>
                        <div class="text-xs font-semibold text-slate-600 mb-1">
                            Realisasi SPJ
                        </div>
                        <div class="text-2xl font-black font-sans text-slate-900 tracking-tight">
                            {{ formatRupiah(hitungan_totals.spj) }}
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-500 space-y-0.5 mt-3 pt-2 border-t border-slate-100">
                        <div>Total Hutang Panjar: <span class="font-medium text-slate-700">{{ formatRupiah(hitungan_totals.total_hutang_panjar) }}</span></div>
                    </div>
                </div>

                <!-- Card 4: Transaksi Aktif -->
                <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs flex flex-col justify-between">
                    <div>
                        <div class="text-xs font-semibold text-slate-600 mb-1">
                            Transaksi Aktif
                        </div>
                        <div class="text-2xl font-black font-sans text-slate-900 tracking-tight">
                            {{ total_transactions.toLocaleString('id-ID') }} <span class="text-base font-normal text-slate-500">Record</span>
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-500 space-y-0.5 mt-3 pt-2 border-t border-slate-100">
                        <div>Terverifikasi: <span class="font-medium text-slate-700">{{ (total_transactions - unverified_count).toLocaleString('id-ID') }}</span></div>
                        <div>Perlu Dicek: <span class="font-medium text-slate-700">{{ unverified_count }}</span></div>
                    </div>
                </div>

            </div>

            <!-- ================= SELISIH PEMBUKUAN BANNER ================= -->
            <div class="bg-[#fff9eb] border border-[#fbd38d] rounded-xl px-4 py-3 flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-2.5 text-[#744210] font-bold text-xs sm:text-sm">
                    <AlertTriangle class="w-4 h-4 text-[#d69e2e] shrink-0" />
                    <span>Selisih pembukuan: <span class="font-black text-[#975a16]">{{ formatRupiah(hitungan_totals.kontrol_selisih) }}</span></span>
                </div>
                <Link 
                    href="/rekonsiliasi" 
                    class="text-xs font-bold text-[#b7791f] hover:text-[#975a16] flex items-center gap-1 transition-colors"
                >
                    <span>Buka Rekonsiliasi</span>
                    <ArrowRight class="w-3.5 h-3.5" />
                </Link>
            </div>

            <!-- ================= TABBED CONTENT CARD ================= -->
            <div class="bg-white border border-slate-200/90 rounded-xl shadow-xs overflow-hidden">
                
                <!-- Tab Headers Bar -->
                <div class="px-5 pt-3.5 border-b border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-6">
                        <button
                            type="button"
                            @click="activeTab = 'pptk'"
                            :class="[
                                'pb-3 text-xs sm:text-sm font-bold transition-all relative cursor-pointer',
                                activeTab === 'pptk'
                                    ? 'text-[#107c41] border-b-2 border-[#107c41]'
                                    : 'text-slate-500 hover:text-slate-800'
                            ]"
                        >
                            Rekapitulasi PPTK
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'monthly'"
                            :class="[
                                'pb-3 text-xs sm:text-sm font-bold transition-all relative cursor-pointer',
                                activeTab === 'monthly'
                                    ? 'text-[#107c41] border-b-2 border-[#107c41]'
                                    : 'text-slate-500 hover:text-slate-800'
                            ]"
                        >
                            Arus Kas Bulanan
                        </button>
                    </div>

                    <Link
                        :href="activeTab === 'monthly' ? '/buku-bank' : '/bku?tab=Hitungan'"
                        class="pb-3 text-xs font-bold text-[#107c41] hover:underline flex items-center gap-1 transition-all"
                    >
                        <span>Buka Sheet Lengkap</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </Link>
                </div>

                <!-- Tab 1: Rekapitulasi PPTK Table -->
                <div v-if="activeTab === 'pptk'" class="overflow-x-auto excel-scroll">
                    <table class="w-full excel-table text-xs">
                        <thead>
                            <tr class="excel-header-row bg-slate-50 text-slate-700">
                                <th class="w-10 text-center font-bold">No</th>
                                <th class="text-left min-w-[170px] font-bold">Nama PPTK</th>
                                <th class="text-right min-w-[120px] font-bold">Debet Toko (Rp)</th>
                                <th class="text-right min-w-[110px] font-bold">Panjar Tunai (Rp)</th>
                                <th class="text-right min-w-[130px] font-bold">Total Kewajiban (Rp)</th>
                                <th class="text-right min-w-[130px] font-bold">Realisasi SPJ (Rp)</th>
                                <th class="text-right min-w-[120px] font-bold">Hutang Panjar (Rp)</th>
                                <th class="text-right min-w-[110px] font-bold">Sisa di Bank (Rp)</th>
                                <th class="text-right min-w-[110px] font-bold">Sisa Tunai (Rp)</th>
                                <th class="text-center min-w-[90px] font-bold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                                v-for="item in pptk_recap" 
                                :key="item.pptk_id"
                                class="hover:bg-slate-50 transition-colors"
                            >
                                <td class="text-center font-mono text-slate-500">{{ item.no }}</td>
                                <td class="font-medium text-slate-900">
                                    <Link 
                                        :href="`/bku?tab=${item.pptk_name.replace(/B\s+|P\s+/g, '').replace('/', '-')}`" 
                                        class="hover:text-emerald-700 hover:underline"
                                    >
                                        {{ item.pptk_name }}
                                    </Link>
                                </td>
                                <td class="text-right font-mono">{{ formatRupiah(item.debet).replace('Rp ', '') }}</td>
                                <td class="text-right font-mono">{{ formatRupiah(item.panjar).replace('Rp ', '') }}</td>
                                <td class="text-right font-mono font-medium text-slate-900">{{ formatRupiah(item.jml).replace('Rp ', '') }}</td>
                                <td class="text-right font-mono text-slate-800">{{ formatRupiah(item.spj).replace('Rp ', '') }}</td>
                                <td class="text-right font-mono text-slate-800">{{ formatRupiah(item.total_hutang_panjar).replace('Rp ', '') }}</td>
                                <td class="text-right font-mono text-slate-700">{{ formatRupiah(item.bank).replace('Rp ', '') }}</td>
                                <td class="text-right font-mono text-slate-700">{{ formatRupiah(item.tunai).replace('Rp ', '') }}</td>
                                <td class="text-center">
                                    <span 
                                        v-if="Math.abs(item.kontrol_selisih) < 1"
                                        class="inline-block px-2.5 py-0.5 text-[10px] font-bold bg-[#c6f6d5] text-[#22543d] rounded"
                                    >
                                        Cocok
                                    </span>
                                    <span 
                                        v-else
                                        class="inline-block px-2.5 py-0.5 text-[10px] font-bold bg-[#feebc8] text-[#c05621] rounded"
                                    >
                                        Selisih
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="excel-total-row bg-slate-100/90 font-bold border-t border-slate-300">
                                <td colspan="2" class="text-center font-bold">TOTAL KESELURUHAN</td>
                                <td class="text-right font-mono font-bold">{{ formatRupiah(hitungan_totals.debet).replace('Rp ', '') }}</td>
                                <td class="text-right font-mono font-bold">{{ formatRupiah(hitungan_totals.panjar).replace('Rp ', '') }}</td>
                                <td class="text-right font-mono font-bold">{{ formatRupiah(hitungan_totals.jml).replace('Rp ', '') }}</td>
                                <td class="text-right font-mono font-bold">{{ formatRupiah(hitungan_totals.spj).replace('Rp ', '') }}</td>
                                <td class="text-right font-mono font-bold">{{ formatRupiah(hitungan_totals.total_hutang_panjar).replace('Rp ', '') }}</td>
                                <td class="text-right font-mono font-bold">{{ formatRupiah(hitungan_totals.bank).replace('Rp ', '') }}</td>
                                <td class="text-right font-mono font-bold">{{ formatRupiah(hitungan_totals.tunai).replace('Rp ', '') }}</td>
                                <td class="text-center font-bold font-mono text-emerald-800">
                                    {{ Math.abs(hitungan_totals.kontrol_selisih) < 1 ? 'OK' : 'DIFF' }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Tab 2: Arus Kas Bulanan View (Desain Sesuai Spesifikasi Visual) -->
                <div v-else-if="activeTab === 'monthly'" class="p-5 sm:p-6 space-y-6">
                    
                    <!-- Section Header & Legend -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg sm:text-xl font-black text-[#107c41] tracking-tight">
                                Arus Kas Bulanan
                            </h2>
                            <span class="text-slate-300 font-light mx-0.5">|</span>
                            <span class="text-xs sm:text-sm font-semibold text-slate-600">
                                {{ trendDateRange }}
                            </span>
                        </div>

                        <!-- Legend -->
                        <div class="flex items-center gap-5">
                            <!-- Penerimaan Legend -->
                            <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
                                <svg class="w-8 h-3.5 shrink-0" viewBox="0 0 32 14">
                                    <line x1="0" y1="7" x2="32" y2="7" stroke="#107c41" stroke-width="2.5" />
                                    <circle cx="16" cy="7" r="3.5" fill="#ffffff" stroke="#107c41" stroke-width="2" />
                                </svg>
                                <span>Penerimaan</span>
                            </div>

                            <!-- Pengeluaran Legend -->
                            <div class="flex items-center gap-2 text-xs font-bold text-slate-700">
                                <svg class="w-8 h-3.5 shrink-0" viewBox="0 0 32 14">
                                    <line x1="0" y1="7" x2="32" y2="7" stroke="#94a3b8" stroke-width="2" stroke-dasharray="4 3" />
                                    <circle cx="16" cy="7" r="3.5" fill="#ffffff" stroke="#94a3b8" stroke-width="2" />
                                </svg>
                                <span>Pengeluaran</span>
                            </div>
                        </div>
                    </div>

                    <!-- Line Chart Canvas (Clean Pure SVG) -->
                    <div class="w-full overflow-x-auto excel-scroll pb-2">
                        <div class="min-w-[700px] relative">
                            
                            <!-- Hover Tooltip Floating Card -->
                            <div 
                                v-if="hoveredIndex !== null && monthly_trends[hoveredIndex]"
                                class="absolute top-0 right-4 z-10 bg-slate-900/90 text-white rounded-lg px-3 py-1.5 text-xs shadow-lg backdrop-blur-xs flex items-center gap-3 pointer-events-none"
                            >
                                <span class="font-bold text-emerald-400">{{ monthly_trends[hoveredIndex].month }} {{ year }}</span>
                                <span>Penerimaan: <b class="text-white">{{ formatRupiah(monthly_trends[hoveredIndex].penerimaan) }}</b></span>
                                <span>Pengeluaran: <b class="text-slate-300">{{ formatRupiah(monthly_trends[hoveredIndex].pengeluaran) }}</b></span>
                                <span>Selisih: <b :class="(monthly_trends[hoveredIndex].saldo ?? (monthly_trends[hoveredIndex].penerimaan - monthly_trends[hoveredIndex].pengeluaran)) >= 0 ? 'text-emerald-300' : 'text-rose-300'">{{ formatRupiah(monthly_trends[hoveredIndex].saldo ?? (monthly_trends[hoveredIndex].penerimaan - monthly_trends[hoveredIndex].pengeluaran)) }}</b></span>
                            </div>

                            <svg 
                                viewBox="0 0 900 185" 
                                class="w-full h-auto overflow-visible select-none"
                            >
                                <defs>
                                    <linearGradient id="penerimaanGradient" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#107c41" stop-opacity="0.16" />
                                        <stop offset="100%" stop-color="#107c41" stop-opacity="0.01" />
                                    </linearGradient>
                                </defs>

                                <!-- Y Axis Unit Label -->
                                <text x="18" y="14" class="text-[10px] font-sans fill-slate-500 font-medium">Rp miliar</text>

                                <!-- Horizontal Grid Lines & Y Ticks -->
                                <g class="grid-lines">
                                    <template v-for="t in chartTicks" :key="'tick-' + t">
                                        <!-- Grid Line (for ticks > 0) -->
                                        <line 
                                            v-if="t > 0"
                                            :x1="chartLeft" 
                                            :y1="getY(t)" 
                                            :x2="chartRight" 
                                            :y2="getY(t)" 
                                            stroke="#e2e8f0" 
                                            stroke-width="0.9" 
                                        />
                                        <!-- Y Axis Tick Number -->
                                        <text 
                                            :x="chartLeft - 8" 
                                            :y="getY(t) + 3.5" 
                                            text-anchor="end" 
                                            class="text-[11px] font-sans fill-slate-500 font-medium"
                                        >
                                            {{ t }}
                                        </text>
                                    </template>
                                </g>

                                <!-- Left Vertical Axis Line -->
                                <line 
                                    :x1="chartLeft" 
                                    :y1="chartTop - 5" 
                                    :x2="chartLeft" 
                                    :y2="chartBottom" 
                                    stroke="#cbd5e1" 
                                    stroke-width="1" 
                                />

                                <!-- Bottom Horizontal Baseline -->
                                <line 
                                    :x1="chartLeft" 
                                    :y1="chartBottom" 
                                    :x2="chartRight" 
                                    :y2="chartBottom" 
                                    stroke="#cbd5e1" 
                                    stroke-width="1" 
                                />

                                <!-- Month X Labels & Ticks -->
                                <g class="x-axis-labels">
                                    <template v-for="(m, i) in monthly_trends" :key="'label-' + m.month">
                                        <!-- Small tick mark -->
                                        <line 
                                            :x1="getX(i)" 
                                            :y1="chartBottom" 
                                            :x2="getX(i)" 
                                            :y2="chartBottom + 3.5" 
                                            stroke="#94a3b8" 
                                            stroke-width="1" 
                                        />
                                        <!-- Month Name -->
                                        <text 
                                            :x="getX(i)" 
                                            :y="chartBottom + 19" 
                                            text-anchor="middle" 
                                            :class="[
                                                'text-[11px] font-sans font-bold transition-colors',
                                                hoveredIndex === i ? 'fill-[#107c41]' : 'fill-slate-700'
                                            ]"
                                        >
                                            {{ m.month }}
                                        </text>
                                    </template>
                                </g>

                                <!-- Area Fill under Penerimaan -->
                                <path 
                                    :d="penerimaanAreaPath" 
                                    fill="url(#penerimaanGradient)" 
                                />

                                <!-- Active Hover Guideline -->
                                <line 
                                    v-if="hoveredIndex !== null" 
                                    :x1="getX(hoveredIndex)" 
                                    :y1="chartTop" 
                                    :x2="getX(hoveredIndex)" 
                                    :y2="chartBottom" 
                                    stroke="#107c41" 
                                    stroke-width="1.2" 
                                    stroke-dasharray="3 3" 
                                    opacity="0.5" 
                                />

                                <!-- Pengeluaran Dashed Line -->
                                <path 
                                    :d="pengeluaranLinePath" 
                                    fill="none" 
                                    stroke="#94a3b8" 
                                    stroke-width="2" 
                                    stroke-dasharray="5 4" 
                                    stroke-linecap="round" 
                                    stroke-linejoin="round" 
                                />

                                <!-- Penerimaan Solid Green Line -->
                                <path 
                                    :d="penerimaanLinePath" 
                                    fill="none" 
                                    stroke="#107c41" 
                                    stroke-width="2.5" 
                                    stroke-linecap="round" 
                                    stroke-linejoin="round" 
                                />

                                <!-- Points on Pengeluaran Line -->
                                <circle 
                                    v-for="(m, i) in monthly_trends" 
                                    :key="'peng-circ-' + m.month"
                                    :cx="getX(i)" 
                                    :cy="getY(m.pengeluaran / 1_000_000_000)" 
                                    r="3.5" 
                                    fill="#ffffff" 
                                    stroke="#94a3b8" 
                                    stroke-width="2" 
                                    :class="hoveredIndex === i ? 'scale-125' : ''"
                                />

                                <!-- Points on Penerimaan Line -->
                                <circle 
                                    v-for="(m, i) in monthly_trends" 
                                    :key="'pen-circ-' + m.month"
                                    :cx="getX(i)" 
                                    :cy="getY(m.penerimaan / 1_000_000_000)" 
                                    r="3.5" 
                                    fill="#ffffff" 
                                    stroke="#107c41" 
                                    stroke-width="2.2" 
                                    :class="hoveredIndex === i ? 'scale-125' : ''"
                                />

                                <!-- Invisible Hover Hit Targets -->
                                <rect 
                                    v-for="(m, i) in monthly_trends" 
                                    :key="'hit-' + m.month"
                                    :x="getX(i) - (chartWidth / Math.max(1, monthly_trends.length - 1)) / 2" 
                                    :y="chartTop - 10" 
                                    :width="chartWidth / Math.max(1, monthly_trends.length - 1)" 
                                    :height="chartBottom - chartTop + 35" 
                                    fill="transparent" 
                                    class="cursor-pointer"
                                    @mouseenter="hoveredIndex = i" 
                                    @mouseleave="hoveredIndex = null" 
                                />
                            </svg>
                        </div>
                    </div>

                    <!-- Financial Summary Data Table (Nominal Penuh / Ringkas) -->
                    <div class="w-full overflow-x-auto excel-scroll pt-2">
                        <div class="min-w-[850px] space-y-2.5">
                            <!-- Table Subtitle & Display Mode Toggle -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="text-xs font-bold text-[#107c41]">
                                    <span v-if="displayMode === 'full'">Nominal Lengkap Kas (Rupiah Riil)</span>
                                    <span v-else>Angka dalam Rp juta &middot; dibulatkan</span>
                                </div>

                                <!-- Toggle Switch: Full vs Compact -->
                                <div class="inline-flex p-0.5 bg-slate-100 rounded-lg border border-slate-200 text-xs font-semibold self-start sm:self-auto">
                                    <button
                                        type="button"
                                        @click="displayMode = 'full'"
                                        :class="[
                                            'px-3 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5',
                                            displayMode === 'full'
                                                ? 'bg-[#107c41] text-white shadow-2xs font-bold'
                                                : 'text-slate-600 hover:text-slate-900'
                                        ]"
                                    >
                                        <span>Nominal Penuh (Rp)</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="displayMode = 'compact'"
                                        :class="[
                                            'px-3 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5',
                                            displayMode === 'compact'
                                                ? 'bg-[#107c41] text-white shadow-2xs font-bold'
                                                : 'text-slate-600 hover:text-slate-900'
                                        ]"
                                    >
                                        <span>Ringkas (Juta)</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Financial Table -->
                            <table class="w-full text-xs border-collapse">
                                <thead>
                                    <tr class="text-slate-500 font-semibold border-b border-slate-200">
                                        <!-- Row header space -->
                                        <th class="py-2 pr-4 text-left w-36 font-bold text-slate-700">
                                            Kategori {{ displayMode === 'full' ? '(Rp)' : '(Juta)' }}
                                        </th>
                                        <!-- Month column headers -->
                                        <th 
                                            v-for="(m, i) in monthly_trends" 
                                            :key="'tbl-th-' + m.month"
                                            :class="[
                                                'py-2 px-2 text-right font-bold transition-colors min-w-[95px]',
                                                hoveredIndex === i ? 'text-[#107c41] bg-emerald-50/50' : 'text-slate-600'
                                            ]"
                                        >
                                            {{ m.month }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Row 1: Penerimaan -->
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <td class="py-2 pr-4 text-left font-bold text-slate-800 whitespace-nowrap">
                                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-[#107c41] mr-2 align-middle"></span>
                                            <span class="align-middle">Penerimaan</span>
                                        </td>
                                        <td 
                                            v-for="(m, i) in monthly_trends" 
                                            :key="'tbl-pen-' + m.month"
                                            :class="[
                                                'py-2 px-2 text-right font-mono transition-colors',
                                                hoveredIndex === i ? 'bg-emerald-50 text-emerald-950 font-bold' : 'text-slate-800'
                                            ]"
                                            :title="formatRupiah(m.penerimaan)"
                                        >
                                            <template v-if="displayMode === 'full'">
                                                {{ formatNominalFull(m.penerimaan) }}
                                            </template>
                                            <template v-else>
                                                {{ formatJuta(m.penerimaan) }}
                                            </template>
                                        </td>
                                    </tr>

                                    <!-- Row 2: Pengeluaran -->
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <td class="py-2 pr-4 text-left font-bold text-slate-700 whitespace-nowrap">
                                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-slate-400 mr-2 align-middle"></span>
                                            <span class="align-middle">Pengeluaran</span>
                                        </td>
                                        <td 
                                            v-for="(m, i) in monthly_trends" 
                                            :key="'tbl-peng-' + m.month"
                                            :class="[
                                                'py-2 px-2 text-right font-mono transition-colors',
                                                hoveredIndex === i ? 'bg-slate-100 text-slate-900 font-bold' : 'text-slate-700'
                                            ]"
                                            :title="formatRupiah(m.pengeluaran)"
                                        >
                                            <template v-if="displayMode === 'full'">
                                                {{ formatNominalFull(m.pengeluaran) }}
                                            </template>
                                            <template v-else>
                                                {{ formatJuta(m.pengeluaran) }}
                                            </template>
                                        </td>
                                    </tr>

                                    <!-- Horizontal Divider Line -->
                                    <tr>
                                        <td :colspan="(monthly_trends?.length || 0) + 1" class="py-1">
                                            <div class="border-t-2 border-slate-200"></div>
                                        </td>
                                    </tr>

                                    <!-- Row 3: Selisih -->
                                    <tr class="font-bold">
                                        <td class="py-2 pr-4 text-left font-bold text-[#107c41] whitespace-nowrap">
                                            Selisih
                                        </td>
                                        <td 
                                            v-for="(m, i) in monthly_trends" 
                                            :key="'tbl-sel-' + m.month"
                                            :class="[
                                                'py-2 px-2 text-right font-mono font-bold transition-colors',
                                                hoveredIndex === i ? 'bg-emerald-50 ring-1 ring-emerald-300 rounded' : '',
                                                (m.saldo ?? (m.penerimaan - m.pengeluaran)) >= 0 ? 'text-emerald-900' : 'text-rose-700'
                                            ]"
                                            :title="formatRupiah(m.saldo ?? (m.penerimaan - m.pengeluaran))"
                                        >
                                            <template v-if="displayMode === 'full'">
                                                {{ formatSelisihFull(m.saldo ?? (m.penerimaan - m.pengeluaran)) }}
                                            </template>
                                            <template v-else>
                                                {{ formatSelisih(m.saldo ?? (m.penerimaan - m.pengeluaran)) }}
                                            </template>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </AppLayout>
</template>
