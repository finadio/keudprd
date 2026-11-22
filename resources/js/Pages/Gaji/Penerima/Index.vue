<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { 
    Users, 
    UserPlus, 
    UploadCloud, 
    FileSpreadsheet, 
    Download, 
    Search, 
    Edit2, 
    Trash2, 
    CheckCircle, 
    XCircle,
    X,
    Filter,
    Shield
} from 'lucide-vue-next';

const props = defineProps({
    recipients: Object,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters.search || '');
const category = ref(props.filters.category || '');
const subCategory = ref(props.filters.sub_category || '');

const handleFilter = () => {
    router.get('/gaji/penerima', {
        search: search.value,
        category: category.value,
        sub_category: subCategory.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilter = () => {
    search.value = '';
    category.value = '';
    subCategory.value = '';
    handleFilter();
};

// Modal State
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const isImportModalOpen = ref(false);

const form = useForm({
    name: '',
    identifier_number: '',
    category: 'asn',
    sub_category: 'pns',
    position_name: '',
    grade: '',
    mkg_years: 0,
    job_class: null,
    political_party: '',
    marital_status: 'married',
    children_count: 0,
    akd_role: 'none',
    akd_name: '',
    has_official_residence: false,
    has_official_car: false,
    tpp_base_amount: 0,
    fixed_salary: null,
    is_active: true,
});

const importForm = useForm({
    file: null,
});

const openAddModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    isModalOpen.value = true;
};

const openEditModal = (r) => {
    isEditing.value = true;
    editingId.value = r.id;
    form.name = r.name;
    form.identifier_number = r.identifier_number || '';
    form.category = r.category;
    form.sub_category = r.sub_category;
    form.position_name = r.position_name;
    form.grade = r.grade || '';
    form.mkg_years = r.mkg_years;
    form.job_class = r.job_class;
    form.political_party = r.political_party || '';
    form.marital_status = r.marital_status;
    form.children_count = r.children_count;
    form.akd_role = r.akd_role || 'none';
    form.akd_name = r.akd_name || '';
    form.has_official_residence = r.has_official_residence;
    form.has_official_car = r.has_official_car;
    form.tpp_base_amount = r.tpp_base_amount;
    form.fixed_salary = r.fixed_salary;
    form.is_active = r.is_active;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(`/gaji/penerima/${editingId.value}`, {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post('/gaji/penerima', {
            onSuccess: () => closeModal(),
        });
    }
};

const toggleActive = (r) => {
    router.post(`/gaji/penerima/${r.id}/toggle-active`, {}, {
        preserveScroll: true,
    });
};

const deleteRecipient = (r) => {
    if (confirm(`Apakah Anda yakin ingin menghapus data penerima "${r.name}"?`)) {
        router.delete(`/gaji/penerima/${r.id}`, {
            preserveScroll: true,
        });
    }
};

const submitImport = () => {
    importForm.post('/gaji/penerima/impor-excel', {
        onSuccess: () => {
            isImportModalOpen.value = false;
            importForm.reset();
        },
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Master Data Penerima Gaji & DPRD - Keuangan Setwan" />

        <div class="space-y-6 pb-12">
            <!-- Header & Navigation -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200/80 p-5">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                Master Data
                            </span>
                            <span class="text-xs text-slate-400">•</span>
                            <span class="text-xs font-medium text-slate-500">Sekretariat DPRD Kab. Banyumas</span>
                        </div>
                        <h1 class="text-xl md:text-2xl font-bold text-slate-800 tracking-tight mt-1">
                            Data Penerima Gaji, Tunjangan & Hak Keuangan
                        </h1>
                        <p class="text-xs md:text-sm text-slate-500 mt-0.5">
                            Daftar seluruh ASN (PNS, PPPK PW, PPPK PT) dan Pimpinan/Anggota DPRD tersimpan permanen agar staf tidak perlu menginput ulang setiap bulan.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <a
                            href="/gaji/penerima/download-template"
                            class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition-all"
                        >
                            <Download class="w-3.5 h-3.5" />
                            <span>Format Excel</span>
                        </a>
                        <button
                            type="button"
                            @click="isImportModalOpen = true"
                            class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold rounded-lg border border-emerald-200 transition-all cursor-pointer"
                        >
                            <UploadCloud class="w-3.5 h-3.5" />
                            <span>Impor Excel</span>
                        </button>
                        <button
                            type="button"
                            @click="openAddModal"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#107c41] hover:bg-[#0e6b37] text-white text-xs font-semibold rounded-lg shadow-xs transition-all cursor-pointer"
                        >
                            <UserPlus class="w-4 h-4" />
                            <span>Tambah Penerima</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Mini Bar -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[11px] text-slate-500 font-medium">Total Penerima</span>
                        <div class="text-lg font-bold text-slate-800">{{ stats?.total || 0 }} Orang</div>
                    </div>
                    <Users class="w-6 h-6 text-slate-400" />
                </div>
                <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[11px] text-slate-500 font-medium">ASN Sekretariat</span>
                        <div class="text-lg font-bold text-emerald-700">{{ stats?.asn_count || 0 }} Orang</div>
                    </div>
                    <Shield class="w-6 h-6 text-emerald-400" />
                </div>
                <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[11px] text-slate-500 font-medium">DPRD Banyumas</span>
                        <div class="text-lg font-bold text-blue-700">{{ stats?.dprd_count || 0 }} Orang</div>
                    </div>
                    <Users class="w-6 h-6 text-blue-400" />
                </div>
                <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[11px] text-slate-500 font-medium">Status Aktif</span>
                        <div class="text-lg font-bold text-emerald-600">{{ stats?.active_count || 0 }} Aktif</div>
                    </div>
                    <CheckCircle class="w-6 h-6 text-emerald-500" />
                </div>
            </div>

            <!-- Filter & Search Toolbar -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div class="sm:col-span-2 relative">
                        <Search class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                        <input
                            v-model="search"
                            @keyup.enter="handleFilter"
                            type="text"
                            placeholder="Cari nama pegawai, anggota dewan, jabatan, partai..."
                            class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        />
                    </div>

                    <div>
                        <select
                            v-model="category"
                            @change="handleFilter"
                            class="w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        >
                            <option value="">Semua Kategori (ASN & DPRD)</option>
                            <option value="asn">Hanya ASN</option>
                            <option value="dprd">Hanya DPRD</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <select
                            v-model="subCategory"
                            @change="handleFilter"
                            class="w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        >
                            <option value="">Semua Sub Kategori</option>
                            <option value="pns">PNS</option>
                            <option value="pppk_penuh_waktu">PPPK Penuh Waktu</option>
                            <option value="pppk_paruh_waktu">PPPK Paruh Waktu</option>
                            <option value="ketua_dprd">Ketua DPRD</option>
                            <option value="wakil_ketua_dprd">Wakil Ketua DPRD</option>
                            <option value="anggota_dprd">Anggota DPRD</option>
                        </select>

                        <button
                            type="button"
                            @click="resetFilter"
                            title="Reset Filter"
                            class="p-1.5 text-slate-400 hover:text-slate-600 rounded border border-slate-200"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Recipient Table -->
            <div class="bg-white rounded-xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-600 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-3 w-12 text-center">No</th>
                                <th class="py-3 px-4">Nama Penerima</th>
                                <th class="py-3 px-3">Kategori</th>
                                <th class="py-3 px-3">Jabatan</th>
                                <th class="py-3 px-3">Gol / MKG</th>
                                <th class="py-3 px-3">Kelas / Partai</th>
                                <th class="py-3 px-3">Fasilitas / AKD</th>
                                <th class="py-3 px-3 text-center">Status</th>
                                <th class="py-3 px-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr 
                                v-for="(r, idx) in recipients.data" 
                                :key="r.id"
                                class="hover:bg-slate-50/70 transition-colors"
                            >
                                <td class="py-3 px-3 text-center text-slate-400 font-mono">
                                    {{ (recipients.current_page - 1) * recipients.per_page + idx + 1 }}
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800">{{ r.name }}</div>
                                    <div class="text-[10.5px] text-slate-400 font-mono">{{ r.identifier_number || 'No ID -' }}</div>
                                </td>
                                <td class="py-3 px-3">
                                    <span 
                                        :class="r.category === 'dprd' ? 'bg-blue-100 text-blue-800 border-blue-200' : 'bg-emerald-100 text-emerald-800 border-emerald-200'"
                                        class="px-2 py-0.5 rounded text-[10.5px] font-bold border"
                                    >
                                        {{ r.sub_category_label }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 font-medium text-slate-700">
                                    {{ r.position_name }}
                                </td>
                                <td class="py-3 px-3 text-slate-600">
                                    <span v-if="r.grade">{{ r.grade }} ({{ r.mkg_years }} Th)</span>
                                    <span v-else class="text-slate-300">-</span>
                                </td>
                                <td class="py-3 px-3 text-slate-600">
                                    <span v-if="r.category === 'dprd'">{{ r.political_party || '-' }}</span>
                                    <span v-else>Kelas {{ r.job_class || '-' }}</span>
                                </td>
                                <td class="py-3 px-3 text-[11px] text-slate-500">
                                    <div v-if="r.category === 'dprd'">
                                        <span class="capitalize">AKD: {{ r.akd_role || 'None' }}</span>
                                        <span v-if="r.has_official_residence" class="block text-amber-600 font-medium">Rumah Negara</span>
                                        <span v-if="r.has_official_car" class="block text-indigo-600 font-medium">Mobil Dinas</span>
                                    </div>
                                    <div v-else>
                                        <span>Anak: {{ r.children_count }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <button 
                                        type="button" 
                                        @click="toggleActive(r)"
                                        :class="r.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-400'"
                                        class="px-2 py-0.5 rounded text-[10.5px] font-bold cursor-pointer"
                                    >
                                        {{ r.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </button>
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="openEditModal(r)"
                                            class="p-1.5 text-slate-500 hover:text-emerald-700 hover:bg-slate-100 rounded cursor-pointer"
                                            title="Ubah Data"
                                        >
                                            <Edit2 class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteRecipient(r)"
                                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded cursor-pointer"
                                            title="Hapus Data"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="recipients.links && recipients.links.length > 3" class="p-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <div>
                        Menampilkan {{ recipients.from || 0 }} - {{ recipients.to || 0 }} dari {{ recipients.total }} data
                    </div>
                    <div class="flex items-center gap-1">
                        <Link
                            v-for="(link, i) in recipients.links"
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
        </div>

        <!-- Add / Edit Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-xl max-h-[90vh] overflow-y-auto p-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                    <h3 class="font-bold text-slate-800 text-sm">
                        {{ isEditing ? 'Ubah Data Penerima' : 'Tambah Penerima Gaji Baru' }}
                    </h3>
                    <button type="button" @click="closeModal" class="text-slate-400 hover:text-slate-600">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nama Lengkap & Gelar *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Contoh: Arif Triyanto, S.Sos., M.Eng"
                            class="w-full px-3 py-1.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Kategori Utama *</label>
                            <select
                                v-model="form.category"
                                class="w-full px-3 py-1.5 rounded-lg border border-slate-300"
                            >
                                <option value="asn">ASN (Sekretariat DPRD)</option>
                                <option value="dprd">DPRD (Pimpinan / Anggota)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Sub Kategori *</label>
                            <select
                                v-model="form.sub_category"
                                class="w-full px-3 py-1.5 rounded-lg border border-slate-300"
                            >
                                <option v-if="form.category === 'asn'" value="pns">PNS</option>
                                <option v-if="form.category === 'asn'" value="pppk_penuh_waktu">PPPK Penuh Waktu</option>
                                <option v-if="form.category === 'asn'" value="pppk_paruh_waktu">PPPK Paruh Waktu</option>
                                <option v-if="form.category === 'dprd'" value="ketua_dprd">Ketua DPRD</option>
                                <option v-if="form.category === 'dprd'" value="wakil_ketua_dprd">Wakil Ketua DPRD</option>
                                <option v-if="form.category === 'dprd'" value="anggota_dprd">Anggota DPRD</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">NIP / NIK / No. Registrasi</label>
                            <input
                                v-model="form.identifier_number"
                                type="text"
                                placeholder="NIP (opsional)"
                                class="w-full px-3 py-1.5 rounded-lg border border-slate-300"
                            />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Jabatan *</label>
                            <input
                                v-model="form.position_name"
                                type="text"
                                required
                                placeholder="Contoh: Sekretaris DPRD, Anggota Komisi A"
                                class="w-full px-3 py-1.5 rounded-lg border border-slate-300"
                            />
                        </div>
                    </div>

                    <!-- Spesifik ASN -->
                    <div v-if="form.category === 'asn'" class="p-3 bg-slate-50 rounded-lg border border-slate-200/80 space-y-3">
                        <div class="font-bold text-slate-700">Parameter ASN</div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-[11px] text-slate-500 mb-0.5">Golongan</label>
                                <input
                                    v-model="form.grade"
                                    type="text"
                                    placeholder="IV/c, IX"
                                    class="w-full px-2.5 py-1 text-xs rounded border border-slate-300"
                                />
                            </div>
                            <div>
                                <label class="block text-[11px] text-slate-500 mb-0.5">MKG (Tahun)</label>
                                <input
                                    v-model="form.mkg_years"
                                    type="number"
                                    min="0"
                                    class="w-full px-2.5 py-1 text-xs rounded border border-slate-300"
                                />
                            </div>
                            <div>
                                <label class="block text-[11px] text-slate-500 mb-0.5">Kelas Jabatan (1-14)</label>
                                <input
                                    v-model="form.job_class"
                                    type="number"
                                    min="1"
                                    max="14"
                                    class="w-full px-2.5 py-1 text-xs rounded border border-slate-300"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Spesifik DPRD -->
                    <div v-if="form.category === 'dprd'" class="p-3 bg-slate-50 rounded-lg border border-slate-200/80 space-y-3">
                        <div class="font-bold text-slate-700">Parameter Hak Keuangan DPRD</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] text-slate-500 mb-0.5">Partai Politik</label>
                                <input
                                    v-model="form.political_party"
                                    type="text"
                                    placeholder="Contoh: PDI Perjuangan"
                                    class="w-full px-2.5 py-1 text-xs rounded border border-slate-300"
                                />
                            </div>
                            <div>
                                <label class="block text-[11px] text-slate-500 mb-0.5">Kedudukan di AKD</label>
                                <select
                                    v-model="form.akd_role"
                                    class="w-full px-2.5 py-1 text-xs rounded border border-slate-300"
                                >
                                    <option value="none">Bukan Anggota AKD (0%)</option>
                                    <option value="anggota">Anggota AKD (3%)</option>
                                    <option value="sekretaris">Sekretaris AKD (4%)</option>
                                    <option value="wakil_ketua">Wakil Ketua AKD (5%)</option>
                                    <option value="ketua">Ketua AKD (7.5%)</option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1.5 pt-1 text-slate-700">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.has_official_residence" type="checkbox" class="rounded text-emerald-600" />
                                <span>Menempati Rumah Negara (Tunjangan Perumahan tidak dihitung)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.has_official_car" type="checkbox" class="rounded text-emerald-600" />
                                <span>Mendapat Kendaraan Dinas Jabatan (Tunjangan Transportasi tidak dihitung)</span>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Status Pernikahan</label>
                            <select v-model="form.marital_status" class="w-full px-3 py-1.5 rounded-lg border border-slate-300">
                                <option value="married">Menikah (Tunj. Istri/Suami 10%)</option>
                                <option value="single">Lajang</option>
                                <option value="widowed">Duda / Janda</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Jumlah Anak Tertanggung (Max 2)</label>
                            <input
                                v-model="form.children_count"
                                type="number"
                                min="0"
                                max="10"
                                class="w-full px-3 py-1.5 rounded-lg border border-slate-300"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-medium cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-[#107c41] hover:bg-[#0e6b37] text-white rounded-lg font-semibold cursor-pointer shadow-xs"
                        >
                            Simpan Data
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Import Excel Modal -->
        <div v-if="isImportModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                    <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                        <FileSpreadsheet class="w-4 h-4 text-emerald-600" />
                        <span>Impor Data Penerima via Excel</span>
                    </h3>
                    <button type="button" @click="isImportModalOpen = false" class="text-slate-400 hover:text-slate-600">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitImport" class="space-y-4 text-xs">
                    <p class="text-slate-500">
                        Pilih berkas Excel (.xlsx) sesuai format template untuk memasukkan atau memperbarui data penerima ASN & DPRD sekaligus.
                    </p>

                    <div>
                        <input
                            type="file"
                            accept=".xlsx, .xls, .csv"
                            required
                            @change="importForm.file = $event.target.files[0]"
                            class="w-full p-2 border border-slate-300 rounded-lg text-xs"
                        />
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <a
                            href="/gaji/penerima/download-template"
                            class="text-xs text-emerald-700 hover:underline flex items-center gap-1 font-semibold"
                        >
                            <Download class="w-3.5 h-3.5" />
                            <span>Unduh Template Contoh</span>
                        </a>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="isImportModalOpen = false"
                                class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="importForm.processing"
                                class="px-3.5 py-1.5 bg-[#107c41] hover:bg-[#0e6b37] text-white rounded-lg font-bold"
                            >
                                Unggah & Impor
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
