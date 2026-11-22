<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { 
    FileText, 
    Download, 
    Printer, 
    Search, 
    Calendar, 
    Coins, 
    CreditCard, 
    CheckCircle2, 
    AlertTriangle,
    Eye,
    TrendingDown,
    Building2,
    Shield
} from 'lucide-vue-next';

const props = defineProps({
    year: Number,
    month: [Number, String],
    periods: Array,
    active_period: Object,
    items: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');
const category = ref(props.filters.category || '');

const formatRupiah = (val) => {
    if (val === null || val === undefined || isNaN(val)) return 'Rp 0';
    return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

const handleFilter = () => {
    router.get('/gaji/laporan', {
        year: props.year,
        period_id: props.active_period?.id,
        search: search.value,
        category: category.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const selectPeriod = (id) => {
    router.get('/gaji/laporan', {
        year: props.year,
        period_id: id,
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Riwayat Penggajian & Ekspor Laporan - Keuangan Setwan" />

        <div class="space-y-6 pb-12">
            <!-- Header & Sub Navigation -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200/80 p-5">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                Laporan & Dokumen Resmi
                            </span>
                            <span class="text-xs text-slate-400">•</span>
                            <span class="text-xs font-medium text-slate-500">TA {{ year }}</span>
                        </div>
                        <h1 class="text-xl md:text-2xl font-bold text-slate-800 tracking-tight mt-1">
                            Riwayat Penggajian, Daftar Nominatif & Slip Gaji
                        </h1>
                        <p class="text-xs md:text-sm text-slate-500 mt-0.5">
                            Arsip resmi pembayaran penghasilan ASN dan hak keuangan DPRD, unduh rekap Excel dan cetak slip individual PDF.
                        </p>
                    </div>

                    <div v-if="active_period" class="flex items-center gap-2 shrink-0">
                        <a
                            :href="`/gaji/laporan/periode/${active_period.id}/export-excel`"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-xs transition-all"
                        >
                            <Download class="w-3.5 h-3.5" />
                            <span>Unduh Nominatif Excel</span>
                        </a>
                    </div>
                </div>

                <!-- Navigation Tabs Submenu -->
                <div class="flex items-center gap-1.5 mt-5 pt-4 border-t border-slate-100 overflow-x-auto text-xs">
                    <Link
                        href="/gaji"
                        class="px-3.5 py-1.5 rounded-lg font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-all whitespace-nowrap"
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
                        class="px-3.5 py-1.5 rounded-lg font-bold bg-[#107c41] text-white transition-all whitespace-nowrap"
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

            <!-- Periode Selector Bar -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-700 flex items-center gap-1.5">
                        <Calendar class="w-4 h-4 text-emerald-600" />
                        <span>Pilih Periode Penggajian:</span>
                    </span>
                    <select
                        :value="active_period?.id"
                        @change="selectPeriod($event.target.value)"
                        class="py-1.5 px-3 rounded-lg border border-slate-300 font-medium text-slate-800"
                    >
                        <option v-for="p in periods" :key="p.id" :value="p.id">
                            {{ p.period_title }} ({{ p.total_recipients }} Orang)
                        </option>
                    </select>
                </div>

                <div v-if="active_period" class="flex items-center gap-3 text-slate-500">
                    <span>Status: <strong class="uppercase text-emerald-700">{{ active_period.status }}</strong></span>
                    <span>•</span>
                    <span>Waktu Pembukuan: <strong>{{ new Date(active_period.created_at).toLocaleDateString('id-ID') }}</strong></span>
                </div>
            </div>

            <!-- Active Period Overview Cards -->
            <div v-if="active_period" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
                    <span class="text-xs text-slate-500 font-medium">Total Penerima</span>
                    <div class="text-xl font-bold text-slate-800 mt-1">{{ active_period.total_recipients }} Orang</div>
                    <div class="text-[11px] text-slate-400 mt-1">ASN & Pimpinan/Anggota DPRD</div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
                    <span class="text-xs text-slate-500 font-medium">Total Penghasilan Bruto</span>
                    <div class="text-xl font-bold text-slate-800 mt-1">{{ formatRupiah(active_period.total_gross) }}</div>
                    <div class="text-[11px] text-slate-400 mt-1">DPRD: {{ formatRupiah(active_period.total_dprd_gross) }}</div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
                    <span class="text-xs text-slate-500 font-medium">Total Potongan (PPh/IWP)</span>
                    <div class="text-xl font-bold text-rose-700 mt-1">{{ formatRupiah(active_period.total_deductions) }}</div>
                    <div class="text-[11px] text-slate-400 mt-1">PPh 21, BPJS Kes, IWP 1%</div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
                    <span class="text-xs text-slate-500 font-medium">Take Home Pay (Netto)</span>
                    <div class="text-xl font-bold text-emerald-700 mt-1">{{ formatRupiah(active_period.total_netto) }}</div>
                    <div class="text-[11px] text-emerald-600 font-medium mt-1">Siap disalurkan via Bank Jateng</div>
                </div>
            </div>

            <!-- Search and Filter inside active period -->
            <div v-if="active_period" class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2 relative">
                        <Search class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                        <input
                            v-model="search"
                            @keyup.enter="handleFilter"
                            type="text"
                            placeholder="Cari nama pegawai atau dewan di periode ini..."
                            class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        />
                    </div>
                    <div>
                        <select
                            v-model="category"
                            @change="handleFilter"
                            class="w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300"
                        >
                            <option value="">Semua Kategori (ASN & DPRD)</option>
                            <option value="asn">Hanya ASN</option>
                            <option value="dprd">Hanya DPRD</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Detailed Nominative Items Table -->
            <div v-if="active_period && items" class="bg-white rounded-xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-600 border-b border-slate-200 font-semibold">
                            <tr>
                                <th class="py-3 px-3 text-center w-10">No</th>
                                <th class="py-3 px-3">Nama Penerima</th>
                                <th class="py-3 px-3">Kategori</th>
                                <th class="py-3 px-3">Jabatan</th>
                                <th class="py-3 px-3 text-right">Gaji Pokok / Rep</th>
                                <th class="py-3 px-3 text-right">Tunjangan & TPP</th>
                                <th class="py-3 px-3 text-right">Bruto</th>
                                <th class="py-3 px-3 text-right text-rose-700">Potongan</th>
                                <th class="py-3 px-3 text-right text-emerald-700 font-bold">Netto (THP)</th>
                                <th class="py-3 px-3 text-center">Cetak Slip</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(it, idx) in items.data" :key="it.id" class="hover:bg-slate-50/70">
                                <td class="py-2.5 px-3 text-center text-slate-400 font-mono">
                                    {{ (items.current_page - 1) * items.per_page + idx + 1 }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-slate-800">{{ it.recipient?.name }}</div>
                                    <div class="text-[10px] text-slate-400">{{ it.recipient?.identifier_number || '-' }}</div>
                                </td>
                                <td class="py-2.5 px-3">
                                    <span 
                                        :class="it.category === 'dprd' ? 'bg-blue-50 text-blue-800 border-blue-200' : 'bg-emerald-50 text-emerald-800 border-emerald-200'"
                                        class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                    >
                                        {{ it.recipient?.sub_category_label }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-slate-700">{{ it.recipient?.position_name }}</td>
                                <td class="py-2.5 px-3 text-right font-mono">{{ formatRupiah(it.basic_salary_or_rep) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-600">
                                    {{ formatRupiah(it.gross_amount - it.basic_salary_or_rep) }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-medium text-slate-800">{{ formatRupiah(it.gross_amount) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-rose-600">{{ formatRupiah(it.total_deductions) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-700 bg-emerald-50/30">
                                    {{ formatRupiah(it.net_amount) }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <a
                                        :href="`/gaji/laporan/item/${it.id}/export-slip`"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-[11px] font-medium"
                                        title="Unduh Slip Gaji PDF"
                                    >
                                        <Printer class="w-3.5 h-3.5 text-slate-500" />
                                        <span>Slip</span>
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="items.links && items.links.length > 3" class="p-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <div>
                        Menampilkan {{ items.from || 0 }} - {{ items.to || 0 }} dari {{ items.total }} data
                    </div>
                    <div class="flex items-center gap-1">
                        <Link
                            v-for="(link, i) in items.links"
                            :key="i"
                            :href="link.url || '#'"
                            :class="[
                                'px-2.5 py-1 rounded text-xs',
                                link.active ? 'bg-[#107c41] text-white font-bold' : 'text-slate-600 hover:bg-slate-100',
                                !link.url ? 'opacity-40 pointer-events-none' : ''
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!active_period" class="bg-white rounded-xl border border-slate-200/80 p-12 text-center text-slate-400 text-xs">
                <FileText class="w-10 h-10 mx-auto text-slate-300 mb-2" />
                <p class="font-medium text-slate-600 text-sm">Belum ada periode penggajian yang dipilih atau dibukukan.</p>
                <p class="mt-1 text-slate-400">Silakan buka menu Simulasi untuk menghitung dan membukukan penggajian bulan berjalan.</p>
                <Link href="/gaji/simulasi" class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 bg-[#107c41] text-white rounded-lg font-bold">
                    <span>Buka Menu Simulasi</span>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
