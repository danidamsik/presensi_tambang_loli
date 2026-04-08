<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    employees: {
        type: Array,
        required: true,
    },
    summary: {
        type: Object,
        required: true,
    },
    overtimes: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const actionLoadingId = ref(null);
const actionLoadingType = ref(null);

const form = reactive({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    status: props.filters.status ?? 'all',
    employee_id: props.filters.employee_id ?? '',
});

const statusClass = (status) => {
    if (status === 'Approved') return 'bg-green-100 text-green-700';
    if (status === 'Rejected') return 'bg-red-100 text-red-700';
    return 'bg-amber-100 text-amber-700';
};

const formatDate = (value) => {
    if (!value) return '-';

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
    if (!value) return '-';

    return value.slice(0, 5);
};

const applyFilter = () => {
    router.get(route('admin.overtimes.index'), {
        date_from: form.date_from,
        date_to: form.date_to,
        status: form.status,
        employee_id: form.employee_id || null,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilter = () => {
    form.status = 'all';
    form.employee_id = '';
    applyFilter();
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
    <Head title="Monitoring & Approval Lembur" />

    <AuthenticatedLayout>
        <template #header>
            <div class="space-y-1">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Monitoring & Approval Lembur</h2>
                <p class="text-sm text-gray-500">Validasi pengajuan lembur dan pantau realisasi jam lembur karyawan.</p>
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

                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                        <p class="text-xs text-gray-500">Total Pengajuan</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.totalRequests }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                        <p class="text-xs text-gray-500">Pending</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.pendingRequests }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                        <p class="text-xs text-gray-500">Approved</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.approvedRequests }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                        <p class="text-xs text-gray-500">Rejected</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.rejectedRequests }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                        <p class="text-xs text-gray-500">Jam Approved</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.approvedHours }} jam</p>
                    </div>
                </div>

                <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500">Dari Tanggal</label>
                            <input v-model="form.date_from" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Sampai Tanggal</label>
                            <input v-model="form.date_to" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Status</label>
                            <select v-model="form.status" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                <option value="all">Semua</option>
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Karyawan</label>
                            <select v-model="form.employee_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                <option value="">Semua Karyawan</option>
                                <option v-for="employee in employees" :key="employee.id" :value="employee.id">
                                    {{ employee.full_name }} ({{ employee.id_number }})
                                </option>
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white hover:bg-slate-800" @click="applyFilter">
                                Terapkan
                            </button>
                            <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" @click="resetFilter">
                                Reset
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-gray-500 border-b border-gray-100">
                                <tr>
                                    <th class="py-2 pe-4 font-medium">Tanggal</th>
                                    <th class="py-2 pe-4 font-medium">Karyawan</th>
                                    <th class="py-2 pe-4 font-medium">Rencana</th>
                                    <th class="py-2 pe-4 font-medium">Aktual</th>
                                    <th class="py-2 pe-4 font-medium">Status</th>
                                    <th class="py-2 pe-4 font-medium">Approver</th>
                                    <th class="py-2 pe-4 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <tr v-for="overtime in overtimes.data" :key="overtime.id">
                                    <td class="py-3 pe-4 whitespace-nowrap">{{ formatDate(overtime.overtime_date) }}</td>
                                    <td class="py-3 pe-4">
                                        <p class="font-medium text-gray-900">{{ overtime.employee_name }}</p>
                                        <p class="text-xs text-gray-500">{{ overtime.id_number ?? '-' }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ overtime.reason ?? '-' }}</p>
                                    </td>
                                    <td class="py-3 pe-4 whitespace-nowrap">{{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}</td>
                                    <td class="py-3 pe-4 whitespace-nowrap">{{ formatTime(overtime.actual_start) }} - {{ formatTime(overtime.actual_end) }}</td>
                                    <td class="py-3 pe-4">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(overtime.approval_status)">
                                            {{ overtime.approval_status }}
                                        </span>
                                    </td>
                                    <td class="py-3 pe-4">{{ overtime.approved_by ?? '-' }}</td>
                                    <td class="py-3 pe-4">
                                        <div v-if="overtime.approval_status === 'Pending'" class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                class="rounded-md bg-green-600 px-2 py-1 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-60"
                                                :disabled="actionLoadingId === overtime.id"
                                                @click="processOvertime(overtime.id, 'approve')"
                                            >
                                                <span v-if="actionLoadingId === overtime.id && actionLoadingType === 'approve'">...</span>
                                                <span v-else>Approve</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-60"
                                                :disabled="actionLoadingId === overtime.id"
                                                @click="processOvertime(overtime.id, 'reject')"
                                            >
                                                <span v-if="actionLoadingId === overtime.id && actionLoadingType === 'reject'">...</span>
                                                <span v-else>Reject</span>
                                            </button>
                                        </div>
                                        <span v-else class="text-xs text-gray-500">Diproses</span>
                                    </td>
                                </tr>
                                <tr v-if="overtimes.data.length === 0">
                                    <td colspan="7" class="py-4 text-center text-gray-500">Data lembur tidak ditemukan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="overtimes.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="(link, index) in overtimes.links"
                            :key="index"
                            :disabled="!link.url || link.active"
                            class="rounded-md border px-3 py-1.5 text-sm disabled:opacity-50"
                            :class="link.active ? 'border-slate-900 bg-slate-900 text-white' : 'border-gray-300 hover:bg-gray-50'"
                            @click="router.visit(link.url, { preserveScroll: true, preserveState: true })"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
