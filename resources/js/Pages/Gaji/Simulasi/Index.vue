<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { 
    Calculator, 
    Coins, 
    ShieldCheck, 
    AlertTriangle, 
    CheckCircle2, 
    Info, 
    FileCheck2, 
    RefreshCw, 
    Download, 
    Eye,
    TrendingDown,
    Building2,
    Users
} from 'lucide-vue-next';

const props = defineProps({
    year: Number,
    month: Number,
    productivity_pct: Number,
    discipline_pct: Number,
    is_reses: Boolean,
    summary: Object,
    budget: Object,
    items: Array,
    existing_period: Object,
});

const simMonth = ref(props.month);
const simYear = ref(props.year);
const simProd = ref(props.productivity_pct);
const simDisp = ref(props.discipline_pct);
const simReses = ref(props.is_reses);
const activeFilterCategory = ref('all');
const searchQuery = ref('');

const monthNames = [
    '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const formatRupiah = (val) => {
    if (val === null || val === undefined || isNaN(val)) return 'Rp 0';
    return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

const runSimulation = () => {
    router.get('/gaji/simulasi', {
        year: simYear.value,
        month: simMonth.value,
        productivity_pct: simProd.value,
        discipline_pct: simDisp.value,
        is_reses: simReses.value ? 1 : 0,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const commitForm = useForm({
    year: props.year,
    month: props.month,
    productivity_pct: props.productivity_pct,
    discipline_pct: props.discipline_pct,
    is_reses: props.is_reses,
});

const handleCommit = () => {
    if (confirm(`Apakah Anda yakin ingin membukukan perhitungan penggajian Bulan ${monthNames[props.month]} ${props.year}? Data akan tersimpan permanen untuk riwayat & cetak slip.`)) {
        commitForm.year = simYear.value;
        commitForm.month = simMonth.value;
        commitForm.productivity_pct = simProd.value;
        commitForm.discipline_pct = simDisp.value;
        commitForm.is_reses = simReses.value;
        commitForm.post('/gaji/simulasi/commit');
    }
};

const filteredItems = computed(() => {
    let list = props.items || [];
    if (activeFilterCategory.value !== 'all') {
        if (activeFilterCategory.value === 'asn') {
            list = list.filter(i => i.category === 'asn');
        } else if (activeFilterCategory.value === 'dprd') {
            list = list.filter(i => i.category === 'dprd');
        } else {
            list = list.filter(i => i.sub_category === activeFilterCategory.value);
        }
    }
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(i => 
            (i.recipient_name && i.recipient_name.toLowerCase().includes(q)) ||
            (i.position_name && i.position_name.toLowerCase().includes(q))
        );
    }
    return list;
});
</script>

<template>
    <AppLayout>
        <Head title="Simulasi Pembayaran Gaji & Tunjangan - Keuangan Setwan" />

        <div class="space-y-6 pb-12">
            <!-- Header & Sub Navigation -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200/80 p-5">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                Simulasi Real-Time
                            </span>
                            <span class="text-xs text-slate-400">•</span>
                            <span class="text-xs font-medium text-slate-500">Bulan {{ monthNames[month] }} {{ year }}</span>
                        </div>
                        <h1 class="text-xl md:text-2xl font-bold text-slate-800 tracking-tight mt-1">
                            Simulasi Perhitungan & Verifikasi Saldo Kas
                        </h1>
                        <p class="text-xs md:text-sm text-slate-500 mt-0.5">
                            Cek seluruh perhitungan penghasilan dan kecukupan anggaran secara instan sebelum melakukan pembayaran resmi.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            @click="handleCommit"
                            :disabled="commitForm.processing"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-[#107c41] hover:bg-[#0e6b37] text-white text-xs md:text-sm font-semibold rounded-lg shadow-xs transition-all cursor-pointer"
                        >
                            <FileCheck2 class="w-4 h-4" />
                            <span>{{ existing_period ? 'Perbarui Pembukuan' : 'Simpan & Bukukan Penggajian' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Simulation Parameters Controls Card -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5 mb-3">
                    <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                        <Calculator class="w-4 h-4 text-emerald-600" />
                        <span>Parameter Simulasi Pembayaran</span>
                    </span>
                    <span v-if="existing_period" class="text-[11px] px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold">
                        Sudah Dibukukan Sebelumnya
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-5 gap-3 text-xs">
                    <div>
                        <label class="block text-slate-500 font-semibold mb-1">Bulan</label>
                        <select v-model="simMonth" class="w-full py-1.5 px-3 rounded-lg border border-slate-300">
                            <option v-for="(m, i) in monthNames.slice(1)" :key="i" :value="i + 1">{{ m }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-500 font-semibold mb-1">Tahun Anggaran</label>
                        <input v-model="simYear" type="number" class="w-full py-1.5 px-3 rounded-lg border border-slate-300 font-medium" />
                    </div>

                    <div>
                        <label class="block text-slate-500 font-semibold mb-1">Capaian Produktivitas (%)</label>
                        <input v-model="simProd" type="number" min="0" max="100" step="0.5" class="w-full py-1.5 px-3 rounded-lg border border-slate-300" />
                    </div>

                    <div>
                        <label class="block text-slate-500 font-semibold mb-1">Disiplin Kerja (%)</label>
                        <input v-model="simDisp" type="number" min="0" max="100" step="0.5" class="w-full py-1.5 px-3 rounded-lg border border-slate-300" />
                    </div>

                    <div class="flex flex-col justify-end">
                        <div class="flex items-center gap-2">
                            <label class="flex items-center gap-1.5 cursor-pointer py-1.5 text-slate-700 font-medium">
                                <input v-model="simReses" type="checkbox" class="rounded text-emerald-600" />
                                <span>Bulan Reses</span>
                            </label>
                            <button
                                type="button"
                                @click="runSimulation"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 py-1.5 px-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold cursor-pointer transition-all"
                            >
                                <RefreshCw class="w-3.5 h-3.5" />
                                <span>Hitung Ulang</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kontrol Anggaran & Diagnosa Alert Banner -->
            <div 
                :class="[
                    'rounded-xl border p-4.5 transition-all shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4',
                    budget?.is_sufficient 
                        ? 'bg-emerald-50/70 border-emerald-200 text-emerald-900' 
                        : 'bg-rose-50/70 border-rose-200 text-rose-900'
                ]"
            >
                <div class="flex items-start gap-3.5">
                    <div 
                        :class="[
                            'w-10 h-10 rounded-lg flex items-center justify-center shrink-0 shadow-xs',
                            budget?.is_sufficient ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'
                        ]"
                    >
                        <ShieldCheck v-if="budget?.is_sufficient" class="w-5 h-5" />
                        <AlertTriangle v-else class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded-md" :class="budget?.is_sufficient ? 'bg-emerald-200/80 text-emerald-800' : 'bg-rose-200/80 text-rose-800'">
                                {{ budget?.status_label }}
                            </span>
                            <span class="text-xs font-medium text-slate-500">Hasil Analisis Plafon Kas</span>
                        </div>
                        <p class="text-xs md:text-sm mt-1">
                            Total kebutuhan: <strong>{{ formatRupiah(summary?.total_gross) }}</strong> vs Plafon Kas Bulanan: <strong>{{ formatRupiah(budget?.monthly_allocation?.total) }}</strong>.
                            <span v-if="budget?.is_sufficient" class="text-emerald-700 font-bold ml-1">
                                Saldo Kas Sisa: +{{ formatRupiah(budget?.budget_difference) }}
                            </span>
                            <span v-else class="text-rose-700 font-bold ml-1">
                                Defisit Kekurangan: -{{ formatRupiah(Math.abs(budget?.budget_difference)) }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="shrink-0 flex items-center gap-2">
                    <button
                        type="button"
                        @click="handleCommit"
                        class="px-4 py-2 rounded-lg text-xs font-bold text-white transition-all cursor-pointer shadow-xs"
                        :class="budget?.is_sufficient ? 'bg-emerald-700 hover:bg-emerald-800' : 'bg-rose-700 hover:bg-rose-800'"
                    >
                        Bukukan Hasil Sekarang
                    </button>
                </div>
            </div>

            <!-- Diagnostics Findings List -->
            <div v-if="budget?.diagnostics && budget.diagnostics.length > 0" class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <span class="text-xs font-bold text-slate-700">Rincian Diagnosa Selisih & Indikator Kesalahan</span>
                    <span class="text-[11px] text-slate-400">Deteksi Otomatis Sistem</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                    <div 
                        v-for="(diag, idx) in budget.diagnostics" 
                        :key="idx"
                        :class="[
                            'p-3 rounded-lg border space-y-1',
                            diag.severity === 'danger' ? 'bg-rose-50/70 border-rose-200 text-rose-900' :
                            diag.severity === 'warning' ? 'bg-amber-50/70 border-amber-200 text-amber-900' :
                            diag.severity === 'info' ? 'bg-sky-50/70 border-sky-200 text-sky-900' :
                            'bg-emerald-50/70 border-emerald-200 text-emerald-900'
                        ]"
                    >
                        <div class="font-bold flex items-center gap-1.5">
                            <AlertTriangle v-if="diag.severity === 'danger' || diag.severity === 'warning'" class="w-3.5 h-3.5 shrink-0" />
                            <CheckCircle2 v-else-if="diag.severity === 'success'" class="w-3.5 h-3.5 shrink-0 text-emerald-600" />
                            <Info v-else class="w-3.5 h-3.5 shrink-0 text-sky-600" />
                            <span>{{ diag.title }}</span>
                        </div>
                        <p class="text-slate-600 text-[11px] pl-5">{{ diag.message }}</p>
                        <p class="text-slate-700 font-medium text-[11px] pl-5">💡 {{ diag.recommendation }}</p>
                    </div>
                </div>
            </div>

            <!-- Filter Buttons & Search -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-1 overflow-x-auto text-xs">
                    <button
                        type="button"
                        @click="activeFilterCategory = 'all'"
                        :class="activeFilterCategory === 'all' ? 'bg-[#107c41] text-white font-bold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-3 py-1.5 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                    >
                        Semua ({{ items?.length || 0 }})
                    </button>
                    <button
                        type="button"
                        @click="activeFilterCategory = 'pns'"
                        :class="activeFilterCategory === 'pns' ? 'bg-[#107c41] text-white font-bold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-3 py-1.5 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                    >
                        PNS (20)
                    </button>
                    <button
                        type="button"
                        @click="activeFilterCategory = 'pppk_penuh_waktu'"
                        :class="activeFilterCategory === 'pppk_penuh_waktu' ? 'bg-[#107c41] text-white font-bold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-3 py-1.5 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                    >
                        PPPK Penuh Waktu (24)
                    </button>
                    <button
                        type="button"
                        @click="activeFilterCategory = 'pppk_paruh_waktu'"
                        :class="activeFilterCategory === 'pppk_paruh_waktu' ? 'bg-[#107c41] text-white font-bold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-3 py-1.5 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                    >
                        PPPK Paruh Waktu (17)
                    </button>
                    <button
                        type="button"
                        @click="activeFilterCategory = 'dprd'"
                        :class="activeFilterCategory === 'dprd' ? 'bg-[#107c41] text-white font-bold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-3 py-1.5 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                    >
                        DPRD Banyumas (50)
                    </button>
                </div>

                <div class="relative w-full sm:w-64">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Filter nama di tabel..."
                        class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-300"
                    />
                </div>
            </div>

            <!-- Detailed Simulation Calculation Table -->
            <div class="bg-white rounded-xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-[11px] text-left">
                        <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-bold">
                            <tr>
                                <th class="py-2.5 px-2.5 text-center w-10">No</th>
                                <th class="py-2.5 px-3">Nama Penerima</th>
                                <th class="py-2.5 px-2.5">Kategori</th>
                                <th class="py-2.5 px-2.5 text-right">Gaji Pokok / Rep</th>
                                <th class="py-2.5 px-2.5 text-right">Paket / Jabatan</th>
                                <th class="py-2.5 px-2.5 text-right">AKD / TKI</th>
                                <th class="py-2.5 px-2.5 text-right">Rumah / Transp</th>
                                <th class="py-2.5 px-2.5 text-right">TPP Daerah</th>
                                <th class="py-2.5 px-3 text-right">Bruto</th>
                                <th class="py-2.5 px-2.5 text-right text-rose-700">Potongan</th>
                                <th class="py-2.5 px-3 text-right text-emerald-700 font-bold">Take Home Pay</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr 
                                v-for="(it, idx) in filteredItems" 
                                :key="idx"
                                class="hover:bg-slate-50/70"
                            >
                                <td class="py-2 px-2.5 text-center text-slate-400 font-mono">{{ idx + 1 }}</td>
                                <td class="py-2 px-3">
                                    <div class="font-bold text-slate-800">{{ it.recipient_name }}</div>
                                    <div class="text-[10px] text-slate-400">{{ it.position_name }}</div>
                                </td>
                                <td class="py-2 px-2.5">
                                    <span 
                                        :class="it.category === 'dprd' ? 'bg-blue-50 text-blue-800 border-blue-200' : 'bg-emerald-50 text-emerald-800 border-emerald-200'"
                                        class="px-1.5 py-0.2 rounded text-[9.5px] font-bold border uppercase"
                                    >
                                        {{ it.sub_category.replace(/_/g, ' ') }}
                                    </span>
                                </td>
                                <td class="py-2 px-2.5 text-right font-mono">{{ formatRupiah(it.basic_salary_or_rep) }}</td>
                                <td class="py-2 px-2.5 text-right font-mono">{{ formatRupiah(it.package_allowance + it.position_allowance) }}</td>
                                <td class="py-2 px-2.5 text-right font-mono">{{ formatRupiah(it.akd_allowance + it.tki_allowance) }}</td>
                                <td class="py-2 px-2.5 text-right font-mono">{{ formatRupiah(it.housing_allowance + it.transport_allowance) }}</td>
                                <td class="py-2 px-2.5 text-right font-mono">{{ formatRupiah(it.tpp_amount) }}</td>
                                <td class="py-2 px-3 text-right font-mono font-semibold text-slate-800">{{ formatRupiah(it.gross_amount) }}</td>
                                <td class="py-2 px-2.5 text-right font-mono text-rose-600">{{ formatRupiah(it.total_deductions) }}</td>
                                <td class="py-2 px-3 text-right font-mono font-bold text-emerald-700 bg-emerald-50/30">
                                    {{ formatRupiah(it.net_amount) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-slate-50 font-bold border-t border-slate-200 text-xs">
                            <tr>
                                <td colspan="8" class="py-3 px-4 text-slate-700 text-right">TOTAL KEBUTUHAN (SIMULASI):</td>
                                <td class="py-3 px-3 text-right font-mono text-slate-900">{{ formatRupiah(summary?.total_gross) }}</td>
                                <td class="py-3 px-2.5 text-right font-mono text-rose-700">{{ formatRupiah(summary?.total_deductions) }}</td>
                                <td class="py-3 px-3 text-right font-mono text-emerald-700 bg-emerald-100/50">{{ formatRupiah(summary?.total_netto) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
