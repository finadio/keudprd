<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { 
    Plus, 
    Search, 
    Download, 
    Printer, 
    Trash2, 
    Edit2,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    SlidersHorizontal,
    X,
    Check,
    Lock,
    Unlock,
    AlertTriangle,
    Clock,
    History,
    CheckCircle2,
    AlertCircle,
    Calendar,
    Filter,
    User,
    ArrowRight,
    ArrowUpDown,
    ArrowUp,
    ArrowDown
} from 'lucide-vue-next';

// Custom directive to auto focus input
const vFocus = {
    mounted: (el) => el.focus()
};

const props = defineProps({
    year: Number,
    current_month: Number,
    month_tabs: Object,
    bank_data: Object,
    pptks: Array,
    period: Object,
    is_locked: Boolean,
});

const monthNames = {
    1: 'Januari',
    2: 'Februari',
    3: 'Maret',
    4: 'April',
    5: 'Mei',
    6: 'Juni',
    7: 'Juli',
    8: 'Agustus',
    9: 'September',
    10: 'Oktober',
    11: 'November',
    12: 'Desember',
};

// -------------------------------------------------------------
// 1. SEARCH, FILTER & QUICK PRESETS
// -------------------------------------------------------------
const searchQuery = ref('');
const showAddModal = ref(false);
const editingTx = ref(null);
const activeSubTab = ref('transaksi'); // 'transaksi', 'rekap', 'identitas'
const showPrintMenu = ref(false);

const quickFilter = ref('all'); // 'all', 'unverified', 'verified', 'receipt', 'disbursement', 'minus', 'gt10m', 'gt50m'
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

// -------------------------------------------------------------
// 2. PERIOD LOCK (TUTUP BUKU BULANAN)
// -------------------------------------------------------------
const confirmToggleLock = () => {
    const action = props.is_locked ? 'membuka kunci' : 'menutup buku & mengunci';
    const warning = props.is_locked 
        ? 'Setelah dibuka, transaksi dapat kembali ditambahkan, diubah, atau dihapus.'
        : 'Setelah ditutup buku, mutasi pada bulan ini akan berstatus Read-Only untuk melindungi integritas laporan kas.';
    if (confirm(`Apakah Anda yakin ingin ${action} periode ${monthNames[props.current_month]} ${props.year}?\n\n${warning}`)) {
        router.post(`/periode/${props.year}/${props.current_month}/toggle-lock`, {}, {
            preserveScroll: true,
        });
    }
};

// -------------------------------------------------------------
// 3. VERIFICATION & ANOMALY COUNTERS
// -------------------------------------------------------------
const verifiedCount = computed(() => {
    if (!props.bank_data?.rows) return 0;
    return props.bank_data.rows.filter(r => r.is_verified).length;
});

const unverifiedCount = computed(() => {
    if (!props.bank_data?.rows) return 0;
    return props.bank_data.rows.filter(r => !r.is_verified).length;
});

const negativeSaldoRows = computed(() => {
    if (!props.bank_data?.rows) return [];
    return props.bank_data.rows.filter(r => Number(r.saldo) < 0);
});

const lowestSaldo = computed(() => {
    if (!negativeSaldoRows.value.length) return 0;
    return Math.min(...negativeSaldoRows.value.map(r => Number(r.saldo)));
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
            row.is_verified = oldVal; // rollback on error
        }
    } catch (e) {
        row.is_verified = oldVal;
    } finally {
        delete togglingVerified.value[row.id];
    }
};

// -------------------------------------------------------------
// 4. AUDIT TRAIL LOG MODAL
// -------------------------------------------------------------
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

// -------------------------------------------------------------
// 5. DYNAMIC COLUMNS PREFERENCES
// -------------------------------------------------------------
const defaultStandardColumns = {
    no: { label: 'No. Urut', visible: true },
    tanggal: { label: 'Tanggal', visible: true },
    uraian: { label: 'Uraian', visible: true },
    penerimaan: { label: 'Penerimaan', visible: true },
    pengeluaran: { label: 'Pengeluaran', visible: true },
    saldo: { label: 'Saldo', visible: true },
    keterangan: { label: 'Keterangan', visible: true },
    memo: { label: 'Catatan / Memo Internal', visible: true },
};

const columnVisibility = ref({ ...defaultStandardColumns });
const customColumns = ref([]);
const newColumnName = ref('');
const showColumnSettings = ref(false);

onMounted(() => {
    try {
        const savedVis = localStorage.getItem('keudprd_bank_col_vis');
        if (savedVis) {
            columnVisibility.value = { ...defaultStandardColumns, ...JSON.parse(savedVis) };
        }
        const savedCustom = localStorage.getItem('keudprd_bank_custom_cols');
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
            const el = document.getElementById('bank-row-' + targetId);
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

watch(() => props.current_month, () => {
    checkAndApplyHighlight();
});

watch(columnVisibility, (val) => {
    try {
        localStorage.setItem('keudprd_bank_col_vis', JSON.stringify(val));
    } catch (e) {}
}, { deep: true });

watch(customColumns, (val) => {
    try {
        localStorage.setItem('keudprd_bank_custom_cols', JSON.stringify(val));
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

// -------------------------------------------------------------
// 6. INLINE EDITING FOR MEMO / CUSTOM CELLS
// -------------------------------------------------------------
const editingCell = ref({ id: null, field: null });
const cellDraftValue = ref('');
const savingStatus = ref({}); // { `${id}_${field}`: 'saving' | 'saved' | 'error' }

const startEditCell = (row, field, initialValue) => {
    if (props.is_locked || !row || !row.id || String(row.id).includes('contra')) return;
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
        const res = await fetch(`/buku-bank/transaksi/${row.id}/inline`, {
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

// -------------------------------------------------------------
// 7. PAGINATION & FILTER LOGIC
// -------------------------------------------------------------
const currentPage = ref(1);
const perPage = ref(10); // default 10 matching mockup

const form = ref({
    transaction_date: `${props.year}-${String(props.current_month).padStart(2, '0')}-05`,
    description: '',
    flow_direction: 'disbursement',
    amount_net: '',
    pptk_id: '',
    notes: '',
});

const switchMonth = (m) => {
    router.get('/buku-bank', { month: Number(m), year: props.year }, { preserveState: true, preserveScroll: true });
};

const prevMonth = () => {
    if (props.current_month > 1) {
        switchMonth(props.current_month - 1);
    }
};

const nextMonth = () => {
    if (props.current_month < 12) {
        switchMonth(props.current_month + 1);
    }
};

const formatRupiah = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '0,00';
    if (val === 0) return '0,00';
    return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
            sortKey.value = '';
            sortOrder.value = 'asc';
        }
    } else {
        sortKey.value = key;
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
    if (key === 'uraian' || key === 'keterangan' || key === 'memo') {
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
    const rows = props.bank_data?.rows || [];
    const groups = {};
    rows.forEach(r => {
        const nominal = Number(r.penerimaan) > 0 ? `rec_${Number(r.penerimaan)}` : `dis_${Number(r.pengeluaran)}`;
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

// Peringatan dini di form tambah transaksi manual
const duplicateWarning = computed(() => {
    if (!form.transaction_date || !form.amount_net || Number(form.amount_net) <= 0) return null;
    const date = form.transaction_date;
    const amount = Number(form.amount_net);
    return (props.bank_data?.rows || []).find(r => {
        if (editingTx.value && r.id === editingTx.value.id) return false;
        return (r.raw_date === date || r.tanggal === date) && 
               (Number(r.penerimaan) === amount || Number(r.pengeluaran) === amount);
    }) || null;
});

const filteredRows = computed(() => {
    if (!props.bank_data?.rows) return [];
    let list = props.bank_data.rows;

    // 1. Text Search
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(r => {
            return (r.uraian && r.uraian.toLowerCase().includes(q)) ||
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
        list = list.filter(r => Number(r.penerimaan) > 0);
    } else if (quickFilter.value === 'disbursement') {
        list = list.filter(r => Number(r.pengeluaran) > 0);
    } else if (quickFilter.value === 'minus') {
        list = list.filter(r => Number(r.saldo) < 0);
    } else if (quickFilter.value === 'gt10m') {
        list = list.filter(r => (Number(r.penerimaan) >= 10000000 || Number(r.pengeluaran) >= 10000000));
    } else if (quickFilter.value === 'gt50m') {
        list = list.filter(r => (Number(r.penerimaan) >= 50000000 || Number(r.pengeluaran) >= 50000000));
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
            list = list.filter(r => (Number(r.penerimaan) >= min || Number(r.pengeluaran) >= min));
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
                    valA = (a.uraian || '').toLowerCase();
                    valB = (b.uraian || '').toLowerCase();
                    return sortOrder.value === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                case 'penerimaan':
                    valA = Number(a.penerimaan) || 0;
                    valB = Number(b.penerimaan) || 0;
                    break;
                case 'pengeluaran':
                    valA = Number(a.pengeluaran) || 0;
                    valB = Number(b.pengeluaran) || 0;
                    break;
                case 'saldo':
                    valA = Number(a.saldo) || 0;
                    valB = Number(b.saldo) || 0;
                    break;
                case 'keterangan':
                    valA = (a.keterangan || '').toLowerCase();
                    valB = (b.keterangan || '').toLowerCase();
                    return sortOrder.value === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                case 'memo':
                    valA = (a.notes || a.keterangan || '').toLowerCase();
                    valB = (b.notes || b.keterangan || '').toLowerCase();
                    return sortOrder.value === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                default:
                    if (a.custom_fields && b.custom_fields && a.custom_fields[sortKey.value] !== undefined) {
                        valA = String(a.custom_fields[sortKey.value] || '').toLowerCase();
                        valB = String(b.custom_fields[sortKey.value] || '').toLowerCase();
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

watch([searchQuery, quickFilter, filterStartDate, filterEndDate, filterMinNominal, perPage, sortKey, sortOrder, () => props.current_month], () => {
    currentPage.value = 1;
});

const openAddModal = () => {
    if (props.is_locked) return;
    editingTx.value = null;
    form.value = {
        transaction_date: `${props.year}-${String(props.current_month).padStart(2, '0')}-05`,
        description: '',
        flow_direction: 'disbursement',
        amount_net: '',
        pptk_id: '',
        notes: '',
    };
    showAddModal.value = true;
};

const openEditModal = (r) => {
    if (props.is_locked) return;
    editingTx.value = r;
    form.value = {
        transaction_date: r.raw_date || `${props.year}-${String(props.current_month).padStart(2, '0')}-05`,
        description: r.uraian,
        flow_direction: r.penerimaan > 0 ? 'receipt' : 'disbursement',
        amount_net: r.penerimaan > 0 ? r.penerimaan : r.pengeluaran,
        pptk_id: r.pptk_id || '',
        notes: r.notes || r.keterangan || '',
    };
    showAddModal.value = true;
};

const saveTransaction = () => {
    if (props.is_locked) return;
    if (editingTx.value) {
        router.put(`/buku-bank/transaksi/${editingTx.value.id}`, form.value, {
            preserveScroll: true,
            onSuccess: () => {
                showAddModal.value = false;
                editingTx.value = null;
            }
        });
    } else {
        router.post('/buku-bank/transaksi', form.value, {
            preserveScroll: true,
            onSuccess: () => {
                showAddModal.value = false;
            }
        });
    }
};

const deleteTransaction = (id) => {
    if (props.is_locked) return;
    if (confirm('Apakah Anda yakin ingin menghapus mutasi bank ini? Saldo berjalan akan dihitung ulang secara otomatis.')) {
        router.delete(`/buku-bank/transaksi/${id}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="`Buku Bank - ${monthNames[current_month]} ${year}`" />

        <div class="space-y-4 max-w-[1920px] mx-auto select-text pb-12">
            
            <!-- 1. Header Toolbar matching Dashboard -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <span>Buku Bank</span>
                    </h1>
                    <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 font-mono text-xs font-bold rounded-md border border-emerald-200">
                        TA {{ year }}
                    </span>

                    <!-- Period Lock / Tutup Buku Status Badge & Toggle -->
                    <button 
                        @click="confirmToggleLock"
                        :class="is_locked ? 'bg-amber-100 text-amber-900 border-amber-300 hover:bg-amber-200' : 'bg-slate-100 text-slate-700 border-slate-300 hover:bg-slate-200'"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-lg border shadow-2xs transition-colors cursor-pointer"
                        :title="is_locked ? 'Periode dikunci (Tutup Buku). Klik untuk buka kunci.' : 'Periode aktif. Klik untuk tutup buku & kunci transaksi.'"
                    >
                        <Lock v-if="is_locked" class="w-3.5 h-3.5 text-amber-700" />
                        <Unlock v-else class="w-3.5 h-3.5 text-slate-500" />
                        <span>{{ is_locked ? 'Tutup Buku (Terkunci)' : 'Kunci Periode' }}</span>
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Tambah Mutasi (Disabled when Locked) -->
                    <button 
                        v-if="!is_locked"
                        @click="openAddModal"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#107c41] hover:bg-[#0e6b37] text-white rounded-lg text-xs font-bold shadow-2xs transition-colors cursor-pointer whitespace-nowrap"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Mutasi</span>
                    </button>
                    <div 
                        v-else 
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 text-slate-400 border border-slate-200 rounded-lg text-xs font-bold cursor-not-allowed select-none"
                        title="Periode telah ditutup buku. Buka kunci terlebih dahulu untuk menambah transaksi."
                    >
                        <Lock class="w-3.5 h-3.5 text-slate-400" />
                        <span>Mutasi Dikunci</span>
                    </div>

                    <!-- Ekspor Excel -->
                    <a 
                        :href="`/laporan/export-bank?month=${current_month}&year=${year}`"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 rounded-lg text-xs font-bold shadow-2xs transition-colors whitespace-nowrap"
                        title="Unduh Spreadsheet Excel"
                    >
                        <Download class="w-4 h-4 text-slate-600" />
                        <span>Ekspor</span>
                    </a>

                    <!-- Cetak Split Dropdown (Dokumen Resmi vs Lembar Kerja Rapat) -->
                    <div class="relative">
                        <button 
                            @click="showPrintMenu = !showPrintMenu"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 rounded-lg text-xs font-bold shadow-2xs transition-colors cursor-pointer whitespace-nowrap"
                            title="Pilihan Cetak Dokumen"
                        >
                            <Printer class="w-4 h-4 text-slate-600" />
                            <span>Cetak</span>
                            <ChevronDown class="w-3 h-3 text-slate-500" />
                        </button>

                        <div 
                            v-if="showPrintMenu" 
                            class="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-xl border border-slate-200 p-2 z-50 text-xs space-y-1 select-none"
                        >
                            <div class="px-2.5 py-1.5 font-bold text-slate-500 text-[10px] uppercase tracking-wider border-b border-slate-100">
                                Pilih Format Cetak:
                            </div>
                            <a 
                                :href="`/buku-bank/cetak?month=${current_month}&year=${year}`"
                                target="_blank"
                                @click="showPrintMenu = false"
                                class="flex items-start gap-2.5 px-2.5 py-2 rounded-lg hover:bg-slate-50 transition-colors text-slate-800 cursor-pointer"
                            >
                                <span class="text-base">📄</span>
                                <div>
                                    <span class="font-bold block text-slate-900">Cetak Dokumen Resmi</span>
                                    <span class="text-[11px] text-slate-500 block">Standar Permendagri, bersih tanpa oret-oret</span>
                                </div>
                            </a>
                            <a 
                                :href="`/buku-bank/cetak?month=${current_month}&year=${year}&with_notes=1`"
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

            <!-- Warning Banner: Period Locked -->
            <div v-if="is_locked" class="p-4 bg-amber-50/95 border border-amber-300 rounded-xl flex items-center justify-between gap-3 text-amber-950 text-xs shadow-2xs">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-200 flex items-center justify-center shrink-0">
                        <Lock class="w-4 h-4 text-amber-900" />
                    </div>
                    <div>
                        <div class="font-bold text-amber-900 text-sm">Periode {{ monthNames[current_month] }} {{ year }} Telah Ditutup Buku (Terkunci)</div>
                        <p class="text-amber-800 text-xs mt-0.5">
                            Dokumen dan saldo telah disahkan. Penambahan, pengubahan, dan penghapusan transaksi dinonaktifkan dalam mode aman (read-only).
                        </p>
                    </div>
                </div>
                <button 
                    @click="confirmToggleLock" 
                    class="px-3 py-1.5 bg-white hover:bg-amber-100 text-amber-900 border border-amber-300 rounded-lg font-bold text-xs shrink-0 cursor-pointer shadow-2xs"
                >
                    Buka Kunci
                </button>
            </div>

            <!-- Warning Banner: Overdraft / Anomaly Running Saldo Minus -->
            <div v-if="negativeSaldoRows.length > 0" class="p-4 bg-rose-50 border border-rose-300 rounded-xl flex items-center justify-between gap-3 text-rose-950 text-xs shadow-2xs">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-rose-200 flex items-center justify-center shrink-0">
                        <AlertTriangle class="w-4 h-4 text-rose-800" />
                    </div>
                    <div>
                        <div class="font-bold text-rose-900 text-sm">Peringatan: Saldo Berjalan Rekening Minus (Anomali)</div>
                        <p class="text-rose-800 text-xs mt-0.5">
                            Terdeteksi <b>{{ negativeSaldoRows.length }}</b> baris transaksi dengan saldo di bawah Rp 0 (Saldo terendah: <b>Rp {{ formatRupiah(lowestSaldo) }}</b>).
                            Pastikan SP2D/Penerimaan dicatat mendahului mutasi pengeluaran.
                        </p>
                    </div>
                </div>
                <button 
                    @click="quickFilter = 'minus'" 
                    class="px-3 py-1.5 bg-white hover:bg-rose-100 text-rose-800 border border-rose-300 rounded-lg font-bold text-xs shrink-0 cursor-pointer shadow-2xs"
                >
                    Lihat {{ negativeSaldoRows.length }} Baris Minus
                </button>
            </div>

            <!-- Card Container -->
            <div class="bg-white border border-slate-200/90 rounded-xl shadow-xs p-5 sm:p-6 space-y-5">

                <!-- 2. Filter & Search Bar + Column Manager Popover -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <!-- Left: Periode Navigation -->
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-700">Periode</span>
                        
                        <button 
                            @click="prevMonth"
                            :disabled="current_month <= 1"
                            class="p-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer shadow-2xs"
                            title="Bulan Sebelumnya"
                        >
                            <ChevronLeft class="w-4 h-4" />
                        </button>

                        <div class="relative">
                            <select
                                :value="current_month"
                                @change="switchMonth($event.target.value)"
                                class="appearance-none bg-white border border-slate-300 rounded-lg pl-3 pr-8 py-1.5 text-xs font-bold text-slate-800 hover:border-slate-400 focus:outline-none focus:ring-1 focus:ring-emerald-500 shadow-2xs cursor-pointer"
                            >
                                <option v-for="(name, m) in monthNames" :key="m" :value="Number(m)">
                                    {{ name }} {{ year }}
                                </option>
                            </select>
                            <ChevronDown class="w-3.5 h-3.5 text-slate-400 absolute right-2.5 top-2.5 pointer-events-none" />
                        </div>

                        <button 
                            @click="nextMonth"
                            :disabled="current_month >= 12"
                            class="p-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer shadow-2xs"
                            title="Bulan Berikutnya"
                        >
                            <ChevronRight class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Right: Search Input + Atur Kolom Popover Button + Filter Popover -->
                    <div class="flex items-center gap-2 flex-1 max-w-2xl">
                        <div class="relative flex-1">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari transaksi bank / memo / nominal..."
                                class="w-full pl-9 pr-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 shadow-2xs"
                            />
                        </div>

                        <!-- Button Toggle Filter Rentang & Nominal -->
                        <button 
                            @click="showAdvancedFilter = !showAdvancedFilter"
                            :class="showAdvancedFilter || hasActiveFilters ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 border rounded-lg text-xs font-bold shadow-2xs transition-colors cursor-pointer whitespace-nowrap"
                            title="Filter rentang tanggal & nominal cepat"
                        >
                            <Filter class="w-3.5 h-3.5" />
                            <span>Filter</span>
                            <span v-if="hasActiveFilters" class="w-2 h-2 rounded-full bg-emerald-600"></span>
                        </button>

                        <!-- Button Deteksi Dobel (Tampil Otomatis Jika Ada Potensi Dobel) -->
                        <button 
                            v-if="duplicateCount > 0"
                            type="button"
                            @click="quickFilter = quickFilter === 'duplicate' ? 'all' : 'duplicate'"
                            :class="[
                                quickFilter === 'duplicate' 
                                    ? 'bg-amber-600 text-white ring-2 ring-amber-400 font-black' 
                                    : 'bg-amber-100 text-amber-900 border border-amber-300 hover:bg-amber-200 font-bold',
                                'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs shadow-2xs transition-all cursor-pointer whitespace-nowrap'
                            ]"
                            :title="quickFilter === 'duplicate' ? 'Klik untuk kembali ke semua transaksi' : 'Klik untuk menyaring dan melihat transaksi yang berpotensi dobel'"
                        >
                            <AlertTriangle class="w-3.5 h-3.5 text-amber-600" />
                            <span>{{ duplicateCount }} Potensi Dobel</span>
                        </button>

                        <!-- Dropdown Popover: Atur Kolom & Kolom Kustom -->
                        <div class="relative">
                            <button 
                                @click="showColumnSettings = !showColumnSettings"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 rounded-lg text-xs font-bold shadow-2xs transition-colors cursor-pointer whitespace-nowrap"
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
                                class="absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-slate-200 p-4 z-50 text-xs space-y-3.5 select-none"
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
                                            v-for="(col, idx) in customColumns" 
                                            :key="col"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-md text-[11px] font-semibold"
                                        >
                                            <span>{{ col }}</span>
                                            <button @click="removeCustomColumn(idx)" class="text-emerald-700 hover:text-rose-600 cursor-pointer">
                                                <X class="w-3 h-3" />
                                            </button>
                                        </span>
                                    </div>
                                    <p v-else class="text-slate-400 italic text-[11px] mb-2">Belum ada kolom kustom tambahan.</p>

                                    <!-- Add New Custom Column Input -->
                                    <div class="flex items-center gap-1.5">
                                        <input 
                                            v-model="newColumnName"
                                            @keydown.enter.prevent="addCustomColumn"
                                            type="text" 
                                            placeholder="Nama kolom baru..." 
                                            class="flex-1 px-2.5 py-1 text-xs border border-slate-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                        />
                                        <button 
                                            @click="addCustomColumn"
                                            type="button"
                                            class="px-2.5 py-1 bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg text-xs font-bold transition-colors cursor-pointer"
                                        >
                                            Tambah
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Advanced Filter Box (Collapsible) -->
                <div v-if="showAdvancedFilter" class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-3 text-xs">
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
                            <label class="block text-slate-600 font-semibold mb-1 text-[11px]">Min. Nominal Mutasi (Rp):</label>
                            <input 
                                v-model="filterMinNominal" 
                                type="number" 
                                placeholder="Misal: 10000000"
                                class="w-full px-2.5 py-1 bg-white border border-slate-300 rounded-lg text-xs font-mono"
                            />
                        </div>
                    </div>
                </div>

                <!-- Quick Filter Pills: Checklist Status & Fast Views -->
                <div class="flex flex-wrap items-center gap-1.5 border-b border-slate-100 pb-3">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1">Status Verifikasi & Filter:</span>
                    
                    <button 
                        @click="quickFilter = 'all'"
                        :class="quickFilter === 'all' ? 'bg-slate-900 text-white font-bold' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg text-xs transition-colors cursor-pointer"
                    >
                        Semua ({{ bank_data.rows?.length || 0 }})
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
                        Penerimaan Saja
                    </button>

                    <button 
                        @click="quickFilter = 'disbursement'"
                        :class="quickFilter === 'disbursement' ? 'bg-rose-700 text-white font-bold' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200'"
                        class="px-2.5 py-1 rounded-lg text-xs transition-colors cursor-pointer"
                    >
                        Pengeluaran Saja
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

                <!-- 3. KPI Summary Cards matching Dashboard Theme -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Penerimaan Bulan Ini -->
                    <div class="bg-emerald-50/50 border border-emerald-100/80 rounded-xl p-4 transition-all">
                        <div class="text-xs font-semibold text-emerald-800 mb-1">Penerimaan bulan ini</div>
                        <div class="text-lg sm:text-xl font-bold font-mono text-emerald-900 tracking-tight">
                            Rp {{ formatRupiah(bank_data.summary?.periode_ini?.penerimaan) }}
                        </div>
                    </div>

                    <!-- Pengeluaran Bulan Ini -->
                    <div class="bg-rose-50/50 border border-rose-100/80 rounded-xl p-4 transition-all">
                        <div class="text-xs font-semibold text-rose-800 mb-1">Pengeluaran bulan ini</div>
                        <div class="text-lg sm:text-xl font-bold font-mono text-rose-900 tracking-tight">
                            Rp {{ formatRupiah(bank_data.summary?.periode_ini?.pengeluaran) }}
                        </div>
                    </div>

                    <!-- Saldo Akhir Bulan -->
                    <div class="bg-slate-50 border border-slate-200/90 rounded-xl p-4 transition-all">
                        <div class="text-xs font-semibold text-slate-700 mb-1">Saldo akhir {{ monthNames[current_month] }}</div>
                        <div class="text-lg sm:text-xl font-bold font-mono text-slate-900 tracking-tight" :class="bank_data.summary?.sampai_periode_ini?.saldo < 0 ? 'text-rose-700' : 'text-slate-900'">
                            Rp {{ formatRupiah(bank_data.summary?.sampai_periode_ini?.saldo) }}
                        </div>
                    </div>
                </div>

                <!-- 4. Sub-Navigation Tabs matching Mockup -->
                <div class="flex items-center gap-1.5 border-b border-slate-200 pb-2">
                    <button 
                        @click="activeSubTab = 'transaksi'"
                        :class="activeSubTab === 'transaksi' ? 'bg-[#0f6b35] text-white font-bold' : 'text-slate-600 hover:bg-slate-100 font-semibold'"
                        class="px-4 py-1.5 rounded-lg text-xs transition-colors cursor-pointer"
                    >
                        Transaksi
                    </button>
                    <button 
                        @click="activeSubTab = 'rekap'"
                        :class="activeSubTab === 'rekap' ? 'bg-[#0f6b35] text-white font-bold' : 'text-slate-600 hover:bg-slate-100 font-semibold'"
                        class="px-4 py-1.5 rounded-lg text-xs transition-colors cursor-pointer"
                    >
                        Rekap &amp; Sisa Kas
                    </button>
                    <button 
                        @click="activeSubTab = 'identitas'"
                        :class="activeSubTab === 'identitas' ? 'bg-[#0f6b35] text-white font-bold' : 'text-slate-600 hover:bg-slate-100 font-semibold'"
                        class="px-4 py-1.5 rounded-lg text-xs transition-colors cursor-pointer"
                    >
                        Identitas &amp; Pengesahan
                    </button>
                </div>

                <!-- ================= VIEW 1: TAB TRANSAKSI (WITH DYNAMIC COLUMNS & INLINE EDIT) ================= -->
                <div v-if="activeSubTab === 'transaksi'" class="space-y-4">
                    <div class="overflow-x-auto excel-scroll border border-slate-300 rounded-lg shadow-2xs">
                        <table class="w-full excel-table text-xs">
                            <thead>
                                <tr class="excel-header-row bg-slate-50 text-slate-700">
                                    <th @click="handleSort('is_verified')" class="w-12 text-center font-bold cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'is_verified' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Status Verifikasi">
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
                                    <th v-if="columnVisibility.no?.visible" @click="handleSort('no')" class="w-12 text-center font-bold cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'no' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan No. Urut (1-9 atau 9-1)">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <span>No. Urut</span>
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
                                    <th v-if="columnVisibility.tanggal?.visible" @click="handleSort('tanggal')" class="w-28 text-center font-bold cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'tanggal' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Tanggal (Terbaru ke Terlama / Terlama ke Terbaru)">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <span>Tanggal</span>
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
                                    <th v-if="columnVisibility.uraian?.visible" @click="handleSort('uraian')" class="text-left min-w-[280px] font-bold cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'uraian' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Uraian (A - Z atau Z - A)">
                                        <div class="flex items-center justify-between gap-1.5">
                                            <span>Uraian</span>
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
                                    <th v-if="columnVisibility.penerimaan?.visible" @click="handleSort('penerimaan')" class="text-right min-w-[130px] font-bold cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'penerimaan' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Penerimaan (Besar ke Kecil / Kecil ke Besar)">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>Penerimaan (Rp)</span>
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
                                    <th v-if="columnVisibility.pengeluaran?.visible" @click="handleSort('pengeluaran')" class="text-right min-w-[130px] font-bold cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'pengeluaran' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Pengeluaran (Besar ke Kecil / Kecil ke Besar)">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>Pengeluaran (Rp)</span>
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
                                    <th v-if="columnVisibility.saldo?.visible" @click="handleSort('saldo')" class="text-right min-w-[140px] font-bold cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'saldo' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Saldo (Besar ke Kecil / Kecil ke Besar)">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span>Saldo (Rp)</span>
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
                                    <th v-if="columnVisibility.keterangan?.visible" @click="handleSort('keterangan')" class="text-left min-w-[130px] font-bold cursor-pointer select-none hover:bg-slate-100 transition-colors group" :class="sortKey === 'keterangan' ? 'bg-emerald-100 text-emerald-950 font-black ring-1 ring-inset ring-emerald-400' : ''" title="Urutkan Keterangan (A - Z atau Z - A)">
                                        <div class="flex items-center justify-between gap-1.5">
                                            <span>Keterangan</span>
                                            <span v-if="sortKey === 'keterangan'" class="px-1.5 py-0.5 rounded-md bg-[#107c41] text-white font-black shadow-xs inline-flex items-center gap-1 text-[10px] shrink-0">
                                                <ArrowUp v-if="sortOrder === 'asc'" class="w-3.5 h-3.5 stroke-[3]" />
                                                <ArrowDown v-else class="w-3.5 h-3.5 stroke-[3]" />
                                                <span>{{ getSortBadgeText('keterangan', sortOrder) }}</span>
                                            </span>
                                            <span v-else class="p-1 rounded bg-slate-200 text-slate-700 group-hover:bg-[#107c41] group-hover:text-white transition-colors inline-flex items-center justify-center shrink-0">
                                                <ArrowUpDown class="w-3.5 h-3.5 stroke-[2.2]" />
                                            </span>
                                        </div>
                                    </th>
                                    
                                    <!-- Memo / Catatan Internal Column Header -->
                                    <th v-if="columnVisibility.memo?.visible" @click="handleSort('memo')" class="text-left min-w-[180px] font-bold bg-amber-50/70 text-amber-950 border-amber-300 cursor-pointer select-none hover:bg-amber-100/70 transition-colors group" :class="sortKey === 'memo' ? 'bg-amber-100 text-amber-950 font-black ring-1 ring-inset ring-amber-400' : ''" title="Urutkan Catatan Memo (A-Z atau Z-A)">
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
                                        :key="'th-'+col"
                                        @click="handleSort(col)"
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

                                    <th class="w-24 text-center font-bold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Saldo Bulan Lalu Carryover (Hanya di Halaman 1) -->
                                <tr v-if="currentPage === 1" class="bg-slate-50/80 hover:bg-slate-100/70 font-semibold text-slate-800 transition-colors">
                                    <td class="text-center font-mono text-slate-400">-</td>
                                    <td v-if="columnVisibility.no?.visible" class="text-center font-mono text-slate-400">-</td>
                                    <td v-if="columnVisibility.tanggal?.visible" class="text-center font-mono text-slate-600">01/{{ String(current_month).padStart(2, '0') }}/{{ year }}</td>
                                    <td v-if="columnVisibility.uraian?.visible" class="italic text-slate-700 font-medium">Saldo bulan lalu</td>
                                    <td v-if="columnVisibility.penerimaan?.visible" class="text-right font-mono text-slate-700">{{ formatRupiah(current_month > 1 ? bank_data.saldo_awal : 0) }}</td>
                                    <td v-if="columnVisibility.pengeluaran?.visible" class="text-right font-mono text-slate-400">0,00</td>
                                    <td v-if="columnVisibility.saldo?.visible" class="text-right font-mono font-bold text-slate-900">{{ formatRupiah(bank_data.saldo_awal) }}</td>
                                    <td v-if="columnVisibility.keterangan?.visible" class="italic text-slate-500">Bawaan</td>
                                    <td v-if="columnVisibility.memo?.visible" class="text-slate-400 italic text-[11px]">-</td>
                                    <td v-for="col in customColumns" :key="'col-init-'+col" class="text-slate-400 italic text-[11px]">-</td>
                                    <td class="text-center"></td>
                                </tr>

                                <!-- Monthly Transactions -->
                                <tr 
                                    v-for="r in paginatedRows" 
                                    :key="r.id"
                                    :id="'bank-row-' + r.id"
                                    :class="[
                                        highlightedRowId === r.id ? 'bg-amber-100 ring-2 ring-amber-400 font-semibold' : (duplicateRowMap.has(r.id) ? 'bg-amber-50/70' : ''),
                                        'hover:bg-slate-50 transition-all duration-300'
                                    ]"
                                >
                                    <!-- Status Verification Toggle Cell -->
                                    <td class="text-center">
                                        <button 
                                            @click="toggleVerified(r)"
                                            class="inline-flex items-center justify-center p-1 rounded-md transition-transform hover:scale-110 cursor-pointer"
                                            :title="r.is_verified ? 'Status: Terverifikasi (Klik untuk ubah jadi belum dicek)' : 'Status: Belum Dicek (Klik untuk tandai terverifikasi)'"
                                        >
                                            <CheckCircle2 v-if="r.is_verified" class="w-4 h-4 text-emerald-600" />
                                            <Clock v-else class="w-4 h-4 text-amber-500" />
                                        </button>
                                    </td>

                                    <td v-if="columnVisibility.no?.visible" class="text-center font-mono text-slate-500">
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
                                    <td v-if="columnVisibility.uraian?.visible" class="font-medium text-slate-900 whitespace-normal">{{ r.uraian }}</td>
                                    <td v-if="columnVisibility.penerimaan?.visible" class="text-right font-mono">
                                        <span v-if="r.penerimaan > 0" class="text-emerald-700 font-bold">{{ formatRupiah(r.penerimaan) }}</span>
                                        <span v-else class="text-slate-400">-</span>
                                    </td>
                                    <td v-if="columnVisibility.pengeluaran?.visible" class="text-right font-mono">
                                        <span v-if="r.pengeluaran > 0" class="text-rose-700 font-bold">{{ formatRupiah(r.pengeluaran) }}</span>
                                        <span v-else class="text-slate-400">-</span>
                                    </td>
                                    
                                    <!-- Saldo with Anomaly / Negative Warning Indicator -->
                                    <td v-if="columnVisibility.saldo?.visible" class="text-right font-mono font-bold">
                                        <span 
                                            v-if="r.saldo < 0" 
                                            class="inline-flex items-center gap-1 text-rose-700 bg-rose-50 px-1.5 py-0.5 rounded border border-rose-200"
                                            title="Peringatan: Saldo negatif!"
                                        >
                                            <AlertTriangle class="w-3 h-3 text-rose-600" />
                                            {{ formatRupiah(r.saldo) }}
                                        </span>
                                        <span v-else class="text-slate-900">
                                            {{ formatRupiah(r.saldo) }}
                                        </span>
                                    </td>
                                    <td v-if="columnVisibility.keterangan?.visible" class="text-slate-600 text-xs">{{ r.keterangan || '-' }}</td>

                                    <!-- Memo / Catatan Internal Cell (Click to Inline Edit) -->
                                    <td 
                                        v-if="columnVisibility.memo?.visible" 
                                        class="text-xs p-1 cursor-pointer bg-amber-50/20 hover:bg-amber-50/60 transition-colors relative"
                                        @click="startEditCell(r, 'memo', r.notes)"
                                        :title="is_locked ? 'Terkunci' : 'Klik untuk mengisi / mengedit memo baris ini'"
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
                                        :key="'td-'+r.id+'-'+col"
                                        class="text-xs p-1 cursor-pointer bg-emerald-50/20 hover:bg-emerald-50/60 transition-colors relative"
                                        @click="startEditCell(r, col, r.custom_fields?.[col])"
                                        :title="is_locked ? 'Terkunci' : `Klik untuk mengedit ${col}`"
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

                                    <!-- Actions Cell: Audit Log, Edit, Delete -->
                                    <td class="text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-1">
                                            <!-- Audit Log History Button -->
                                            <button 
                                                @click="openAuditModal(r)"
                                                class="text-slate-400 hover:text-blue-600 p-1 rounded transition-colors cursor-pointer"
                                                title="Lihat Riwayat Perubahan & Audit Trail"
                                            >
                                                <History class="w-3.5 h-3.5" />
                                            </button>

                                            <!-- Edit Button -->
                                            <button 
                                                v-if="!is_locked"
                                                @click="openEditModal(r)"
                                                class="text-slate-400 hover:text-emerald-700 p-1 rounded transition-colors cursor-pointer"
                                                title="Edit Mutasi"
                                            >
                                                <Edit2 class="w-3.5 h-3.5" />
                                            </button>

                                            <!-- Delete Button -->
                                            <button 
                                                v-if="!is_locked"
                                                @click="deleteTransaction(r.id)"
                                                class="text-slate-400 hover:text-rose-600 p-1 rounded transition-colors cursor-pointer"
                                                title="Hapus Mutasi"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" />
                                            </button>

                                            <!-- Lock Indicator if Locked -->
                                            <span v-if="is_locked" class="text-slate-400 p-1" title="Terkunci (Mode Baca Saja)">
                                                <Lock class="w-3.5 h-3.5 text-slate-300" />
                                            </span>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="paginatedRows.length === 0">
                                    <td :colspan="9 + customColumns.length" class="text-center py-8 text-slate-400 italic">
                                        Tidak ada transaksi yang cocok dengan filter atau pencarian Anda.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls matching Mockup -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600 pt-2 select-none">
                        <div class="flex items-center gap-2">
                            <span>Tampilkan:</span>
                            <div class="relative">
                                <select 
                                    v-model="perPage" 
                                    class="appearance-none bg-white border border-slate-300 rounded-md pl-2.5 pr-6 py-1 text-xs font-semibold text-slate-800 hover:border-slate-400 focus:outline-none focus:ring-1 focus:ring-emerald-500 shadow-2xs cursor-pointer"
                                >
                                    <option :value="10">10</option>
                                    <option :value="20">20</option>
                                    <option :value="50">50</option>
                                    <option :value="100">100</option>
                                    <option value="all">Semua</option>
                                </select>
                                <ChevronDown class="w-3 h-3 text-slate-400 absolute right-1.5 top-2 pointer-events-none" />
                            </div>
                            <span class="text-slate-400 ml-2">
                                Menampilkan {{ filteredRows.length > 0 ? (currentPage - 1) * (perPage === 'all' ? filteredRows.length : perPage) + 1 : 0 }} - 
                                {{ perPage === 'all' ? filteredRows.length : Math.min(currentPage * perPage, filteredRows.length) }} dari {{ filteredRows.length }} mutasi
                            </span>
                        </div>

                        <!-- Pagination Pages -->
                        <div v-if="perPage !== 'all' && totalPages > 1" class="flex items-center gap-1">
                            <button
                                @click="currentPage--"
                                :disabled="currentPage <= 1"
                                class="px-2.5 py-1 rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors font-medium shadow-2xs"
                            >
                                Sebelumnya
                            </button>

                            <template v-for="(p, i) in displayedPages" :key="i">
                                <span v-if="p === '...'" class="px-2 py-1 text-slate-400">...</span>
                                <button
                                    v-else
                                    @click="currentPage = p"
                                    :class="currentPage === p ? 'bg-[#0f6b35] text-white font-bold border-[#0f6b35]' : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-300'"
                                    class="w-7 h-7 rounded-md border text-xs font-medium transition-colors shadow-2xs cursor-pointer"
                                >
                                    {{ p }}
                                </button>
                            </template>

                            <button
                                @click="currentPage++"
                                :disabled="currentPage >= totalPages"
                                class="px-2.5 py-1 rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors font-medium shadow-2xs"
                            >
                                Berikutnya
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ================= VIEW 2: TAB REKAP & SISA KAS ================= -->
                <div v-if="activeSubTab === 'rekap'" class="space-y-6">
                    <div class="border border-slate-300 rounded-xl overflow-hidden shadow-2xs">
                        <div class="bg-slate-100 px-4 py-2.5 border-b border-slate-300 font-bold text-xs text-slate-800 flex items-center justify-between">
                            <span>REKAPITULASI PENERIMAAN &amp; PENGELUARAN BUKU BANK</span>
                            <span class="font-mono text-emerald-800 font-bold">{{ monthNames[current_month] }} {{ year }}</span>
                        </div>
                        <table class="w-full text-xs excel-table">
                            <thead>
                                <tr class="bg-slate-50 text-slate-700">
                                    <th class="text-left font-bold py-2 px-3">Uraian Akumulasi</th>
                                    <th class="text-right font-bold py-2 px-3">Penerimaan (Rp)</th>
                                    <th class="text-right font-bold py-2 px-3">Pengeluaran (Rp)</th>
                                    <th class="text-right font-bold py-2 px-3">Saldo Buku (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="hover:bg-slate-50">
                                    <td class="font-medium text-slate-800 px-3 py-2">1. Jumlah Periode Ini ({{ monthNames[current_month] }})</td>
                                    <td class="text-right font-mono px-3 py-2 text-emerald-700 font-bold">{{ formatRupiah(bank_data.summary?.periode_ini?.penerimaan) }}</td>
                                    <td class="text-right font-mono px-3 py-2 text-rose-700 font-bold">{{ formatRupiah(bank_data.summary?.periode_ini?.pengeluaran) }}</td>
                                    <td class="text-right font-mono px-3 py-2 font-bold">{{ formatRupiah(bank_data.summary?.periode_ini?.saldo) }}</td>
                                </tr>
                                <tr class="hover:bg-slate-50">
                                    <td class="font-medium text-slate-800 px-3 py-2">2. Jumlah Sampai Dengan Periode Lalu</td>
                                    <td class="text-right font-mono px-3 py-2">{{ formatRupiah(bank_data.summary?.sampai_periode_lalu?.penerimaan) }}</td>
                                    <td class="text-right font-mono px-3 py-2">{{ formatRupiah(bank_data.summary?.sampai_periode_lalu?.pengeluaran) }}</td>
                                    <td class="text-right font-mono px-3 py-2 font-bold">{{ formatRupiah(bank_data.summary?.sampai_periode_lalu?.saldo) }}</td>
                                </tr>
                                <tr class="bg-emerald-50/70 font-bold border-t border-emerald-200">
                                    <td class="px-3 py-2 text-emerald-950">3. Jumlah Sampai Dengan Periode Ini</td>
                                    <td class="text-right font-mono px-3 py-2 text-emerald-900">{{ formatRupiah(bank_data.summary?.sampai_periode_ini?.penerimaan) }}</td>
                                    <td class="text-right font-mono px-3 py-2 text-rose-900">{{ formatRupiah(bank_data.summary?.sampai_periode_ini?.pengeluaran) }}</td>
                                    <td class="text-right font-mono px-3 py-2 text-emerald-950 font-black text-sm">{{ formatRupiah(bank_data.summary?.sampai_periode_ini?.saldo) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Rekap Sisa Kas Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-5 border border-slate-300 rounded-xl bg-slate-50/60 shadow-2xs space-y-2">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Sisa Kas Menurut Buku Bank:</span>
                            <div class="text-2xl font-black font-mono text-emerald-800">
                                Rp {{ formatRupiah(bank_data.sisa_kas) }}
                            </div>
                            <p class="text-[11px] text-slate-500">Saldo kas yang tersimpan di Rekening Kas Daerah / Giro Setwan pada akhir bulan {{ monthNames[current_month] }}.</p>
                        </div>

                        <div class="p-5 border border-slate-300 rounded-xl bg-slate-50/60 shadow-2xs space-y-2">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Status Penutupan Buku:</span>
                            <div class="text-base font-bold flex items-center gap-2" :class="is_locked ? 'text-amber-800' : 'text-emerald-700'">
                                <Lock v-if="is_locked" class="w-4 h-4" />
                                <CheckCircle2 v-else class="w-4 h-4" />
                                <span>{{ is_locked ? 'Tutup Buku / Periode Terkunci' : 'Periode Terbuka & Siap Rekonsiliasi' }}</span>
                            </div>
                            <p class="text-[11px] text-slate-500">
                                {{ is_locked ? 'Periode ini telah disahkan. Klik Kunci Periode di atas jika perlu membuka kunci.' : 'Klik Kunci Periode di atas bila seluruh mutasi bulan ini telah selesai dan valid.' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ================= VIEW 3: TAB IDENTITAS & PENGESAHAN ================= -->
                <div v-if="activeSubTab === 'identitas'" class="space-y-6">
                    <!-- Identity Metadata Card -->
                    <div class="border border-slate-300 rounded-xl p-5 bg-white shadow-2xs space-y-4">
                        <h3 class="font-bold text-xs text-slate-900 border-b border-slate-200 pb-2 uppercase tracking-wide">Identitas Dokumen Penatausahaan</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <table class="w-full">
                                <tbody>
                                    <tr class="border-b border-slate-100">
                                        <td class="py-1.5 text-slate-500 w-36 font-medium">SKPD</td>
                                        <td class="py-1.5 font-bold text-slate-900">{{ bank_data.meta?.skpd }}</td>
                                    </tr>
                                    <tr class="border-b border-slate-100">
                                        <td class="py-1.5 text-slate-500 font-medium">Pengguna Anggaran</td>
                                        <td class="py-1.5 font-bold text-slate-900">{{ bank_data.meta?.pa_kpa }}</td>
                                    </tr>
                                    <tr class="border-b border-slate-100">
                                        <td class="py-1.5 text-slate-500 font-medium">Bendahara Pengeluaran</td>
                                        <td class="py-1.5 font-bold text-slate-900">{{ bank_data.meta?.bendahara }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <table class="w-full">
                                <tbody>
                                    <tr class="border-b border-slate-100">
                                        <td class="py-1.5 text-slate-500 w-36 font-medium">Tahun Anggaran</td>
                                        <td class="py-1.5 font-mono font-bold text-slate-900">{{ year }}</td>
                                    </tr>
                                    <tr class="border-b border-slate-100">
                                        <td class="py-1.5 text-slate-500 font-medium">Bulan Aktif</td>
                                        <td class="py-1.5 font-bold text-slate-900">{{ monthNames[current_month]?.toUpperCase() }} {{ year }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Signatures Card -->
                    <div class="border border-slate-300 rounded-xl p-6 bg-slate-50/50 shadow-2xs">
                        <h3 class="font-bold text-xs text-slate-900 mb-6 border-b border-slate-200 pb-2 uppercase tracking-wide">Lembar Pengesahan Dokumen</h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 text-center text-xs">
                            <div class="flex flex-col justify-between h-48 bg-white p-4 rounded-lg border border-slate-300 shadow-2xs">
                                <div>
                                    <p class="font-bold text-slate-900">Mengetahui,</p>
                                    <p class="font-semibold text-slate-700">Sekretaris DPRD Kab. Banyumas</p>
                                </div>
                                <div>
                                    <p class="font-bold underline text-slate-900 text-sm">{{ bank_data.meta?.pa_kpa }}</p>
                                    <p class="text-[11px] text-slate-500 font-mono mt-0.5">NIP. {{ bank_data.meta?.pa_kpa?.includes('SUMARDI') ? '19680430 199002 1 003' : '19700512 199603 1 004' }}</p>
                                </div>
                            </div>

                            <div class="flex flex-col justify-between h-48 bg-white p-4 rounded-lg border border-slate-300 shadow-2xs">
                                <div>
                                    <p class="text-slate-500 text-[11px]">Purwokerto, Akhir Bulan</p>
                                    <p class="font-bold text-slate-900">Bendahara Pengeluaran</p>
                                </div>
                                <div>
                                    <p class="font-bold underline text-slate-900 text-sm">{{ bank_data.meta?.bendahara }}</p>
                                    <p class="text-[11px] text-slate-500 font-mono mt-0.5">NIP. {{ bank_data.meta?.bendahara_nip || '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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
                            <p class="text-[11px] text-emerald-200/80 mt-0.5">Catatan historis perubahan data transaksi disusun memanjang ke bawah</p>
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
                                <div v-if="activeAuditTx?.penerimaan">
                                    <span class="text-slate-500 font-sans text-[11px]">Penerimaan: </span>
                                    <span class="font-bold text-emerald-700">Rp {{ formatRupiah(activeAuditTx?.penerimaan) }}</span>
                                </div>
                                <div v-if="activeAuditTx?.pengeluaran">
                                    <span class="text-slate-500 font-sans text-[11px]">Pengeluaran: </span>
                                    <span class="font-bold text-rose-700">Rp {{ formatRupiah(activeAuditTx?.pengeluaran) }}</span>
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

        <!-- ================= MODAL: ADD / EDIT BANK MUTATION ================= -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl border border-slate-300 max-w-lg w-full overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-900 text-white flex items-center justify-between">
                    <h3 class="text-xs font-bold flex items-center gap-2">
                        <Edit2 v-if="editingTx" class="w-4 h-4 text-emerald-400" />
                        <Plus v-else class="w-4 h-4 text-emerald-400" />
                        {{ editingTx ? 'Edit Nominal & Mutasi Rekening Giro' : 'Catat Mutasi Rekening Giro Bank' }}
                    </h3>
                    <button @click="showAddModal = false" class="text-slate-400 hover:text-white cursor-pointer">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="saveTransaction" class="p-5 space-y-3.5 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Tanggal Mutasi</label>
                            <input v-model="form.transaction_date" type="date" required class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs font-medium" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Arah Arus Kas</label>
                            <select v-model="form.flow_direction" class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs font-medium">
                                <option value="disbursement">Pengeluaran (Debet Giro)</option>
                                <option value="receipt">Penerimaan (Kredit / SP2D)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Uraian Transaksi Bank</label>
                        <textarea v-model="form.description" rows="2" required placeholder="Contoh: 000006/LS/SETWAN atau CASH INDAH KONTRIBUSI PERJADI..." class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nominal (Rp)</label>
                            <input v-model="form.amount_net" type="number" step="0.01" required placeholder="0.00" class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs font-mono font-bold" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Alokasi PPTK</label>
                            <select v-model="form.pptk_id" class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs font-medium">
                                <option value="">-- Umum / Rekening Bank --</option>
                                <option v-for="p in pptks" :key="p.id" :value="p.id">{{ p.display_name }} ({{ p.name }})</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Keterangan / Tag</label>
                        <input v-model="form.notes" type="text" placeholder="Misal: panjar bintek, gaji dprd, tagihan ritamart..." class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs font-medium" />
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
                        <button type="button" @click="showAddModal = false" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold cursor-pointer">Batal</button>
                        <button type="submit" class="px-4 py-1.5 bg-[#107c41] hover:bg-[#0e6b37] text-white rounded-lg font-bold flex items-center gap-1.5 shadow-2xs cursor-pointer">
                            <Check class="w-4 h-4" /> {{ editingTx ? 'Simpan Perubahan' : 'Simpan Mutasi Bank' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </AppLayout>
</template>
