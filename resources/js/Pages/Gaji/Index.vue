<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    Users, 
    Coins, 
    Landmark, 
    Calculator, 
    AlertTriangle, 
    CheckCircle2, 
    Info, 
    FileText, 
    ArrowRight, 
    ShieldCheck, 
    Settings,
    CreditCard,
    TrendingDown,
    Building2,
    PieChart
} from 'lucide-vue-next';

const props = defineProps({
    year: Number,
    month: Number,
    kpis: Object,
    budget: Object,
    recent_periods: Array,
});

const formatRupiah = (val) => {
    if (val === null || val === undefined || isNaN(val)) return 'Rp 0';
    return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

const monthNames = [
    '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const currentMonthName = computed(() => monthNames[props.month] || 'November');
</script>

<template>
    <AppLayout>
        <Head title="Modul Gaji & Hak Keuangan DPRD - Keuangan Setwan" />

        <div class="space-y-6 pb-12">
            <!-- Header & Sub Navigation -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200/80 p-5">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                TA {{ year }}
                            </span>
                            <span class="text-xs text-slate-400">•</span>
                            <span class="text-xs font-medium text-slate-500">Pemerintah Kabupaten Banyumas</span>
                        </div>
                        <h1 class="text-xl md:text-2xl font-bold text-slate-800 tracking-tight mt-1">
                            Perhitungan Gaji, Tunjangan & Hak Keuangan DPRD
                        </h1>
                        <p class="text-xs md:text-sm text-slate-500 mt-0.5">
                            Otomatisasi penggajian ASN (PNS, PPPK) dan Hak Keuangan Pimpinan & Anggota DPRD dengan verifikasi kontrol anggaran real-time.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <Link
                            href="/gaji/simulasi"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-[#107c41] hover:bg-[#0e6b37] text-white text-xs md:text-sm font-semibold rounded-lg shadow-xs transition-all cursor-pointer"
                        >
                            <Calculator class="w-4 h-4" />
                            <span>Jalankan Simulasi Pembayaran</span>
                        </Link>
                    </div>
                </div>

                <!-- Navigation Tabs Submenu -->
                <div class="flex items-center gap-1.5 mt-5 pt-4 border-t border-slate-100 overflow-x-auto text-xs">
                    <Link
                        href="/gaji"
                        class="px-3.5 py-1.5 rounded-lg font-bold bg-[#107c41] text-white transition-all whitespace-nowrap"
                    >
                        Dashboard & Kontrol
                    </Link>
                    <Link
                        href="/gaji/penerima"
                        class="px-3.5 py-1.5 rounded-lg font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-all whitespace-nowrap"
                    >
                        Data Penerima (ASN & DPRD)
                    </Link>
                    <Link
                        href="/gaji/simulasi"
                        class="px-3.5 py-1.5 rounded-lg font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-all whitespace-nowrap"
                    >
                        Simulasi & Hitung Otomatis
                    </Link>
                    <Link
                        href="/gaji/laporan"
                        class="px-3.5 py-1.5 rounded-lg font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-all whitespace-nowrap"
                    >
                        Riwayat & Ekspor Laporan
                    </Link>
                    <Link
                        href="/gaji/pengaturan"
                        class="px-3.5 py-1.5 rounded-lg font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-all whitespace-nowrap"
                    >
                        Tarif Regulasi & Plafon
                    </Link>
                </div>
            </div>

            <!-- Early Warning / Status Kontrol Anggaran Banner -->
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
                            <span class="text-xs font-medium text-slate-500">Estimasi Bulan {{ currentMonthName }} {{ year }}</span>
                        </div>
                        <p class="text-xs md:text-sm mt-1">
                            <span v-if="budget?.is_sufficient">
                                Plafon anggaran kas bulanan sebesar <strong>{{ formatRupiah(budget?.monthly_allocation?.total) }}</strong> mencukupi untuk memenuhi kebutuhan pembayaran gaji & hak keuangan sebesar <strong>{{ formatRupiah(kpis?.total_gross) }}</strong>.
                                Sisa saldo kas bulanan: <strong>{{ formatRupiah(budget?.budget_difference) }}</strong>.
                            </span>
                            <span v-else>
                                Perhatian: Total kebutuhan pembayaran sebesar <strong>{{ formatRupiah(kpis?.total_gross) }}</strong> melampaui plafon alokasi kas bulanan <strong>{{ formatRupiah(budget?.monthly_allocation?.total) }}</strong>. Terdapat kekurangan saldo sebesar <strong>{{ formatRupiah(Math.abs(budget?.budget_difference)) }}</strong>.
                            </span>
                        </p>
                    </div>
                </div>

                <div class="shrink-0 flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <span class="text-[11px] text-slate-500 block">Rasio Kebutuhan vs Plafon</span>
                        <span class="text-base font-bold" :class="budget?.is_sufficient ? 'text-emerald-700' : 'text-rose-700'">
                            {{ budget?.burn_rate_pct }}%
                        </span>
                    </div>
                    <Link
                        href="/gaji/simulasi"
                        class="px-3.5 py-2 rounded-lg text-xs font-bold transition-all"
                        :class="budget?.is_sufficient ? 'bg-emerald-700 hover:bg-emerald-800 text-white' : 'bg-rose-700 hover:bg-rose-800 text-white'"
                    >
                        Buka Detail Diagnosa
                    </Link>
                </div>
            </div>

            <!-- KPI Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Card 1: Penerima -->
                <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500">Total Penerima Aktif</span>
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                            <Users class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-slate-800">{{ kpis?.total_recipients }}</span>
                        <span class="text-xs text-slate-500 font-medium">Orang</span>
                    </div>
                    <div class="mt-2.5 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-600">
                        <span>ASN: <strong>{{ kpis?.total_asn }}</strong> (20 PNS, 24 PW, 17 PT)</span>
                        <span>DPRD: <strong>{{ kpis?.total_dprd }}</strong></span>
                    </div>
                </div>

                <!-- Card 2: Total Bruto Kebutuhan -->
                <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500">Kebutuhan Bruto Bulanan</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <Coins class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="text-xl font-bold text-slate-800 tracking-tight">{{ formatRupiah(kpis?.total_gross) }}</span>
                    </div>
                    <div class="mt-2.5 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-600">
                        <span>DPRD: {{ formatRupiah(kpis?.dprd_gross) }}</span>
                        <span>ASN: {{ formatRupiah(kpis?.asn_gross) }}</span>
                    </div>
                </div>

                <!-- Card 3: Total Potongan -->
                <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500">Estimasi Total Potongan</span>
                        <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                            <TrendingDown class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="text-xl font-bold text-slate-800 tracking-tight">{{ formatRupiah(kpis?.total_deductions) }}</span>
                    </div>
                    <div class="mt-2.5 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-600">
                        <span>PPh 21, IWP 1%, Iuran BPJS</span>
                        <span class="text-emerald-600 font-semibold">Tercatat Otomatis</span>
                    </div>
                </div>

                <!-- Card 4: Take Home Pay (Netto) -->
                <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500">Take Home Pay (Netto)</span>
                        <div class="w-8 h-8 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center">
                            <CreditCard class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="text-xl font-bold text-emerald-700 tracking-tight">{{ formatRupiah(kpis?.total_netto) }}</span>
                    </div>
                    <div class="mt-2.5 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-600">
                        <span>Dana siap bayar via bank transfer</span>
                        <span class="font-bold text-slate-700">Bank Jateng</span>
                    </div>
                </div>
            </div>

            <!-- Two Columns Section: Breakdown & Diagnosa -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Col 1: Rincian Pagu Anggaran vs Realisasi -->
                <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                            <Landmark class="w-4 h-4 text-emerald-600" />
                            <span>Plafon Alokasi Anggaran (APBD {{ year }})</span>
                        </h3>
                        <Link href="/gaji/pengaturan" class="text-xs text-emerald-600 hover:underline">
                            Ubah Plafon
                        </Link>
                    </div>

                    <div class="space-y-3 text-xs">
                        <!-- DPRD Budget Row -->
                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
                            <div class="flex justify-between font-semibold text-slate-700">
                                <span>Belanja Gaji & Tunjangan DPRD</span>
                                <span>{{ formatRupiah(budget?.annual_pagu?.dprd) }} / thn</span>
                            </div>
                            <div class="flex justify-between text-slate-500 mt-1">
                                <span>Alokasi Kas Bulanan:</span>
                                <span class="font-medium text-slate-800">{{ formatRupiah(budget?.monthly_allocation?.dprd) }}</span>
                            </div>
                            <div class="flex justify-between text-slate-500 mt-0.5">
                                <span>Kebutuhan Pembayaran DPRD:</span>
                                <span class="font-bold text-emerald-700">{{ formatRupiah(kpis?.dprd_gross) }}</span>
                            </div>
                        </div>

                        <!-- ASN Budget Row -->
                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
                            <div class="flex justify-between font-semibold text-slate-700">
                                <span>Belanja Gaji, Tunjangan & TPP ASN</span>
                                <span>{{ formatRupiah(budget?.annual_pagu?.asn) }} / thn</span>
                            </div>
                            <div class="flex justify-between text-slate-500 mt-1">
                                <span>Alokasi Kas Bulanan:</span>
                                <span class="font-medium text-slate-800">{{ formatRupiah(budget?.monthly_allocation?.asn) }}</span>
                            </div>
                            <div class="flex justify-between text-slate-500 mt-0.5">
                                <span>Kebutuhan Pembayaran ASN:</span>
                                <span class="font-bold text-emerald-700">{{ formatRupiah(kpis?.asn_gross) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-emerald-50/50 rounded-lg border border-emerald-100 text-xs space-y-1">
                        <div class="flex justify-between text-slate-600">
                            <span>Total Plafon Bulanan:</span>
                            <span class="font-semibold">{{ formatRupiah(budget?.monthly_allocation?.total) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Total Kebutuhan:</span>
                            <span class="font-semibold text-slate-800">{{ formatRupiah(budget?.gross_requirement?.total) }}</span>
                        </div>
                        <div class="flex justify-between text-emerald-800 font-bold pt-1 border-t border-emerald-200">
                            <span>Sisa Saldo Kas:</span>
                            <span>{{ formatRupiah(budget?.budget_difference) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Col 2 & 3: Diagnosa Selisih & Rekomendasi Cerdas -->
                <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                                <ShieldCheck class="w-4 h-4 text-emerald-600" />
                                <span>Hasil Diagnosa Sistem & Kepatuhan Regulasi</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Pengecekan otomatis atas indikasi kesalahan input, perhitungan ganda, dan kelengkapan tunjangan.
                            </p>
                        </div>
                        <span class="text-xs px-2 py-0.5 bg-slate-100 text-slate-600 rounded-md font-medium">
                            {{ budget?.diagnostics?.length || 0 }} Temuan
                        </span>
                    </div>

                    <div class="space-y-3">
                        <div 
                            v-for="(diag, idx) in budget?.diagnostics" 
                            :key="idx"
                            :class="[
                                'p-3.5 rounded-lg border text-xs space-y-1 transition-all',
                                diag.severity === 'danger' ? 'bg-rose-50/70 border-rose-200 text-rose-900' :
                                diag.severity === 'warning' ? 'bg-amber-50/70 border-amber-200 text-amber-900' :
                                diag.severity === 'info' ? 'bg-sky-50/70 border-sky-200 text-sky-900' :
                                'bg-emerald-50/70 border-emerald-200 text-emerald-900'
                            ]"
                        >
                            <div class="flex items-center gap-2 font-bold">
                                <AlertTriangle v-if="diag.severity === 'danger' || diag.severity === 'warning'" class="w-4 h-4 shrink-0" />
                                <CheckCircle2 v-else-if="diag.severity === 'success'" class="w-4 h-4 shrink-0 text-emerald-600" />
                                <Info v-else class="w-4 h-4 shrink-0 text-sky-600" />
                                <span>{{ diag.title }}</span>
                            </div>
                            <p class="pl-6 text-slate-600 leading-relaxed">{{ diag.message }}</p>
                            <p class="pl-6 pt-1 text-[11.5px] font-medium" :class="diag.severity === 'danger' ? 'text-rose-700' : 'text-slate-700'">
                                💡 <strong>Saran Tindakan:</strong> {{ diag.recommendation }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Riwayat Periode Terakhir -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                    <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                        <FileText class="w-4 h-4 text-emerald-600" />
                        <span>Riwayat Penggajian Terakhir yang Dibukukan</span>
                    </h3>
                    <Link href="/gaji/laporan" class="text-xs text-emerald-600 hover:underline flex items-center gap-1">
                        <span>Lihat Semua Riwayat</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </Link>
                </div>

                <div v-if="recent_periods && recent_periods.length > 0" class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-600 border-b border-slate-200">
                            <tr>
                                <th class="py-2.5 px-3">Periode</th>
                                <th class="py-2.5 px-3">Jumlah Penerima</th>
                                <th class="py-2.5 px-3">Penghasilan Bruto</th>
                                <th class="py-2.5 px-3">Total Potongan</th>
                                <th class="py-2.5 px-3">Take Home Pay</th>
                                <th class="py-2.5 px-3">Status Anggaran</th>
                                <th class="py-2.5 px-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="p in recent_periods" :key="p.id" class="hover:bg-slate-50/60">
                                <td class="py-2.5 px-3 font-semibold text-slate-800">{{ p.period_title }}</td>
                                <td class="py-2.5 px-3">{{ p.total_recipients }} Orang</td>
                                <td class="py-2.5 px-3 font-mono font-medium">{{ formatRupiah(p.total_gross) }}</td>
                                <td class="py-2.5 px-3 font-mono text-amber-700">{{ formatRupiah(p.total_deductions) }}</td>
                                <td class="py-2.5 px-3 font-mono font-bold text-emerald-700">{{ formatRupiah(p.total_netto) }}</td>
                                <td class="py-2.5 px-3">
                                    <span 
                                        :class="p.is_budget_sufficient ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                        class="px-2 py-0.5 rounded text-[10.5px] font-bold"
                                    >
                                        {{ p.is_budget_sufficient ? 'Mencukupi' : 'Defisit' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <Link 
                                        :href="`/gaji/laporan?period_id=${p.id}`"
                                        class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 rounded text-slate-700 font-medium text-[11px]"
                                    >
                                        Buka Rincian
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="text-center py-8 text-slate-400 text-xs">
                    <p>Belum ada data periode penggajian yang dibukukan secara permanen.</p>
                    <Link href="/gaji/simulasi" class="mt-2 inline-flex items-center gap-1.5 text-emerald-600 font-semibold hover:underline">
                        <span>Mulai simulasi penggajian pertama</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
