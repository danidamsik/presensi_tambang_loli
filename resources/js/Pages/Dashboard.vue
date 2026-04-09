<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
    attendanceToday: {
        type: Object,
        required: true,
    },
    setting: {
        type: Object,
        default: null,
    },
    recentAttendances: {
        type: Array,
        required: true,
    },
    pendingOvertimes: {
        type: Array,
        required: true,
    },
    recentOvertimes: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const actionLoadingId = ref(null);
const actionLoadingType = ref(null);

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const todayLabel = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
    year: 'numeric',
}).format(new Date());

const attendanceRate = computed(() => {
    if (!props.summary.totalEmployees) {
        return 0;
    }

    return Math.round((props.summary.presentEmployeesToday / props.summary.totalEmployees) * 100);
});

const attendanceRateTone = computed(() => {
    if (attendanceRate.value >= 85) {
        return 'Siap operasional';
    }

    if (attendanceRate.value >= 60) {
        return 'Perlu dipantau';
    }

    return 'Butuh perhatian';
});

const summaryCards = computed(() => [
    {
        label: 'Total Karyawan',
        value: props.summary.totalEmployees,
        hint: 'Akun aktif dengan role employee',
        accent: 'from-slate-900 via-slate-800 to-slate-700 text-white shadow-slate-900/20 dark:from-slate-100 dark:via-slate-200 dark:to-slate-300 dark:text-slate-950 dark:shadow-slate-500/10',
    },
    {
        label: 'Kehadiran Hari Ini',
        value: `${attendanceRate.value}%`,
        hint: `${props.summary.presentEmployeesToday} dari ${props.summary.totalEmployees} karyawan sudah hadir`,
        accent: 'from-emerald-500 via-emerald-400 to-teal-300 text-emerald-950 shadow-emerald-500/25',
    },
    {
        label: 'Lembur Pending',
        value: props.summary.pendingOvertimes,
        hint: 'Butuh approval agar operasional tidak tertunda',
        accent: 'from-amber-300 via-amber-200 to-orange-100 text-amber-950 shadow-amber-500/20',
    },
    {
        label: 'Jam Lembur Disetujui',
        value: `${props.summary.approvedOvertimeHoursThisMonth} jam`,
        hint: 'Akumulasi lembur approved bulan berjalan',
        accent: 'from-sky-500 via-cyan-400 to-teal-200 text-sky-950 shadow-sky-500/25',
    },
]);

const attendanceMetrics = computed(() => [
    {
        label: 'Clock In',
        value: props.attendanceToday.clockedIn,
        hint: 'Sudah melakukan absensi masuk',
    },
    {
        label: 'Clock Out',
        value: props.attendanceToday.clockedOut,
        hint: 'Sudah menyelesaikan shift',
    },
    {
        label: 'Terlambat',
        value: props.attendanceToday.lateCheckIn,
        hint: 'Masuk melewati jam kerja',
    },
]);

const overtimeHighlights = computed(() => [
    {
        label: 'Approved',
        value: props.summary.approvedOvertimesThisMonth,
        tone: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
    },
    {
        label: 'Rejected',
        value: props.summary.rejectedOvertimesThisMonth,
        tone: 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
    },
]);

const quickActions = [
    {
        label: 'Kelola Karyawan',
        description: 'Tambah atau rapikan akun pegawai',
        routeName: 'admin.employees.index',
    },
    {
        label: 'Cek Presensi',
        description: 'Tinjau catatan kehadiran terbaru',
        routeName: 'admin.attendances.index',
    },
    {
        label: 'Approval Lembur',
        description: 'Masuk ke antrean approval admin',
        routeName: 'admin.overtimes.index',
    },
    {
        label: 'Atur Sistem',
        description: 'Update radius dan jam kerja',
        routeName: 'admin.settings.index',
    },
];

const formatDate = (value) => {
    if (!value) {
        return '-';
    }

    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) {
        return value;
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(year, month - 1, day));
};

const formatTime = (value) => {
    if (!value) {
        return '-';
    }

    return value.slice(0, 5);
};

const statusClass = (status) => {
    if (status === 'Approved') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    if (status === 'Rejected') {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300';
    }

    return 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
};

const processOvertime = (id, action) => {
    if (action === 'reject' && !window.confirm('Tolak pengajuan lembur ini?')) {
        return;
    }

    actionLoadingId.value = id;
    actionLoadingType.value = action;

    router.patch(route(`admin.overtimes.${action}`, id), {}, {
        preserveScroll: true,
        onFinish: () => {
            actionLoadingId.value = null;
            actionLoadingType.value = null;
        },
    });
};
</script>

<template>
    <Head title="Dashboard Admin" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-3">
                    <span class="inline-flex w-fit items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                        Dashboard Admin
                    </span>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl dark:text-slate-100">
                            Pantau kondisi operasional dalam satu layar.
                        </h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-400">
                            Ringkasan ini membantu admin memonitor presensi, status lembur, dan kesiapan sistem setiap hari.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[23rem]">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/80">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Hari Ini</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ todayLabel }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/80">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Status Kehadiran</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ attendanceRateTone }}</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <div v-if="flashSuccess" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                {{ flashError }}
            </div>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.9fr)]">
                <div class="overflow-hidden rounded-[28px] bg-slate-950 text-white shadow-[0_30px_90px_rgba(15,23,42,0.18)] dark:bg-slate-900">
                    <div class="grid gap-6 p-6 sm:p-8">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="max-w-xl">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-300">Snapshot Operasional</p>
                                <h2 class="mt-4 text-3xl font-semibold leading-tight sm:text-4xl">
                                    {{ props.summary.presentEmployeesToday }} karyawan sudah hadir dan dashboard siap dipakai.
                                </h2>
                                <p class="mt-4 text-sm leading-7 text-slate-300">
                                    Fokus utama hari ini ada pada kestabilan kehadiran, approval lembur, dan konsistensi pengaturan area kerja.
                                </p>
                            </div>

                            <div class="rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur">
                                <p class="text-xs uppercase tracking-[0.22em] text-slate-400">Kehadiran Hari Ini</p>
                                <p class="mt-3 text-4xl font-semibold text-white">{{ attendanceRate }}%</p>
                                <div class="mt-4 h-2 rounded-full bg-white/10">
                                    <div class="h-2 rounded-full bg-gradient-to-r from-amber-300 via-amber-400 to-orange-300" :style="{ width: `${attendanceRate}%` }"></div>
                                </div>
                                <p class="mt-3 text-sm text-slate-300">{{ attendanceRateTone }}</p>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <article
                                v-for="card in summaryCards"
                                :key="card.label"
                                class="rounded-[24px] bg-gradient-to-br p-[1px] shadow-lg"
                                :class="card.accent"
                            >
                                <div class="h-full rounded-[23px] bg-white/12 p-5 backdrop-blur dark:bg-slate-950/35">
                                    <p class="text-sm font-medium opacity-80">{{ card.label }}</p>
                                    <p class="mt-3 text-3xl font-semibold">{{ card.value }}</p>
                                    <p class="mt-3 text-sm leading-6 opacity-75">{{ card.hint }}</p>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6">
                    <section class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Pengaturan Sistem</p>
                                <h3 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Lokasi dan jam kerja</h3>
                            </div>
                            <Link
                                :href="route('admin.settings.index')"
                                class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Kelola
                            </Link>
                        </div>

                        <div v-if="setting" class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/80">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Radius Presensi</p>
                                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ setting.radius_meters }} m</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Area valid untuk clock in dan clock out.</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/80">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Jam Operasional</p>
                                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">
                                    {{ formatTime(setting.check_in_time) }} - {{ formatTime(setting.check_out_time) }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Jadwal referensi kehadiran harian.</p>
                            </div>
                            <div class="rounded-2xl border border-dashed border-slate-200 p-4 dark:border-slate-700">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Latitude</p>
                                <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ setting.latitude ?? '-' }}</p>
                            </div>
                            <div class="rounded-2xl border border-dashed border-slate-200 p-4 dark:border-slate-700">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Longitude</p>
                                <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ setting.longitude ?? '-' }}</p>
                            </div>
                        </div>

                        <p v-else class="mt-6 rounded-2xl border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            Data pengaturan belum tersedia. Lengkapi lokasi kerja dan jam operasional agar presensi lebih akurat.
                        </p>
                    </section>

                    <section class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Aksi Cepat</p>
                        <div class="mt-5 grid gap-3">
                            <Link
                                v-for="action in quickActions"
                                :key="action.routeName"
                                :href="route(action.routeName)"
                                class="group rounded-2xl border border-slate-200 px-4 py-4 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900/80"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ action.label }}</p>
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ action.description }}</p>
                                    </div>
                                    <span class="text-slate-400 transition group-hover:translate-x-1 group-hover:text-slate-600 dark:group-hover:text-slate-300">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M5 12H19M19 12L13 6M19 12L13 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </div>
                            </Link>
                        </div>
                    </section>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
                <div class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Monitoring Hari Ini</p>
                            <h3 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Presensi harian</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                Pantau cepat jumlah masuk, pulang, dan keterlambatan tanpa berpindah halaman.
                            </p>
                        </div>

                        <div class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                            {{ props.summary.presentEmployeesToday }} / {{ props.summary.totalEmployees }} hadir
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-3">
                        <div
                            v-for="metric in attendanceMetrics"
                            :key="metric.label"
                            class="rounded-3xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-900/80"
                        >
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ metric.label }}</p>
                            <p class="mt-3 text-4xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ metric.value }}</p>
                            <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ metric.hint }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Ringkasan Lembur</p>
                    <h3 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Pergerakan bulan berjalan</h3>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="item in overtimeHighlights"
                            :key="item.label"
                            class="rounded-2xl px-4 py-4"
                            :class="item.tone"
                        >
                            <p class="text-xs uppercase tracking-[0.2em]">{{ item.label }}</p>
                            <p class="mt-2 text-3xl font-semibold">{{ item.value }}</p>
                        </div>
                    </div>
                    <div class="mt-6 rounded-3xl bg-slate-950 px-5 py-5 text-white dark:bg-slate-900">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Pending Saat Ini</p>
                        <p class="mt-3 text-4xl font-semibold">{{ props.summary.pendingOvertimes }}</p>
                        <p class="mt-3 text-sm leading-6 text-slate-300">
                            Prioritaskan approval agar jadwal kerja tambahan tidak tertahan.
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(320px,0.95fr)]">
                <div class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Data Terbaru</p>
                            <h3 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Presensi terakhir</h3>
                        </div>
                        <Link
                            :href="route('admin.attendances.index')"
                            class="hidden rounded-2xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 sm:inline-flex dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Lihat semua
                        </Link>
                    </div>
                    <div class="mt-6 space-y-3 md:hidden">
                        <article
                            v-for="attendance in recentAttendances"
                            :key="attendance.id"
                            class="rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/80"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-slate-100">{{ attendance.employee_name }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ attendance.id_number ?? '-' }}</p>
                                </div>
                                <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-600 shadow-sm dark:bg-slate-800 dark:text-slate-300">
                                    {{ formatDate(attendance.date) }}
                                </span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-slate-600 dark:text-slate-300">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">In</p>
                                    <p class="mt-1 font-medium">{{ formatTime(attendance.clock_in_at) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">Out</p>
                                    <p class="mt-1 font-medium">{{ formatTime(attendance.clock_out_at) }}</p>
                                </div>
                            </div>
                            <div class="mt-4 space-y-2 text-sm text-slate-500 dark:text-slate-400">
                                <p class="truncate" :title="attendance.clock_in_location ?? '-'">In: {{ attendance.clock_in_location ?? '-' }}</p>
                                <p class="truncate" :title="attendance.clock_out_location ?? '-'">Out: {{ attendance.clock_out_location ?? '-' }}</p>
                            </div>
                        </article>

                        <div v-if="recentAttendances.length === 0" class="rounded-3xl border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            Belum ada data presensi.
                        </div>
                    </div>

                    <div class="mt-6 hidden overflow-x-auto md:block">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                <tr>
                                    <th class="py-3 pe-4 font-medium">Tanggal</th>
                                    <th class="py-3 pe-4 font-medium">Karyawan</th>
                                    <th class="py-3 pe-4 font-medium">Clock In</th>
                                    <th class="py-3 pe-4 font-medium">Clock Out</th>
                                    <th class="py-3 pe-4 font-medium">Lokasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                                <tr v-for="attendance in recentAttendances" :key="attendance.id">
                                    <td class="py-4 pe-4 whitespace-nowrap">{{ formatDate(attendance.date) }}</td>
                                    <td class="py-4 pe-4">
                                        <p class="font-semibold text-slate-900 dark:text-slate-100">{{ attendance.employee_name }}</p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ attendance.id_number ?? '-' }}</p>
                                    </td>
                                    <td class="py-4 pe-4 whitespace-nowrap">{{ formatTime(attendance.clock_in_at) }}</td>
                                    <td class="py-4 pe-4 whitespace-nowrap">{{ formatTime(attendance.clock_out_at) }}</td>
                                    <td class="py-4 pe-4">
                                        <p class="max-w-[220px] truncate" :title="attendance.clock_in_location ?? '-'">
                                            In: {{ attendance.clock_in_location ?? '-' }}
                                        </p>
                                        <p class="mt-1 max-w-[220px] truncate" :title="attendance.clock_out_location ?? '-'">
                                            Out: {{ attendance.clock_out_location ?? '-' }}
                                        </p>
                                    </td>
                                </tr>
                                <tr v-if="recentAttendances.length === 0">
                                    <td colspan="5" class="py-8 text-center text-slate-500 dark:text-slate-400">Belum ada data presensi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="self-start xl:sticky xl:top-28">
                    <div class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Approval</p>
                                <h3 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Lembur pending</h3>
                            </div>
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                {{ pendingOvertimes.length }} antrean
                            </span>
                        </div>

                        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <div class="rounded-2xl bg-slate-950 px-4 py-4 text-white dark:bg-slate-900">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Perlu diproses</p>
                                <p class="mt-2 text-3xl font-semibold">{{ pendingOvertimes.length }}</p>
                                <p class="mt-2 text-sm leading-6 text-slate-300">Prioritaskan approval agar jadwal kerja tambahan tidak tertahan.</p>
                            </div>
                            <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-4 dark:border-slate-700">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Fokus panel</p>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    Panel ini diringkas khusus untuk aksi cepat. Detail histori tetap ada di section riwayat lembur.
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 border-t border-slate-200 pt-6 dark:border-slate-800">
                            <div v-if="pendingOvertimes.length > 0" class="max-h-[32rem] space-y-3 overflow-y-auto pe-1">
                                <article
                                    v-for="overtime in pendingOvertimes"
                                    :key="overtime.id"
                                    class="rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/80"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ overtime.employee_name }}</p>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ overtime.id_number ?? '-' }}</p>
                                        </div>
                                        <span class="inline-flex shrink-0 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                            {{ formatDate(overtime.overtime_date) }}
                                        </span>
                                    </div>

                                    <div class="mt-4 grid gap-3">
                                        <div class="rounded-2xl bg-white px-4 py-3 dark:bg-slate-800">
                                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Rencana</p>
                                            <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                                                {{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}
                                            </p>
                                        </div>
                                        <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-3 dark:border-slate-700">
                                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Alasan</p>
                                            <p class="mt-2 max-h-[4.5rem] overflow-hidden text-sm leading-6 text-slate-700 dark:text-slate-300">{{ overtime.reason ?? '-' }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid grid-cols-2 gap-3">
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="actionLoadingId === overtime.id"
                                            @click="processOvertime(overtime.id, 'approve')"
                                        >
                                            <span v-if="actionLoadingId === overtime.id && actionLoadingType === 'approve'">Memproses...</span>
                                            <span v-else>Approve</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-2xl bg-rose-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="actionLoadingId === overtime.id"
                                            @click="processOvertime(overtime.id, 'reject')"
                                        >
                                            <span v-if="actionLoadingId === overtime.id && actionLoadingType === 'reject'">Memproses...</span>
                                            <span v-else>Reject</span>
                                        </button>
                                    </div>
                                </article>
                            </div>

                            <div v-else class="rounded-3xl border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                Tidak ada pengajuan lembur yang menunggu approval.
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Histori</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Riwayat lembur terbaru</h3>
                    </div>
                    <Link
                        :href="route('admin.reports.index')"
                        class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        Buka laporan
                    </Link>
                </div>

                <div class="mt-6 space-y-3 md:hidden">
                    <article
                        v-for="overtime in recentOvertimes"
                        :key="overtime.id"
                        class="rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/80"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ overtime.employee_name }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ formatDate(overtime.overtime_date) }}</p>
                            </div>
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(overtime.approval_status)">
                                {{ overtime.approval_status }}
                            </span>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-slate-600 dark:text-slate-300">
                            <div>
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">Rencana</p>
                                <p class="mt-1 font-medium">{{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">Aktual</p>
                                <p class="mt-1 font-medium">{{ formatTime(overtime.actual_start) }} - {{ formatTime(overtime.actual_end) }}</p>
                            </div>
                        </div>
                    </article>

                    <div v-if="recentOvertimes.length === 0" class="rounded-3xl border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        Belum ada data lembur.
                    </div>
                </div>

                <div class="mt-6 hidden overflow-x-auto md:block">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="py-3 pe-4 font-medium">Tanggal</th>
                                <th class="py-3 pe-4 font-medium">Karyawan</th>
                                <th class="py-3 pe-4 font-medium">Status</th>
                                <th class="py-3 pe-4 font-medium">Rencana</th>
                                <th class="py-3 pe-4 font-medium">Aktual</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                            <tr v-for="overtime in recentOvertimes" :key="overtime.id">
                                <td class="py-4 pe-4 whitespace-nowrap">{{ formatDate(overtime.overtime_date) }}</td>
                                <td class="py-4 pe-4 font-semibold text-slate-900 dark:text-slate-100">{{ overtime.employee_name }}</td>
                                <td class="py-4 pe-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(overtime.approval_status)">
                                        {{ overtime.approval_status }}
                                    </span>
                                </td>
                                <td class="py-4 pe-4 whitespace-nowrap">
                                    {{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}
                                </td>
                                <td class="py-4 pe-4 whitespace-nowrap">
                                    {{ formatTime(overtime.actual_start) }} - {{ formatTime(overtime.actual_end) }}
                                </td>
                            </tr>
                            <tr v-if="recentOvertimes.length === 0">
                                <td colspan="5" class="py-8 text-center text-slate-500 dark:text-slate-400">Belum ada data lembur.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
