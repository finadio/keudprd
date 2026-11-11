<script setup>
import { ref } from 'vue';
import { Head, usePage, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    Printer, 
    Download, 
    FileSpreadsheet, 
    Landmark, 
    BookOpen, 
    Users,
    FileCheck,
    Archive,
    Mail,
    Send,
    CheckCircle2,
    AlertCircle,
    Loader2
} from 'lucide-vue-next';

const props = defineProps({
    year: Number,
    pptks: Array,
    setting: Object,
});

const page = usePage();
const selectedBankMonth = ref(1);
const selectedPptkId = ref('');

const emailForm = useForm({
    email: page.props.auth?.user?.email || 'bendahara@dprd.banyumaskab.go.id',
    year: props.year || 2026,
});

const sendBackupEmail = () => {
    emailForm.post('/laporan/backup-email', {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Laporan & Ekspor" />

        <div class="space-y-4">
            <!-- Header -->
            <div class="bg-white border border-slate-200 rounded p-4 shadow-sm">
                <h1 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                    <Printer class="w-5 h-5 text-emerald-600" />
                    Cetak Laporan & Ekspor File Excel (.xlsx)
                </h1>
                <p class="text-xs text-slate-500">
                    Format cetak dan ekspor spreadsheet 100% mengikuti standar workbook asli Sekretariat DPRD Kabupaten Banyumas.
                </p>
            </div>

            <!-- ================= BACKUP ARSIP ZIP & EMAIL ================= -->
            <div class="bg-gradient-to-br from-emerald-950 via-[#04331a] to-emerald-900 rounded-xl p-5 text-white shadow-md border border-emerald-800">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 text-[11px] font-bold mb-2">
                            <Archive class="w-3.5 h-3.5" />
                            <span>Cadangan Lengkap &amp; Formula Otomatis</span>
                        </div>
                        <h2 class="text-base sm:text-lg font-bold text-white tracking-tight">
                            Cadangkan Seluruh Buku Kas &amp; Mutasi Bank (Bentuk ZIP)
                        </h2>
                        <p class="text-xs text-emerald-100/80 mt-1 leading-relaxed">
                            Unduh seluruh arsip pembukuan dalam satu berkas <b>.zip</b>. Berisi salinan Excel asli <b>BKU Per PPTK (20 Sheet)</b> dan <b>Buku Bank (10 Bulan)</b> dengan <b>seluruh rumus running balance, saldo carryover, dan keterkaitan rumus antar sel yang utuh</b>, serta berkas dump database SQL &amp; JSON.
                        </p>
                    </div>

                    <!-- Direct Download ZIP Button -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 shrink-0">
                        <a 
                            :href="`/laporan/backup-zip?year=${year}`"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 rounded-lg text-xs font-black shadow-md hover:shadow-lg transition-all cursor-pointer"
                        >
                            <Download class="w-4 h-4 stroke-[2.5]" />
                            <span>Unduh Cadangan Lengkap (.zip)</span>
                        </a>
                    </div>
                </div>

                <!-- Email Backup Option Sub-Bar -->
                <div class="mt-4 pt-4 border-t border-emerald-800/80 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2 text-emerald-200">
                        <Mail class="w-4 h-4 text-emerald-400 shrink-0" />
                        <span class="font-medium">Kirim arsip cadangan ZIP langsung ke email:</span>
                    </div>

                    <form @submit.prevent="sendBackupEmail" class="flex items-center gap-2 max-w-md w-full">
                        <input 
                            v-model="emailForm.email"
                            type="email"
                            required
                            placeholder="Alamat email penerima..."
                            class="flex-1 px-3 py-1.5 rounded-lg bg-white/10 border border-emerald-700 text-white placeholder-emerald-300/50 text-xs focus:ring-1 focus:ring-emerald-400 outline-none"
                        />
                        <button 
                            type="submit"
                            :disabled="emailForm.processing"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-white hover:bg-emerald-50 text-emerald-950 font-bold rounded-lg text-xs transition-colors disabled:opacity-60 cursor-pointer shrink-0"
                        >
                            <Loader2 v-if="emailForm.processing" class="w-3.5 h-3.5 animate-spin text-emerald-800" />
                            <Send v-else class="w-3.5 h-3.5 text-emerald-800" />
                            <span>{{ emailForm.processing ? 'Mengirim...' : 'Kirim ke Email' }}</span>
                        </button>
                    </form>
                </div>

                <!-- Flash Notice -->
                <div v-if="$page.props.flash?.success" class="mt-3 p-2.5 rounded-lg bg-emerald-500/20 border border-emerald-400/40 text-emerald-200 text-xs flex items-center gap-2">
                    <CheckCircle2 class="w-4 h-4 text-emerald-300 shrink-0" />
                    <span>{{ $page.props.flash.success }}</span>
                </div>
                <div v-if="$page.props.flash?.error" class="mt-3 p-2.5 rounded-lg bg-rose-500/20 border border-rose-400/40 text-rose-200 text-xs flex items-center gap-2">
                    <AlertCircle class="w-4 h-4 text-rose-300 shrink-0" />
                    <span>{{ $page.props.flash.error }}</span>
                </div>
            </div>

            <!-- Report Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- Report 1: Buku Bank Bulanan -->
                <div class="bg-white border border-slate-200 rounded p-4 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 text-blue-700 font-bold text-sm mb-1">
                            <Landmark class="w-4 h-4" /> 1. Buku Bank Bulanan
                        </div>
                        <p class="text-xs text-slate-600 mb-3">
                            Mencetak mutasi giro bank lengkap dengan saldo awal carryover, transaksi harian, rekapitulasi penutupan kas, pecahan sisa kas, serta tanda tangan Sekretaris DPRD & Bendahara Pengeluaran.
                        </p>
                        
                        <div class="mb-4">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Pilih Bulan Pembukuan:</label>
                            <select v-model="selectedBankMonth" class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs">
                                <option :value="1">Januari {{ year }}</option>
                                <option :value="2">Februari {{ year }}</option>
                                <option :value="3">Maret {{ year }}</option>
                                <option :value="4">April {{ year }}</option>
                                <option :value="5">Mei {{ year }}</option>
                                <option :value="6">Juni {{ year }}</option>
                                <option :value="7">Juli {{ year }}</option>
                                <option :value="8">Agustus {{ year }}</option>
                                <option :value="9">September {{ year }}</option>
                                <option :value="10">Oktober {{ year }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-3 border-t border-slate-100">
                        <a 
                            :href="`/laporan/export-bank?month=${selectedBankMonth}&year=${year}`"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-bold transition-colors"
                        >
                            <Download class="w-4 h-4" /> Unduh Excel (.xlsx)
                        </a>
                        <a 
                            :href="`/buku-bank?month=${selectedBankMonth}&year=${year}`"
                            class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-xs font-semibold border border-slate-300 transition-colors"
                        >
                            Pratinjau Layar
                        </a>
                    </div>
                </div>

                <!-- Report 2: Rekapitulasi BKU (Hitungan) -->
                <div class="bg-white border border-slate-200 rounded p-4 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 text-emerald-700 font-bold text-sm mb-1">
                            <FileSpreadsheet class="w-4 h-4" /> 2. Rekapitulasi BKU Per PPTK (Lembar Hitungan)
                        </div>
                        <p class="text-xs text-slate-600 mb-3">
                            Matriks rekapitulasi eksekutif atas akumulasi belanja Debet Toko, Panjar Tunai, Realisasi SPJ, Sisa di Bank, Sisa Tunai, dan Kontrol Selisih seluruh 9 PPTK Setwan.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 pt-3 border-t border-slate-100">
                        <a 
                            :href="`/laporan/export-hitungan?year=${year}`"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-bold transition-colors"
                        >
                            <Download class="w-4 h-4" /> Unduh Excel (.xlsx)
                        </a>
                        <a 
                            href="/bku?tab=Hitungan"
                            class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-xs font-semibold border border-slate-300 transition-colors"
                        >
                            Pratinjau Layar
                        </a>
                    </div>
                </div>

                <!-- Report 3: Buku Kas Umum (UP) & Kas Kecil (KK) -->
                <div class="bg-white border border-slate-200 rounded p-4 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 text-slate-800 font-bold text-sm mb-1">
                            <BookOpen class="w-4 h-4" /> 3. BKU Kas Persediaan (UP) & Kas Kecil (KK)
                        </div>
                        <p class="text-xs text-slate-600 mb-3">
                            Buku Kas Umum induk yang mencatat realisasi Uang Persediaan (Rp 720.000.000) dan mutasi fisik uang kas tunai brankas bendahara sepanjang tahun anggaran.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 pt-3 border-t border-slate-100">
                        <a 
                            href="/bku?tab=UP"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded text-xs font-bold transition-colors"
                        >
                            Buka BKU UP
                        </a>
                        <a 
                            href="/bku?tab=KK"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-slate-700 hover:bg-slate-800 text-white rounded text-xs font-bold transition-colors"
                        >
                            Buka Kas Kecil (KK)
                        </a>
                    </div>
                </div>

                <!-- Report 4: Buku Kegiatan Reses & Pajak PPh 23 -->
                <div class="bg-white border border-slate-200 rounded p-4 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 text-indigo-700 font-bold text-sm mb-1">
                            <FileCheck class="w-4 h-4" /> 4. Laporan Kegiatan Reses DPRD & PPh 23
                        </div>
                        <p class="text-xs text-slate-600 mb-3">
                            Rincian debet rekening belanja sewa tempat kegiatan reses anggota dewan dengan rincian potongan PPh Pasal 23 (2%) dan pengembalian sisa ke Kasda.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 pt-3 border-t border-slate-100">
                        <a 
                            href="/bku?tab=Reses+1"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-2 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-bold transition-colors"
                        >
                            Reses Sidang 1
                        </a>
                        <a 
                            href="/bku?tab=Reses2"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-2 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-bold transition-colors"
                        >
                            Reses Sidang 2
                        </a>
                        <a 
                            href="/bku?tab=Reses3"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-2 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-bold transition-colors"
                        >
                            Reses Sidang 3
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
