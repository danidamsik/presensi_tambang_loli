<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Head title="Sistem Presensi" />

    <div class="min-h-screen relative overflow-hidden bg-slate-50 text-slate-900 flex flex-col items-center justify-center">
        <!-- Background Ornaments -->
        <div class="absolute inset-0 z-0 pointer-events-none">
            <div class="absolute top-[-20%] left-[-10%] w-[600px] h-[600px] rounded-full bg-amber-300/30 blur-[150px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[800px] h-[800px] rounded-full bg-orange-200/30 blur-[180px]"></div>
        </div>

        <div class="z-10 w-full max-w-5xl mx-auto px-6 text-center">
            
            <div class="flex justify-center mb-10 transform hover:scale-105 transition-transform duration-500">
                <ApplicationLogo class="text-slate-900 drop-shadow-sm" />
            </div>

            <h1 class="text-5xl md:text-7xl font-extrabold tracking-tight mb-6 bg-clip-text text-transparent bg-gradient-to-br from-slate-900 to-slate-600">
                Presensi Digital <br/>
                <span class="text-amber-600 bg-clip-text bg-gradient-to-r from-amber-500 to-orange-600">Terintegrasi</span>
            </h1>

            <p class="mt-4 text-xl md:text-2xl text-slate-600 max-w-2xl mx-auto leading-relaxed mb-12">
                Sistem cerdas pencatatan kehadiran dan lembur untuk ekosistem kerja Tambang Loli yang cepat, presisi, dan transparan.
            </p>

            <div v-if="canLogin" class="flex flex-col sm:flex-row justify-center gap-4">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="px-8 py-4 bg-gradient-to-r from-amber-500 to-orange-500 rounded-xl font-bold text-white uppercase tracking-widest hover:from-amber-400 hover:to-orange-400 transition-all duration-300 shadow-[0_0_20px_rgba(245,158,11,0.2)] hover:shadow-[0_0_30px_rgba(245,158,11,0.4)] transform hover:-translate-y-1"
                >
                    Masuk ke Dashboard
                </Link>

                <template v-else>
                    <Link
                        :href="route('login')"
                        class="px-8 py-4 bg-gradient-to-r from-amber-500 to-orange-500 rounded-xl font-bold text-white uppercase tracking-widest hover:from-amber-400 hover:to-orange-400 transition-all duration-300 shadow-[0_0_20px_rgba(245,158,11,0.2)] hover:shadow-[0_0_30px_rgba(245,158,11,0.4)] transform hover:-translate-y-1"
                    >
                        Login Karyawan
                    </Link>

                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="px-8 py-4 bg-white/80 backdrop-blur-sm border border-slate-200 rounded-xl font-bold text-slate-700 uppercase tracking-widest hover:bg-slate-50 transition-all duration-300 shadow-md transform hover:-translate-y-1"
                    >
                        Daftar Baru
                    </Link>
                </template>
            </div>
        </div>

        <div class="absolute bottom-4 text-center text-sm text-slate-500 z-10 w-full">
            Laravel v{{ laravelVersion }} (PHP v{{ phpVersion }}) 
        </div>
    </div>
</template>

<style>
/* Custom styles if necessary, but Tailwind covers everything */
</style>
