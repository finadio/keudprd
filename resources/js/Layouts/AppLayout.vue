<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { 
    LayoutDashboard, 
    BookOpen, 
    Landmark, 
    ArrowLeftRight, 
    Printer, 
    Settings, 
    UploadCloud,
    CheckCircle2,
    Building2,
    User,
    LogIn,
    LogOut,
    Menu,
    X,
    Search,
    ArrowUpRight,
    Calendar,
    Loader2,
    Tag,
    Clock,
    AlertCircle
} from 'lucide-vue-next';

const page = usePage();

// Default open on desktop (width >= 768px), closed on mobile
const getInitialSidebarState = () => {
    if (typeof window === 'undefined') return true;
    if (window.innerWidth < 768) return false;
    const saved = localStorage.getItem('keudprd_sidebar_open');
    return saved !== null ? saved === 'true' : true;
};

const sidebarOpen = ref(getInitialSidebarState());

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
    if (typeof window !== 'undefined' && window.innerWidth >= 768) {
        localStorage.setItem('keudprd_sidebar_open', String(sidebarOpen.value));
    }
};

const handleNavClick = () => {
    if (typeof window !== 'undefined' && window.innerWidth < 768) {
        sidebarOpen.value = false;
    }
};

const authUser = computed(() => page.props.auth?.user);
const activeFiscalYear = computed(() => page.props.fiscal_year?.active || 2026);
const defaultFiscalYear = computed(() => page.props.fiscal_year?.default || 2026);
const availableFiscalYears = computed(() => page.props.fiscal_year?.available || [2026]);
const isViewingArchiveYear = computed(() => activeFiscalYear.value !== defaultFiscalYear.value);

const switchFiscalYear = (newYear) => {
    router.post('/tahun-anggaran/pilih', {
        year: Number(newYear),
        current_url: typeof window !== 'undefined' ? window.location.pathname + window.location.search : '/dashboard',
    }, {
        preserveScroll: false,
    });
};

const navItems = [
    { name: 'Dashboard', href: '/dashboard', routeName: 'dashboard', icon: LayoutDashboard },
    { name: 'BKU Per PPTK', href: '/bku', routeName: 'bku.*', icon: BookOpen },
    { name: 'Buku Bank', href: '/buku-bank', routeName: 'buku-bank.*', icon: Landmark },
    { name: 'Impor Transaksi', href: '/impor', routeName: 'impor.*', icon: UploadCloud },
    { name: 'Rekonsiliasi', href: '/rekonsiliasi', routeName: 'rekonsiliasi.*', icon: ArrowLeftRight },
    { name: 'Laporan & Ekspor', href: '/laporan', routeName: 'laporan.*', icon: Printer },
    { name: 'Pengaturan & Master', href: '/pengaturan', routeName: 'pengaturan.*', icon: Settings },
];

const isCurrentRoute = (pattern) => {
    const currentUrl = page.url;
    if (pattern === 'dashboard' && currentUrl.startsWith('/dashboard')) return true;
    if (pattern === 'bku.*' && currentUrl.startsWith('/bku')) return true;
    if (pattern === 'buku-bank.*' && currentUrl.startsWith('/buku-bank')) return true;
    if (pattern === 'impor.*' && currentUrl.startsWith('/impor')) return true;
    if (pattern === 'rekonsiliasi.*' && currentUrl.startsWith('/rekonsiliasi')) return true;
    if (pattern === 'laporan.*' && currentUrl.startsWith('/laporan')) return true;
    if (pattern === 'pengaturan.*' && currentUrl.startsWith('/pengaturan')) return true;
    return false;
};

const logout = () => {
    router.post('/logout');
};

const refreshPage = () => {
    if (typeof window !== 'undefined') {
        window.location.reload();
    }
};

// ================= GLOBAL SEARCH (CTRL + K) =================
const isSearchOpen = ref(false);
const searchQuery = ref('');
const searchResults = ref([]);
const searchCount = ref(0);
const isSearching = ref(false);
const searchFilter = ref('all'); // 'all', 'bku', 'bank'
const searchInputRef = ref(null);
let searchDebounceTimeout = null;

const openSearch = () => {
    isSearchOpen.value = true;
    nextTick(() => {
        searchInputRef.value?.focus();
    });
};

const closeSearch = () => {
    isSearchOpen.value = false;
};

const clearSearch = () => {
    searchQuery.value = '';
    searchResults.value = [];
    searchCount.value = 0;
    searchInputRef.value?.focus();
};

const handleGlobalKeydown = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        openSearch();
    } else if (e.key === 'Escape' && isSearchOpen.value) {
        closeSearch();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});

const onSearchInput = () => {
    if (searchDebounceTimeout) clearTimeout(searchDebounceTimeout);
    
    if (searchQuery.value.trim().length < 2) {
        searchResults.value = [];
        searchCount.value = 0;
        isSearching.value = false;
        return;
    }

    isSearching.value = true;
    searchDebounceTimeout = setTimeout(async () => {
        try {
            const res = await fetch(`/api/search?year=${activeFiscalYear.value}&q=${encodeURIComponent(searchQuery.value.trim())}`);
            const data = await res.json();
            searchResults.value = data.results || [];
            searchCount.value = data.count || 0;
        } catch (err) {
            console.error('Search error:', err);
        } finally {
            isSearching.value = false;
        }
    }, 200);
};

const filteredSearchResults = computed(() => {
    if (searchFilter.value === 'bku') {
        return searchResults.value.filter(r => Boolean(r.bku_tab));
    }
    if (searchFilter.value === 'bank') {
        return searchResults.value.filter(r => Boolean(r.bank_month));
    }
    return searchResults.value;
});

const jumpToResult = (url) => {
    closeSearch();
    router.visit(url);
};
</script>

<template>
    <div class="min-h-screen flex bg-[#f8faf9] font-sans text-slate-800 print:bg-white print:block print:min-h-0">
        
        <!-- ================= MOBILE SIDEBAR BACKDROP ================= -->
        <div 
            v-if="sidebarOpen" 
            @click="sidebarOpen = false" 
            class="fixed inset-0 z-40 bg-black/50 md:hidden print:hidden"
        ></div>

        <!-- ================= GLOBAL DARK GREEN SIDEBAR ================= -->
        <aside 
            :class="[
                'bg-[#04331a] text-white flex flex-col shrink-0 z-40 select-none print:hidden no-print',
                // Desktop: murni animasi lebar (width) halus tanpa transform-translate yang menyebabkan efek melayang/kedip
                'hidden md:flex md:sticky md:top-0 md:h-screen md:transition-[width] md:duration-300 md:ease-in-out md:overflow-hidden',
                sidebarOpen ? 'md:w-60' : 'md:w-0',
                // Mobile: drawer meluncur dengan overlay
                'fixed inset-y-0 left-0 z-50 w-60 transform transition-transform duration-300 ease-in-out md:static md:transform-none',
                sidebarOpen ? 'max-md:translate-x-0 max-md:flex' : 'max-md:-translate-x-full'
            ]"
        >
            <!-- Wrapper dengan w-60 agar konten tidak terjepit saat transisi animasi -->
            <div class="w-60 flex flex-col h-full shrink-0">
                <!-- Logo & Brand Header -->
                <div class="p-5 flex flex-col items-start gap-1">
                    <button
                        type="button"
                        @click="refreshPage"
                        class="flex items-center gap-3 text-left cursor-pointer hover:opacity-90 active:scale-[0.98] transition-all focus:outline-none"
                        title="Muat ulang halaman (Refresh)"
                    >
                        <img 
                            :src="'/images/logo-banyumas-sm.png'" 
                            alt="Logo Daerah Kabupaten Banyumas" 
                            class="w-9 h-9 object-contain drop-shadow shrink-0" 
                        />
                        <div class="font-black text-white text-xs sm:text-sm tracking-wider uppercase leading-tight">
                            KEUDPRD<br>BANYUMAS
                        </div>
                    </button>

                    <!-- Pemilih Tahun Anggaran (Multi-Year Switcher) -->
                    <div class="mt-2.5 w-full flex items-center gap-1.5">
                        <div class="relative flex-1">
                            <select
                                :value="activeFiscalYear"
                                @change="switchFiscalYear($event.target.value)"
                                class="w-full appearance-none pl-2.5 pr-6 py-1 bg-emerald-900/90 hover:bg-emerald-800/90 border border-emerald-700/70 text-emerald-100 text-[11px] font-mono font-bold rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-400 cursor-pointer transition-colors"
                                title="Pilih Tahun Anggaran (lihat tahun aktif atau arsip tahun lama)"
                            >
                                <option
                                    v-for="yr in availableFiscalYears"
                                    :key="yr"
                                    :value="yr"
                                    class="bg-[#04331a] text-white"
                                >
                                    TA {{ yr }}{{ yr === defaultFiscalYear ? ' (Aktif)' : ' (Arsip)' }}
                                </option>
                            </select>
                            <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[9px] text-emerald-300">▼</span>
                        </div>
                        <Link
                            href="/pengaturan"
                            class="px-2 py-1 bg-emerald-900/70 hover:bg-emerald-700 text-emerald-200 border border-emerald-700/60 rounded-lg text-[10px] font-bold transition-colors shrink-0"
                            title="Kelola & Buka Tahun Anggaran Baru"
                        >
                            +TA
                        </Link>
                    </div>
                </div>

                <!-- Navigation Links -->
                <nav class="flex-1 px-3 py-2 space-y-1 text-xs overflow-y-auto">
                    <Link
                        v-for="item in navItems"
                        :key="item.name"
                        :href="item.href"
                        @click="handleNavClick"
                        :class="[
                            'flex items-center gap-2.5 px-3 py-2.5 rounded-lg font-medium transition-all whitespace-nowrap',
                            isCurrentRoute(item.routeName)
                                ? 'bg-[#107c41] text-white font-bold shadow-xs'
                                : 'text-emerald-100/75 hover:bg-emerald-900/40 hover:text-white'
                        ]"
                    >
                        <component :is="item.icon" class="w-4 h-4 shrink-0" />
                        <span>{{ item.name }}</span>
                    </Link>
                </nav>

                <!-- Sidebar Bottom Info (Optional SKPD) -->
                <div class="p-4 border-t border-emerald-900/40 text-[11px] text-emerald-300/60 hidden md:block whitespace-nowrap">
                    Setwan Kab. Banyumas
                </div>
            </div>
        </aside>

        <!-- ================= RIGHT MAIN LAYOUT ================= -->
        <div class="flex-1 flex flex-col min-w-0 min-h-screen print:min-h-0 print:block print:w-full">
            
            <!-- TOP NAVBAR -->
            <header class="bg-white border-b border-slate-200 px-4 sm:px-6 lg:px-8 py-2.5 flex items-center justify-between sticky top-0 z-30 shadow-2xs print:hidden no-print">
                
                <!-- Left: SKPD & Sidebar Toggle Button -->
                <div class="flex items-center gap-3 shrink-0">
                    <button 
                        type="button"
                        @click="toggleSidebar" 
                        class="w-9 h-9 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 hover:text-emerald-800 transition-colors shadow-2xs flex items-center justify-center cursor-pointer shrink-0"
                        :title="sidebarOpen ? 'Sembunyikan Sidebar' : 'Tampilkan Sidebar'"
                    >
                        <Menu class="w-4.5 h-4.5 text-emerald-700" />
                    </button>

                    <button
                        type="button"
                        @click="refreshPage"
                        class="flex items-center gap-2 text-left cursor-pointer hover:opacity-80 active:scale-[0.98] transition-all rounded-md px-1.5 py-1 hover:bg-slate-50 focus:outline-none"
                        title="Muat ulang halaman (Refresh)"
                    >
                        <img 
                            :src="'/images/logo-banyumas-sm.png'" 
                            alt="Logo Banyumas" 
                            class="w-6 h-6 object-contain hidden sm:block shrink-0" 
                        />
                        <div class="font-bold text-xs sm:text-sm tracking-wider uppercase text-slate-800">
                            SETWAN KAB. BANYUMAS
                        </div>
                    </button>
                </div>

                <!-- Center: Global Search Bar Trigger (Ctrl + K) -->
                <div class="flex-1 max-w-md mx-4 hidden sm:block">
                    <button 
                        type="button"
                        @click="openSearch"
                        class="w-full flex items-center justify-between px-3.5 py-1.5 rounded-xl bg-slate-100/90 hover:bg-slate-100 border border-slate-200 hover:border-emerald-500 text-slate-500 hover:text-slate-800 transition-all text-xs group cursor-pointer shadow-2xs"
                        title="Buka Pencarian Transaksi Global (Ctrl + K)"
                    >
                        <div class="flex items-center gap-2">
                            <Search class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-700 transition-colors" />
                            <span class="text-slate-500 group-hover:text-slate-700 font-normal">Cari transaksi, BKU, bank, nominal...</span>
                        </div>
                        <kbd class="hidden md:inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-mono font-bold bg-white border border-slate-300 rounded text-slate-500 shadow-2xs">
                            Ctrl K
                        </kbd>
                    </button>
                </div>

                <!-- Right: Profile, Mobile Search & Logout Button -->
                <div class="flex items-center gap-2 sm:gap-3 text-xs shrink-0">
                    <!-- Mobile Search Button -->
                    <button
                        type="button"
                        @click="openSearch"
                        class="sm:hidden p-2 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600 hover:text-emerald-700 cursor-pointer"
                        title="Cari Transaksi"
                    >
                        <Search class="w-4 h-4 text-emerald-700" />
                    </button>
                    
                    <!-- Bendahara Special Badge (only if logged in as Indah) -->
                    <div 
                        v-if="authUser && (authUser.role === 'Bendahara' || authUser.email?.includes('bendahara') || authUser.name?.toLowerCase().includes('indah'))" 
                        class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-100 border border-slate-200 text-slate-700"
                    >
                        <User class="w-3.5 h-3.5 text-emerald-600" />
                        <span class="text-slate-500">Bendahara:</span>
                        <span class="font-bold text-slate-800">INDAH SETIYOWATI, A.Md.AB.</span>
                    </div>

                    <!-- User Profile Info -->
                    <template v-if="authUser">
                        <div class="flex items-center gap-2.5 pl-2 sm:border-l sm:border-slate-200">
                            <div class="w-8 h-8 rounded-full bg-[#107c41] text-white font-bold flex items-center justify-center text-xs shadow-2xs">
                                {{ authUser.name.charAt(0) }}
                            </div>
                            <div class="text-left hidden sm:block">
                                <div class="font-bold text-xs text-slate-900 leading-tight">
                                    {{ authUser.name }}
                                </div>
                                <div class="text-[9px] text-[#107c41] font-black uppercase tracking-wider">
                                    {{ authUser.role || 'Super Admin' }}
                                </div>
                            </div>
                        </div>

                        <!-- Logout Button -->
                        <button
                            @click="logout"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 rounded-lg text-xs font-semibold shadow-2xs transition-colors cursor-pointer"
                            title="Keluar dari sistem"
                        >
                            <LogOut class="w-3.5 h-3.5" />
                            <span>Keluar</span>
                        </button>
                    </template>

                    <!-- Guest Mode Button -->
                    <template v-else>
                        <Link
                            href="/login"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-[#107c41] hover:bg-[#0e6b37] text-white text-xs font-bold shadow-2xs transition-colors"
                        >
                            <LogIn class="w-3.5 h-3.5" />
                            <span>Masuk / Login</span>
                        </Link>
                    </template>
                </div>
            </header>

            <!-- MAIN BODY CONTENT -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-[1920px] w-full mx-auto print:p-0 print:m-0 print:max-w-none print:w-full print:block">
                <div
                    v-if="isViewingArchiveYear"
                    class="mb-4 px-4 py-2.5 bg-amber-50 border border-amber-300 rounded-xl flex flex-wrap items-center justify-between gap-2 text-xs text-amber-950 shadow-2xs print:hidden no-print"
                >
                    <div class="flex items-center gap-2">
                        <Calendar class="w-4 h-4 text-amber-700 shrink-0" />
                        <span>
                            Anda sedang membuka <strong>Arsip Tahun Anggaran {{ activeFiscalYear }}</strong>
                            (Tahun aktif berjalan: <strong>TA {{ defaultFiscalYear }}</strong>).
                        </span>
                    </div>
                    <button
                        type="button"
                        @click="switchFiscalYear(defaultFiscalYear)"
                        class="px-3 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-bold text-[11px] transition-colors cursor-pointer"
                    >
                        Kembali ke TA {{ defaultFiscalYear }}
                    </button>
                </div>
                <slot />
            </main>

            <!-- FOOTER BAR -->
            <footer class="mt-auto px-6 lg:px-8 py-3.5 border-t border-slate-200/80 bg-white flex items-center justify-between text-xs text-slate-500 select-none print:hidden no-print">
                <div class="flex items-center gap-1.5 text-emerald-700 font-semibold">
                    <CheckCircle2 class="w-4 h-4" />
                    <span>Ready</span>
                </div>
                <div class="text-slate-400 font-medium">
                    &copy; 2026 Setwan Banyumas
                </div>
            </footer>

        </div>

        <!-- ================= MODAL: GLOBAL SEARCH (CTRL + K) ================= -->
        <div 
            v-if="isSearchOpen" 
            class="fixed inset-0 z-50 flex items-start justify-center pt-14 sm:pt-20 p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            @click.self="closeSearch"
        >
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-2xl w-full flex flex-col overflow-hidden max-h-[82vh]">
                
                <!-- Search Input Bar -->
                <div class="p-3 sm:p-4 border-b border-slate-200 flex items-center gap-3 bg-white sticky top-0 z-10">
                    <Search class="w-5 h-5 text-emerald-600 shrink-0" />
                    <input 
                        ref="searchInputRef"
                        v-model="searchQuery" 
                        @input="onSearchInput"
                        type="text" 
                        placeholder="Ketik uraian, no bukti, nominal, nama PPTK... (Esc untuk batal)"
                        class="flex-1 text-sm sm:text-base outline-none border-none focus:ring-0 p-0 text-slate-800 placeholder-slate-400 font-medium"
                    />
                    <Loader2 v-if="isSearching" class="w-4 h-4 text-emerald-600 animate-spin shrink-0" />
                    <button 
                        v-else-if="searchQuery" 
                        @click="clearSearch" 
                        class="p-1 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                    <button 
                        @click="closeSearch" 
                        class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 text-[11px] font-bold rounded-md cursor-pointer"
                    >
                        Esc
                    </button>
                </div>

                <!-- Category Filters & Summary Toolbar -->
                <div class="px-4 py-2 bg-slate-50 border-b border-slate-200 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-1.5">
                        <button 
                            @click="searchFilter = 'all'" 
                            :class="['px-2.5 py-1 rounded-md font-semibold transition-colors cursor-pointer', searchFilter === 'all' ? 'bg-[#107c41] text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-200']"
                        >
                            Semua ({{ searchResults.length }})
                        </button>
                        <button 
                            @click="searchFilter = 'bku'" 
                            :class="['px-2.5 py-1 rounded-md font-semibold transition-colors cursor-pointer', searchFilter === 'bku' ? 'bg-[#107c41] text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-200']"
                        >
                            BKU Per PPTK
                        </button>
                        <button 
                            @click="searchFilter = 'bank'" 
                            :class="['px-2.5 py-1 rounded-md font-semibold transition-colors cursor-pointer', searchFilter === 'bank' ? 'bg-[#107c41] text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-200']"
                        >
                            Buku Bank
                        </button>
                    </div>

                    <div v-if="searchQuery.trim().length >= 2" class="text-slate-500 text-[11px]">
                        <span>Ditemukan <b>{{ filteredSearchResults.length }}</b> transaksi</span>
                    </div>
                </div>

                <!-- Search Results Container -->
                <div class="flex-1 overflow-y-auto p-3 sm:p-4 space-y-2 min-h-[160px] max-h-[55vh]">
                    
                    <!-- State: Initial Help / Tips -->
                    <div v-if="!searchQuery || searchQuery.trim().length < 2" class="py-8 text-center text-slate-500">
                        <Search class="w-8 h-8 text-slate-300 mx-auto mb-2" />
                        <div class="font-bold text-sm text-slate-700">Pencarian Transaksi Global</div>
                        <div class="text-xs text-slate-500 max-w-sm mx-auto mt-1">
                            Cari data di seluruh 20 sheet BKU dan 10-12 bulan Buku Bank berdasarkan kata kunci, nominal, tanggal, atau nomor bukti.
                        </div>
                        <div class="flex flex-wrap items-center justify-center gap-1.5 mt-3 text-[11px]">
                            <span class="text-slate-400">Contoh:</span>
                            <button @click="searchQuery = 'GAJI'; onSearchInput()" class="px-2 py-0.5 rounded bg-slate-100 hover:bg-emerald-50 text-slate-700 hover:text-emerald-700 border border-slate-200 cursor-pointer">GAJI</button>
                            <button @click="searchQuery = 'Gaya'; onSearchInput()" class="px-2 py-0.5 rounded bg-slate-100 hover:bg-emerald-50 text-slate-700 hover:text-emerald-700 border border-slate-200 cursor-pointer">Gaya</button>
                            <button @click="searchQuery = 'Ritamart'; onSearchInput()" class="px-2 py-0.5 rounded bg-slate-100 hover:bg-emerald-50 text-slate-700 hover:text-emerald-700 border border-slate-200 cursor-pointer">Ritamart</button>
                            <button @click="searchQuery = '1000000'; onSearchInput()" class="px-2 py-0.5 rounded bg-slate-100 hover:bg-emerald-50 text-slate-700 hover:text-emerald-700 border border-slate-200 cursor-pointer">1.000.000</button>
                        </div>
                    </div>

                    <!-- State: Not Found -->
                    <div v-else-if="filteredSearchResults.length === 0 && !isSearching" class="py-8 text-center text-slate-500">
                        <AlertCircle class="w-8 h-8 text-slate-300 mx-auto mb-2" />
                        <div class="font-bold text-sm text-slate-700">Tidak ada transaksi yang cocok</div>
                        <div class="text-xs text-slate-500 mt-1">
                            Tidak ditemukan hasil untuk "{{ searchQuery }}". Coba kata kunci atau nominal yang berbeda.
                        </div>
                    </div>

                    <!-- State: Results List -->
                    <div 
                        v-for="item in filteredSearchResults" 
                        :key="item.id"
                        class="p-3 rounded-xl border border-slate-200/90 hover:border-emerald-500 hover:bg-emerald-50/20 transition-all bg-white shadow-2xs group flex flex-col gap-2"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-mono text-[11px] font-bold">
                                        <Calendar class="w-3 h-3 text-slate-500" />
                                        {{ item.date }}
                                    </span>
                                    <span v-if="item.pptk_name" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-50 text-blue-800 border border-blue-200 text-[10px] font-bold">
                                        {{ item.pptk_name }}
                                    </span>
                                    <span v-if="item.document_number && item.document_number !== '-'" class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 font-mono text-[10px]">
                                        {{ item.document_number }}
                                    </span>
                                    <span :class="[
                                        'px-2 py-0.5 rounded-md text-[10px] font-bold uppercase',
                                        item.flow_direction === 'receipt' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'
                                    ]">
                                        {{ item.flow_direction === 'receipt' ? 'Penerimaan' : 'Pengeluaran' }}
                                    </span>
                                </div>
                                <div class="font-bold text-xs sm:text-sm text-slate-900 group-hover:text-emerald-900 transition-colors">
                                    {{ item.description }}
                                </div>
                                <div v-if="item.notes" class="text-[11px] text-slate-500 mt-0.5 italic">
                                    Catatan: {{ item.notes }}
                                </div>
                            </div>

                            <!-- Nominal Display -->
                            <div class="text-right shrink-0">
                                <div :class="['font-mono font-bold text-xs sm:text-sm', item.flow_direction === 'receipt' ? 'text-emerald-700' : 'text-rose-700']">
                                    {{ item.flow_direction === 'receipt' ? '+' : '-' }} {{ item.amount_formatted }}
                                </div>
                            </div>
                        </div>

                        <!-- Direct Jump Action Buttons -->
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2 text-xs">
                            <span class="text-[11px] text-slate-400">Buka langsung ke:</span>
                            <div class="flex items-center gap-2">
                                <button 
                                    @click="jumpToResult(item.bku_url)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-600 text-emerald-800 hover:text-white border border-emerald-200 transition-colors font-bold text-[11px] cursor-pointer shadow-2xs"
                                    :title="'Buka sheet BKU ' + item.bku_tab"
                                >
                                    <BookOpen class="w-3 h-3" />
                                    <span>BKU Sheet: {{ item.bku_tab }}</span>
                                    <ArrowUpRight class="w-3 h-3" />
                                </button>
                                <button 
                                    @click="jumpToResult(item.bank_url)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-700 text-slate-700 hover:text-white border border-slate-200 transition-colors font-bold text-[11px] cursor-pointer shadow-2xs"
                                    :title="'Buka Buku Bank Bulan ' + item.bank_tab"
                                >
                                    <Landmark class="w-3 h-3" />
                                    <span>Bank: {{ item.bank_tab }}</span>
                                    <ArrowUpRight class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Footer Hint -->
                <div class="px-4 py-2.5 bg-slate-50 border-t border-slate-200 text-[11px] text-slate-500 flex items-center justify-between select-none">
                    <div class="flex items-center gap-2">
                        <span>Pintasan:</span>
                        <kbd class="px-1.5 py-0.5 bg-white border border-slate-300 rounded font-mono font-bold text-slate-600">Ctrl K</kbd>
                        <span>Cari</span>
                        <kbd class="px-1.5 py-0.5 bg-white border border-slate-300 rounded font-mono font-bold text-slate-600">Esc</kbd>
                        <span>Tutup</span>
                    </div>
                    <span class="text-emerald-700 font-semibold">KeuDPRD Banyumas</span>
                </div>

            </div>
        </div>
    </div>
</template>
