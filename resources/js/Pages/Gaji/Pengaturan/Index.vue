<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { 
    Settings, 
    Landmark, 
    BookOpen, 
    ShieldCheck, 
    Edit2, 
    CheckCircle2, 
    X,
    FileText,
    Coins,
    Building2,
    RefreshCw,
    Sliders,
    Plus,
    Trash2,
    Sparkles,
    AlertCircle
} from 'lucide-vue-next';

const props = defineProps({
    year: Number,
    allocations: Array,
    configs: Array,
});

const formatRupiah = (val) => {
    if (val === null || val === undefined || isNaN(val)) return 'Rp 0';
    return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

// ================= 1. SINKRONISASI KE SELURUH DATA =================
const syncForm = useForm({
    year: props.year,
});

const handleSyncAll = () => {
    if (confirm(`Apakah Anda yakin ingin menyinkronkan seluruh formula, komposisi persentase, dan tarif terbaru ke SEMUA data penggajian dan simulasi di Tahun Anggaran ${props.year}?\n\nSistem akan menghitung ulang seluruh item penerima secara otomatis tanpa perlu input manual.`)) {
        syncForm.post('/gaji/pengaturan/sync-all');
    }
};

// ================= 2. EDIT ALOKASI PLAFON KONTROL ANGGARAN =================
const isAllocModalOpen = ref(false);
const editingAlloc = ref(null);
const allocForm = useForm({
    budget_name: '',
    account_code: '',
    annual_pagu: 0,
    monthly_allocation: 0,
    notes: '',
});

const openEditAlloc = (alloc) => {
    editingAlloc.value = alloc;
    allocForm.budget_name = alloc.budget_name;
    allocForm.account_code = alloc.account_code || '';
    allocForm.annual_pagu = alloc.annual_pagu;
    allocForm.monthly_allocation = alloc.monthly_allocation;
    allocForm.notes = alloc.notes || '';
    isAllocModalOpen.value = true;
};

const submitAllocForm = () => {
    allocForm.put(`/gaji/pengaturan/allocation/${editingAlloc.value.id}`, {
        onSuccess: () => {
            isAllocModalOpen.value = false;
        },
    });
};

// ================= 3. EDIT FORMULA & KOMPOSISI PERSENTASE =================
const isConfigModalOpen = ref(false);
const editingConfig = ref(null);
const configForm = useForm({
    name: '',
    legal_reference: '',
    config_value: {},
});

const newParamKey = ref('');
const newParamValue = ref('');

const paramLabels = {
    uang_representasi_ketua: { label: 'Uang Representasi Ketua DPRD', type: 'currency', desc: 'Dasar 100% Gaji Pokok Bupati' },
    pct_wakil_ketua: { label: 'Persentase Uang Rep. Wakil Ketua', type: 'percent', desc: '80% dari Ketua' },
    pct_anggota: { label: 'Persentase Uang Rep. Anggota DPRD', type: 'percent', desc: '75% dari Ketua' },
    pct_uang_paket: { label: 'Uang Paket Dewan', type: 'percent', desc: '10% dari Uang Representasi' },
    pct_tunj_jabatan: { label: 'Tunjangan Jabatan DPRD', type: 'percent', desc: '145% dari Uang Representasi' },
    tki_multiplier: { label: 'Multiplier TKI (Komunikasi Intensif)', type: 'multiplier', desc: '7x Uang Rep Ketua (Kemampuan Tinggi)' },
    pct_akd_ketua: { label: 'Tunjangan Ketua AKD (Komisi/Banggar/Banmus)', type: 'percent', desc: '7,5% dari Tunj. Jabatan Ketua' },
    pct_akd_wakil: { label: 'Tunjangan Wakil Ketua AKD', type: 'percent', desc: '5% dari Tunj. Jabatan Ketua' },
    pct_akd_sekretaris: { label: 'Tunjangan Sekretaris AKD', type: 'percent', desc: '4% dari Tunj. Jabatan Ketua' },
    pct_akd_anggota: { label: 'Tunjangan Anggota AKD', type: 'percent', desc: '3% dari Tunj. Jabatan Ketua' },
    housing_ketua: { label: 'Tunjangan Perumahan Ketua DPRD', type: 'currency', desc: 'Jika tidak menempati Rumah Negara' },
    housing_wakil_ketua: { label: 'Tunjangan Perumahan Wakil Ketua', type: 'currency', desc: 'Jika tidak menempati Rumah Negara' },
    housing_anggota: { label: 'Tunjangan Perumahan Anggota', type: 'currency', desc: 'Perda 6/2017 jo Perbup 9/2024' },
    transport_anggota: { label: 'Tunjangan Transportasi Anggota', type: 'currency', desc: 'Pimpinan difasilitasi mobil dinas' },
    transport_pimpinan: { label: 'Tunjangan Transportasi Pimpinan', type: 'currency', desc: 'Jika mobil dinas tidak tersedia' },
    reses_per_event: { label: 'Tunjangan Reses per Kegiatan', type: 'currency', desc: 'Diberikan saat masa reses' },
    pct_pph21: { label: 'Tarif Potongan PPh 21 Final', type: 'percent', desc: 'Tarif PPh Pasal 17' },
    pct_bpjs: { label: 'Tarif Potongan BPJS Kesehatan', type: 'percent', desc: '1% tanggungan dewan' },

    // Formula ASN
    pct_spouse: { label: 'Tunjangan Suami / Istri ASN', type: 'percent', desc: '10% dari Gaji Pokok' },
    pct_child: { label: 'Tunjangan Anak per Jiwa', type: 'percent', desc: '2% dari Gaji Pokok (Max 2 Anak)' },
    rice_per_person: { label: 'Tunjangan Beras / Pangan per Jiwa', type: 'currency', desc: '10 kg beras (Rp 7.242 / kg) max 4 jiwa' },
    tpp_prod_weight: { label: 'Bobot Produktivitas Kerja TPP', type: 'percent', desc: 'Maksimal 70% dari TPP Dasar' },
    tpp_disp_weight: { label: 'Bobot Disiplin Kehadiran TPP', type: 'percent', desc: 'Maksimal 30% dari TPP Dasar' },
    pppk_paruh_waktu_default: { label: 'Standar Gaji PPPK Paruh Waktu', type: 'currency', desc: 'Gaji kesepakatan perjanjian kerja' },
    iwp_pct: { label: 'Iuran Wajib Pegawai (IWP)', type: 'percent', desc: '1% dari (Gapok + Tunjangan Keluarga)' },
    bpjs_pct: { label: 'Iuran BPJS Kesehatan ASN', type: 'percent', desc: '4% tanggungan iuran' },

    // TPP Class Banyumas
    class_14: { label: 'TPP Kelas 14 (Sekretaris DPRD)', type: 'currency', desc: 'Eselon II.a' },
    class_12: { label: 'TPP Kelas 12 (Kepala Bagian)', type: 'currency', desc: 'Eselon III.a' },
    class_9: { label: 'TPP Kelas 9 (Kasubbag / Subkoordinator)', type: 'currency', desc: 'Eselon IV.a' },
    class_8: { label: 'TPP Kelas 8 (Fungsional Ahli Pertama)', type: 'currency', desc: 'Jabatan Fungsional' },
    class_7: { label: 'TPP Kelas 7 (Pelaksana / Fungsional Mahir)', type: 'currency', desc: 'Staf Pelaksana' },
    class_6: { label: 'TPP Kelas 6 (Pelaksana / Fungsional Terampil)', type: 'currency', desc: 'Staf Pelaksana' },
    class_5: { label: 'TPP Kelas 5 (Pelaksana / Fungsional Pemula)', type: 'currency', desc: 'Staf Pelaksana' },
    class_3: { label: 'TPP Kelas 3 (Pelaksana Operasional)', type: 'currency', desc: 'Staf Operasional' },
    class_1: { label: 'TPP Kelas 1 (Pelaksana Umum)', type: 'currency', desc: 'Staf Umum' },
    pppk_penuh_waktu: { label: 'TPP PPPK Penuh Waktu', type: 'currency', desc: 'Besaran Flat Perbup 104/2025' },
    pppk_paruh_waktu: { label: 'TPP PPPK Paruh Waktu', type: 'currency', desc: 'Rp 0 (Dikecualikan Pasal 18 huruf l)' },
};

const openEditConfig = (cfg) => {
    editingConfig.value = cfg;
    configForm.name = cfg.name;
    configForm.legal_reference = cfg.legal_reference || '';
    configForm.config_value = { ...(cfg.config_value || {}) };
    newParamKey.value = '';
    newParamValue.value = '';
    isConfigModalOpen.value = true;
};

const addNewParam = () => {
    if (!newParamKey.value.trim()) return;
    const key = newParamKey.value.trim().toLowerCase().replace(/[^a-z0-9_]/g, '_');
    configForm.config_value[key] = isNaN(newParamValue.value) ? newParamValue.value : Number(newParamValue.value);
    newParamKey.value = '';
    newParamValue.value = '';
};

const removeParam = (key) => {
    if (confirm(`Hapus parameter "${key}" dari formula ini?`)) {
        delete configForm.config_value[key];
    }
};

const submitConfigForm = (syncImmediately = false) => {
    configForm.put(`/gaji/pengaturan/config/${editingConfig.value.id}`, {
        onSuccess: () => {
            isConfigModalOpen.value = false;
            if (syncImmediately) {
                syncForm.post('/gaji/pengaturan/sync-all');
            }
        },
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Pengaturan Formula & Tarif Gaji - Keuangan Setwan" />

        <div class="space-y-6 pb-12">
            <!-- Header & Action Bar -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200/80 p-5">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                Konfigurasi & Regulasi Fleksibel
                            </span>
                            <span class="text-xs text-slate-400">•</span>
                            <span class="text-xs font-medium text-slate-500">Dasar Perhitungan Resmi</span>
                        </div>
                        <h1 class="text-xl md:text-2xl font-bold text-slate-800 tracking-tight mt-1">
                            Formula Perhitungan, Tarif Acuan & Plafon Anggaran
                        </h1>
                        <p class="text-xs md:text-sm text-slate-500 mt-0.5">
                            Semua rumus, persentase komposisi, dan tarif disimpan di database agar staf dapat menyesuaikan setiap ada kebijakan baru dan langsung menyinkronkannya ke seluruh data.
                        </p>
                    </div>

                    <!-- Tombol Utama: Sinkronkan ke Semua Data -->
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            @click="handleSyncAll"
                            :disabled="syncForm.processing"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#107c41] hover:bg-[#0e6b37] text-white text-xs md:text-sm font-bold rounded-lg shadow-xs transition-all cursor-pointer disabled:opacity-50"
                            title="Terapkan formula dan tarif terbaru ke seluruh data penggajian"
                        >
                            <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': syncForm.processing }" />
                            <span>{{ syncForm.processing ? 'Menyinkronkan Data...' : 'Sinkronkan ke Seluruh Perhitungan' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Info Box Banner Sinkronisasi -->
                <div class="mt-4 p-3.5 bg-emerald-50/70 border border-emerald-200/80 rounded-lg flex items-center justify-between gap-3 text-xs text-emerald-900">
                    <div class="flex items-center gap-2.5">
                        <Sparkles class="w-4 h-4 text-emerald-700 shrink-0" />
                        <span>
                            <strong>Otomatisasi Sinkron:</strong> Apabila ada perubahan peraturan bupati, persentase tunjangan, atau penambahan komponen baru, Anda cukup mengedit formulanya di bawah lalu klik tombol <strong>Sinkronkan</strong> untuk mengkalibrasi ulang seluruh perhitungan penerima secara instan.
                        </span>
                    </div>
                </div>
            </div>

            <!-- Bagian 1: Alokasi Plafon Kontrol Anggaran (DPA/APBD) -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                            <Landmark class="w-4 h-4 text-emerald-600" />
                            <span>Alokasi Plafon Kontrol Anggaran (Tahun Anggaran {{ year }})</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Batas pagu tahunan dan plafon kas bulanan yang digunakan mesin kontrol anggaran untuk mendeteksi kecukupan saldo.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div 
                        v-for="alloc in allocations" 
                        :key="alloc.id"
                        class="p-4 rounded-xl border border-slate-200/80 bg-slate-50/50 space-y-3"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="text-[10.5px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">
                                    {{ alloc.category === 'dprd' ? 'Hak Keuangan DPRD' : 'Gaji & TPP ASN' }}
                                </span>
                                <h4 class="font-bold text-slate-800 text-sm mt-1.5">{{ alloc.budget_name }}</h4>
                                <span class="text-[11px] text-slate-400 font-mono">{{ alloc.account_code || 'Kode Rekening -' }}</span>
                            </div>
                            <button
                                type="button"
                                @click="openEditAlloc(alloc)"
                                class="p-1.5 bg-white hover:bg-slate-100 text-slate-600 rounded-lg border border-slate-200 cursor-pointer shadow-2xs"
                                title="Ubah Plafon"
                            >
                                <Edit2 class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-slate-200/70">
                            <div>
                                <span class="text-slate-500 text-[11px]">Pagu Tahunan:</span>
                                <div class="font-bold text-slate-800 text-sm mt-0.5">{{ formatRupiah(alloc.annual_pagu) }}</div>
                            </div>
                            <div>
                                <span class="text-slate-500 text-[11px]">Plafon Kas Bulanan:</span>
                                <div class="font-bold text-emerald-700 text-sm mt-0.5">{{ formatRupiah(alloc.monthly_allocation) }}</div>
                            </div>
                        </div>

                        <div v-if="alloc.notes" class="text-[11px] text-slate-500 bg-white p-2 rounded border border-slate-200/60">
                            <strong>Dasar Alokasi:</strong> {{ alloc.notes }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bagian 2: Daftar Formula & Konfigurasi Tarif Acuan -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                            <ShieldCheck class="w-4 h-4 text-emerald-600" />
                            <span>Formula Perhitungan & Komposisi Tarif Acuan</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Klik tombol <strong>Ubah Formula & Tarif</strong> pada setiap kartu untuk mengubah persentase, nominal acuan, atau menambahkan variabel perhitungan baru.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 text-xs">
                    <div 
                        v-for="cfg in configs" 
                        :key="cfg.id"
                        class="rounded-xl border border-slate-200/80 bg-white flex flex-col justify-between shadow-2xs overflow-hidden"
                    >
                        <!-- Header Kartu -->
                        <div class="p-4 border-b border-slate-100 bg-slate-50/70 flex items-start justify-between gap-3">
                            <div>
                                <span 
                                    class="text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider"
                                    :class="cfg.category === 'dprd' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800'"
                                >
                                    {{ cfg.category.toUpperCase() }}
                                </span>
                                <h4 class="font-bold text-slate-800 text-sm mt-1.5">{{ cfg.name }}</h4>
                                <span class="text-[11px] text-slate-500 block mt-0.5">{{ cfg.legal_reference }}</span>
                            </div>

                            <button
                                type="button"
                                @click="openEditConfig(cfg)"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-white hover:bg-slate-100 text-emerald-700 hover:text-emerald-800 font-semibold rounded-lg border border-slate-200 shadow-2xs text-[11px] cursor-pointer shrink-0 transition-colors"
                            >
                                <Edit2 class="w-3 h-3" />
                                <span>Ubah Formula</span>
                            </button>
                        </div>

                        <!-- Daftar Komponen Parameter -->
                        <div class="p-4 flex-1">
                            <ul class="space-y-2 text-slate-600 text-[11.5px] max-h-96 overflow-y-auto pr-1">
                                <li 
                                    v-for="(val, key) in cfg.config_value" 
                                    :key="key"
                                    class="flex items-center justify-between py-1 border-b border-slate-50 gap-2"
                                >
                                    <div class="min-w-0">
                                        <span class="font-medium text-slate-700 block truncate" :title="paramLabels[key]?.label || key">
                                            {{ paramLabels[key]?.label || key }}
                                        </span>
                                        <span v-if="paramLabels[key]?.desc" class="text-[10px] text-slate-400 block truncate">
                                            {{ paramLabels[key].desc }}
                                        </span>
                                    </div>
                                    <div class="font-bold text-slate-800 shrink-0 text-right">
                                        <span v-if="paramLabels[key]?.type === 'percent'">
                                            {{ val }}%
                                        </span>
                                        <span v-else-if="paramLabels[key]?.type === 'multiplier'">
                                            {{ val }}x
                                        </span>
                                        <span v-else-if="paramLabels[key]?.type === 'currency' || !isNaN(val)">
                                            {{ formatRupiah(val) }}
                                        </span>
                                        <span v-else>
                                            {{ val }}
                                        </span>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <!-- Footer Kartu -->
                        <div class="p-3 bg-slate-50/50 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
                            <span>{{ Object.keys(cfg.config_value || {}).length }} Komponen Formula</span>
                            <span class="text-emerald-700 font-medium">Aktif</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= MODAL EDIT ALOKASI PLAFON ================= -->
        <div v-if="isAllocModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-5 text-xs">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                    <h3 class="font-bold text-slate-800 text-sm">Ubah Alokasi Kontrol Anggaran</h3>
                    <button type="button" @click="isAllocModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitAllocForm" class="space-y-3.5">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nama Pos Belanja *</label>
                        <input v-model="allocForm.budget_name" type="text" required class="w-full px-3 py-1.5 rounded-lg border border-slate-300" />
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Kode Rekening</label>
                        <input v-model="allocForm.account_code" type="text" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 font-mono" />
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Pagu APBD Tahunan (Rp) *</label>
                        <input v-model.number="allocForm.annual_pagu" type="number" required class="w-full px-3 py-1.5 rounded-lg border border-slate-300" />
                        <span class="text-[10px] text-slate-400 mt-0.5 block">{{ formatRupiah(allocForm.annual_pagu) }}</span>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Plafon Kas Bulanan (Rp) *</label>
                        <input v-model.number="allocForm.monthly_allocation" type="number" required class="w-full px-3 py-1.5 rounded-lg border border-slate-300" />
                        <span class="text-[10px] text-slate-400 mt-0.5 block">{{ formatRupiah(allocForm.monthly_allocation) }}</span>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Catatan / Regulasi Acuan</label>
                        <textarea v-model="allocForm.notes" rows="2" class="w-full px-3 py-1.5 rounded-lg border border-slate-300"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="isAllocModalOpen = false" class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50">
                            Batal
                        </button>
                        <button type="submit" :disabled="allocForm.processing" class="px-4 py-1.5 rounded-lg bg-[#107c41] text-white font-bold hover:bg-[#0e6b37]">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL EDIT FORMULA & TARIF DINAMIS ================= -->
        <div v-if="isConfigModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col p-5 text-xs">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4 shrink-0">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Editor Formula & Komposisi Tarif</span>
                        <h3 class="font-bold text-slate-800 text-base">{{ editingConfig?.name }}</h3>
                    </div>
                    <button type="button" @click="isConfigModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- Form Content (Scrollable) -->
                <form @submit.prevent="submitConfigForm(false)" class="flex-1 overflow-y-auto space-y-4 pr-1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Judul Konfigurasi *</label>
                            <input v-model="configForm.name" type="text" required class="w-full px-3 py-1.5 rounded-lg border border-slate-300 font-medium" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Dasar Hukum / Regulasi</label>
                            <input v-model="configForm.legal_reference" type="text" class="w-full px-3 py-1.5 rounded-lg border border-slate-300" />
                        </div>
                    </div>

                    <!-- Parameter List Inputs -->
                    <div class="space-y-3 pt-2">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                            <span class="font-bold text-slate-800 text-xs">Daftar Variabel & Nilai Perhitungan</span>
                            <span class="text-[11px] text-slate-400">Ubah angka di bawah sesuai peraturan baru</span>
                        </div>

                        <div class="space-y-2.5">
                            <div 
                                v-for="(val, key) in configForm.config_value" 
                                :key="key"
                                class="p-2.5 rounded-lg bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-3"
                            >
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <label class="font-bold text-slate-800 text-xs truncate">
                                            {{ paramLabels[key]?.label || key }}
                                        </label>
                                        <span class="text-[10px] text-slate-400 font-mono">({{ key }})</span>
                                    </div>
                                    <span v-if="paramLabels[key]?.desc" class="text-[10.5px] text-slate-500 block truncate">
                                        {{ paramLabels[key].desc }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <div class="relative w-44">
                                        <input 
                                            v-model.number="configForm.config_value[key]" 
                                            type="number" 
                                            step="any"
                                            class="w-full pl-2.5 pr-8 py-1.5 rounded-lg border border-slate-300 text-right font-bold text-slate-800 text-xs focus:ring-1 focus:ring-emerald-500"
                                        />
                                        <span 
                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400 pointer-events-none"
                                        >
                                            {{ paramLabels[key]?.type === 'percent' ? '%' : (paramLabels[key]?.type === 'multiplier' ? 'x' : 'Rp') }}
                                        </span>
                                    </div>

                                    <button
                                        type="button"
                                        @click="removeParam(key)"
                                        class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition-colors"
                                        title="Hapus variabel"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tambah Variabel / Komponen Baru Form -->
                    <div class="p-3 bg-emerald-50/50 border border-dashed border-emerald-300 rounded-lg space-y-2">
                        <span class="font-bold text-emerald-900 text-xs flex items-center gap-1.5">
                            <Plus class="w-3.5 h-3.5 text-emerald-700" />
                            <span>Tambah Komponen / Variabel Perhitungan Baru:</span>
                        </span>
                        <div class="flex flex-col sm:flex-row items-center gap-2">
                            <input 
                                v-model="newParamKey" 
                                type="text" 
                                placeholder="Nama key (mis: tunjangan_khusus)" 
                                class="flex-1 w-full px-2.5 py-1.5 rounded-lg border border-emerald-200 bg-white text-xs font-mono"
                            />
                            <input 
                                v-model.number="newParamValue" 
                                type="number" 
                                placeholder="Nominal / Persentase" 
                                class="w-full sm:w-44 px-2.5 py-1.5 rounded-lg border border-emerald-200 bg-white text-xs text-right font-bold"
                            />
                            <button
                                type="button"
                                @click="addNewParam"
                                class="w-full sm:w-auto px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-lg shrink-0 transition-colors cursor-pointer"
                            >
                                + Tambahkan
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Modal Actions -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-2 pt-4 border-t border-slate-100 shrink-0 mt-2">
                    <span class="text-[11px] text-slate-500">
                        Perubahan akan langsung aktif di simulasi & dapat disinkronkan ke seluruh periode.
                    </span>

                    <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                        <button 
                            type="button" 
                            @click="isConfigModalOpen = false" 
                            class="px-3.5 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="button" 
                            @click="submitConfigForm(false)" 
                            :disabled="configForm.processing"
                            class="px-4 py-1.5 rounded-lg bg-slate-800 text-white font-semibold hover:bg-slate-700 cursor-pointer"
                        >
                            Simpan Perubahan
                        </button>
                        <button 
                            type="button" 
                            @click="submitConfigForm(true)" 
                            :disabled="configForm.processing"
                            class="px-4 py-1.5 rounded-lg bg-[#107c41] text-white font-bold hover:bg-[#0e6b37] cursor-pointer shadow-xs flex items-center gap-1.5"
                            title="Simpan perubahan dan langsung sinkronkan ke semua penggajian tersimpan"
                        >
                            <RefreshCw class="w-3.5 h-3.5" />
                            <span>Simpan & Langsung Sinkronkan</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
