<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
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

const summaryCards = computed(() => [
    {
        label: 'Total Karyawan',
        value: props.summary.totalEmployees,
        hint: 'Akun role Employee',
    },
    {
        label: 'Hadir Hari Ini',
        value: `${props.summary.presentEmployeesToday}/${props.summary.totalEmployees}`,
        hint: 'Berdasarkan tabel presensi',
    },
    {
        label: 'Lembur Pending',
        value: props.summary.pendingOvertimes,
        hint: 'Menunggu approval admin',
    },
    {
        label: 'Jam Lembur Disetujui',
        value: `${props.summary.approvedOvertimeHoursThisMonth} jam`,
        hint: 'Akumulasi bulan berjalan',
    },
    {
        label: 'Lembur Approved (Bulan Ini)',
        value: props.summary.approvedOvertimesThisMonth,
        hint: 'Status approved',
    },
    {
        label: 'Lembur Rejected (Bulan Ini)',
        value: props.summary.rejectedOvertimesThisMonth,
        hint: 'Status rejected',
    },
]);

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
        return 'bg-green-100 text-green-700';
    }

    if (status === 'Rejected') {
        return 'bg-red-100 text-red-700';
    }

    return 'bg-amber-100 text-amber-700';
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
            <div class="space-y-1">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Dashboard Admin
                </h2>
                <p class="text-sm text-gray-500">
                    Monitoring presensi, pengaturan sistem, dan approval lembur.
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <div v-if="flashSuccess" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ flashSuccess }}
                </div>
                <div v-if="flashError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ flashError }}
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    <div
                        v-for="card in summaryCards"
                        :key="card.label"
                        class="bg-white border border-gray-100 rounded-xl shadow-sm p-5"
                    >
                        <p class="text-sm text-gray-500">{{ card.label }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ card.value }}</p>
                        <p class="mt-1 text-xs text-gray-400">{{ card.hint }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white border border-gray-100 rounded-xl shadow-sm p-5">
                        <h3 class="text-lg font-semibold text-gray-800">Monitoring Presensi Hari Ini</h3>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="rounded-lg border border-gray-200 p-4">
                                <p class="text-xs text-gray-500">Clock In</p>
                                <p class="mt-1 text-xl font-semibold text-gray-900">{{ attendanceToday.clockedIn }}</p>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-4">
                                <p class="text-xs text-gray-500">Clock Out</p>
                                <p class="mt-1 text-xl font-semibold text-gray-900">{{ attendanceToday.clockedOut }}</p>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-4">
                                <p class="text-xs text-gray-500">Terlambat</p>
                                <p class="mt-1 text-xl font-semibold text-gray-900">{{ attendanceToday.lateCheckIn }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5">
                        <h3 class="text-lg font-semibold text-gray-800">Pengaturan Lokasi & Jam Kerja</h3>
                        <div v-if="setting" class="mt-4 space-y-3 text-sm text-gray-700">
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-500">Latitude</span>
                                <span class="font-medium">{{ setting.latitude ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-500">Longitude</span>
                                <span class="font-medium">{{ setting.longitude ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-500">Radius</span>
                                <span class="font-medium">{{ setting.radius_meters }} meter</span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-500">Jam Masuk</span>
                                <span class="font-medium">{{ formatTime(setting.check_in_time) }}</span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-500">Jam Pulang</span>
                                <span class="font-medium">{{ formatTime(setting.check_out_time) }}</span>
                            </div>
                        </div>
                        <p v-else class="mt-4 text-sm text-gray-500">
                            Data pengaturan belum tersedia.
                        </p>
                    </div>
                </div>

                <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5">
                    <h3 class="text-lg font-semibold text-gray-800">Presensi Terbaru</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-gray-500 border-b border-gray-100">
                                <tr>
                                    <th class="py-2 pe-4 font-medium">Tanggal</th>
                                    <th class="py-2 pe-4 font-medium">Karyawan</th>
                                    <th class="py-2 pe-4 font-medium">Clock In</th>
                                    <th class="py-2 pe-4 font-medium">Clock Out</th>
                                    <th class="py-2 pe-4 font-medium">Lokasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <tr v-for="attendance in recentAttendances" :key="attendance.id">
                                    <td class="py-3 pe-4 whitespace-nowrap">{{ formatDate(attendance.date) }}</td>
                                    <td class="py-3 pe-4">
                                        <p class="font-medium text-gray-900">{{ attendance.employee_name }}</p>
                                        <p class="text-xs text-gray-500">{{ attendance.id_number ?? '-' }}</p>
                                    </td>
                                    <td class="py-3 pe-4 whitespace-nowrap">{{ formatTime(attendance.clock_in_at) }}</td>
                                    <td class="py-3 pe-4 whitespace-nowrap">{{ formatTime(attendance.clock_out_at) }}</td>
                                    <td class="py-3 pe-4">
                                        <p class="truncate max-w-[220px]" :title="attendance.clock_in_location ?? '-'">
                                            In: {{ attendance.clock_in_location ?? '-' }}
                                        </p>
                                        <p class="truncate max-w-[220px]" :title="attendance.clock_out_location ?? '-'">
                                            Out: {{ attendance.clock_out_location ?? '-' }}
                                        </p>
                                    </td>
                                </tr>
                                <tr v-if="recentAttendances.length === 0">
                                    <td colspan="5" class="py-4 text-center text-gray-500">Belum ada data presensi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5">
                        <h3 class="text-lg font-semibold text-gray-800">Approval Lembur Pending</h3>
                        <div class="mt-4 space-y-3">
                            <div
                                v-for="overtime in pendingOvertimes"
                                :key="overtime.id"
                                class="rounded-lg border border-gray-200 p-4"
                            >
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ overtime.employee_name }}</p>
                                        <p class="text-xs text-gray-500">{{ overtime.id_number ?? '-' }}</p>
                                        <p class="mt-1 text-sm text-gray-600">
                                            {{ formatDate(overtime.overtime_date) }} | {{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}
                                        </p>
                                        <p class="mt-2 text-sm text-gray-700">{{ overtime.reason ?? '-' }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="px-3 py-1.5 rounded-md text-xs font-medium bg-green-600 text-white hover:bg-green-700 disabled:opacity-60"
                                            :disabled="actionLoadingId === overtime.id"
                                            @click="processOvertime(overtime.id, 'approve')"
                                        >
                                            <span v-if="actionLoadingId === overtime.id && actionLoadingType === 'approve'">Memproses...</span>
                                            <span v-else>Approve</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="px-3 py-1.5 rounded-md text-xs font-medium bg-red-600 text-white hover:bg-red-700 disabled:opacity-60"
                                            :disabled="actionLoadingId === overtime.id"
                                            @click="processOvertime(overtime.id, 'reject')"
                                        >
                                            <span v-if="actionLoadingId === overtime.id && actionLoadingType === 'reject'">Memproses...</span>
                                            <span v-else>Reject</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <p v-if="pendingOvertimes.length === 0" class="text-sm text-gray-500">
                                Tidak ada pengajuan lembur yang menunggu approval.
                            </p>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5">
                        <h3 class="text-lg font-semibold text-gray-800">Riwayat Lembur Terbaru</h3>
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-gray-500 border-b border-gray-100">
                                    <tr>
                                        <th class="py-2 pe-4 font-medium">Tanggal</th>
                                        <th class="py-2 pe-4 font-medium">Karyawan</th>
                                        <th class="py-2 pe-4 font-medium">Status</th>
                                        <th class="py-2 pe-4 font-medium">Rencana</th>
                                        <th class="py-2 pe-4 font-medium">Aktual</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    <tr v-for="overtime in recentOvertimes" :key="overtime.id">
                                        <td class="py-3 pe-4 whitespace-nowrap">{{ formatDate(overtime.overtime_date) }}</td>
                                        <td class="py-3 pe-4">{{ overtime.employee_name }}</td>
                                        <td class="py-3 pe-4">
                                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium" :class="statusClass(overtime.approval_status)">
                                                {{ overtime.approval_status }}
                                            </span>
                                        </td>
                                        <td class="py-3 pe-4 whitespace-nowrap">
                                            {{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}
                                        </td>
                                        <td class="py-3 pe-4 whitespace-nowrap">
                                            {{ formatTime(overtime.actual_start) }} - {{ formatTime(overtime.actual_end) }}
                                        </td>
                                    </tr>
                                    <tr v-if="recentOvertimes.length === 0">
                                        <td colspan="5" class="py-4 text-center text-gray-500">Belum ada data lembur.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
