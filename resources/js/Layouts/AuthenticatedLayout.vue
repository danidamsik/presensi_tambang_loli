<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const isSidebarCollapsed = ref(false);
const page = usePage();
const SIDEBAR_STORAGE_KEY = 'admin-sidebar-collapsed';
let sidebarResizeTimeoutId = null;
let sidebarResizeFrameId = null;

const isAdmin = computed(() => page.props.auth?.user?.role === 'Admin');
const homeRouteName = computed(() => (isAdmin.value ? 'dashboard' : 'home'));

const navLinks = computed(() => {
    if (isAdmin.value) {
        return [
            { name: 'dashboard', label: 'Dashboard', description: 'Ringkasan operasional harian' },
            { name: 'admin.employees.index', label: 'Karyawan', description: 'Kelola data akun pegawai' },
            { name: 'admin.settings.index', label: 'Pengaturan', description: 'Lokasi kerja dan jam operasional' },
            { name: 'admin.attendances.index', label: 'Presensi', description: 'Tinjau catatan kehadiran' },
            { name: 'admin.overtimes.index', label: 'Lembur', description: 'Approval dan histori lembur' },
            { name: 'admin.reports.index', label: 'Laporan', description: 'Rekap dan ekspor data' },
        ];
    }

    return [{ name: 'home', label: 'Home', description: 'Ringkasan aktivitas Anda' }];
});

const currentNav = computed(() => navLinks.value.find((link) => route().current(link.name)) ?? navLinks.value[0]);

const displayName = computed(() => {
    const user = page.props.auth?.user;

    return user?.full_name ?? user?.name ?? 'User';
});

const userInitials = computed(() => {
    const name = displayName.value.trim();

    if (!name) {
        return 'U';
    }

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
});

const toggleNavigationDropdown = () => {
    showingNavigationDropdown.value = !showingNavigationDropdown.value;
};

const closeNavigationDropdown = () => {
    showingNavigationDropdown.value = false;
};

const persistSidebarState = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(SIDEBAR_STORAGE_KEY, String(isSidebarCollapsed.value));
};

const emitAdminLayoutResize = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new Event('resize'));
    window.dispatchEvent(new CustomEvent('admin-layout:resize', {
        detail: {
            sidebarCollapsed: isSidebarCollapsed.value,
        },
    }));
};

const scheduleAdminLayoutResize = () => {
    if (typeof window === 'undefined') {
        return;
    }

    if (sidebarResizeFrameId) {
        window.cancelAnimationFrame(sidebarResizeFrameId);
    }

    if (sidebarResizeTimeoutId) {
        window.clearTimeout(sidebarResizeTimeoutId);
    }

    sidebarResizeFrameId = window.requestAnimationFrame(() => {
        emitAdminLayoutResize();

        sidebarResizeTimeoutId = window.setTimeout(() => {
            emitAdminLayoutResize();
        }, 120);
    });
};

const toggleDesktopSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    persistSidebarState();
    scheduleAdminLayoutResize();
};

onMounted(() => {
    if (typeof window === 'undefined' || !isAdmin.value) {
        return;
    }

    isSidebarCollapsed.value = window.localStorage.getItem(SIDEBAR_STORAGE_KEY) === 'true';
    scheduleAdminLayoutResize();
});

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return;
    }

    if (sidebarResizeTimeoutId) {
        window.clearTimeout(sidebarResizeTimeoutId);
    }

    if (sidebarResizeFrameId) {
        window.cancelAnimationFrame(sidebarResizeFrameId);
    }
});
</script>

<template>
    <div v-if="isAdmin" class="min-h-screen bg-slate-100 text-slate-900 transition-colors dark:bg-slate-950 dark:text-slate-100">
        <div class="pointer-events-none fixed inset-0 overflow-hidden">
            <div class="absolute inset-x-0 top-0 h-72 bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.22),_transparent_42%),radial-gradient(circle_at_top_right,_rgba(15,23,42,0.12),_transparent_38%)] dark:bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.18),_transparent_38%),radial-gradient(circle_at_top_right,_rgba(148,163,184,0.14),_transparent_32%)]"></div>
            <div class="absolute bottom-0 left-1/2 h-80 w-80 -translate-x-1/2 rounded-full bg-amber-200/30 blur-3xl dark:bg-amber-500/10"></div>
        </div>

        <div class="relative flex min-h-screen">
            <aside
                v-show="!isSidebarCollapsed"
                class="hidden w-80 shrink-0 overflow-hidden border-r border-slate-200/70 bg-white/85 px-6 py-8 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur xl:flex xl:flex-col dark:border-slate-800 dark:bg-slate-950/85"
            >
                <Link :href="route(homeRouteName)" class="inline-flex">
                    <ApplicationLogo class="max-w-full" />
                </Link>

                <div class="mt-10 rounded-3xl border border-slate-200/80 bg-slate-950 px-5 py-5 text-white shadow-xl shadow-slate-900/10 dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-xs uppercase tracking-[0.28em] text-amber-300">Admin Console</p>
                    <h1 class="mt-3 text-2xl font-semibold leading-tight">Panel kendali presensi dan lembur.</h1>
                    <p class="mt-3 text-sm text-slate-300">
                        Gunakan navigasi ini untuk memantau kehadiran, pengaturan lokasi, dan approval operasional.
                    </p>
                </div>

                <nav class="mt-8 space-y-2">
                    <Link
                        v-for="link in navLinks"
                        :key="link.name"
                        :href="route(link.name)"
                        class="group block rounded-2xl border px-4 py-4 transition"
                        :class="route().current(link.name)
                            ? 'border-amber-200 bg-amber-50 text-slate-900 shadow-sm dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-slate-100'
                            : 'border-transparent bg-white/70 text-slate-600 hover:border-slate-200 hover:bg-white hover:text-slate-900 dark:bg-slate-900/40 dark:text-slate-400 dark:hover:border-slate-700 dark:hover:bg-slate-900 dark:hover:text-slate-100'"
                        @click="closeNavigationDropdown"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold">{{ link.label }}</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                    {{ link.description }}
                                </p>
                            </div>
                            <span
                                class="flex h-10 w-10 items-center justify-center rounded-2xl text-sm font-semibold transition"
                                :class="route().current(link.name)
                                    ? 'bg-slate-900 text-white dark:bg-amber-400 dark:text-slate-950'
                                    : 'bg-slate-100 text-slate-500 group-hover:bg-amber-100 group-hover:text-amber-700 dark:bg-slate-800 dark:text-slate-300 dark:group-hover:bg-amber-500/15 dark:group-hover:text-amber-300'"
                            >
                                {{ link.label.slice(0, 1) }}
                            </span>
                        </div>
                    </Link>
                </nav>

                <div class="mt-auto rounded-3xl border border-slate-200/70 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-900/80">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-sm font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                            {{ userInitials }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ displayName }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $page.props.auth.user.email }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex items-center gap-3">
                        <ThemeToggle class="flex-1 justify-center" />
                        <Link
                            :href="route('profile.edit')"
                            class="inline-flex flex-1 items-center justify-center rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-white dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Profil
                        </Link>
                    </div>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-30 border-b border-white/60 bg-white/70 backdrop-blur xl:border-none xl:bg-transparent dark:border-slate-800 dark:bg-slate-950/80 xl:dark:bg-transparent">
                    <div class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                        <div class="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 xl:hidden dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                                @click="toggleNavigationDropdown"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path
                                        :d="showingNavigationDropdown ? 'M6 6L18 18M6 18L18 6' : 'M4 7H20M4 12H20M4 17H14'"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </button>

                            <button
                                type="button"
                                class="hidden h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 xl:inline-flex dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                                @click="toggleDesktopSidebar"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path
                                        :d="isSidebarCollapsed ? 'M4 12H20M14 6L20 12L14 18' : 'M4 12H20M10 6L4 12L10 18'"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </button>

                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400 dark:text-slate-500">Admin Workspace</p>
                                <h2 class="truncate text-lg font-semibold text-slate-900 dark:text-slate-100">{{ currentNav?.label }}</h2>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <ThemeToggle compact />

                            <div class="hidden items-center gap-3 rounded-2xl border border-white/70 bg-white/80 px-3 py-2 shadow-sm backdrop-blur sm:flex dark:border-slate-700 dark:bg-slate-900/80">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-100 text-sm font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                    {{ userInitials }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ displayName }}</p>
                                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">Administrator</p>
                                </div>
                            </div>

                            <Dropdown align="right" width="56">
                                <template #trigger>
                                    <span class="inline-flex">
                                        <button
                                            type="button"
                                            class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                                        >
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path
                                                    d="M12 5V5.01M12 12V12.01M12 19V19.01"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />
                                            </svg>
                                        </button>
                                    </span>
                                </template>

                                <template #content>
                                    <div class="px-4 py-3 text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">
                                        Akun Admin
                                    </div>
                                    <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                                    <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <div v-if="showingNavigationDropdown" class="border-t border-slate-200 bg-white/95 px-4 py-4 shadow-xl backdrop-blur xl:hidden dark:border-slate-800 dark:bg-slate-950/95">
                        <div class="space-y-2">
                            <Link
                                v-for="link in navLinks"
                                :key="link.name"
                                :href="route(link.name)"
                                class="block rounded-2xl border px-4 py-4 transition"
                                :class="route().current(link.name)
                                    ? 'border-amber-200 bg-amber-50 text-slate-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-slate-100'
                                    : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-white dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-200 dark:hover:bg-slate-900'"
                                @click="closeNavigationDropdown"
                            >
                                <p class="text-sm font-semibold">{{ link.label }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ link.description }}</p>
                            </Link>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <Link
                                :href="route('profile.edit')"
                                class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200"
                                @click="closeNavigationDropdown"
                            >
                                Profil
                            </Link>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white dark:bg-amber-400 dark:text-slate-950"
                            >
                                Log Out
                            </Link>
                        </div>
                    </div>
                </header>

                <main class="relative flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
                        <section
                            v-if="$slots.header"
                            class="rounded-[28px] border border-white/70 bg-white/80 p-5 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur sm:p-7 dark:border-slate-800 dark:bg-slate-950/80"
                        >
                            <slot name="header" />
                        </section>

                        <slot />
                    </div>
                </main>
            </div>
        </div>
    </div>

    <div v-else>
        <div class="min-h-screen bg-gray-100 transition-colors dark:bg-slate-900">
            <nav class="border-b border-gray-100 bg-white transition-colors dark:border-slate-700 dark:bg-slate-900">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <div class="flex shrink-0 items-center">
                                <Link :href="route(homeRouteName)">
                                    <ApplicationLogo class="block h-9 w-auto fill-current text-gray-800 dark:text-slate-100" />
                                </Link>
                            </div>

                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <Link
                                    v-for="link in navLinks"
                                    :key="link.name"
                                    :href="route(link.name)"
                                    class="inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out"
                                    :class="route().current(link.name)
                                        ? 'border-amber-400 text-gray-900 focus:border-amber-700 dark:text-slate-100'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 focus:border-gray-300 focus:text-gray-700 dark:text-slate-400 dark:hover:text-slate-100'"
                                >
                                    {{ link.label }}
                                </Link>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <ThemeToggle compact class="me-3" />

                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition ease-in-out duration-150 hover:text-gray-700 focus:outline-none dark:bg-slate-900 dark:text-slate-300 dark:hover:text-slate-100"
                                            >
                                                {{ displayName }}

                                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <div class="-me-2 flex items-center sm:hidden">
                            <ThemeToggle compact class="me-2" />
                            <button
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100 dark:focus:bg-slate-800 dark:focus:text-slate-100"
                                @click="toggleNavigationDropdown"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="space-y-1 pb-3 pt-2">
                        <Link
                            v-for="link in navLinks"
                            :key="link.name"
                            :href="route(link.name)"
                            class="block w-full border-l-4 py-2 pe-4 ps-3 text-start text-base font-medium transition duration-150 ease-in-out"
                            :class="route().current(link.name)
                                ? 'border-amber-400 bg-amber-50 text-amber-700 focus:border-amber-700 focus:bg-amber-100 focus:text-amber-800'
                                : 'border-transparent text-gray-600 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 focus:border-gray-300 focus:bg-gray-50 focus:text-gray-800 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100'"
                            @click="closeNavigationDropdown"
                        >
                            {{ link.label }}
                        </Link>
                    </div>

                    <div class="border-t border-gray-200 pb-1 pt-4 dark:border-slate-700">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800 dark:text-slate-100">{{ displayName }}</div>
                            <div class="text-sm font-medium text-gray-500 dark:text-slate-400">{{ $page.props.auth.user.email }}</div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <Link
                                :href="route('profile.edit')"
                                class="block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-gray-600 transition duration-150 ease-in-out hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                            >
                                Profile
                            </Link>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-gray-600 transition duration-150 ease-in-out hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                            >
                                Log Out
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>

            <header v-if="$slots.header" class="bg-white shadow transition-colors dark:border-b dark:border-slate-700 dark:bg-slate-900">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
