<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col justify-center items-center pt-6 sm:pt-0 bg-slate-50 relative overflow-hidden text-slate-900 transition-colors dark:bg-slate-950 dark:text-slate-100">
        <div class="absolute top-4 right-4 z-20">
            <ThemeToggle compact />
        </div>

        <!-- Abstract gradient background spheres -->
        <div class="absolute inset-0 z-0 pointer-events-none">
            <div class="absolute -top-20 -left-20 w-96 h-96 rounded-full bg-amber-300/30 blur-[120px]"></div>
            <div class="absolute bottom-0 right-0 w-[500px] h-[500px] rounded-full bg-orange-200/30 blur-[150px] dark:bg-orange-500/20"></div>
        </div>

        <div class="z-10 mb-8 transform hover:scale-105 transition-transform duration-500">
            <Link href="/">
                <ApplicationLogo class="w-full text-slate-900 drop-shadow-sm dark:text-slate-100" />
            </Link>
        </div>

        <div
            class="z-10 w-full sm:max-w-md px-8 py-10 bg-white/80 backdrop-blur-2xl border border-slate-200 shadow-xl sm:rounded-3xl relative transition-colors dark:bg-slate-900/80 dark:border-slate-700"
        >
            <slot />
        </div>
    </div>
</template>
