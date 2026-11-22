<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
    Building2
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

// Edit Allocation Modal
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
</script>

<template>
    <AppLayout>
        <Head title="Pengaturan Tarif & Regulasi Acuan - Keuangan Setwan" />

        <div class="space-y-6 pb-12">
            <!-- Header & Sub Navigation -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200/80 p-5">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                Konfigurasi & Regulasi
                            </span>
                            <span class="text-xs text-slate-400">•</span>
                            <span class="text-xs font-medium text-slate-500">Dasar Perhitungan Resmi</span>
                        </div>
                        <h1 class="text-xl md:text-2xl font-bold text-slate-800 tracking-tight mt-1">
                            Dasar Regulasi, Tarif Acuan & Plafon Anggaran
                        </h1>
                        <p class="text-xs md:text-sm text-slate-500 mt-0.5">
                            Semua rumus dan nominal acuan disimpan di database sehingga dapat dikonfigurasi admin secara fleksibel apabila ada perubahan kebijakan baru.
                        </p>
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

            <!-- Bagian 2: Daftar Konfigurasi Tarif Acuan & Regulasi -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                            <ShieldCheck class="w-4 h-4 text-emerald-600" />
                            <span>Regulasi & Tarif Dasar Acuan Otomatisasi</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Besaran yang digunakan sistem berasal langsung dari peraturan perundang-undangan resmi yang berlaku di Kabupaten Banyumas.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <!-- Kartu 1: Hak Keuangan DPRD -->
                    <div class="p-4 rounded-xl border border-slate-200/80 bg-white space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-slate-800 text-sm">Hak Keuangan DPRD (PP 18/2017 jo Perbup 9/2024)</h4>
                            <span class="text-[10px] font-bold px-2 py-0.5 bg-blue-100 text-blue-800 rounded">DPRD</span>
                        </div>
                        <ul class="space-y-1.5 text-slate-600 text-[11.5px]">
                            <li class="flex justify-between">
                                <span>Uang Representasi Ketua:</span>
                                <strong class="text-slate-800">Rp 2.100.000 (100% Gaji Pokok Bupati)</strong>
                            </li>
                            <li class="flex justify-between">
                                <span>Uang Representasi Wakil Ketua:</span>
                                <strong class="text-slate-800">Rp 1.680.000 (80%)</strong>
                            </li>
                            <li class="flex justify-between">
                                <span>Uang Representasi Anggota:</span>
                                <strong class="text-slate-800">Rp 1.575.000 (75%)</strong>
                            </li>
                            <li class="flex justify-between">
                                <span>Uang Paket:</span>
                                <strong class="text-slate-800">10% Uang Representasi</strong>
                            </li>
                            <li class="flex justify-between">
                                <span>Tunjangan Jabatan:</span>
                                <strong class="text-slate-800">145% Uang Representasi</strong>
                            </li>
                            <li class="flex justify-between">
                                <span>Tunjangan Komunikasi Intensif (TKI):</span>
                                <strong class="text-slate-800">Rp 14.700.000 / orang / bulan (7x)</strong>
                            </li>
                            <li class="flex justify-between">
                                <span>Tunjangan Perumahan Ketua / Waka / Anggota:</span>
                                <strong class="text-slate-800">Rp 42,6jt / Rp 34,6jt / Rp 23,6jt</strong>
                            </li>
                            <li class="flex justify-between">
                                <span>Tunjangan Transportasi Anggota:</span>
                                <strong class="text-slate-800">Rp 13.500.000 / bulan</strong>
                            </li>
                        </ul>
                    </div>

                    <!-- Kartu 2: TPP ASN Setwan -->
                    <div class="p-4 rounded-xl border border-slate-200/80 bg-white space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-slate-800 text-sm">TPP ASN Setwan (Perbup Banyumas 104/2025)</h4>
                            <span class="text-[10px] font-bold px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded">ASN</span>
                        </div>
                        <ul class="space-y-1.5 text-slate-600 text-[11.5px]">
                            <li class="flex justify-between">
                                <span>Sekretaris DPRD (Kelas 14):</span>
                                <strong class="text-slate-800">Rp 15.000.000 / bulan</strong>
                            </li>
                            <li class="flex justify-between">
                                <span>Kepala Bagian (Kelas 12):</span>
                                <strong class="text-slate-800">Rp 8.500.000 / bulan</strong>
                            </li>
                            <li class="flex justify-between">
                                <span>Kepala Subbagian (Kelas 9):</span>
                                <strong class="text-slate-800">Rp 5.250.000 / bulan</strong>
                            </li>
                            <li class="flex justify-between">
                                <span>Fungsional Ahli Muda (Kelas 9-10):</span>
                                <strong class="text-slate-800">Rp 4.900.000 - Rp 5.000.000</strong>
                            </li>
                            <li class="flex justify-between">
                                <span>Pelaksana Kelas 7 / 6 / 5:</span>
                                <strong class="text-slate-800">Rp 3.300.000 / Rp 2.800.000 / Rp 2.700.000</strong>
                            </li>
                            <li class="flex justify-between">
                                <span>PPPK Penuh Waktu:</span>
                                <strong class="text-slate-800">Rp 1.600.000 / bulan</strong>
                            </li>
                            <li class="flex justify-between text-amber-700 font-medium">
                                <span>PPPK Paruh Waktu:</span>
                                <strong>Dikecualikan (Pasal 18 huruf l)</strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Allocation Modal -->
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
                        <label class="block font-semibold text-slate-700 mb-1">Pagu Anggaran Tahunan (Rp) *</label>
                        <input v-model="allocForm.annual_pagu" type="number" required min="0" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 font-mono font-bold" />
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Plafon Kas Bulanan (Rp) *</label>
                        <input v-model="allocForm.monthly_allocation" type="number" required min="0" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 font-mono font-bold text-emerald-700" />
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Catatan / Dasar Regulasi</label>
                        <textarea v-model="allocForm.notes" rows="2" class="w-full px-3 py-1.5 rounded-lg border border-slate-300"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="isAllocModalOpen = false" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg">
                            Batal
                        </button>
                        <button type="submit" :disabled="allocForm.processing" class="px-4 py-1.5 bg-[#107c41] hover:bg-[#0e6b37] text-white rounded-lg font-bold">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
