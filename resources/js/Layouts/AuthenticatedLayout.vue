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

    return [
        { name: 'home', label: 'Home', description: 'Ringkasan aktivitas Anda' },
        { name: 'employee.attendance.index', label: 'Presensi', description: 'Absen masuk dan pulang' },
        { name: 'employee.overtimes.index', label: 'Lembur', description: 'Pengajuan dan presensi lembur' },
    ];
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
        <div class="min-h-screen">
            <aside
                v-show="!isSidebarCollapsed"
                class="hidden border-r border-slate-200 bg-white px-4 py-6 xl:fixed xl:inset-y-0 xl:left-0 xl:z-20 xl:flex xl:h-screen xl:w-64 xl:flex-col xl:overflow-y-auto dark:border-slate-800 dark:bg-slate-900"
            >
                <Link :href="route(homeRouteName)" class="inline-flex">
                    <ApplicationLogo class="max-w-full" />
                </Link>

                <nav class="mt-6 space-y-1">
                    <Link
                        v-for="link in navLinks"
                        :key="link.name"
                        :href="route(link.name)"
                        class="block rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="route().current(link.name)
                            ? 'bg-slate-900 text-white dark:bg-amber-500/15 dark:text-amber-200'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-slate-50'"
                        @click="closeNavigationDropdown"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <div class="mt-auto border-t border-slate-200 pt-4 dark:border-slate-800">
                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ displayName }}</p>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $page.props.auth.user.email }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <ThemeToggle class="justify-center" />
                        <Link
                            :href="route('profile.edit')"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Profil
                        </Link>
                    </div>
                </div>
            </aside>

            <div
                class="flex min-h-screen flex-col transition-[padding] duration-300"
                :class="isSidebarCollapsed ? 'xl:pl-0' : 'xl:pl-64'"
            >
                <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95">
                    <div class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                        <div class="flex min-w-0 items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50 xl:hidden dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
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
                                class="hidden h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50 xl:inline-flex dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
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

                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ currentNav?.label }}</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <ThemeToggle compact />
                            <span class="hidden max-w-[12rem] truncate text-sm text-slate-600 sm:block dark:text-slate-300">{{ displayName }}</span>

                            <Dropdown align="right" width="56">
                                <template #trigger>
                                    <span class="inline-flex">
                                        <button
                                            type="button"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                                        >
                                            <span class="text-xs font-semibold">{{ userInitials }}</span>
                                        </button>
                                    </span>
                                </template>

                                <template #content>
                                    <div class="px-4 py-3 text-xs uppercase tracking-[0.12em] text-slate-400 dark:text-slate-500">Akun</div>
                                    <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                                    <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <div v-if="showingNavigationDropdown" class="border-t border-slate-200 bg-white px-4 py-3 xl:hidden dark:border-slate-800 dark:bg-slate-950">
                        <div class="space-y-1">
                            <Link
                                v-for="link in navLinks"
                                :key="link.name"
                                :href="route(link.name)"
                                class="block rounded-lg px-3 py-2 text-sm font-medium transition"
                                :class="route().current(link.name)
                                    ? 'bg-slate-900 text-white dark:bg-amber-500/15 dark:text-amber-200'
                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-slate-50'"
                                @click="closeNavigationDropdown"
                            >
                                {{ link.label }}
                            </Link>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <Link
                                :href="route('profile.edit')"
                                class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200"
                                @click="closeNavigationDropdown"
                            >
                                Profil
                            </Link>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white dark:bg-slate-100 dark:text-slate-900"
                            >
                                Log Out
                            </Link>
                        </div>
                    </div>
                </header>

                <main class="flex-1 px-4 py-4 sm:px-6 lg:px-8">
                    <div class="mx-auto flex w-full max-w-7xl flex-col gap-4">
                        <section
                            v-if="$slots.header"
                            class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                        >
                            <slot name="header" />
                        </section>

                        <slot />
                    </div>
                </main>
            </div>
        </div>
    </div>

    <div v-else class="min-h-screen bg-slate-100 text-slate-900 transition-colors dark:bg-slate-950 dark:text-slate-100">
        <div class="min-h-screen">
            <aside
                class="hidden border-r border-slate-200 bg-white px-4 py-6 xl:fixed xl:inset-y-0 xl:left-0 xl:z-20 xl:flex xl:h-screen xl:w-64 xl:flex-col xl:overflow-y-auto dark:border-slate-800 dark:bg-slate-900"
            >
                <Link :href="route(homeRouteName)" class="inline-flex">
                    <ApplicationLogo class="max-w-full" />
                </Link>

                <nav class="mt-6 space-y-1">
                    <Link
                        v-for="link in navLinks"
                        :key="link.name"
                        :href="route(link.name)"
                        class="block rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="route().current(link.name)
                            ? 'bg-slate-900 text-white dark:bg-amber-500/15 dark:text-amber-200'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-slate-50'"
                        @click="closeNavigationDropdown"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <div class="mt-auto border-t border-slate-200 pt-4 dark:border-slate-800">
                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ displayName }}</p>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $page.props.auth.user.email }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <ThemeToggle class="justify-center" />
                        <Link
                            :href="route('profile.edit')"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Profil
                        </Link>
                    </div>
                </div>
            </aside>

            <div class="flex min-h-screen flex-col xl:pl-64">
                <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95">
                    <div class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                        <div class="flex min-w-0 items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50 xl:hidden dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
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

                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ currentNav?.label }}</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <ThemeToggle compact />
                            <span class="hidden max-w-[12rem] truncate text-sm text-slate-600 sm:block dark:text-slate-300">{{ displayName }}</span>

                            <Dropdown align="right" width="56">
                                <template #trigger>
                                    <span class="inline-flex">
                                        <button
                                            type="button"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                                        >
                                            <span class="text-xs font-semibold">{{ userInitials }}</span>
                                        </button>
                                    </span>
                                </template>

                                <template #content>
                                    <div class="px-4 py-3 text-xs uppercase tracking-[0.12em] text-slate-400 dark:text-slate-500">Akun</div>
                                    <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                                    <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <div v-if="showingNavigationDropdown" class="border-t border-slate-200 bg-white px-4 py-3 xl:hidden dark:border-slate-800 dark:bg-slate-950">
                        <div class="space-y-1">
                            <Link
                                v-for="link in navLinks"
                                :key="link.name"
                                :href="route(link.name)"
                                class="block rounded-lg px-3 py-2 text-sm font-medium transition"
                                :class="route().current(link.name)
                                    ? 'bg-slate-900 text-white dark:bg-amber-500/15 dark:text-amber-200'
                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-slate-50'"
                                @click="closeNavigationDropdown"
                            >
                                {{ link.label }}
                            </Link>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <Link
                                :href="route('profile.edit')"
                                class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200"
                                @click="closeNavigationDropdown"
                            >
                                Profil
                            </Link>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white dark:bg-slate-100 dark:text-slate-900"
                            >
                                Log Out
                            </Link>
                        </div>
                    </div>
                </header>

                <main class="flex-1 px-4 py-4 sm:px-6 lg:px-8">
                    <div class="mx-auto flex w-full max-w-7xl flex-col gap-4">
                        <section
                            v-if="$slots.header"
                            class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                        >
                            <slot name="header" />
                        </section>

                        <slot />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
