<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    Plus, 
    Search, 
    Printer, 
    Download, 
    FileSpreadsheet, 
    Filter,
    Edit2,
    Trash2,
    Check,
    X,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    ChevronUp,
    AlertCircle,
    FolderKanban,
    PanelLeft,
    Layers,
    Folder,
    Percent,
    BookOpen,
    SlidersHorizontal,
    Lock,
    Unlock,
    AlertTriangle,
    Clock,
    History,
    CheckCircle2,
    User,
    ArrowRight,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    Building,
    Utensils,
    Coins,
    Landmark
} from 'lucide-vue-next';

// Custom directive to auto focus input
const vFocus = {
    mounted: (el) => el.focus()
};

const props = defineProps({
    year: Number,
    active_tab: String,
    all_tabs: Array,
    pptks: Array,
    sheet_data: Object,
    setting: Object,
    periods: Array,
});

const searchQuery = ref('');
const showAddModal = ref(false);
const editingTx = ref(null);
const isSidebarOpen = ref(true);
const sheetSearchQuery = ref('');

// Dual Synchronized Scrollbars
const topScrollRef = ref(null);
const bottomScrollRef = ref(null);
const tableScrollWidth = ref(1400);

let isSyncing = false;
const syncScroll = (source) => {
    if (isSyncing) return;
    isSyncing = true;
    if (source === 'top' && bottomScrollRef.value && topScrollRef.value) {
        bottomScrollRef.value.scrollLeft = topScrollRef.value.scrollLeft;
    } else if (source === 'bottom' && topScrollRef.value && bottomScrollRef.value) {
        topScrollRef.value.scrollLeft = bottomScrollRef.value.scrollLeft;
    }
    requestAnimationFrame(() => { isSyncing = false; });
};

const updateTableWidth = () => {
    if (bottomScrollRef.value) {
        tableScrollWidth.value = bottomScrollRef.value.scrollWidth;
    }
};

onMounted(() => {
    updateTableWidth();
    window.addEventListener('resize', updateTableWidth);
});

const debResesSubTab = ref('sewa');

watch(debResesSubTab, () => {
    currentPage.value = 1;
});

watch(() => props.active_tab, (tab) => {
    nextTick(updateTableWidth);
    debResesSubTab.value = 'sewa';
    if (tab && (tab.startsWith('Reses') || tab.startsWith('Deb'))) {
        expandedGroups.value['Reses & Pajak'] = true;
    }
}, { immediate: true });

// Accordion expanded state
const expandedGroups = ref({
    'Kas Induk & Rekap': true,
    'Buku Pembantu PPTK': false,
    'Reses & Pajak': false
});

const toggleGroup = (name) => {
    expandedGroups.value[name] = !expandedGroups.value[name];
};

// 20 Sheets configuration matching standard BKU Setwan
const rawGroups = [
    {
        name: 'Kas Induk & Rekap',
        icon: Layers,
        items: [
            { id: 'Hitungan', label: 'Rekap Hitungan Kas' },
            { id: 'UP', label: 'Buku Kas Induk (Uang Persediaan)' },
            { id: 'KK', label: 'Buku Kas Kecil (Kas Tunai)' },
            { id: 'Jml Deb Rek', label: 'Matriks 12 Bulan Debet Rekening' },
            { id: 'Deb Rek', label: 'Buku Panjar & Debet Rekening' },
        ]
    },
    {
        name: 'Buku Pembantu PPTK',
        icon: Folder,
        items: [
            { id: 'Aul-Ety', label: 'Ibu Aulia / Ety' },
            { id: 'Gaya', label: 'Ibu Gayatri' },
            { id: 'Ratman', label: 'Pak Suratman, S.E.' },
            { id: 'Aul-Rina', label: 'Ibu Aulia / Rina' },
            { id: 'Sigit', label: 'Pak Sigit Dwi Yunianto, S.H.' },
            { id: 'SgngSyesa', label: 'Pak Sugeng / Syesaria' },
            { id: 'SgngEmi', label: 'Pak Sugeng / Emi' },
            { id: 'Sri L', label: 'Ibu Sri Lestari' },
            { id: 'Irna', label: 'Ibu Irnawatingrum, M.Si.' },
        ]
    },
    {
        name: 'Reses & Pajak',
        icon: Percent,
        items: [
            { id: 'Reses 1', label: 'Reses Masa Sidang 1' },
            { id: 'Deb Reses1', label: 'Debet Pajak Reses 1' },
            { id: 'Reses2', label: 'Reses Masa Sidang 2' },
            { id: 'Deb Reses2', label: 'Debet Pajak Reses 2' },
            { id: 'Reses3', label: 'Reses Masa Sidang 3' },
            { id: 'DebReses3', label: 'Debet Pajak Reses 3' },
        ]
    }
];

// Ensure the group containing active_tab is opened
for (const group of rawGroups) {
    if (group.items.some(item => item.id === props.active_tab)) {
        expandedGroups.value[group.name] = true;
        break;
    }
}

// Filtered groups by sheet search query
const tabGroups = computed(() => {
    const q = sheetSearchQuery.value.toLowerCase().trim();
    if (!q) return rawGroups;

    return rawGroups.map(g => ({
        ...g,
        items: g.items.filter(item => 
            item.id.toLowerCase().includes(q) || 
            item.label.toLowerCase().includes(q)
        )
    })).filter(g => g.items.length > 0);
});

// Title of active tab
const activeTabTitle = computed(() => {
    for (const group of rawGroups) {
        const found = group.items.find(i => i.id === props.active_tab);
        if (found) return found.label;
    }
    return props.active_tab;
});

// Form State
const form = ref({
    transaction_date: new Date().toISOString().split('T')[0],
    document_number: '',
    description: '',
    flow_direction: 'disbursement',
    amount_net: '',
    transaction_type: 'debet_rekening',
    payment_method: 'bank_transfer',
    pptk_id: '',
    notes: '',
});

const switchTab = (tabName) => {
    router.get('/bku', { tab: tabName, year: props.year }, { preserveState: true, preserveScroll: true });
};

const formatRupiah = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '-';
    if (val === 0) return '0';
    return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

// Quick Filters & Verification State
const quickFilter = ref('all'); // 'all', 'unverified', 'verified', 'receipt', 'disbursement', 'minus', 'gt10m'
const filterStartDate = ref('');
const filterEndDate = ref('');
const filterMinNominal = ref('');
const showAdvancedFilter = ref(false);

const resetFilters = () => {
    searchQuery.value = '';
    quickFilter.value = 'all';
    filterStartDate.value = '';
    filterEndDate.value = '';
    filterMinNominal.value = '';
};

const hasActiveFilters = computed(() => {
    return searchQuery.value || quickFilter.value !== 'all' || filterStartDate.value || filterEndDate.value || filterMinNominal.value;
});

// Anomaly / Negative Saldo Detector
const negativeSaldoRows = computed(() => {
    if (!props.sheet_data?.rows) return [];
    return props.sheet_data.rows.filter(r => Number(r.saldo) < 0);
});

const lowestSaldo = computed(() => {
    if (!negativeSaldoRows.value.length) return 0;
    return Math.min(...negativeSaldoRows.value.map(r => Number(r.saldo)));
});

// Verification Counters & Toggle
const verifiedCount = computed(() => {
    if (!props.sheet_data?.rows) return 0;
    return props.sheet_data.rows.filter(r => r.is_verified).length;
});

const unverifiedCount = computed(() => {
    if (!props.sheet_data?.rows) return 0;
    return props.sheet_data.rows.filter(r => !r.is_verified).length;
});

const togglingVerified = ref({});
const toggleVerified = async (row) => {
    if (!row || !row.id || String(row.id).includes('contra')) return;
    const oldVal = row.is_verified;
    row.is_verified = !oldVal; // optimistic update
    togglingVerified.value[row.id] = true;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const res = await fetch(`/transaksi/${row.id}/toggle-verified`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
                'Accept': 'application/json',
            },
        });
        if (!res.ok) {
            row.is_verified = oldVal;
        }
    } catch (e) {
        row.is_verified = oldVal;
    } finally {
        delete togglingVerified.value[row.id];
    }
};

// Period Lock Status
const isPeriodLocked = computed(() => {
    return props.periods?.some(p => p.is_closed) ?? false;
});

const confirmTogglePeriodLock = () => {
    const action = isPeriodLocked.value ? 'membuka kunci' : 'menutup buku & mengunci';
    if (confirm(`Apakah Anda yakin ingin ${action} periode BKU Tahun Anggaran ${props.year}?`)) {
        router.post(`/periode/${props.year}/1/toggle-lock`, {}, {
            preserveScroll: true,
        });
    }
};

// Audit Trail History Modal State
const showAuditModal = ref(false);
const activeAuditTx = ref(null);
const auditLogs = ref([]);
const loadingAudit = ref(false);

const openAuditModal = async (row) => {
    if (!row || !row.id || String(row.id).includes('contra')) return;
    activeAuditTx.value = row;
    showAuditModal.value = true;
    loadingAudit.value = true;
    auditLogs.value = [];
    try {
        const res = await fetch(`/transaksi/${row.id}/audit-logs`);
        if (res.ok) {
            const data = await res.json();
            auditLogs.value = data.logs || [];
        }
    } catch (e) {
        auditLogs.value = [];
    } finally {
        loadingAudit.value = false;
    }
};

const formatAuditAction = (action) => {
    switch (action) {
        case 'create':
            return { label: 'Transaksi Baru Dibuat', color: 'bg-emerald-100 text-emerald-800 border-emerald-300', dot: 'bg-emerald-600' };
        case 'update':
            return { label: 'Pembaruan Transaksi (Form)', color: 'bg-blue-100 text-blue-800 border-blue-300', dot: 'bg-blue-600' };
        case 'inline_update':
            return { label: 'Edit Cepat / Koreksi Baris', color: 'bg-amber-100 text-amber-800 border-amber-300', dot: 'bg-amber-500' };
        case 'toggle_verified':
            return { label: 'Perubahan Status Verifikasi', color: 'bg-purple-100 text-purple-800 border-purple-300', dot: 'bg-purple-600' };
        default:
            return { label: action, color: 'bg-slate-100 text-slate-700 border-slate-300', dot: 'bg-slate-500' };
    }
};

const formatAuditFieldLabel = (key) => {
    const map = {
        amount_net: 'Nominal Bersih (Rp)',
        amount_gross: 'Nominal Kotor (Rp)',
        nominal: 'Nominal (Rp)',
        amount: 'Nominal (Rp)',
        description: 'Uraian Transaksi',
        uraian: 'Uraian Transaksi',
        transaction_date: 'Tanggal Transaksi',
        tanggal: 'Tanggal Transaksi',
        flow_direction: 'Arus Kas (Masuk/Keluar)',
        notes: 'Catatan / Memo Internal',
        memo: 'Catatan / Memo Internal',
        keterangan: 'Keterangan Transaksi',
        is_verified: 'Status Verifikasi',
        pptk_id: 'Alokasi PPTK',
        sub_activity_id: 'Sub Kegiatan',
        account_id: 'Kode Rekening',
        tax_ppn: 'Pajak PPN (Rp)',
        tax_pph21: 'Pajak PPh 21 (Rp)',
        tax_pph22: 'Pajak PPh 22 (Rp)',
        tax_pph23: 'Pajak PPh 23 (Rp)',
        tax_daerah: 'Pajak Daerah (Rp)',
    };
    return map[key] || key.replace(/_/g, ' ').toUpperCase();
};

const formatAuditFieldValue = (key, val) => {
    if (val === null || val === undefined || val === '') return '—';
    if (key === 'is_verified') {
        return val ? 'Terverifikasi ✓' : 'Belum Dicek / Menunggu';
    }
    if (key === 'flow_direction') {
        return val === 'in' ? 'Penerimaan Kas (Masuk)' : 'Pengeluaran Kas (Keluar)';
    }
    const currencyFields = ['amount_net', 'amount_gross', 'nominal', 'amount', 'penerimaan', 'pengeluaran', 'debet', 'kredit', 'tax_ppn', 'tax_pph21', 'tax_pph22', 'tax_pph23', 'tax_daerah'];
    if (currencyFields.includes(key)) {
        const num = parseFloat(val);
        if (!isNaN(num)) {
            return 'Rp ' + num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }
    return String(val);
};

const getAuditDiffList = (log) => {
    const rows = [];
    const oldVals = log.old_values || {};
    const newVals = log.new_values || {};

    if (log.action === 'create') {
        Object.keys(newVals).forEach(key => {
            if (['id', 'created_at', 'updated_at', 'deleted_at'].includes(key)) return;
            rows.push({
                key,
                label: formatAuditFieldLabel(key),
                isNew: true,
                oldVal: null,
                newVal: formatAuditFieldValue(key, newVals[key]),
            });
        });
        return rows;
    }

    const allKeys = Array.from(new Set([...Object.keys(oldVals), ...Object.keys(newVals)]));
    allKeys.forEach(key => {
        if (['id', 'created_at', 'updated_at', 'deleted_at'].includes(key)) return;
        const vOld = oldVals[key];
        const vNew = newVals[key];
        if (vOld != vNew || allKeys.length <= 3) {
            rows.push({
                key,
                label: formatAuditFieldLabel(key),
                isNew: false,
                oldVal: formatAuditFieldValue(key, vOld),
                newVal: formatAuditFieldValue(key, vNew),
            });
        }
    });

    return rows;
};

// Column Sorting State & Helper
const sortKey = ref('');
const sortOrder = ref('asc'); // 'asc' or 'desc'

const parseDateForSort = (dStr) => {
    if (!dStr) return 0;
    if (typeof dStr === 'number') return dStr;
    const str = String(dStr).trim();
    if (str.includes('-')) {
        return new Date(str).getTime() || 0;
    }
    const parts = str.split('/');
    if (parts.length === 3) {
        return new Date(`${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`).getTime() || 0;
    }
    return 0;
};

const handleSort = (key) => {
    if (sortKey.value === key) {
        if (sortOrder.value === 'asc') {
            sortOrder.value = 'desc';
        } else {
            // Third click resets sort
            sortKey.value = '';
            sortOrder.value = 'asc';
        }
    } else {
        sortKey.value = key;
        // User requested: date newest first (desc), names A-Z (asc)
        if (key === 'tanggal' || key === 'raw_date') {
            sortOrder.value = 'desc';
        } else {
            sortOrder.value = 'asc';
        }
    }
};

const getSortBadgeText = (key, order) => {
    if (key === 'tanggal' || key === 'raw_date') {
        return order === 'desc' ? 'Terbaru' : 'Terlama';
    }
    if (key === 'uraian' || key === 'pptk' || key === 'pptk_name' || key === 'memo' || key === 'keterangan') {
        return order === 'asc' ? 'A - Z' : 'Z - A';
    }
    if (key === 'no') {
        return order === 'asc' ? '1 - 9' : '9 - 1';
    }
    if (key === 'is_verified') {
        return order === 'asc' ? 'Sudah Cek' : 'Belum Cek';
    }
    return order === 'desc' ? 'Terbesar' : 'Terkecil';
};

// Deteksi Transaksi Dobel (Tanggal & Nominal Identik)
const duplicateRowMap = computed(() => {
    const map = new Map();
    const rows = props.sheet_data?.rows || [];
    const groups = {};
    rows.forEach(r => {
        const nominal = Number(r.penerimaan || r.debet || 0) > 0 
            ? `rec_${Number(r.penerimaan || r.debet)}` 
            : `dis_${Number(r.pengeluaran || r.panjar || r.spj || 0)}`;
        const key = `${r.raw_date || r.tanggal}_${nominal}`;
        if (!groups[key]) groups[key] = [];
        groups[key].push(r.id);
    });
    Object.values(groups).forEach(ids => {
        if (ids.length > 1) {
            ids.forEach(id => map.set(id, ids.length));
        }
    });
    return map;
});

const duplicateCount = computed(() => duplicateRowMap.value.size);

// Peringatan dini di modal input BKU
const duplicateWarning = computed(() => {
    if (!form.transaction_date || !form.amount_net || Number(form.amount_net) <= 0) return null;
    const date = form.transaction_date;
    const amount = Number(form.amount_net);
    const doc = form.document_number?.trim();
    return (props.sheet_data?.rows || []).find(r => {
        if (editingTx.value && r.id === editingTx.value.id) return false;
        if (doc && r.document_number && r.document_number.trim() === doc) return true;
        return (r.raw_date === date || r.tanggal === date) && 
               (Number(r.penerimaan || r.debet || 0) === amount || Number(r.pengeluaran || r.panjar || r.spj || 0) === amount);
    }) || null;
});

// Filtered rows for sheets
const filteredRows = computed(() => {
    if (!props.sheet_data) return [];
    let list = [];
    if (props.sheet_data.type === 'deb_reses') {
        if (debResesSubTab.value === 'mamin') {
            list = props.sheet_data.mamin?.rows || [];
        } else if (debResesSubTab.value === 'tunjangan') {
            list = props.sheet_data.tunjangan?.rows || [];
        } else {
            list = props.sheet_data.sewa?.rows || props.sheet_data.rows || [];
        }
    } else {
        list = props.sheet_data.rows || [];
    }

    if (!Array.isArray(list)) return [];

    // 1. Text Search
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(r => {
            return (r.uraian && r.uraian.toLowerCase().includes(q)) ||
                   (r.nama && r.nama.toLowerCase().includes(q)) ||
                   (r.no && String(r.no).includes(q)) ||
                   (r.tanggal && r.tanggal.includes(q)) ||
                   (r.keterangan && r.keterangan.toLowerCase().includes(q)) ||
                   (r.notes && r.notes.toLowerCase().includes(q)) ||
                   (r.pptk_name && r.pptk_name.toLowerCase().includes(q));
        });
    }

    // 2. Quick Filter Preset
    if (quickFilter.value === 'duplicate') {
        list = list.filter(r => duplicateRowMap.value.has(r.id));
    } else if (quickFilter.value === 'unverified') {
        list = list.filter(r => !r.is_verified);
    } else if (quickFilter.value === 'verified') {
        list = list.filter(r => r.is_verified);
    } else if (quickFilter.value === 'receipt') {
        list = list.filter(r => Number(r.penerimaan) > 0 || Number(r.debet) > 0);
    } else if (quickFilter.value === 'disbursement') {
        list = list.filter(r => Number(r.pengeluaran) > 0 || Number(r.panjar) > 0 || Number(r.spj) > 0);
    } else if (quickFilter.value === 'minus') {
        list = list.filter(r => Number(r.saldo) < 0);
    } else if (quickFilter.value === 'gt10m') {
        list = list.filter(r => (Number(r.penerimaan) >= 10000000 || Number(r.pengeluaran) >= 10000000 || Number(r.debet) >= 10000000 || Number(r.panjar) >= 10000000));
    }

    // 3. Date Range Filter
    if (filterStartDate.value) {
        list = list.filter(r => r.raw_date >= filterStartDate.value);
    }
    if (filterEndDate.value) {
        list = list.filter(r => r.raw_date <= filterEndDate.value);
    }

    // 4. Min Nominal Filter
    if (filterMinNominal.value) {
        const min = Number(filterMinNominal.value);
        if (!isNaN(min) && min > 0) {
            list = list.filter(r => (Number(r.penerimaan) >= min || Number(r.pengeluaran) >= min || Number(r.debet) >= min || Number(r.panjar) >= min));
        }
    }

    // 5. Interactive Column Sorting
    if (sortKey.value) {
        list = [...list].sort((a, b) => {
            let valA, valB;

            switch (sortKey.value) {
                case 'is_verified':
                    valA = a.is_verified ? 1 : 0;
                    valB = b.is_verified ? 1 : 0;
                    break;
                case 'no':
                    valA = parseInt(a.no) || 0;
                    valB = parseInt(b.no) || 0;
                    break;
                case 'tanggal':
                    valA = parseDateForSort(a.raw_date || a.tanggal);
                    valB = parseDateForSort(b.raw_date || b.tanggal);
                    break;
                case 'uraian':
                    valA = (a.uraian || a.pptk_name || '').toLowerCase();
                    valB = (b.uraian || b.pptk_name || '').toLowerCase();
                    return sortOrder.value === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                case 'pptk':
                case 'pptk_name':
                    valA = (a.pptk_name || '').toLowerCase();
                    valB = (b.pptk_name || '').toLowerCase();
                    return sortOrder.value === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                case 'penerimaan':
                    valA = Number(a.penerimaan ?? a.debet) || 0;
                    valB = Number(b.penerimaan ?? b.debet) || 0;
                    break;
                case 'pengeluaran':
                    valA = Number(a.pengeluaran ?? a.panjar ?? a.spj) || 0;
                    valB = Number(b.pengeluaran ?? b.panjar ?? b.spj) || 0;
                    break;
                case 'saldo':
                    valA = Number(a.saldo) || 0;
                    valB = Number(b.saldo) || 0;
                    break;
                case 'debet':
                    valA = Number(a.debet) || 0;
                    valB = Number(b.debet) || 0;
                    break;
                case 'panjar':
                    valA = Number(a.panjar) || 0;
                    valB = Number(b.panjar) || 0;
                    break;
                case 'spj':
                    valA = Number(a.spj) || 0;
                    valB = Number(b.spj) || 0;
                    break;
                case 'jml':
                    valA = Number(a.jml) || 0;
                    valB = Number(b.jml) || 0;
                    break;
                case 'total_hutang_panjar':
                    valA = Number(a.total_hutang_panjar) || 0;
                    valB = Number(b.total_hutang_panjar) || 0;
                    break;
                case 'bank':
                    valA = Number(a.bank) || 0;
                    valB = Number(b.bank) || 0;
                    break;
                case 'tunai':
                    valA = Number(a.tunai) || 0;
                    valB = Number(b.tunai) || 0;
                    break;
                case 'spj_lanjutan':
                    valA = Number(a.spj_lanjutan) || 0;
                    valB = Number(b.spj_lanjutan) || 0;
                    break;
                case 'sisa_panjar':
                    valA = Number(a.sisa_panjar) || 0;
                    valB = Number(b.sisa_panjar) || 0;
                    break;
                case 'total_sisa_riil':
                    valA = Number(a.total_sisa_riil) || 0;
                    valB = Number(b.total_sisa_riil) || 0;
                    break;
                case 'kontrol_selisih':
                    valA = Number(a.kontrol_selisih) || 0;
                    valB = Number(b.kontrol_selisih) || 0;
                    break;
                case 'memo':
                    valA = (a.notes || a.keterangan || '').toLowerCase();
                    valB = (b.notes || b.keterangan || '').toLowerCase();
                    return sortOrder.value === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                case 'keterangan':
                    valA = (a.keterangan || '').toLowerCase();
                    valB = (b.keterangan || '').toLowerCase();
                    return sortOrder.value === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                default:
                    if (a.custom_values && b.custom_values && a.custom_values[sortKey.value] !== undefined) {
                        valA = String(a.custom_values[sortKey.value] || '').toLowerCase();
                        valB = String(b.custom_values[sortKey.value] || '').toLowerCase();
                        return sortOrder.value === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                    }
                    valA = a[sortKey.value] ?? '';
                    valB = b[sortKey.value] ?? '';
                    if (typeof valA === 'string') {
                        return sortOrder.value === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                    }
            }

            if (valA < valB) return sortOrder.value === 'asc' ? -1 : 1;
            if (valA > valB) return sortOrder.value === 'asc' ? 1 : -1;
            return 0;
        });
    }

    return list;
});

// Pagination State
const currentPage = ref(1);
const perPage = ref(20); // 10, 20, 50, 100, 'all'

const totalRowsCount = computed(() => {
    return filteredRows.value.length;
});

const totalPages = computed(() => {
    if (perPage.value === 'all' || !totalRowsCount.value) return 1;
    return Math.ceil(totalRowsCount.value / perPage.value);
});

const paginatedRows = computed(() => {
    const rows = filteredRows.value;
    if (perPage.value === 'all') return rows;
    
    const start = (currentPage.value - 1) * perPage.value;
    return rows.slice(start, start + perPage.value);
});

const displayedPages = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }
    if (current <= 4) {
        return [1, 2, 3, 4, 5, '...', total];
    }
    if (current >= total - 3) {
        return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
    }
    return [1, '...', current - 1, current, current + 1, '...', total];
});

watch([searchQuery, quickFilter, filterStartDate, filterEndDate, filterMinNominal, perPage, sortKey, sortOrder, () => props.active_tab], () => {
    currentPage.value = 1;
});

// Column visibility state
const defaultStandardColumns = {
    no: { label: 'No. Urut', visible: true },
    tanggal: { label: 'Tanggal', visible: true },
    uraian: { label: 'Uraian', visible: true },
    penerimaan: { label: 'Penerimaan', visible: true },
    pengeluaran: { label: 'Pengeluaran', visible: true },
    saldo: { label: 'Saldo', visible: true },
    memo: { label: 'Catatan / Memo Internal', visible: true },
};

const columnVisibility = ref({ ...defaultStandardColumns });
const customColumns = ref([]);
const newColumnName = ref('');
const showColumnSettings = ref(false);

// Load column preferences from localStorage
onMounted(() => {
    try {
        const savedVis = localStorage.getItem('keudprd_bku_col_vis');
        if (savedVis) {
            columnVisibility.value = { ...defaultStandardColumns, ...JSON.parse(savedVis) };
        }
        const savedCustom = localStorage.getItem('keudprd_bku_custom_cols');
        if (savedCustom) {
            customColumns.value = JSON.parse(savedCustom);
        }
    } catch (e) {}
    checkAndApplyHighlight();
});

// ================= GLOBAL SEARCH HIGHLIGHT HANDLER =================
const highlightedRowId = ref(null);

const checkAndApplyHighlight = () => {
    if (typeof window === 'undefined') return;
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('highlight');
    if (!highlightId) return;

    const targetId = Number(highlightId);
    highlightedRowId.value = targetId;

    // Check if target is in filteredRows to jump to the right page
    const rowIndex = filteredRows.value.findIndex(r => r.id === targetId);
    if (rowIndex !== -1 && perPage.value !== 'all') {
        const targetPage = Math.floor(rowIndex / perPage.value) + 1;
        currentPage.value = targetPage;
    }

    nextTick(() => {
        setTimeout(() => {
            const el = document.getElementById('bku-row-' + targetId);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 200);
    });

    // Remove pulse highlight after 4.5 seconds
    setTimeout(() => {
        highlightedRowId.value = null;
    }, 4500);
};

watch(() => props.active_tab, () => {
    checkAndApplyHighlight();
});

watch(columnVisibility, (val) => {
    try {
        localStorage.setItem('keudprd_bku_col_vis', JSON.stringify(val));
    } catch (e) {}
}, { deep: true });

watch(customColumns, (val) => {
    try {
        localStorage.setItem('keudprd_bku_custom_cols', JSON.stringify(val));
    } catch (e) {}
}, { deep: true });

const addCustomColumn = () => {
    const name = newColumnName.value.trim();
    if (name && !customColumns.value.includes(name)) {
        customColumns.value.push(name);
        newColumnName.value = '';
    }
};

const removeCustomColumn = (idx) => {
    customColumns.value.splice(idx, 1);
};

// Inline Editing for Notes / Custom Fields
const editingCell = ref({ id: null, field: null });
const cellDraftValue = ref('');
const savingStatus = ref({}); // { `${id}_${field}`: 'saving' | 'saved' | 'error' }

const startEditCell = (row, field, initialValue) => {
    if (!row || !row.id || String(row.id).includes('contra')) return;
    editingCell.value = { id: row.id, field };
    cellDraftValue.value = initialValue || '';
};

const cancelEditCell = () => {
    editingCell.value = { id: null, field: null };
};

const saveCellDraft = async (row, field) => {
    if (editingCell.value.id !== row.id || editingCell.value.field !== field) return;
    
    const key = `${row.id}_${field}`;
    const val = cellDraftValue.value;
    editingCell.value = { id: null, field: null };

    // Optimistic UI update
    if (field === 'memo' || field === 'notes') {
        row.notes = val;
    } else {
        if (!row.custom_fields) row.custom_fields = {};
        row.custom_fields[field] = val;
    }

    savingStatus.value[key] = 'saving';

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const res = await fetch(`/bku/transaksi/${row.id}/inline`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ field, value: val }),
        });
        if (res.ok) {
            savingStatus.value[key] = 'saved';
            setTimeout(() => {
                delete savingStatus.value[key];
            }, 1800);
        } else {
            savingStatus.value[key] = 'error';
        }
    } catch (e) {
        savingStatus.value[key] = 'error';
    }
};

const showPrintMenu = ref(false);

const printDocument = (mode = 'all') => {
    showPrintMenu.value = false;
    if (mode === 'all') {
        const previousPerPage = perPage.value;
        perPage.value = 'all';
        nextTick(() => {
            window.print();
            setTimeout(() => {
                perPage.value = previousPerPage;
            }, 800);
        });
    } else {
        window.print();
    }
};

const openAddModal = () => {
    if (isPeriodLocked.value) return;
    editingTx.value = null;
    const currentPptk = props.pptks.find(p => p.sheet_name === props.active_tab);
    form.value = {
        transaction_date: new Date().toISOString().split('T')[0],
        document_number: '',
        description: '',
        flow_direction: 'disbursement',
        amount_net: '',
        transaction_type: props.active_tab.startsWith('Deb') ? 'debet_rekening' : (props.active_tab === 'KK' ? 'panjar_disbursement' : 'debet_rekening'),
        payment_method: props.active_tab === 'KK' ? 'cash_tunai' : 'bank_transfer',
        pptk_id: currentPptk ? currentPptk.id : '',
        notes: '',
    };
    showAddModal.value = true;
};

const openEditModal = (r) => {
    if (isPeriodLocked.value || !r || !r.id || r.is_contra || String(r.id).includes('contra')) return;

    editingTx.value = r;
    const isReceipt = (r.penerimaan && r.penerimaan > 0);
    const amount = isReceipt 
        ? r.penerimaan 
        : (r.pengeluaran || r.debet || r.panjar || r.spj || r.bruto || 0);

    let formattedDate = r.raw_date;
    if (!formattedDate && r.tanggal) {
        const parts = r.tanggal.split('/');
        if (parts.length === 3) {
            formattedDate = `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
        }
    }

    const currentPptk = props.pptks.find(p => p.sheet_name === props.active_tab);

    form.value = {
        transaction_date: formattedDate || new Date().toISOString().split('T')[0],
        document_number: r.no_bukti || r.document_number || '',
        description: r.uraian || '',
        flow_direction: isReceipt ? 'receipt' : 'disbursement',
        amount_net: amount || '',
        transaction_type: r.transaction_type || (props.active_tab.startsWith('Deb') ? 'debet_rekening' : (props.active_tab === 'KK' ? 'panjar_disbursement' : 'debet_rekening')),
        payment_method: r.payment_method || (props.active_tab === 'KK' ? 'cash_tunai' : 'bank_transfer'),
        pptk_id: r.pptk_id || (currentPptk ? currentPptk.id : ''),
        notes: r.keterangan || r.notes || '',
    };
    showAddModal.value = true;
};

const saveTransaction = () => {
    if (isPeriodLocked.value) return;
    if (editingTx.value) {
        router.put(`/bku/transaksi/${editingTx.value.id}`, form.value, {
            onSuccess: () => {
                showAddModal.value = false;
                editingTx.value = null;
            }
        });
    } else {
        router.post('/bku/transaksi', form.value, {
            onSuccess: () => {
                showAddModal.value = false;
            }
        });
    }
};

const deleteTransaction = (id) => {
    if (isPeriodLocked.value) return;
    if (confirm('Yakin ingin menghapus transaksi ini dari pembukuan?')) {
        router.delete(`/bku/transaksi/${id}`);
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="`BKU - ${activeTabTitle}`" />

        <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden flex min-h-[calc(100vh-140px)] print:border-0 print:shadow-none print:rounded-none print:overflow-visible print:block print:w-full print:min-h-0">
            
            <!-- ================= LEFT SUB-SIDEBAR: 20 SHEET DROPDOWN ACCORDION ================= -->
            <aside 
                :class="[
                    'bg-[#fcfdfc] transition-[width] duration-200 ease-in-out flex flex-col shrink-0 select-none overflow-hidden print:hidden no-print',
                    isSidebarOpen ? 'w-72 border-r border-slate-200' : 'w-0 border-r-0'
                ]"
            >
                <!-- Fixed Width Container to prevent content squashing/leaking during collapse animation -->
                <div class="w-72 h-full flex flex-col shrink-0">
                    <!-- Sidebar Header -->
                    <div class="p-3.5 border-b border-slate-200 bg-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <BookOpen class="w-4 h-4 text-[#107c41]" />
                            <span class="font-bold text-xs text-slate-800">Lembar BKU</span>
                            <span class="text-[11px] font-bold bg-[#dcfce7] text-[#065f46] px-2 py-0.5 rounded-full">20</span>
                        </div>
                        <button 
                            @click="isSidebarOpen = false" 
                            class="text-slate-400 hover:text-slate-700 p-1 rounded-md border border-slate-200 hover:bg-slate-50 transition-colors cursor-pointer"
                            title="Tutup Sidebar"
                        >
                            <ChevronLeft class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Search Input for Sheets -->
                    <div class="p-3 border-b border-slate-200 bg-white">
                        <div class="relative">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" />
                            <input
                                v-model="sheetSearchQuery"
                                type="text"
                                placeholder="Cari lembar / PPTK"
                                class="w-full pl-8 pr-2.5 py-1.5 text-xs bg-white border border-slate-200 rounded-lg text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                            />
                        </div>
                    </div>

                    <!-- Accordion Groups (Clean Dropdown Style) -->
                    <div class="flex-1 overflow-y-auto p-2.5 space-y-2 text-xs excel-scroll">
                        <div v-for="group in tabGroups" :key="group.name" class="border-b border-slate-100 pb-1.5 last:border-b-0">
                            <!-- Group Accordion Header -->
                            <button 
                                @click="toggleGroup(group.name)"
                                class="w-full flex items-center justify-between px-2 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-100/70 rounded-lg transition-colors cursor-pointer select-none"
                            >
                                <div class="flex items-center gap-2">
                                    <component :is="group.icon" class="w-4 h-4 text-[#107c41]" />
                                    <span>{{ group.name }}</span>
                                    <span class="text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60 px-1.5 py-0.5 rounded-full">
                                        {{ group.items.length }}
                                    </span>
                                </div>
                                <ChevronUp v-if="expandedGroups[group.name] || sheetSearchQuery.trim() !== ''" class="w-3.5 h-3.5 text-slate-400" />
                                <ChevronDown v-else class="w-3.5 h-3.5 text-slate-400" />
                            </button>
                            
                            <!-- Group Items -->
                            <div 
                                v-show="expandedGroups[group.name] || sheetSearchQuery.trim() !== ''" 
                                class="mt-1 space-y-0.5 pl-1"
                            >
                                <button
                                    v-for="item in group.items"
                                    :key="item.id"
                                    @click="switchTab(item.id)"
                                    :class="[
                                        'w-full text-left py-2 px-3 text-xs transition-colors cursor-pointer flex items-center justify-between',
                                        active_tab === item.id
                                            ? 'bg-[#dcfce7] border-l-4 border-[#107c41] text-[#065f46] font-bold rounded-r-lg shadow-2xs'
                                            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 font-medium rounded-lg'
                                    ]"
                                >
                                    <span class="truncate">{{ item.label }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-sidebar Bottom Status -->
                    <div class="p-3 border-t border-slate-200 bg-[#fcfdfc] text-xs text-slate-500">
                        Sheet aktif: <span class="font-semibold text-slate-700">{{ active_tab }}</span>
                    </div>
                </div>
            </aside>

            <!-- ================= RIGHT MAIN WORKSPACE ================= -->
            <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-white print:overflow-visible print:block print:w-full">
                
                <!-- Action Toolbar matching Mockup (Responsive flex-wrap to prevent clipping) -->
                <div class="bg-white border-b border-slate-200 px-4 sm:px-6 py-2.5 flex flex-wrap items-center justify-between gap-2.5 min-h-[58px] print:hidden no-print">
                    <div class="flex items-center gap-2.5 shrink-0">
                        <!-- Toggle Button to Open Sidebar if Closed -->
                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95"
                        >
                            <button
                                v-if="!isSidebarOpen"
                                @click="isSidebarOpen = true"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 rounded-lg text-xs font-semibold shadow-2xs transition-colors cursor-pointer shrink-0"
                                title="Buka Lembar BKU"
                            >
                                <ChevronRight class="w-3.5 h-3.5 text-[#107c41]" />
                                <span>Lembar BKU</span>
                            </button>
                        </Transition>

                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-base sm:text-lg font-bold text-slate-900 leading-snug whitespace-nowrap">{{ activeTabTitle }}</h1>
                                <button 
                                    @click="confirmTogglePeriodLock"
                                    :class="isPeriodLocked ? 'bg-amber-100 text-amber-900 border-amber-300 hover:bg-amber-200' : 'bg-slate-100 text-slate-700 border-slate-300 hover:bg-slate-200'"
                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 text-[11px] font-bold rounded-lg border shadow-2xs transition-colors cursor-pointer"
                                    :title="isPeriodLocked ? 'Periode dikunci (Tutup Buku). Klik untuk buka kunci.' : 'Periode aktif. Klik untuk tutup buku & kunci transaksi.'"
                                >
                                    <Lock v-if="isPeriodLocked" class="w-3 h-3 text-amber-700" />
                                    <Unlock v-else class="w-3 h-3 text-slate-500" />
                                    <span>{{ isPeriodLocked ? 'Tutup Buku' : 'Kunci' }}</span>
                                </button>
                            </div>
                            <p class="text-[11px] text-slate-500 font-medium">BKU Per PPTK</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 justify-end">
                        <button 
                            v-if="!isPeriodLocked"
                            @click="openAddModal"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#107c41] hover:bg-[#0c6334] text-white rounded-lg text-xs font-semibold shadow-xs transition-colors cursor-pointer whitespace-nowrap shrink-0"
                            title="Tambah Transaksi Baru"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>Transaksi</span>
                        </button>
                        <div 
                            v-else 
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 text-slate-400 border border-slate-200 rounded-lg text-xs font-semibold cursor-not-allowed select-none whitespace-nowrap shrink-0"
                            title="Periode telah ditutup buku. Buka kunci untuk menambah transaksi."
                        >
                            <Lock class="w-3.5 h-3.5 text-slate-400" />
                            <span>Terkunci</span>
                        </div>

                        <!-- Search Input (Compact & Responsive) -->
                        <div class="relative shrink-0">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari..."
                                class="w-32 sm:w-40 md:w-48 pl-8 pr-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 shadow-2xs"
                            />
                        </div>

                        <!-- Button Toggle Filter Rentang & Nominal -->
                        <button 
                            @click="showAdvancedFilter = !showAdvancedFilter"
                            :class="showAdvancedFilter || hasActiveFilters ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 border rounded-lg text-xs font-semibold shadow-2xs transition-colors cursor-pointer whitespace-nowrap shrink-0"
                            title="Filter rentang tanggal & nominal cepat"
                        >
                            <Filter class="w-3.5 h-3.5" />
                            <span>Filter</span>
                            <span v-if="hasActiveFilters" class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                        </button>

                        <!-- Button Cek Potensi Dobel (Otomatis Tampil Jika Ada Baris Dobel) -->
                        <button 
                            v-if="duplicateCount > 0"
                            type="button"
                            @click="quickFilter = quickFilter === 'duplicate' ? 'all' : 'duplicate'"
                            :class="[
                                quickFilter === 'duplicate' 
                                    ? 'bg-amber-600 text-white ring-2 ring-amber-400 font-bold' 
                                    : 'bg-amber-100 text-amber-900 border border-amber-300 hover:bg-amber-200 font-semibold',
                                'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs shadow-2xs transition-all cursor-pointer whitespace-nowrap shrink-0'
                            ]"
                            :title="quickFilter === 'duplicate' ? 'Klik untuk kembali ke semua transaksi' : 'Klik untuk menyaring dan melihat transaksi yang berpotensi dobel'"
                        >
                            <AlertTriangle class="w-3.5 h-3.5 text-amber-600" />
                            <span>{{ duplicateCount }} Potensi Dobel</span>
                        </button>

                        <!-- Atur Kolom Popover Button -->
                        <div v-if="active_tab !== 'Hitungan' && active_tab !== 'Jml Deb Rek'" class="relative shrink-0">
                            <button 
                                @click="showColumnSettings = !showColumnSettings"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 rounded-lg text-xs font-semibold shadow-2xs transition-colors cursor-pointer whitespace-nowrap"
                                title="Atur kolom & tambah kolom kustom baru"
                            >
                                <SlidersHorizontal class="w-3.5 h-3.5 text-slate-500" />
                                <span>Atur Kolom</span>
                                <span v-if="customColumns.length > 0" class="px-1.5 py-0.2 bg-emerald-100 text-emerald-800 text-[10px] font-bold rounded-full">
                                    +{{ customColumns.length }}
                                </span>
                            </button>

                            <!-- Popover Menu -->
                            <div 
                                v-if="showColumnSettings" 
                                class="absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-slate-200 p-4 z-50 text-xs space-y-3.5 select-none text-left"
                            >
                                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                    <span class="font-bold text-slate-900">Pengaturan Kolom Tabel</span>
                                    <button @click="showColumnSettings = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                                        <X class="w-4 h-4" />
                                    </button>
                                </div>

                                <!-- Standard Columns Checklist -->
                                <div>
                                    <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Visibilitas Kolom Standar:</div>
                                    <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                                        <label v-for="(col, key) in columnVisibility" :key="key" class="flex items-center gap-2 cursor-pointer text-slate-700 hover:text-slate-900">
                                            <input type="checkbox" v-model="col.visible" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer" />
                                            <span :class="{'line-through text-slate-400': !col.visible}" class="font-medium">{{ col.label }}</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Custom Dynamic Columns Section -->
                                <div class="pt-2 border-t border-slate-100">
                                    <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Kolom Tambahan Kustom:</div>
                                    
                                    <!-- Tag list -->
                                    <div v-if="customColumns.length > 0" class="flex flex-wrap gap-1.5 mb-2.5">
                                        <span 
                                            v-for="(cc, idx) in customColumns" 
                                            :key="idx" 
                                            class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-md text-[11px] font-semibold"
                                        >
                                            {{ cc }}
                                            <button @click="removeCustomColumn(idx)" class="hover:text-rose-600 text-slate-400 cursor-pointer" title="Hapus kolom ini">
                                                <X class="w-3 h-3" />
                                            </button>
                                        </span>
                                    </div>
                                    <div v-else class="text-[11px] text-slate-400 italic mb-2">
                                        Belum ada kolom kustom tambahan.
                                    </div>

                                    <!-- Add input -->
                                    <div class="flex items-center gap-1.5">
                                        <input 
                                            v-model="newColumnName" 
                                            @keydown.enter="addCustomColumn"
                                            type="text" 
                                            placeholder="Contoh: No. SP2D / Paraf"
                                            class="flex-1 px-2.5 py-1 text-xs border border-slate-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500 font-medium"
                                        />
                                        <button 
                                            @click="addCustomColumn"
                                            class="px-2.5 py-1 bg-[#107c41] hover:bg-[#0c6334] text-white rounded-lg text-xs font-bold transition-colors cursor-pointer whitespace-nowrap"
                                        >
                                            + Tambah
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <a 
                            v-if="active_tab === 'Hitungan'"
                            href="/laporan/export-hitungan"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 rounded-lg text-xs font-medium shadow-2xs transition-colors whitespace-nowrap shrink-0"
                            title="Unduh Spreadsheet Excel"
                        >
                            <Download class="w-3.5 h-3.5 text-slate-500" />
                            <span>Ekspor</span>
                        </a>

                        <!-- Cetak Split Dropdown (Dokumen Resmi vs Lembar Kerja Rapat) -->
                        <div class="relative shrink-0">
                            <button 
                                @click="showPrintMenu = !showPrintMenu"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 rounded-lg text-xs font-semibold shadow-2xs hover:border-slate-400 transition-colors cursor-pointer whitespace-nowrap"
                                title="Pilihan Cetak Dokumen"
                            >
                                <Printer class="w-3.5 h-3.5 text-slate-600" />
                                <span>Cetak</span>
                                <ChevronDown class="w-3 h-3 text-slate-500" />
                            </button>

                            <div 
                                v-if="showPrintMenu" 
                                class="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-xl border border-slate-200 p-2 z-50 text-xs space-y-1 select-none text-left"
                            >
                                <div class="px-2.5 py-1.5 font-bold text-slate-500 text-[10px] uppercase tracking-wider border-b border-slate-100">
                                    Pilih Format Cetak:
                                </div>
                                <a 
                                    :href="`/bku/cetak?tab=${active_tab}&year=${year}`" 
                                    target="_blank"
                                    @click="showPrintMenu = false"
                                    class="flex items-start gap-2.5 px-2.5 py-2 rounded-lg hover:bg-slate-50 transition-colors text-slate-800 cursor-pointer"
                                >
                                    <span class="text-base">📄</span>
                                    <div>
                                        <span class="font-bold block text-slate-900">Cetak Dokumen Resmi</span>
                                        <span class="text-[11px] text-slate-500 block">Format standar Permendagri bersih di tab baru</span>
                                    </div>
                                </a>
                                <a 
                                    :href="`/bku/cetak?tab=${active_tab}&year=${year}&with_notes=1`" 
                                    target="_blank"
                                    @click="showPrintMenu = false"
                                    class="flex items-start gap-2.5 px-2.5 py-2 rounded-lg hover:bg-amber-50/80 transition-colors text-slate-800 cursor-pointer border border-amber-200 bg-amber-50/40"
                                >
                                    <span class="text-base">📝</span>
                                    <div>
                                        <span class="font-bold block text-amber-950">Cetak Lembar Kerja Rapat</span>
                                        <span class="text-[11px] text-amber-700 block">Termasuk catatan memo internal & oret-oret</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Spreadsheet Work Area -->
                <div class="flex-1 overflow-auto excel-scroll p-6 bg-white print:overflow-visible print:p-0 print:block print:w-full space-y-4">
                    
                    <!-- Warning Banner: Period Locked -->
                    <div v-if="isPeriodLocked" class="p-3.5 bg-amber-50/95 border border-amber-300 rounded-xl flex items-center justify-between gap-3 text-amber-950 text-xs shadow-2xs print:hidden">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-lg bg-amber-200 flex items-center justify-center shrink-0">
                                <Lock class="w-4 h-4 text-amber-900" />
                            </div>
                            <div>
                                <span class="font-bold text-amber-900 text-xs">Periode BKU Telah Ditutup Buku (Terkunci)</span>
                                <p class="text-amber-800 text-[11px] mt-0.5">
                                    Dokumen dan saldo pada lembar ini dalam status aman (read-only) untuk kepatuhan audit. Buka kunci untuk menambah atau mengedit transaksi.
                                </p>
                            </div>
                        </div>
                        <button 
                            @click="confirmTogglePeriodLock" 
                            class="px-2.5 py-1 bg-white hover:bg-amber-100 text-amber-900 border border-amber-300 rounded-lg font-bold text-xs shrink-0 cursor-pointer shadow-2xs"
                        >
                            Buka Kunci
                        </button>
                    </div>

                    <!-- Warning Banner: Saldo Minus / Overdraft Anomali -->
                    <div v-if="negativeSaldoRows.length > 0" class="p-3.5 bg-rose-50 border border-rose-300 rounded-xl flex items-center justify-between gap-3 text-rose-950 text-xs shadow-2xs print:hidden">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-lg bg-rose-200 flex items-center justify-center shrink-0">
                                <AlertTriangle class="w-4 h-4 text-rose-800" />
                            </div>
                            <div>
                                <span class="font-bold text-rose-900 text-xs">Peringatan: Saldo Berjalan Minus (Anomali)</span>
                                <p class="text-rose-800 text-[11px] mt-0.5">
                                    Terdeteksi <b>{{ negativeSaldoRows.length }}</b> baris transaksi dengan saldo di bawah Rp 0 (Saldo terendah: <b>Rp {{ formatRupiah(lowestSaldo) }}</b>).
                                </p>
                            </div>
                        </div>
                        <button 
                            @click="quickFilter = 'minus'" 
                            class="px-2.5 py-1 bg-white hover:bg-rose-100 text-rose-800 border border-rose-300 rounded-lg font-bold text-xs shrink-0 cursor-pointer shadow-2xs"
                        >
                            Lihat Baris Minus ({{ negativeSaldoRows.length }})
                        </button>
                    </div>

                    <!-- Advanced Filter Box (Collapsible) -->
                    <div v-if="showAdvancedFilter" class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2.5 text-xs print:hidden">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span class="font-bold text-slate-800 flex items-center gap-1.5">
                                <Filter class="w-3.5 h-3.5 text-emerald-700" />
                                <span>Filter Rentang Tanggal & Ambang Nominal</span>
                            </span>
                            <button 
                                v-if="hasActiveFilters"
                                @click="resetFilters" 
                                class="text-rose-600 hover:text-rose-800 font-bold text-[11px] cursor-pointer"
                            >
                                Reset Semua Filter
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-slate-600 font-semibold mb-1 text-[11px]">Dari Tanggal:</label>
                                <input 
                                    v-model="filterStartDate" 
                                    type="date" 
                                    class="w-full px-2.5 py-1 bg-white border border-slate-300 rounded-lg text-xs"
                                />
                            </div>
                            <div>
                                <label class="block text-slate-600 font-semibold mb-1 text-[11px]">Sampai Tanggal:</label>
                                <input 
                                    v-model="filterEndDate" 
                                    type="date" 
                                    class="w-full px-2.5 py-1 bg-white border border-slate-300 rounded-lg text-xs"
                                />
                            </div>
                            <div>
                                <label class="block text-slate-600 font-semibold mb-1 text-[11px]">Min. Nominal (Rp):</label>
                                <input 
                                    v-model="filterMinNominal" 
                                    type="number" 
                                    placeholder="Contoh: 10000000"
                                    class="w-full px-2.5 py-1 bg-white border border-slate-300 rounded-lg text-xs font-mono"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Quick Filter Pills: Checklist Status & Fast Views -->
                    <div class="flex flex-wrap items-center gap-1.5 border-b border-slate-100 pb-2.5 print:hidden">
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1">Status Verifikasi & Filter:</span>
                        
                        <button 
                            @click="quickFilter = 'all'"
                            :class="quickFilter === 'all' ? 'bg-slate-900 text-white font-bold' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                            class="px-2.5 py-1 rounded-lg text-xs transition-colors cursor-pointer"
                        >
                            Semua ({{ sheet_data.rows?.length || 0 }})
                        </button>

                        <button 
                            @click="quickFilter = 'unverified'"
                            :class="quickFilter === 'unverified' ? 'bg-amber-600 text-white font-bold' : 'bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100'"
                            class="px-2.5 py-1 rounded-lg text-xs transition-colors cursor-pointer flex items-center gap-1"
                        >
                            <Clock class="w-3 h-3" />
                            <span>Belum Dicek ({{ unverifiedCount }})</span>
                        </button>

                        <button 
                            @click="quickFilter = 'verified'"
                            :class="quickFilter === 'verified' ? 'bg-emerald-700 text-white font-bold' : 'bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100'"
                            class="px-2.5 py-1 rounded-lg text-xs transition-colors cursor-pointer flex items-center gap-1"
                        >
                            <CheckCircle2 class="w-3 h-3" />
                            <span>Terverifikasi ({{ verifiedCount }})</span>
                        </button>

                        <button 
                            @click="quickFilter = 'receipt'"
                            :class="quickFilter === 'receipt' ? 'bg-emerald-800 text-white font-bold' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200'"
                            class="px-2.5 py-1 rounded-lg text-xs transition-colors cursor-pointer"
                        >
                            Penerimaan / Debet
                        </button>

                        <button 
                            @click="quickFilter = 'disbursement'"
                            :class="quickFilter === 'disbursement' ? 'bg-rose-700 text-white font-bold' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200'"
                            class="px-2.5 py-1 rounded-lg text-xs transition-colors cursor-pointer"
                        >
                            Pengeluaran / Panjar
                        </button>

                        <button 
                            @click="quickFilter = 'gt10m'"
                            :class="quickFilter === 'gt10m' ? 'bg-blue-700 text-white font-bold' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200'"
                            class="px-2.5 py-1 rounded-lg text-xs transition-colors cursor-pointer"
                        >
                            &ge; 10 Juta
                        </button>

                        <button 
                            v-if="negativeSaldoRows.length > 0"
                            @click="quickFilter = 'minus'"
                            :class="quickFilter === 'minus' ? 'bg-rose-800 text-white font-bold' : 'bg-rose-100 text-rose-800 border border-rose-300 font-bold'"
                            class="px-2.5 py-1 rounded-lg text-xs transition-colors cursor-pointer flex items-center gap-1"
                        >
                            <AlertTriangle class="w-3 h-3 text-rose-700" />
                            <span>Saldo Minus ({{ negativeSaldoRows.length }})</span>
                        </button>
                    </div>

                    <!-- Official Excel Document Header -->
                    <div class="mb-5 text-center select-text">
                        <h1 class="text-lg font-bold text-slate-900 tracking-tight">
                            <span v-if="active_tab === 'Hitungan'">Rekapitulasi BKU per PPTK</span>
                            <span v-else-if="active_tab === 'UP'">Buku Kas Umum (UP)</span>
                            <span v-else-if="active_tab === 'KK'">Buku Kas Kecil (Kas Tunai)</span>
                            <span v-else-if="active_tab === 'Jml Deb Rek'">Jumlah Debet Rekening & Panjar Bulanan</span>
                            <span v-else-if="active_tab === 'Deb Rek'">Buku Panjar / Debet Rekening Induk</span>
                            <span v-else-if="active_tab.startsWith('Reses')">Buku Reses Sidang</span>
                            <span v-else-if="active_tab.startsWith('Deb')">Debet Rekening Reses & PPh 23</span>
                            <span v-else>Buku Pembantu PPTK: {{ sheet_data.meta?.pptk || active_tab }}</span>
                        </h1>
                        <h2 class="text-sm font-semibold text-slate-700 mt-0.5">Kabupaten Banyumas</h2>
                        
                        <!-- Official Document Header (Kop SKPD & Bendahara Rapi & Presisi) -->
                        <div class="mt-4 inline-block mx-auto max-w-full text-left">
                            <div class="border-y border-slate-200 py-3 px-6 bg-slate-50/70 rounded-xs shadow-2xs">
                                <div class="flex flex-col md:flex-row items-start gap-x-14 gap-y-2 text-xs text-slate-700">
                                    <!-- Kolom Kiri -->
                                    <table class="text-xs">
                                        <tbody>
                                            <tr>
                                                <td class="py-0.5 pr-3 font-semibold text-slate-600 whitespace-nowrap">SKPD</td>
                                                <td class="py-0.5 px-1.5 text-slate-400 font-bold text-center">:</td>
                                                <td class="py-0.5 font-medium text-slate-900 whitespace-nowrap">
                                                    {{ sheet_data.meta?.skpd || setting?.skpd_name || 'SETWAN KABUPATEN BANYUMAS' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 font-semibold text-slate-600 whitespace-nowrap">Pengguna Anggaran</td>
                                                <td class="py-0.5 px-1.5 text-slate-400 font-bold text-center">:</td>
                                                <td class="py-0.5 font-medium text-slate-900 whitespace-nowrap">
                                                    {{ sheet_data.meta?.pa_kpa || setting?.pa_kpa_name }}
                                                </td>
                                            </tr>
                                            <tr v-if="sheet_data.meta?.pptk">
                                                <td class="py-0.5 pr-3 font-semibold text-slate-600 whitespace-nowrap">PPTK</td>
                                                <td class="py-0.5 px-1.5 text-slate-400 font-bold text-center">:</td>
                                                <td class="py-0.5 font-bold text-slate-900 whitespace-nowrap">
                                                    {{ sheet_data.meta?.pptk }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <!-- Kolom Kanan -->
                                    <table class="text-xs">
                                        <tbody>
                                            <tr>
                                                <td class="py-0.5 pr-3 font-semibold text-slate-600 whitespace-nowrap">Bendahara Pengeluaran</td>
                                                <td class="py-0.5 px-1.5 text-slate-400 font-bold text-center">:</td>
                                                <td class="py-0.5 font-medium text-slate-900 whitespace-nowrap">
                                                    {{ sheet_data.meta?.bendahara || setting?.treasurer_name }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="py-0.5 pr-3 font-semibold text-slate-600 whitespace-nowrap">Periode</td>
                                                <td class="py-0.5 px-1.5 text-slate-400 font-bold text-center">:</td>
                                                <td class="py-0.5 font-medium text-slate-900 whitespace-nowrap">
                                                    {{ sheet_data.meta?.periode || ('JANUARI s/d DESEMBER ' + year) }}
                                                </td>
                                            </tr>
                                            <tr v-if="sheet_data.meta?.pptk">
                                                <td colspan="3" class="py-0.5 select-none text-transparent">&nbsp;</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Synchronized Horizontal Slider (Bisa geser kolom langsung dari atas tanpa scroll ke bawah!) -->
                    <div 
                        v-show="tableScrollWidth > 700"
                        ref="topScrollRef" 
                        @scroll="syncScroll('top')" 
                        class="overflow-x-auto excel-scroll bg-slate-100 border border-slate-300 rounded h-2.5 mb-1.5 cursor-ew-resize select-none print:hidden no-print"
                        title="Slider horizontal atas (geser kolom tabel langsung dari atas tanpa scroll ke bawah)"
                    >
                        <div :style="{ width: tableScrollWidth + 'px', height: '1px' }"></div>
                    </div>

                    <!-- Table Container (Horizontal slider bottom synced automatically) -->
                    <div 
                        ref="bottomScrollRef" 
                        @scroll="syncScroll('bottom')" 
                        class="overflow-x-auto excel-scroll border border-slate-300 rounded print:overflow-visible print:border-0 print:rounded-none print:w-full"
                    >

                        <!-- ================= VIEW 1: HITUNGAN SUMMARY ================= -->
                        <div v-if="active_tab === 'Hitungan'">
                        <table class="w-full excel-table border border-slate-300">
                            <thead>
                                <tr class="excel-header-row bg-slate-50 text-slate-700">
                                    <th rowspan="2" @click="handleSort('no')" class="w-12 text-center cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'no' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan No">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <span>No</span>
                                            <span v-if="sortKey === 'no'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('no', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th rowspan="2" @click="handleSort('pptk_name')" class="min-w-[170px] text-left cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'pptk_name' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Nama PPTK (A-Z / Z-A)">
                                        <div class="flex items-center justify-between gap-1.5">
                                            <span>PPTK</span>
                                            <span v-if="sortKey === 'pptk_name'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('pptk_name', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th @click="handleSort('debet')" class="min-w-[120px] text-right cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'debet' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Debet">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>Debet (Rp)</span>
                                            <span v-if="sortKey === 'debet'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('debet', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th @click="handleSort('panjar')" class="min-w-[120px] text-right cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'panjar' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Panjar">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>Panjar (Rp)</span>
                                            <span v-if="sortKey === 'panjar'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('panjar', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th @click="handleSort('jml')" class="min-w-[120px] text-right cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'jml' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Jumlah">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>Jml (Rp)</span>
                                            <span v-if="sortKey === 'jml'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('jml', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th @click="handleSort('spj')" class="min-w-[120px] text-right cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'spj' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan SPJ">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>SPJ (Rp)</span>
                                            <span v-if="sortKey === 'spj'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('spj', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th @click="handleSort('total_hutang_panjar')" class="min-w-[130px] text-right cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'total_hutang_panjar' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Hutang Panjar">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>Hutang Panjar (Rp)</span>
                                            <span v-if="sortKey === 'total_hutang_panjar'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('total_hutang_panjar', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th @click="handleSort('bank')" class="min-w-[110px] text-right cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'bank' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Bank">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>Bank (Rp)</span>
                                            <span v-if="sortKey === 'bank'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('bank', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th @click="handleSort('tunai')" class="min-w-[110px] text-right cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'tunai' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Tunai">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>Tunai (Rp)</span>
                                            <span v-if="sortKey === 'tunai'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('tunai', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th @click="handleSort('spj_lanjutan')" class="min-w-[120px] text-right cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'spj_lanjutan' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan SPJ Panjar Feb">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>SPJ Panjar Feb</span>
                                            <span v-if="sortKey === 'spj_lanjutan'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('spj_lanjutan', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th @click="handleSort('sisa_panjar')" class="min-w-[120px] text-right cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'sisa_panjar' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Sisa Panjar">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>Sisa Panjar</span>
                                            <span v-if="sortKey === 'sisa_panjar'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('sisa_panjar', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th @click="handleSort('total_sisa_riil')" class="min-w-[130px] text-right cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'total_sisa_riil' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Total Sisa Riil">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>Total Sisa Riil</span>
                                            <span v-if="sortKey === 'total_sisa_riil'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('total_sisa_riil', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th @click="handleSort('kontrol_selisih')" class="min-w-[110px] text-right text-emerald-800 cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'kontrol_selisih' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Kontrol Selisih">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>Kontrol Selisih</span>
                                            <span v-if="sortKey === 'kontrol_selisih'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('kontrol_selisih', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                </tr>
                                <tr class="bg-slate-100 text-[10px] font-mono text-slate-500">
                                    <th>2</th>
                                    <th>3</th>
                                    <th>4 (2+3)</th>
                                    <th>5</th>
                                    <th>6 (4-5)</th>
                                    <th>7 (ket 6)</th>
                                    <th>8 (ket 6)</th>
                                    <th>9</th>
                                    <th>10 (8-9)</th>
                                    <th>11 (7+10)</th>
                                    <th class="text-emerald-800">G - L (=0)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="item in paginatedRows" 
                                    :key="item.pptk_id"
                                    class="hover:bg-slate-50 font-sans"
                                >
                                    <td class="text-center font-mono text-slate-500">{{ item.no }}</td>
                                    <td class="font-semibold text-slate-800">
                                        <button @click="switchTab(item.pptk_name.replace(/B\s+|P\s+/g, '').replace('/', '-'))" class="hover:text-emerald-700 hover:underline text-left cursor-pointer">
                                            {{ item.pptk_name }}
                                        </button>
                                    </td>
                                    <td class="text-right font-mono">{{ formatRupiah(item.debet) }}</td>
                                    <td class="text-right font-mono">{{ formatRupiah(item.panjar) }}</td>
                                    <td class="text-right font-mono font-semibold bg-slate-50/70">{{ formatRupiah(item.jml) }}</td>
                                    <td class="text-right font-mono text-emerald-700 font-semibold">{{ formatRupiah(item.spj) }}</td>
                                    <td class="text-right font-mono text-amber-700 font-semibold">{{ formatRupiah(item.total_hutang_panjar) }}</td>
                                    <td class="text-right font-mono text-slate-600">{{ formatRupiah(item.bank) }}</td>
                                    <td class="text-right font-mono text-slate-600">{{ formatRupiah(item.tunai) }}</td>
                                    <td class="text-right font-mono text-slate-600">{{ formatRupiah(item.spj_lanjutan) }}</td>
                                    <td class="text-right font-mono text-slate-600">{{ formatRupiah(item.sisa_panjar) }}</td>
                                    <td class="text-right font-mono font-semibold">{{ formatRupiah(item.total_sisa_riil) }}</td>
                                    <td 
                                        :class="[
                                            'text-right font-mono font-bold',
                                            Math.abs(item.kontrol_selisih) < 1 ? 'text-emerald-700 bg-emerald-50/50' : 'text-rose-700 bg-rose-50'
                                        ]"
                                    >
                                        {{ formatRupiah(item.kontrol_selisih) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="excel-total-row bg-slate-100 font-bold">
                                    <td colspan="2" class="text-center">JUMLAH</td>
                                    <td class="text-right font-mono">{{ formatRupiah(sheet_data.totals?.debet) }}</td>
                                    <td class="text-right font-mono">{{ formatRupiah(sheet_data.totals?.panjar) }}</td>
                                    <td class="text-right font-mono">{{ formatRupiah(sheet_data.totals?.jml) }}</td>
                                    <td class="text-right font-mono text-emerald-800">{{ formatRupiah(sheet_data.totals?.spj) }}</td>
                                    <td class="text-right font-mono text-amber-800">{{ formatRupiah(sheet_data.totals?.total_hutang_panjar) }}</td>
                                    <td class="text-right font-mono">{{ formatRupiah(sheet_data.totals?.bank) }}</td>
                                    <td class="text-right font-mono">{{ formatRupiah(sheet_data.totals?.tunai) }}</td>
                                    <td class="text-right font-mono">-</td>
                                    <td class="text-right font-mono">-</td>
                                    <td class="text-right font-mono">{{ formatRupiah(sheet_data.totals?.total_sisa_riil) }}</td>
                                    <td class="text-right font-mono text-emerald-800 bg-emerald-50">
                                        {{ formatRupiah(sheet_data.totals?.kontrol_selisih) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- ================= VIEW 2: STANDARD BKU (UP / KK / PPTK SHEETS) ================= -->
                    <div v-else-if="sheet_data.rows && active_tab !== 'Jml Deb Rek' && active_tab !== 'Deb Rek' && !active_tab.startsWith('Deb')">
                        <table class="w-full excel-table border border-slate-300">
                            <thead>
                                <tr class="excel-header-row bg-slate-50 text-slate-700">
                                    <th @click="handleSort('is_verified')" class="w-12 text-center cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'is_verified' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" rowspan="2" title="Urutkan berdasarkan status verifikasi">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <span>Status</span>
                                            <span v-if="sortKey === 'is_verified'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('is_verified', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th v-if="columnVisibility.no?.visible" @click="handleSort('no')" rowspan="2" class="w-12 text-center cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'no' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan No. Urut (1-9 atau 9-1)">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <span>NO. URUT</span>
                                            <span v-if="sortKey === 'no'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('no', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th v-if="columnVisibility.tanggal?.visible" @click="handleSort('tanggal')" rowspan="2" class="w-28 text-center cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'tanggal' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Tanggal (Terbaru ke Terlama / Terlama ke Terbaru)">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <span>TANGGAL</span>
                                            <span v-if="sortKey === 'tanggal'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('tanggal', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th v-if="columnVisibility.uraian?.visible" @click="handleSort('uraian')" rowspan="2" class="min-w-[320px] text-left cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'uraian' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Uraian (A - Z atau Z - A)">
                                        <div class="flex items-center justify-between gap-1.5">
                                            <span>URAIAN</span>
                                            <span v-if="sortKey === 'uraian'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('uraian', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th v-if="columnVisibility.penerimaan?.visible" @click="handleSort('penerimaan')" class="min-w-[130px] text-right cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'penerimaan' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Penerimaan (Besar ke Kecil / Kecil ke Besar)">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>PENERIMAAN</span>
                                            <span v-if="sortKey === 'penerimaan'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('penerimaan', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th v-if="columnVisibility.pengeluaran?.visible" @click="handleSort('pengeluaran')" class="min-w-[130px] text-right cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'pengeluaran' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Pengeluaran (Besar ke Kecil / Kecil ke Besar)">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>PENGELUARAN</span>
                                            <span v-if="sortKey === 'pengeluaran'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('pengeluaran', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th v-if="columnVisibility.saldo?.visible" @click="handleSort('saldo')" class="min-w-[130px] text-right cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'saldo' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Saldo (Besar ke Kecil / Kecil ke Besar)">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>SALDO</span>
                                            <span v-if="sortKey === 'saldo'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('saldo', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    
                                    <!-- Columns for individual PPTK sheets -->
                                    <th v-if="sheet_data.rows[0]?.debet !== undefined" @click="handleSort('debet')" class="min-w-[110px] bg-slate-100 text-right cursor-pointer select-none hover:bg-slate-200 transition-colors group" :class="sortKey === 'debet' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Debet">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>Debet</span>
                                            <span v-if="sortKey === 'debet'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('debet', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th v-if="sheet_data.rows[0]?.panjar !== undefined" @click="handleSort('panjar')" class="min-w-[110px] bg-slate-100 text-right cursor-pointer select-none hover:bg-slate-200 transition-colors group" :class="sortKey === 'panjar' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Panjar">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>Panjar</span>
                                            <span v-if="sortKey === 'panjar'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('panjar', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th v-if="sheet_data.rows[0]?.spj !== undefined" @click="handleSort('spj')" class="min-w-[110px] bg-slate-100 text-right cursor-pointer select-none hover:bg-slate-200 transition-colors group" :class="sortKey === 'spj' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan SPJ">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>SPJ</span>
                                            <span v-if="sortKey === 'spj'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('spj', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    <th v-if="sheet_data.rows[0]?.jml !== undefined" @click="handleSort('jml')" class="min-w-[110px] bg-slate-100 text-right cursor-pointer select-none hover:bg-slate-200 transition-colors group" :class="sortKey === 'jml' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Jml">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>Jml</span>
                                            <span v-if="sortKey === 'jml'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('jml', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>

                                    <!-- Memo / Catatan Internal Column Header -->
                                    <th v-if="columnVisibility.memo?.visible" @click="handleSort('memo')" rowspan="2" class="text-left min-w-[180px] font-bold bg-amber-50/70 text-amber-950 border-amber-300 cursor-pointer select-none hover:bg-amber-100/70 transition-colors group" :class="sortKey === 'memo' ? 'bg-amber-100 text-amber-950 font-black ring-1 ring-inset ring-amber-400' : ''" title="Urutkan Catatan Memo (A-Z atau Z-A)">
                                        <div class="flex items-center justify-between gap-1.5">
                                            <div class="flex items-center gap-1">
                                                <span>Memo / Oret-oret</span>
                                                <span class="text-[9px] text-amber-700 font-normal bg-amber-100 px-1 rounded">(Klik sel)</span>
                                            </div>
                                            <span v-if="sortKey === 'memo'" class="px-1.5 py-0.5 rounded-md bg-amber-700 text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('memo', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-amber-200 text-amber-800 group-hover:bg-amber-700 group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>

                                    <!-- Dynamic Custom Columns Headers -->
                                    <th 
                                        v-for="col in customColumns" 
                                        :key="'th-bku-'+col"
                                        @click="handleSort(col)"
                                        rowspan="2"
                                        class="text-left min-w-[150px] font-bold bg-emerald-50/70 text-emerald-950 border-emerald-300 cursor-pointer select-none hover:bg-emerald-100/70 transition-colors group"
                                        :class="sortKey === col ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''"
                                        :title="`Urutkan kolom ${col}`"
                                    >
                                        <div class="flex items-center justify-between gap-1.5">
                                            <span>{{ col }}</span>
                                            <div class="flex items-center gap-1">
                                                <span v-if="sortKey === col" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                    <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                    <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                    <span>{{ sortOrder === 'asc' ? 'A-Z' : 'Z-A' }}</span>
                                                </span>
                                                <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                    <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                                </span>
                                                <button @click.stop="removeCustomColumn(customColumns.indexOf(col))" class="text-slate-400 hover:text-rose-600 cursor-pointer p-0.5 rounded ml-1" title="Hapus kolom ini">
                                                    <X class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    </th>

                                    <th class="w-20 text-center print:hidden" rowspan="2">Aksi</th>
                                </tr>
                                <tr class="bg-slate-100 text-[10px] font-mono text-slate-500">
                                    <th v-if="columnVisibility.penerimaan?.visible">4</th>
                                    <th v-if="columnVisibility.pengeluaran?.visible">5</th>
                                    <th v-if="columnVisibility.saldo?.visible">6</th>
                                    <th v-if="sheet_data.rows[0]?.debet !== undefined">H</th>
                                    <th v-if="sheet_data.rows[0]?.panjar !== undefined">I</th>
                                    <th v-if="sheet_data.rows[0]?.spj !== undefined">J</th>
                                    <th v-if="sheet_data.rows[0]?.jml !== undefined">K</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="r in paginatedRows" 
                                    :key="r.id"
                                    :id="'bku-row-' + r.id"
                                    :class="[
                                        highlightedRowId === r.id ? 'bg-amber-100 ring-2 ring-amber-400 font-semibold' : (duplicateRowMap.has(r.id) ? 'bg-amber-50/70' : ''),
                                        'hover:bg-slate-50 font-sans transition-all duration-300'
                                    ]"
                                >
                                    <!-- Status Verification Toggle Cell -->
                                    <td class="text-center">
                                        <button 
                                            @click="toggleVerified(r)"
                                            class="inline-flex items-center justify-center p-1 rounded-md transition-transform hover:scale-110 cursor-pointer"
                                            :title="r.is_verified ? 'Status: Terverifikasi (Klik untuk ubah jadi belum dicek)' : 'Status: Belum Dicek (Klik untuk tandai terverifikasi)'"
                                        >
                                            <CheckCircle2 v-if="r.is_verified" class="w-3.5 h-3.5 text-emerald-600" />
                                            <Clock v-else class="w-3.5 h-3.5 text-amber-500" />
                                        </button>
                                    </td>

                                    <td v-if="columnVisibility.no?.visible" class="text-center font-mono text-slate-600">
                                        <span>{{ r.no }}</span>
                                        <span 
                                            v-if="duplicateRowMap.has(r.id)" 
                                            class="ml-1 inline-flex items-center gap-0.5 px-1 py-0.2 rounded bg-amber-100 text-amber-900 border border-amber-300 text-[9px] font-bold"
                                            title="Potensi dobel: terdapat transaksi lain dengan tanggal & nominal yang persis sama"
                                        >
                                            <AlertTriangle class="w-2.5 h-2.5 text-amber-600" /> Dobel
                                        </span>
                                    </td>
                                    <td v-if="columnVisibility.tanggal?.visible" class="text-center font-mono text-slate-700 whitespace-nowrap">{{ r.tanggal }}</td>
                                    <td v-if="columnVisibility.uraian?.visible" class="text-slate-900">{{ r.uraian }}</td>
                                    <td v-if="columnVisibility.penerimaan?.visible" class="text-right font-mono text-emerald-800">{{ r.penerimaan > 0 ? formatRupiah(r.penerimaan) : '-' }}</td>
                                    <td v-if="columnVisibility.pengeluaran?.visible" class="text-right font-mono text-rose-800">{{ r.pengeluaran > 0 ? formatRupiah(r.pengeluaran) : '-' }}</td>
                                    
                                    <!-- Saldo Cell with Anomaly/Negative Highlight -->
                                    <td v-if="columnVisibility.saldo?.visible" class="text-right font-mono font-semibold">
                                        <span 
                                            v-if="r.saldo < 0" 
                                            class="inline-flex items-center gap-1 text-rose-700 bg-rose-50 px-1.5 py-0.5 rounded border border-rose-200"
                                            title="Peringatan: Saldo negatif!"
                                        >
                                            <AlertTriangle class="w-3 h-3 text-rose-600" />
                                            {{ formatRupiah(r.saldo) }}
                                        </span>
                                        <span v-else>
                                            {{ formatRupiah(r.saldo) }}
                                        </span>
                                    </td>
                                    
                                    <td v-if="r.debet !== undefined" class="text-right font-mono">{{ r.debet > 0 ? formatRupiah(r.debet) : '-' }}</td>
                                    <td v-if="r.panjar !== undefined" class="text-right font-mono">{{ r.panjar !== 0 ? formatRupiah(r.panjar) : '-' }}</td>
                                    <td v-if="r.spj !== undefined" class="text-right font-mono text-emerald-700">{{ r.spj > 0 ? formatRupiah(r.spj) : '-' }}</td>
                                    <td v-if="r.jml !== undefined" class="text-right font-mono font-semibold">{{ formatRupiah(r.jml) }}</td>

                                    <!-- Memo / Catatan Internal Cell (Click to Inline Edit) -->
                                    <td 
                                        v-if="columnVisibility.memo?.visible" 
                                        class="text-xs p-1 cursor-pointer bg-amber-50/20 hover:bg-amber-50/60 transition-colors relative"
                                        @click="startEditCell(r, 'memo', r.notes)"
                                        :title="isPeriodLocked ? 'Terkunci' : 'Klik untuk mengisi / mengedit memo baris ini'"
                                    >
                                        <div v-if="editingCell.id === r.id && editingCell.field === 'memo'" class="flex items-center gap-1">
                                            <input 
                                                v-model="cellDraftValue" 
                                                @blur="saveCellDraft(r, 'memo')"
                                                @keydown.enter="saveCellDraft(r, 'memo')"
                                                @keydown.esc="cancelEditCell"
                                                v-focus
                                                type="text" 
                                                class="w-full text-xs px-2 py-0.5 bg-white border border-amber-400 rounded focus:outline-none focus:ring-1 focus:ring-amber-500 text-slate-900 shadow-xs"
                                                placeholder="Tulis catatan/memo..."
                                            />
                                        </div>
                                        <div v-else class="flex items-center justify-between min-h-[22px] px-1 group">
                                            <span :class="r.notes ? 'text-slate-800 font-medium' : 'text-slate-400 italic text-[11px] group-hover:text-amber-800'">
                                                {{ r.notes || '+ tulis memo' }}
                                            </span>
                                            <span v-if="savingStatus[`${r.id}_memo`] === 'saved'" class="text-emerald-600 text-[10px] font-bold">✓ Tersimpan</span>
                                            <span v-else-if="savingStatus[`${r.id}_memo`] === 'saving'" class="text-amber-600 text-[10px]">Menyimpan...</span>
                                        </div>
                                    </td>

                                    <!-- Dynamic Custom Column Cells (Click to Inline Edit) -->
                                    <td 
                                        v-for="col in customColumns" 
                                        :key="'td-bku-'+r.id+'-'+col"
                                        class="text-xs p-1 cursor-pointer bg-emerald-50/20 hover:bg-emerald-50/60 transition-colors relative"
                                        @click="startEditCell(r, col, r.custom_fields?.[col])"
                                        :title="isPeriodLocked ? 'Terkunci' : `Klik untuk mengedit ${col}`"
                                    >
                                        <div v-if="editingCell.id === r.id && editingCell.field === col" class="flex items-center gap-1">
                                            <input 
                                                v-model="cellDraftValue" 
                                                @blur="saveCellDraft(r, col)"
                                                @keydown.enter="saveCellDraft(r, col)"
                                                @keydown.esc="cancelEditCell"
                                                v-focus
                                                type="text" 
                                                class="w-full text-xs px-2 py-0.5 bg-white border border-emerald-400 rounded focus:outline-none focus:ring-1 focus:ring-emerald-500 text-slate-900 shadow-xs"
                                                :placeholder="`Isi ${col}...`"
                                            />
                                        </div>
                                        <div v-else class="flex items-center justify-between min-h-[22px] px-1 group">
                                            <span :class="r.custom_fields?.[col] ? 'text-slate-800 font-medium' : 'text-slate-400 italic text-[11px] group-hover:text-emerald-800'">
                                                {{ r.custom_fields?.[col] || '+ isi' }}
                                            </span>
                                            <span v-if="savingStatus[`${r.id}_${col}`] === 'saved'" class="text-emerald-600 text-[10px] font-bold">✓ Tersimpan</span>
                                            <span v-else-if="savingStatus[`${r.id}_${col}`] === 'saving'" class="text-amber-600 text-[10px]">Menyimpan...</span>
                                        </div>
                                    </td>

                                    <td class="text-center whitespace-nowrap print:hidden">
                                        <div class="flex items-center justify-center gap-1">
                                            <!-- Audit Trail Button -->
                                            <button 
                                                @click="openAuditModal(r)"
                                                class="text-slate-400 hover:text-blue-600 p-0.5 rounded transition-colors cursor-pointer"
                                                title="Lihat riwayat perubahan & audit log"
                                            >
                                                <History class="w-3.5 h-3.5" />
                                            </button>

                                            <!-- Edit Button -->
                                            <button 
                                                v-if="!isPeriodLocked"
                                                @click="openEditModal(r)"
                                                class="text-slate-400 hover:text-emerald-700 p-0.5 rounded transition-colors cursor-pointer"
                                                title="Edit nominal / transaksi"
                                            >
                                                <Edit2 class="w-3.5 h-3.5" />
                                            </button>

                                            <!-- Delete Button -->
                                            <button 
                                                v-if="!isPeriodLocked"
                                                @click="deleteTransaction(r.id)"
                                                class="text-slate-400 hover:text-rose-600 p-0.5 rounded transition-colors cursor-pointer"
                                                title="Hapus baris"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" />
                                            </button>

                                            <!-- Lock Indicator -->
                                            <span v-if="isPeriodLocked" class="text-slate-300 p-0.5" title="Terkunci (Mode Baca Saja)">
                                                <Lock class="w-3.5 h-3.5" />
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="excel-total-row bg-slate-100 font-bold">
                                    <td :colspan="(columnVisibility.no?.visible ? 1 : 0) + (columnVisibility.tanggal?.visible ? 1 : 0) + (columnVisibility.uraian?.visible ? 1 : 0) + 1" class="text-center font-bold">TOTAL MUTASI</td>
                                    <td v-if="columnVisibility.penerimaan?.visible" class="text-right font-mono text-emerald-900">{{ formatRupiah(sheet_data.total_penerimaan || sheet_data.summary?.total_spj) }}</td>
                                    <td v-if="columnVisibility.pengeluaran?.visible" class="text-right font-mono text-rose-900">{{ formatRupiah(sheet_data.total_pengeluaran || sheet_data.summary?.total_panjar) }}</td>
                                    <td v-if="columnVisibility.saldo?.visible" class="text-right font-mono">{{ formatRupiah(sheet_data.saldo_akhir || sheet_data.summary?.sisa_kewajiban) }}</td>
                                    <td v-if="sheet_data.rows[0]?.debet !== undefined" class="text-right font-mono">{{ formatRupiah(sheet_data.summary?.total_debet) }}</td>
                                    <td v-if="sheet_data.rows[0]?.panjar !== undefined" class="text-right font-mono">{{ formatRupiah(sheet_data.summary?.total_panjar) }}</td>
                                    <td v-if="sheet_data.rows[0]?.spj !== undefined" class="text-right font-mono">{{ formatRupiah(sheet_data.summary?.total_spj) }}</td>
                                    <td v-if="sheet_data.rows[0]?.jml !== undefined" class="text-right font-mono">{{ formatRupiah(sheet_data.summary?.sisa_kewajiban) }}</td>
                                    <td v-if="columnVisibility.memo?.visible"></td>
                                    <td v-for="col in customColumns" :key="'tf-bku-'+col"></td>
                                    <td class="print:hidden"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- ================= VIEW 3: MATRIKS JML DEB REK ================= -->
                    <div v-else-if="active_tab === 'Jml Deb Rek'">
                        <h3 class="text-xs font-bold text-slate-800 uppercase mb-2">1. Bagian Debet Rekening (Toko)</h3>
                        <table class="w-full excel-table border border-slate-300 mb-6">
                            <thead>
                                <tr class="excel-header-row bg-slate-50 text-slate-700">
                                    <th class="text-left min-w-[150px]">PPTK</th>
                                    <th v-for="m in 12" :key="'d-head-'+m" class="min-w-[85px]">{{ sheet_data.month_names?.[m] || m }}</th>
                                    <th class="min-w-[110px] bg-slate-200">Total Setahun</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, pptkId) in sheet_data.debet" :key="'d-'+pptkId">
                                    <td class="font-semibold text-slate-800">{{ row.pptk_name }}</td>
                                    <td v-for="m in 12" :key="'d-val-'+pptkId+'-'+m" class="text-right font-mono">
                                        {{ row[m] !== 0 ? formatRupiah(row[m]) : '-' }}
                                    </td>
                                    <td class="text-right font-mono font-bold bg-slate-50">{{ formatRupiah(row.total) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <h3 class="text-xs font-bold text-slate-800 uppercase mb-2">2. Bagian Panjar Tunai</h3>
                        <table class="w-full excel-table border border-slate-300">
                            <thead>
                                <tr class="excel-header-row bg-slate-50 text-slate-700">
                                    <th class="text-left min-w-[150px]">PPTK</th>
                                    <th v-for="m in 12" :key="'p-head-'+m" class="min-w-[85px]">{{ sheet_data.month_names?.[m] || m }}</th>
                                    <th class="min-w-[110px] bg-slate-200">Total Setahun</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, pptkId) in sheet_data.panjar" :key="'p-'+pptkId">
                                    <td class="font-semibold text-slate-800">{{ row.pptk_name }}</td>
                                    <td v-for="m in 12" :key="'p-val-'+pptkId+'-'+m" class="text-right font-mono">
                                        {{ row[m] !== 0 ? formatRupiah(row[m]) : '-' }}
                                    </td>
                                    <td class="text-right font-mono font-bold bg-slate-50">{{ formatRupiah(row.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ================= VIEW 4: DEBET REKENING INDUK (DEB REK) ================= -->
                    <div v-else-if="active_tab === 'Deb Rek'">
                        <table class="w-full excel-table border border-slate-300">
                            <thead>
                                <tr class="excel-header-row bg-slate-50 text-slate-700">
                                    <th rowspan="2" class="w-10">NO</th>
                                    <th rowspan="2" class="w-24">TANGGAL</th>
                                    <th rowspan="2" class="min-w-[280px] text-left">URAIAN</th>
                                    <th rowspan="2" class="min-w-[110px]">PENERIMAAN</th>
                                    <th rowspan="2" class="min-w-[110px]">PENGELUARAN</th>
                                    <th rowspan="2" class="min-w-[110px]">SALDO</th>
                                    <th :colspan="sheet_data.pptks?.length || 9" class="bg-slate-200 text-center">ALOKASI PPTK</th>
                                    <th rowspan="2" class="w-16 print:hidden">AKSI</th>
                                </tr>
                                <tr class="excel-header-row text-[10px] bg-slate-100 text-slate-600">
                                    <th v-for="p in sheet_data.pptks" :key="'h-p-'+p.id" class="min-w-[100px]">
                                        {{ p.display_name }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="r in paginatedRows" 
                                    :key="r.id"
                                    :class="[
                                        r.is_contra ? 'bg-slate-50 italic text-slate-500' : 'bg-white text-slate-900',
                                        'hover:bg-blue-50/40'
                                    ]"
                                >
                                    <td class="text-center font-mono">{{ r.no }}</td>
                                    <td class="text-center font-mono">{{ r.tanggal }}</td>
                                    <td :class="r.is_contra ? 'pl-6 font-medium text-blue-900' : ''">{{ r.uraian }}</td>
                                    <td class="text-right font-mono">{{ r.penerimaan > 0 ? formatRupiah(r.penerimaan) : '-' }}</td>
                                    <td class="text-right font-mono">{{ r.pengeluaran > 0 ? formatRupiah(r.pengeluaran) : '-' }}</td>
                                    <td class="text-right font-mono font-semibold">{{ formatRupiah(r.saldo) }}</td>

                                    <td 
                                        v-for="p in sheet_data.pptks" 
                                        :key="'col-'+p.id+'-'+r.id"
                                        class="text-right font-mono"
                                    >
                                        {{ r.pptk_cols?.[p.id] > 0 ? formatRupiah(r.pptk_cols[p.id]) : '-' }}
                                    </td>
                                    <td class="text-center whitespace-nowrap print:hidden">
                                        <div v-if="!r.is_contra && r.id && !String(r.id).includes('contra')" class="flex items-center justify-center gap-1">
                                            <button 
                                                @click="openEditModal(r)"
                                                class="text-slate-400 hover:text-blue-600 p-0.5 rounded transition-colors cursor-pointer"
                                                title="Edit nominal debet rekening"
                                            >
                                                <Edit2 class="w-3.5 h-3.5" />
                                            </button>
                                            <button 
                                                @click="deleteTransaction(r.id)"
                                                class="text-slate-400 hover:text-rose-600 p-0.5 rounded transition-colors cursor-pointer"
                                                title="Hapus baris"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ================= VIEW 5: BKU RESES KAS UMUM ================= -->
                    <div v-else-if="sheet_data.type === 'bku_reses' || (active_tab.startsWith('Reses') && !active_tab.startsWith('Deb'))" class="space-y-6">
                        <!-- Main BKU Reses Table -->
                        <div class="border border-slate-300 rounded overflow-hidden">
                            <table class="w-full excel-table border-collapse">
                                <thead>
                                    <tr class="excel-header-row bg-slate-50 text-slate-700">
                                        <th class="w-12 text-center">NO</th>
                                        <th class="w-28 text-center">TANGGAL</th>
                                        <th class="min-w-[320px] text-left">URAIAN TRANSAKSI</th>
                                        <th class="min-w-[140px] text-right">PENERIMAAN (Rp)</th>
                                        <th class="min-w-[140px] text-right">PENGELUARAN (Rp)</th>
                                        <th class="min-w-[150px] text-right">SALDO (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="r in paginatedRows" :key="r.id || r.no" class="hover:bg-slate-50">
                                        <td class="text-center font-mono text-slate-500">{{ r.no }}</td>
                                        <td class="text-center font-mono text-slate-700">{{ r.tanggal }}</td>
                                        <td class="font-medium text-slate-900">{{ r.uraian }}</td>
                                        <td class="text-right font-mono text-emerald-800">
                                            {{ Number(r.penerimaan) > 0 ? formatRupiah(r.penerimaan) : '-' }}
                                        </td>
                                        <td class="text-right font-mono text-slate-800">
                                            {{ Number(r.pengeluaran) > 0 ? formatRupiah(r.pengeluaran) : '-' }}
                                        </td>
                                        <td class="text-right font-mono font-bold text-slate-900">
                                            {{ formatRupiah(r.saldo) }}
                                        </td>
                                    </tr>
                                    <tr v-if="paginatedRows.length === 0">
                                        <td colspan="6" class="text-center py-6 text-slate-400">Tidak ada data transaksi BKU Reses</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="excel-total-row bg-slate-100 font-bold border-t-2 border-slate-300">
                                        <td colspan="3" class="text-center">TOTAL KAS UMUM RESES</td>
                                        <td class="text-right font-mono text-emerald-900">
                                            {{ formatRupiah(sheet_data.summary?.total_penerimaan) }}
                                        </td>
                                        <td class="text-right font-mono text-slate-900">
                                            {{ formatRupiah(sheet_data.summary?.total_pengeluaran) }}
                                        </td>
                                        <td class="text-right font-mono text-emerald-950 font-black">
                                            {{ formatRupiah(sheet_data.summary?.saldo_akhir) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Rekapitulasi Alokasi Dana & Sisa Kasda Reses (Kolom I - L) -->
                        <div v-if="sheet_data.rekap_kasda && sheet_data.rekap_kasda.length > 0" class="bg-slate-50/80 border border-slate-300 rounded-xl p-4 max-w-3xl">
                            <div class="flex items-center gap-2 mb-3 pb-2 border-b border-slate-200">
                                <Landmark class="w-4 h-4 text-emerald-700" />
                                <h4 class="font-bold text-xs text-slate-900 uppercase tracking-wider">
                                    Rekapitulasi Alokasi Dana &amp; Sisa Kasda Reses (Kolom I - L)
                                </h4>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs excel-table border border-slate-300 bg-white">
                                    <thead>
                                        <tr class="bg-emerald-800 text-white font-bold">
                                            <th class="py-2 px-3 text-left">KATEGORI BELANJA</th>
                                            <th class="py-2 px-3 text-right">PAGU SP2D (Rp)</th>
                                            <th class="py-2 px-3 text-right">REALISASI SPJ (Rp)</th>
                                            <th class="py-2 px-3 text-right">SISA KASDA (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr 
                                            v-for="(k, idx) in sheet_data.rekap_kasda.filter(x => x.kategori || x.sp2d || x.kasda)" 
                                            :key="idx"
                                            :class="k.kategori && k.kategori.includes('SETOR') ? 'bg-amber-50 font-bold' : 'hover:bg-slate-50'"
                                        >
                                            <td class="py-1.5 px-3 font-semibold text-slate-800">{{ k.kategori || '-' }}</td>
                                            <td class="py-1.5 px-3 text-right font-mono">
                                                {{ Number(k.sp2d) > 0 ? formatRupiah(k.sp2d) : '-' }}
                                            </td>
                                            <td class="py-1.5 px-3 text-right font-mono">
                                                {{ Number(k.spj) > 0 ? formatRupiah(k.spj) : '-' }}
                                            </td>
                                            <td class="py-1.5 px-3 text-right font-mono font-bold" :class="Number(k.kasda) > 0 ? 'text-rose-700' : 'text-slate-500'">
                                                {{ Number(k.kasda) > 0 ? formatRupiah(k.kasda) : '-' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ================= VIEW 6: DEBET RESES & RINCIAN PAJAK ================= -->
                    <div v-else-if="sheet_data.type === 'deb_reses' || active_tab.startsWith('Deb')" class="space-y-4">
                        <!-- Sub-tab Navigation Bar: Bagian 1, Bagian 2, Bagian 3 -->
                        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 pb-2.5 print:hidden">
                            <!-- Tab Sewa Tempat & PPh 23 -->
                            <button
                                @click="debResesSubTab = 'sewa'"
                                :class="[
                                    'px-3.5 py-2 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-2 shadow-2xs',
                                    debResesSubTab === 'sewa'
                                        ? 'bg-emerald-700 text-white ring-2 ring-emerald-400'
                                        : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-300'
                                ]"
                            >
                                <Building class="w-3.5 h-3.5" />
                                <span>1. Sewa Tempat &amp; PPh 23 (2%)</span>
                                <span :class="debResesSubTab === 'sewa' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'" class="px-1.5 py-0.2 rounded-full text-[10px] font-mono">
                                    {{ sheet_data.sewa?.rows?.length || 50 }} Anggota
                                </span>
                            </button>

                            <!-- Tab Makan Minum Reses -->
                            <button
                                @click="debResesSubTab = 'mamin'"
                                :class="[
                                    'px-3.5 py-2 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-2 shadow-2xs',
                                    debResesSubTab === 'mamin'
                                        ? 'bg-emerald-700 text-white ring-2 ring-emerald-400'
                                        : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-300'
                                ]"
                            >
                                <Utensils class="w-3.5 h-3.5" />
                                <span>2. Makan Minum Reses</span>
                                <span :class="debResesSubTab === 'mamin' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'" class="px-1.5 py-0.2 rounded-full text-[10px] font-mono">
                                    {{ sheet_data.mamin?.rows?.length || 0 }} Anggota
                                </span>
                            </button>

                            <!-- Tab Tunjangan Reses & PPh 21 -->
                            <button
                                @click="debResesSubTab = 'tunjangan'"
                                :class="[
                                    'px-3.5 py-2 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-2 shadow-2xs',
                                    debResesSubTab === 'tunjangan'
                                        ? 'bg-emerald-700 text-white ring-2 ring-emerald-400'
                                        : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-300'
                                ]"
                            >
                                <Coins class="w-3.5 h-3.5" />
                                <span>3. Tunjangan Reses &amp; PPh 21</span>
                                <span :class="debResesSubTab === 'tunjangan' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'" class="px-1.5 py-0.2 rounded-full text-[10px] font-mono">
                                    {{ sheet_data.tunjangan?.rows?.length || 0 }} Anggota
                                </span>
                            </button>
                        </div>

                        <!-- Sub-tab 1: Table Sewa Tempat & PPh 23 (Full 50 Anggota Dewan) -->
                        <div v-if="debResesSubTab === 'sewa'">
                            <table class="w-full excel-table border border-slate-300">
                                <thead>
                                    <tr class="excel-header-row bg-slate-50 text-slate-700">
                                        <th rowspan="2" class="w-10 text-center">NO</th>
                                        <th rowspan="2" class="min-w-[260px] text-left">NAMA ANGGOTA DEWAN / URAIAN</th>
                                        <th colspan="3" class="text-center bg-emerald-50/70 text-emerald-950 font-bold border-b border-slate-300">
                                            PENARIKAN AWAL PANJAR
                                        </th>
                                        <th colspan="3" class="text-center bg-blue-50/70 text-blue-950 font-bold border-b border-slate-300">
                                            REALISASI SPJ
                                        </th>
                                        <th colspan="3" class="text-center bg-amber-50/70 text-amber-950 font-bold border-b border-slate-300">
                                            SETOR KASDA
                                        </th>
                                    </tr>
                                    <tr class="excel-header-row bg-slate-100/90 text-slate-700 text-[11px]">
                                        <th class="min-w-[100px] text-right">BRUTO</th>
                                        <th class="min-w-[90px] text-right text-rose-700">PPH 23 (2%)</th>
                                        <th class="min-w-[100px] text-right font-bold text-emerald-900">NETTO</th>
                                        <th class="min-w-[100px] text-right">SPJ BRUTO</th>
                                        <th class="min-w-[90px] text-right text-rose-700">PPH SPJ</th>
                                        <th class="min-w-[100px] text-right font-bold text-blue-900">NETTO SPJ</th>
                                        <th class="min-w-[95px] text-right">SETOR BRUTO</th>
                                        <th class="min-w-[85px] text-right text-rose-700">PPH SETOR</th>
                                        <th class="min-w-[95px] text-right font-bold text-amber-900">NETTO SETOR</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="r in paginatedRows" :key="r.id || r.no" class="hover:bg-slate-50">
                                        <td class="text-center font-mono text-slate-500">{{ r.no }}</td>
                                        <td class="font-medium text-slate-900">{{ r.uraian }}</td>
                                        <td class="text-right font-mono">{{ formatRupiah(r.bruto) }}</td>
                                        <td class="text-right font-mono text-rose-700">{{ Number(r.pajak) > 0 ? formatRupiah(r.pajak) : '-' }}</td>
                                        <td class="text-right font-mono font-semibold text-emerald-800">{{ formatRupiah(r.netto) }}</td>
                                        <td class="text-right font-mono">{{ Number(r.spj) > 0 ? formatRupiah(r.spj) : '-' }}</td>
                                        <td class="text-right font-mono text-rose-700">{{ Number(r.pph_spj) > 0 ? formatRupiah(r.pph_spj) : '-' }}</td>
                                        <td class="text-right font-mono font-semibold text-blue-800">{{ Number(r.netto_spj) > 0 ? formatRupiah(r.netto_spj) : '-' }}</td>
                                        <td class="text-right font-mono text-slate-600">{{ Number(r.setor) > 0 ? formatRupiah(r.setor) : '-' }}</td>
                                        <td class="text-right font-mono text-rose-700">{{ Number(r.pph_setor) > 0 ? formatRupiah(r.pph_setor) : '-' }}</td>
                                        <td class="text-right font-mono font-semibold text-amber-800">{{ Number(r.netto_setor) > 0 ? formatRupiah(r.netto_setor) : '-' }}</td>
                                    </tr>
                                    <tr v-if="paginatedRows.length === 0">
                                        <td colspan="11" class="text-center py-6 text-slate-400">Tidak ada baris data sewa tempat</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="excel-total-row bg-slate-100 font-bold border-t-2 border-slate-300">
                                        <td colspan="2" class="text-center">TOTAL SEWA &amp; PPH 23</td>
                                        <td class="text-right font-mono">{{ formatRupiah(sheet_data.sewa?.totals?.bruto) }}</td>
                                        <td class="text-right font-mono text-rose-800">{{ formatRupiah(sheet_data.sewa?.totals?.pajak) }}</td>
                                        <td class="text-right font-mono text-emerald-800">{{ formatRupiah(sheet_data.sewa?.totals?.netto) }}</td>
                                        <td class="text-right font-mono">{{ formatRupiah(sheet_data.sewa?.totals?.spj) }}</td>
                                        <td class="text-right font-mono text-rose-800">{{ formatRupiah(sheet_data.sewa?.totals?.pph_spj) }}</td>
                                        <td class="text-right font-mono text-blue-800">{{ formatRupiah(sheet_data.sewa?.totals?.netto_spj) }}</td>
                                        <td class="text-right font-mono">{{ formatRupiah(sheet_data.sewa?.totals?.setor) }}</td>
                                        <td class="text-right font-mono text-rose-800">{{ formatRupiah(sheet_data.sewa?.totals?.pph_setor) }}</td>
                                        <td class="text-right font-mono text-amber-800">{{ formatRupiah(sheet_data.sewa?.totals?.netto_setor) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Sub-tab 2: Table Makan Minum Reses -->
                        <div v-else-if="debResesSubTab === 'mamin'">
                            <table class="w-full excel-table border border-slate-300">
                                <thead>
                                    <tr class="excel-header-row bg-slate-50 text-slate-700">
                                        <th class="w-12 text-center">NO</th>
                                        <th class="min-w-[320px] text-left">NAMA ANGGOTA DEWAN / URAIAN MAKAN MINUM</th>
                                        <th class="min-w-[140px] text-right">BELANJA BRUTO (Rp)</th>
                                        <th class="min-w-[140px] text-right">NETTO (Rp)</th>
                                        <th class="min-w-[140px] text-right">REALISASI SPJ (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="r in paginatedRows" :key="r.id || r.no" class="hover:bg-slate-50">
                                        <td class="text-center font-mono text-slate-500">{{ r.no }}</td>
                                        <td class="font-medium text-slate-900">{{ r.uraian }}</td>
                                        <td class="text-right font-mono">{{ Number(r.bruto) > 0 ? formatRupiah(r.bruto) : '-' }}</td>
                                        <td class="text-right font-mono font-semibold text-emerald-800">{{ Number(r.netto) > 0 ? formatRupiah(r.netto) : '-' }}</td>
                                        <td class="text-right font-mono text-blue-800">{{ Number(r.spj) > 0 ? formatRupiah(r.spj) : '-' }}</td>
                                    </tr>
                                    <tr v-if="paginatedRows.length === 0">
                                        <td colspan="5" class="text-center py-6 text-slate-400">Tidak ada baris data makan minum</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Sub-tab 3: Table Tunjangan Reses & PPh 21 -->
                        <div v-else-if="debResesSubTab === 'tunjangan'">
                            <table class="w-full excel-table border border-slate-300">
                                <thead>
                                    <tr class="excel-header-row bg-slate-50 text-slate-700">
                                        <th class="w-12 text-center">NO</th>
                                        <th class="min-w-[320px] text-left">NAMA ANGGOTA DEWAN / URAIAN TUNJANGAN</th>
                                        <th class="min-w-[150px] text-right">TUNJANGAN BRUTO (Rp)</th>
                                        <th class="min-w-[150px] text-right text-rose-700">POTONGAN PPH 21 (Rp)</th>
                                        <th class="min-w-[150px] text-right font-bold text-emerald-900">NETTO DITERIMA (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="r in paginatedRows" :key="r.id || r.no" class="hover:bg-slate-50">
                                        <td class="text-center font-mono text-slate-500">{{ r.no }}</td>
                                        <td class="font-medium text-slate-900">{{ r.uraian }}</td>
                                        <td class="text-right font-mono">{{ Number(r.bruto) > 0 ? formatRupiah(r.bruto) : '-' }}</td>
                                        <td class="text-right font-mono text-rose-700 font-semibold">{{ Number(r.pajak) > 0 ? formatRupiah(r.pajak) : '-' }}</td>
                                        <td class="text-right font-mono font-bold text-emerald-800">{{ Number(r.netto) > 0 ? formatRupiah(r.netto) : '-' }}</td>
                                    </tr>
                                    <tr v-if="paginatedRows.length === 0">
                                        <td colspan="5" class="text-center py-6 text-slate-400">Tidak ada baris data tunjangan reses</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="excel-total-row bg-slate-100 font-bold border-t-2 border-slate-300">
                                        <td colspan="2" class="text-center">TOTAL TUNJANGAN RESES &amp; PPH 21</td>
                                        <td class="text-right font-mono">{{ formatRupiah(sheet_data.tunjangan?.totals?.bruto) }}</td>
                                        <td class="text-right font-mono text-rose-800">{{ formatRupiah(sheet_data.tunjangan?.totals?.pajak) }}</td>
                                        <td class="text-right font-mono text-emerald-800">{{ formatRupiah(sheet_data.tunjangan?.totals?.netto) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Table Pagination Controls -->
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs select-none bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 print:hidden no-print">
                    <!-- Left: Rows per page selector & Count info -->
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1.5">
                            <span class="text-slate-500 font-medium">Tampilkan:</span>
                            <div class="inline-flex rounded-md shadow-2xs">
                                <button
                                    v-for="size in [10, 20, 50, 100, 'all']"
                                    :key="size"
                                    @click="perPage = size"
                                    :class="[
                                        'px-2.5 py-1 text-xs font-semibold border first:rounded-l-md last:rounded-r-md -ml-px transition-colors cursor-pointer',
                                        perPage === size
                                            ? 'bg-[#107c41] border-[#107c41] text-white z-10'
                                            : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-100'
                                    ]"
                                >
                                    {{ size === 'all' ? 'Semua' : size }}
                                </button>
                            </div>
                        </div>

                        <span class="text-slate-300">|</span>

                        <span class="text-slate-600">
                            Menampilkan 
                            <strong class="text-slate-900 font-mono">{{ totalRowsCount === 0 ? 0 : (perPage === 'all' ? 1 : (currentPage - 1) * perPage + 1) }}</strong> - 
                            <strong class="text-slate-900 font-mono">{{ perPage === 'all' ? totalRowsCount : Math.min(currentPage * perPage, totalRowsCount) }}</strong> 
                            dari <strong class="text-slate-900 font-mono">{{ totalRowsCount }}</strong> data
                        </span>
                    </div>

                    <!-- Right: Page navigation buttons -->
                    <div v-if="perPage !== 'all' && totalPages > 1" class="flex items-center gap-1">
                        <button
                            @click="currentPage--"
                            :disabled="currentPage === 1"
                            class="px-2.5 py-1 bg-white hover:bg-slate-100 disabled:opacity-40 disabled:hover:bg-white text-slate-700 border border-slate-200 rounded-md font-medium text-xs transition-colors cursor-pointer disabled:cursor-not-allowed"
                        >
                            Sebelumnya
                        </button>

                        <template v-for="(p, idx) in displayedPages" :key="'p-' + idx">
                            <span v-if="p === '...'" class="px-2 text-slate-400">...</span>
                            <button
                                v-else
                                @click="currentPage = p"
                                :class="[
                                    'min-w-[28px] h-7 px-2 text-xs font-semibold rounded-md border transition-colors cursor-pointer',
                                    currentPage === p
                                        ? 'bg-[#107c41] border-[#107c41] text-white shadow-2xs'
                                        : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-100'
                                ]"
                            >
                                {{ p }}
                            </button>
                        </template>

                        <button
                            @click="currentPage++"
                            :disabled="currentPage === totalPages"
                            class="px-2.5 py-1 bg-white hover:bg-slate-100 disabled:opacity-40 disabled:hover:bg-white text-slate-700 border border-slate-200 rounded-md font-medium text-xs transition-colors cursor-pointer disabled:cursor-not-allowed"
                        >
                            Berikutnya
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

        <!-- Add Transaction Modal -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 print:hidden">
            <div class="bg-white rounded-lg shadow-xl border border-slate-300 max-w-lg w-full overflow-hidden">
                <div class="px-4 py-3 bg-slate-900 text-white flex items-center justify-between">
                    <h3 class="text-sm font-bold flex items-center gap-2">
                        <Edit2 v-if="editingTx" class="w-4 h-4 text-emerald-400" />
                        <Plus v-else class="w-4 h-4 text-emerald-400" />
                        {{ editingTx ? 'Edit Nominal & Transaksi BKU' : 'Catat Transaksi BKU' }}
                    </h3>
                    <button @click="showAddModal = false" class="text-slate-400 hover:text-white cursor-pointer">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="saveTransaction" class="p-4 space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Tanggal</label>
                            <input v-model="form.transaction_date" type="date" required class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Nomor Dokumen/SP2D</label>
                            <input v-model="form.document_number" type="text" placeholder="Misal: 000026/LS/SETWAN" class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs" />
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Uraian Transaksi</label>
                        <textarea v-model="form.description" rows="2" required placeholder="Uraian belanja atau panjar..." class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Arah Arus Kas</label>
                            <select v-model="form.flow_direction" class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs">
                                <option value="disbursement">Pengeluaran (Debet)</option>
                                <option value="receipt">Penerimaan (Kredit)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Nominal (Rp)</label>
                            <input v-model="form.amount_net" type="number" step="1" required placeholder="0" class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs font-mono" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Metode Pembayaran</label>
                            <select v-model="form.payment_method" class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs">
                                <option value="bank_transfer">Transfer Bank (Giro)</option>
                                <option value="cash_tunai">Kas Tunai (Kas Kecil)</option>
                                <option value="debit_card_giro">Debet Toko Langsung</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">PPTK Penanggung Jawab</label>
                            <select v-model="form.pptk_id" class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs">
                                <option value="">-- Umum / Tanpa PPTK --</option>
                                <option v-for="p in pptks" :key="p.id" :value="p.id">{{ p.display_name }} ({{ p.name }})</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Keterangan Tambahan</label>
                        <input v-model="form.notes" type="text" placeholder="Catatan internal..." class="w-full px-2.5 py-1.5 border border-slate-300 rounded text-xs" />
                    </div>

                    <!-- Peringatan Potensi Dobel Real-time -->
                    <div v-if="duplicateWarning" class="p-2.5 bg-amber-50 border border-amber-300 rounded-lg text-amber-900 text-xs flex items-start gap-2">
                        <AlertTriangle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                        <div>
                            <div class="font-bold">Perhatian Potensi Transaksi Dobel:</div>
                            <div class="text-[11px] mt-0.5">Sudah ada transaksi pada tanggal <b>{{ duplicateWarning.tanggal }}</b> sebesar <b>{{ formatRupiah(Number(duplicateWarning.penerimaan) > 0 ? duplicateWarning.penerimaan : duplicateWarning.pengeluaran) }}</b> (<i>"{{ duplicateWarning.uraian }}"</i>). Pastikan ini bukan pencatatan ganda.</div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-200 flex justify-end gap-2">
                        <button type="button" @click="showAddModal = false" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded font-medium cursor-pointer">Batal</button>
                        <button type="submit" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-medium flex items-center gap-1.5 shadow-sm cursor-pointer">
                            <Check class="w-4 h-4" /> {{ editingTx ? 'Simpan Perubahan' : 'Simpan Transaksi' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: AUDIT TRAIL LOG ================= -->
        <div v-if="showAuditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-300 max-w-2xl w-full max-h-[85vh] flex flex-col overflow-hidden text-xs">
                <!-- Header with Setwan Theme -->
                <div class="px-5 py-4 bg-[#04331a] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-600/70 border border-emerald-400/30 flex items-center justify-center text-white shadow-xs">
                            <History class="w-5 h-5 text-emerald-200" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm tracking-tight text-white flex items-center gap-2">
                                <span>Riwayat Perubahan &amp; Audit Trail</span>
                                <span class="px-2 py-0.5 bg-emerald-800 text-emerald-200 text-[10px] font-mono rounded border border-emerald-700">
                                    ID #{{ activeAuditTx?.id }}
                                </span>
                            </h3>
                            <p class="text-[11px] text-emerald-200/80 mt-0.5">Catatan historis perubahan data transaksi BKU disusun memanjang ke bawah</p>
                        </div>
                    </div>
                    <button @click="showAuditModal = false" class="text-white/60 hover:text-white p-1.5 rounded-lg hover:bg-white/10 transition-colors cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Scrollable Body with Downward Timeline -->
                <div class="p-5 space-y-5 overflow-y-auto flex-1 select-text">
                    <!-- Current Context Header Card -->
                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2.5">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-2">
                            <div>
                                <span class="text-slate-400 font-bold block text-[10px] uppercase tracking-wider">Uraian Transaksi</span>
                                <div class="font-bold text-slate-900 text-sm mt-0.5">{{ activeAuditTx?.uraian }}</div>
                            </div>
                            <div class="sm:text-right shrink-0">
                                <span class="text-slate-400 font-bold block text-[10px] uppercase tracking-wider">Tanggal Transaksi</span>
                                <div class="font-mono font-bold text-slate-800 text-xs mt-0.5">{{ activeAuditTx?.tanggal }}</div>
                            </div>
                        </div>

                        <div class="pt-2.5 border-t border-slate-200/80 flex flex-wrap items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-4 font-mono">
                                <div v-if="activeAuditTx?.penerimaan || activeAuditTx?.debet">
                                    <span class="text-slate-500 font-sans text-[11px]">Penerimaan: </span>
                                    <span class="font-bold text-emerald-700">Rp {{ formatRupiah(activeAuditTx?.penerimaan || activeAuditTx?.debet) }}</span>
                                </div>
                                <div v-if="activeAuditTx?.pengeluaran || activeAuditTx?.panjar || activeAuditTx?.spj">
                                    <span class="text-slate-500 font-sans text-[11px]">Pengeluaran: </span>
                                    <span class="font-bold text-rose-700">Rp {{ formatRupiah(activeAuditTx?.pengeluaran || activeAuditTx?.panjar || activeAuditTx?.spj) }}</span>
                                </div>
                            </div>
                            <div>
                                <span 
                                    :class="activeAuditTx?.is_verified ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-amber-100 text-amber-800 border-amber-300'" 
                                    class="px-2.5 py-0.5 rounded-md text-[10px] font-sans font-bold border inline-flex items-center gap-1"
                                >
                                    <CheckCircle2 v-if="activeAuditTx?.is_verified" class="w-3 h-3" />
                                    <Clock v-else class="w-3 h-3" />
                                    <span>{{ activeAuditTx?.is_verified ? 'Terverifikasi' : 'Belum Dicek' }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div v-if="loadingAudit" class="text-center py-12 text-slate-400 space-y-2">
                        <Clock class="w-6 h-6 mx-auto animate-spin text-slate-300" />
                        <div class="font-medium">Memuat riwayat perubahan transaksi...</div>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="auditLogs.length === 0" class="text-center py-10 text-slate-400 bg-slate-50/50 rounded-xl border border-dashed border-slate-200 space-y-1">
                        <History class="w-6 h-6 mx-auto text-slate-300" />
                        <div class="font-medium text-slate-600">Belum Ada Riwayat Perubahan</div>
                        <div class="text-[11px] text-slate-400 italic">Transaksi ini belum pernah diubah secara manual atau diedit inline.</div>
                    </div>

                    <!-- Downward Timeline List -->
                    <div v-else class="relative pl-6 ml-3 border-l-2 border-emerald-300 space-y-6 py-2">
                        <div 
                            v-for="(log, idx) in auditLogs" 
                            :key="log.id || idx"
                            class="relative"
                        >
                            <!-- Timeline Dot Indicator -->
                            <span 
                                :class="formatAuditAction(log.action).dot" 
                                class="w-3.5 h-3.5 rounded-full absolute -left-[31px] top-3.5 ring-4 ring-white shadow-xs"
                            ></span>

                            <!-- Log Entry Card -->
                            <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden">
                                <!-- Card Header -->
                                <div class="px-4 py-2.5 bg-slate-50/90 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span 
                                            :class="formatAuditAction(log.action).color" 
                                            class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wider"
                                        >
                                            {{ formatAuditAction(log.action).label }}
                                        </span>
                                        <span class="font-bold text-slate-800 text-xs flex items-center gap-1.5">
                                            <User class="w-3.5 h-3.5 text-slate-400" />
                                            {{ log.user_name }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 font-mono flex items-center gap-1.5">
                                        <Clock class="w-3 h-3 text-slate-400" />
                                        <span>{{ log.created_at }}</span>
                                        <span v-if="log.created_at_human" class="text-slate-400">({{ log.created_at_human }})</span>
                                    </div>
                                </div>

                                <!-- Vertical Stacked Differences -->
                                <div class="p-3.5 space-y-2.5">
                                    <div 
                                        v-for="(diff, dIdx) in getAuditDiffList(log)" 
                                        :key="dIdx"
                                        class="p-2.5 bg-slate-50/80 rounded-lg border border-slate-200/80 text-xs"
                                    >
                                        <div class="font-bold text-slate-700 text-[11px] mb-1.5 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                            <span>{{ diff.label }}</span>
                                        </div>

                                        <!-- If Creation: Single Vertical Block -->
                                        <div v-if="diff.isNew" class="font-mono text-emerald-800 font-bold pl-3 text-[11px]">
                                            {{ diff.newVal }}
                                        </div>

                                        <!-- If Update: Before & After Memanjang ke Bawah -->
                                        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2 pl-3">
                                            <div class="bg-rose-50/70 border border-rose-200/80 rounded-md p-2">
                                                <div class="text-[10px] font-bold text-rose-600 uppercase mb-0.5 tracking-wider">Nilai Sebelumnya:</div>
                                                <div class="font-mono text-rose-800 line-through text-[11px] break-words">
                                                    {{ diff.oldVal }}
                                                </div>
                                            </div>
                                            <div class="bg-emerald-50/80 border border-emerald-200/80 rounded-md p-2">
                                                <div class="text-[10px] font-bold text-emerald-700 uppercase mb-0.5 tracking-wider flex items-center gap-1">
                                                    <ArrowRight class="w-3 h-3" />
                                                    <span>Diubah Menjadi:</span>
                                                </div>
                                                <div class="font-mono text-emerald-900 font-bold text-[11px] break-words">
                                                    {{ diff.newVal }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Fallback when no diff -->
                                    <div v-if="getAuditDiffList(log).length === 0" class="text-[11px] text-slate-500 italic p-2">
                                        Pencatatan aktivitas sistem tanpa rekaman perbedaan kolom.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer with Count & Close Button -->
                <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0">
                    <span class="text-slate-500 text-[11px]">
                        Total <strong>{{ auditLogs.length }}</strong> rekaman riwayat audit
                    </span>
                    <button @click="showAuditModal = false" class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold rounded-lg cursor-pointer transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

    </AppLayout>
</template>

<style>
@page {
    size: landscape;
    margin: 8mm 6mm;
}

@media print {
    /* Hide all browser / UI components */
    header,
    aside,
    footer,
    nav,
    .no-print,
    .print\:hidden,
    button,
    input,
    textarea,
    select,
    .excel-scroll::-webkit-scrollbar {
        display: none !important;
    }

    html, body, #app {
        height: auto !important;
        min-height: 0 !important;
        overflow: visible !important;
        background: #ffffff !important;
    }

    /* Table rules for landscape paper */
    .excel-table {
        width: 100% !important;
        max-width: 100% !important;
        border-collapse: collapse !important;
        font-size: 7pt !important;
        table-layout: auto !important;
    }

    .excel-table th,
    .excel-table td {
        border: 1px solid #1e293b !important;
        padding: 2.5px 3px !important;
        min-width: 0 !important;
        width: auto !important;
        color: #000000 !important;
    }

    .excel-table th {
        background-color: #f1f5f9 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        font-weight: 700 !important;
        text-align: center !important;
    }

    .excel-table td.font-mono {
        white-space: nowrap !important;
        font-size: 6.8pt !important;
        letter-spacing: -0.02em !important;
    }

    .excel-table thead {
        display: table-header-group !important;
    }

    .excel-table tfoot {
        display: table-footer-group !important;
    }

    .excel-total-row,
    .excel-total-row td {
        background-color: #f8fafc !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        font-weight: bold !important;
        border-top: 2px solid #000000 !important;
        border-bottom: 2px solid #000000 !important;
    }
}
</style>

