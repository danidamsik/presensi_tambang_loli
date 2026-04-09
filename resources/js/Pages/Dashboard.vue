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
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Dashboard Admin</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Ringkasan presensi, pengaturan, dan lembur hari ini.</p>
                </div>
                <div class="text-sm text-slate-500 dark:text-slate-400">
                    <p>{{ todayLabel }}</p>
                    <p class="font-medium text-slate-700 dark:text-slate-300">{{ attendanceRateTone }}</p>
                </div>
            </div>
        </template>

        <div class="space-y-4">
            <div v-if="flashSuccess" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                {{ flashError }}
            </div>

            <section class="grid items-start gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Ringkasan Utama</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Nilai utama untuk memantau kondisi operasional hari ini.</p>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article
                            v-for="card in summaryCards"
                            :key="card.label"
                            class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60"
                        >
                            <p class="text-xs uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ card.value }}</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ card.hint }}</p>
                        </article>
                    </div>
                </div>

                <div class="grid gap-4">
                    <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Aksi Cepat</h2>
                            <Link
                                :href="route('admin.settings.index')"
                                class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Buka Pengaturan
                            </Link>
                        </div>
                        <div class="mt-3 grid gap-2">
                            <Link
                                v-for="action in quickActions"
                                :key="action.routeName"
                                :href="route(action.routeName)"
                                class="rounded-lg border border-slate-200 px-3 py-3 text-sm transition hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                            >
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ action.label }}</p>
                                <p class="mt-1 text-slate-500 dark:text-slate-400">{{ action.description }}</p>
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Pengaturan Sistem</h2>
                        <div v-if="setting" class="mt-3 grid gap-2 sm:grid-cols-2">
                            <div class="rounded-lg bg-slate-50 px-3 py-3 text-sm dark:bg-slate-800/60">
                                <p class="text-slate-500 dark:text-slate-400">Radius Presensi</p>
                                <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">{{ setting.radius_meters }} m</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-3 py-3 text-sm dark:bg-slate-800/60">
                                <p class="text-slate-500 dark:text-slate-400">Jam Operasional</p>
                                <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">{{ formatTime(setting.check_in_time) }} - {{ formatTime(setting.check_out_time) }}</p>
                            </div>
                            <div class="rounded-lg border border-dashed border-slate-200 px-3 py-3 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">
                                Lat: {{ setting.latitude ?? '-' }}
                            </div>
                            <div class="rounded-lg border border-dashed border-slate-200 px-3 py-3 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">
                                Lng: {{ setting.longitude ?? '-' }}
                            </div>
                        </div>
                        <p v-else class="mt-3 rounded-lg border border-dashed border-slate-200 px-3 py-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            Pengaturan sistem belum tersedia.
                        </p>
                    </section>
                </div>
            </section>

            <section class="grid items-start gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Monitoring Presensi Hari Ini</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ props.summary.presentEmployeesToday }} / {{ props.summary.totalEmployees }} hadir</p>
                        </div>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                            {{ attendanceRate }}%
                        </span>
                    </div>

                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        <div
                            v-for="metric in attendanceMetrics"
                            :key="metric.label"
                            class="rounded-lg border border-slate-200 px-3 py-3 text-sm dark:border-slate-700"
                        >
                            <p class="text-slate-500 dark:text-slate-400">{{ metric.label }}</p>
                            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ metric.value }}</p>
                            <p class="mt-1 text-slate-500 dark:text-slate-400">{{ metric.hint }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Ringkasan Lembur</h3>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <div
                            v-for="item in overtimeHighlights"
                            :key="item.label"
                            class="rounded-lg px-3 py-3 text-sm"
                            :class="item.tone"
                        >
                            <p>{{ item.label }}</p>
                            <p class="mt-1 text-2xl font-semibold">{{ item.value }}</p>
                        </div>
                    </div>
                    <div class="mt-3 rounded-lg bg-slate-100 px-3 py-3 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        Pending: <span class="font-semibold">{{ props.summary.pendingOvertimes }}</span>
                    </div>
                </div>
            </section>

            <section class="grid items-start gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(320px,0.95fr)]">
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Presensi Terakhir</h3>
                        <Link
                            :href="route('admin.attendances.index')"
                            class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Lihat Semua
                        </Link>
                    </div>

                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                <tr>
                                    <th class="py-2 pe-3 font-medium">Tanggal</th>
                                    <th class="py-2 pe-3 font-medium">Karyawan</th>
                                    <th class="py-2 pe-3 font-medium">Clock In</th>
                                    <th class="py-2 pe-3 font-medium">Clock Out</th>
                                    <th class="py-2 pe-3 font-medium">Lokasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                                <tr v-for="attendance in recentAttendances" :key="attendance.id">
                                    <td class="py-3 pe-3 whitespace-nowrap">{{ formatDate(attendance.date) }}</td>
                                    <td class="py-3 pe-3">
                                        <p class="font-semibold text-slate-900 dark:text-slate-100">{{ attendance.employee_name }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ attendance.id_number ?? '-' }}</p>
                                    </td>
                                    <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(attendance.clock_in_at) }}</td>
                                    <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(attendance.clock_out_at) }}</td>
                                    <td class="py-3 pe-3">
                                        <p class="max-w-[220px] truncate" :title="attendance.clock_in_location ?? '-'">In: {{ attendance.clock_in_location ?? '-' }}</p>
                                        <p class="max-w-[220px] truncate" :title="attendance.clock_out_location ?? '-'">Out: {{ attendance.clock_out_location ?? '-' }}</p>
                                    </td>
                                </tr>
                                <tr v-if="recentAttendances.length === 0">
                                    <td colspan="5" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada data presensi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Lembur Pending</h3>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                            {{ pendingOvertimes.length }} antrean
                        </span>
                    </div>

                    <div v-if="pendingOvertimes.length > 0" class="mt-3 max-h-[32rem] space-y-2 overflow-y-auto pe-1">
                        <article
                            v-for="overtime in pendingOvertimes"
                            :key="overtime.id"
                            class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ overtime.employee_name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ overtime.id_number ?? '-' }}</p>
                                </div>
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ formatDate(overtime.overtime_date) }}</span>
                            </div>

                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                {{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ overtime.reason ?? '-' }}</p>

                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="actionLoadingId === overtime.id"
                                    @click="processOvertime(overtime.id, 'approve')"
                                >
                                    <span v-if="actionLoadingId === overtime.id && actionLoadingType === 'approve'">Memproses...</span>
                                    <span v-else>Approve</span>
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="actionLoadingId === overtime.id"
                                    @click="processOvertime(overtime.id, 'reject')"
                                >
                                    <span v-if="actionLoadingId === overtime.id && actionLoadingType === 'reject'">Memproses...</span>
                                    <span v-else>Reject</span>
                                </button>
                            </div>
                        </article>
                    </div>

                    <div v-else class="mt-3 rounded-lg border border-dashed border-slate-200 px-3 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        Tidak ada pengajuan lembur yang menunggu approval.
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Riwayat Lembur Terbaru</h3>
                    <Link
                        :href="route('admin.reports.index')"
                        class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        Buka Laporan
                    </Link>
                </div>

                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            <tr>
                                <th class="py-2 pe-3 font-medium">Tanggal</th>
                                <th class="py-2 pe-3 font-medium">Karyawan</th>
                                <th class="py-2 pe-3 font-medium">Status</th>
                                <th class="py-2 pe-3 font-medium">Rencana</th>
                                <th class="py-2 pe-3 font-medium">Aktual</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                            <tr v-for="overtime in recentOvertimes" :key="overtime.id">
                                <td class="py-3 pe-3 whitespace-nowrap">{{ formatDate(overtime.overtime_date) }}</td>
                                <td class="py-3 pe-3 font-semibold text-slate-900 dark:text-slate-100">{{ overtime.employee_name }}</td>
                                <td class="py-3 pe-3">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(overtime.approval_status)">
                                        {{ overtime.approval_status }}
                                    </span>
                                </td>
                                <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}</td>
                                <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(overtime.actual_start) }} - {{ formatTime(overtime.actual_end) }}</td>
                            </tr>
                            <tr v-if="recentOvertimes.length === 0">
                                <td colspan="5" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada data lembur.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
