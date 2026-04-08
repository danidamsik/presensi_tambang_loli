<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

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
    attendances: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const form = reactive({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    employee_id: props.filters.employee_id ?? '',
});

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
    router.get(route('admin.attendances.index'), {
        date_from: form.date_from,
        date_to: form.date_to,
        employee_id: form.employee_id || null,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilter = () => {
    form.employee_id = '';
    applyFilter();
};
</script>

<template>
    <Head title="Monitoring Presensi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="space-y-1">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Monitoring Presensi</h2>
                <p class="text-sm text-gray-500">Pantau data absensi masuk dan pulang seluruh karyawan.</p>
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

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                        <p class="text-xs text-gray-500">Total Record</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.totalRecords }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                        <p class="text-xs text-gray-500">Clock In</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.clockedIn }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                        <p class="text-xs text-gray-500">Clock Out</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.clockedOut }}</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-4">
                        <p class="text-xs text-gray-500">Terlambat</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.lateCheckIn }}</p>
                    </div>
                </div>

                <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500">Dari Tanggal</label>
                            <input v-model="form.date_from" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Sampai Tanggal</label>
                            <input v-model="form.date_to" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
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
                                    <th class="py-2 pe-4 font-medium">Clock In</th>
                                    <th class="py-2 pe-4 font-medium">Clock Out</th>
                                    <th class="py-2 pe-4 font-medium">Lokasi</th>
                                    <th class="py-2 pe-4 font-medium">Foto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <tr v-for="attendance in attendances.data" :key="attendance.id">
                                    <td class="py-3 pe-4 whitespace-nowrap">{{ formatDate(attendance.date) }}</td>
                                    <td class="py-3 pe-4">
                                        <p class="font-medium text-gray-900">{{ attendance.employee_name }}</p>
                                        <p class="text-xs text-gray-500">{{ attendance.id_number ?? '-' }}</p>
                                    </td>
                                    <td class="py-3 pe-4 whitespace-nowrap">{{ formatTime(attendance.clock_in_at) }}</td>
                                    <td class="py-3 pe-4 whitespace-nowrap">{{ formatTime(attendance.clock_out_at) }}</td>
                                    <td class="py-3 pe-4">
                                        <p class="truncate max-w-[220px]" :title="attendance.clock_in_location ?? '-'">In: {{ attendance.clock_in_location ?? '-' }}</p>
                                        <p class="truncate max-w-[220px]" :title="attendance.clock_out_location ?? '-'">Out: {{ attendance.clock_out_location ?? '-' }}</p>
                                    </td>
                                    <td class="py-3 pe-4">
                                        <div class="flex flex-col gap-1">
                                            <a v-if="attendance.clock_in_photo" :href="attendance.clock_in_photo" target="_blank" class="text-blue-600 hover:underline">Foto In</a>
                                            <a v-if="attendance.clock_out_photo" :href="attendance.clock_out_photo" target="_blank" class="text-blue-600 hover:underline">Foto Out</a>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="attendances.data.length === 0">
                                    <td colspan="6" class="py-4 text-center text-gray-500">Data presensi tidak ditemukan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="attendances.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="(link, index) in attendances.links"
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
