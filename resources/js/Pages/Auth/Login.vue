<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    LogIn, 
    Lock, 
    Mail, 
    ArrowLeft 
} from 'lucide-vue-next';

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="min-h-screen bg-[#e8f3ec] flex items-center justify-center p-3 sm:p-5 lg:p-8 font-sans selection:bg-emerald-600 selection:text-white">
        <Head title="Masuk / Login - KeuDPRD Banyumas" />

        <!-- Master Card: Responsive, Wide & Proportionate -->
        <div class="w-full max-w-[1280px] min-h-[640px] lg:min-h-[720px] border-[3.5px] border-[#0e5c36] rounded-[28px] md:rounded-[36px] bg-white shadow-2xl overflow-hidden grid grid-cols-1 md:grid-cols-2">
            
            <!-- LEFT COLUMN: PHOTO BACKGROUND & BRANDING -->
            <div 
                class="relative bg-cover bg-center min-h-[360px] md:min-h-full flex flex-col justify-between overflow-hidden"
                style="background-image: url('/images/bg-dprd.jpg');"
            >
                <!-- Deep Forest Gradient Overlay -->
                <div class="absolute inset-0 bg-gradient-to-b from-[#084828]/95 via-[#084828]/55 to-transparent pointer-events-none"></div>

                <!-- Branding Header Content -->
                <div class="relative z-10 p-8 sm:p-10 lg:p-14">
                    <!-- Official Banyumas Logo Badge -->
                    <div class="w-16 h-16 rounded-2xl bg-white/15 backdrop-blur-md border border-white/25 flex items-center justify-center p-2 mb-6 shadow-sm">
                        <img 
                            :src="'/images/logo-banyumas-sm.png'" 
                            alt="Logo Daerah Kabupaten Banyumas" 
                            class="w-full h-full object-contain drop-shadow" 
                        />
                    </div>

                    <!-- Title -->
                    <h1 class="text-3xl lg:text-4xl font-black text-white tracking-wide uppercase leading-tight drop-shadow-sm">
                        KEUDPRD BANYUMAS
                    </h1>

                    <!-- Subtitle -->
                    <p class="text-xs sm:text-sm lg:text-base text-emerald-100 font-medium leading-relaxed mt-3 drop-shadow-sm">
                        Sistem Digitalisasi BKU Per PPTK & Buku Bank TA 2026<br>
                        <span class="text-emerald-200/90 font-normal">Sekretariat DPRD Kabupaten Banyumas</span>
                    </p>
                </div>

                <!-- Bottom spacer for photo exposure -->
                <div class="relative z-10 p-6 md:p-8"></div>
            </div>

            <!-- RIGHT COLUMN: CLEAN MANUAL LOGIN FORM -->
            <div class="bg-white p-8 sm:p-12 lg:p-16 flex flex-col justify-center">
                
                <!-- Auth Card Header -->
                <div class="flex items-center justify-between pb-6 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <Lock class="w-5 h-5 text-emerald-700 stroke-[2.5]" />
                        <span class="text-sm sm:text-base font-black text-slate-800 tracking-wider uppercase">
                            AUTENTIKASI PENGGUNA
                        </span>
                    </div>
                    <span class="text-[11px] font-bold text-slate-500 bg-slate-100 border border-slate-200/80 px-3 py-1 rounded-md tracking-wider uppercase">
                        INTERNAL
                    </span>
                </div>

                <!-- Login Form (Manual) -->
                <form @submit.prevent="submit" class="mt-8 space-y-5">
                    <div>
                        <label for="email" class="block font-bold text-slate-800 text-xs sm:text-sm mb-2">
                            Alamat Email Kedinasan
                        </label>
                        <div class="relative">
                            <Mail class="w-4 h-4 text-slate-400 absolute left-4 top-3.5" />
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="nama@dprd.banyumaskab.go.id"
                                class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-emerald-600 focus:bg-white focus:border-transparent transition-all"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-rose-600 font-medium">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <label for="password" class="block font-bold text-slate-800 text-xs sm:text-sm mb-2">
                            Kata Sandi
                        </label>
                        <div class="relative">
                            <Lock class="w-4 h-4 text-slate-400 absolute left-4 top-3.5" />
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-emerald-600 focus:bg-white focus:border-transparent transition-all"
                            />
                        </div>
                        <p v-if="form.errors.password" class="mt-1.5 text-xs text-rose-600 font-medium">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2.5 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                v-model="form.remember"
                                class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                            />
                            <span class="text-xs sm:text-sm font-semibold text-slate-700">Ingat saya di perangkat ini</span>
                        </label>
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-3.5 px-5 rounded-xl bg-[#007a43] hover:bg-[#006838] text-white text-xs sm:text-sm font-bold shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2.5 cursor-pointer disabled:opacity-50"
                        >
                            <span v-if="form.processing" class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
                            <template v-else>
                                <LogIn class="w-4 h-4 stroke-[2.2]" />
                                <span>Masuk ke Sistem Keuangan</span>
                            </template>
                        </button>
                    </div>
                </form>

                <!-- Back to Dashboard -->
                <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                    <Link
                        href="/dashboard"
                        class="text-xs sm:text-sm font-bold text-slate-700 hover:text-emerald-700 inline-flex items-center gap-2 transition-colors"
                    >
                        <ArrowLeft class="w-4 h-4" /> Kembali ke Pratinjau Dashboard
                    </Link>
                </div>

            </div>
        </div>
    </div>
</template>
