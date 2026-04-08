<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    employees: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const search = ref(props.filters.q ?? '');
const editingEmployee = ref(null);

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const createForm = useForm({
    id_number: '',
    full_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const editForm = useForm({
    id_number: '',
    full_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const formatDateTime = (value) => {
    if (!value) return '-';

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const applyFilter = () => {
    router.get(route('admin.employees.index'), { q: search.value }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilter = () => {
    search.value = '';
    applyFilter();
};

const submitCreate = () => {
    createForm.post(route('admin.employees.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
};

const startEdit = (employee) => {
    editingEmployee.value = employee;
    editForm.id_number = employee.id_number;
    editForm.full_name = employee.full_name;
    editForm.email = employee.email;
    editForm.password = '';
    editForm.password_confirmation = '';
};

const cancelEdit = () => {
    editingEmployee.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const submitEdit = () => {
    if (!editingEmployee.value) return;

    editForm.patch(route('admin.employees.update', editingEmployee.value.id), {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    });
};

const removeEmployee = (employee) => {
    if (!window.confirm(`Hapus karyawan ${employee.full_name}?`)) {
        return;
    }

    router.delete(route('admin.employees.destroy', employee.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Kelola Karyawan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="space-y-1">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Kelola Data Karyawan</h2>
                <p class="text-sm text-gray-500">Tambah, ubah, dan hapus akun karyawan.</p>
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

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                        <p class="text-sm text-gray-500">Total Karyawan</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ summary.totalEmployees }}</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                        <p class="text-sm text-gray-500">Total Admin</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ summary.totalAdmins }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div class="xl:col-span-1 bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                        <h3 class="text-lg font-semibold text-gray-900">Tambah Karyawan</h3>
                        <form class="mt-4 space-y-3" @submit.prevent="submitCreate">
                            <div>
                                <label class="block text-sm text-gray-600">ID Number</label>
                                <input v-model="createForm.id_number" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" required />
                                <p v-if="createForm.errors.id_number" class="mt-1 text-xs text-red-600">{{ createForm.errors.id_number }}</p>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600">Nama Lengkap</label>
                                <input v-model="createForm.full_name" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" required />
                                <p v-if="createForm.errors.full_name" class="mt-1 text-xs text-red-600">{{ createForm.errors.full_name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600">Email</label>
                                <input v-model="createForm.email" type="email" class="mt-1 w-full rounded-md border-gray-300 text-sm" required />
                                <p v-if="createForm.errors.email" class="mt-1 text-xs text-red-600">{{ createForm.errors.email }}</p>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600">Password</label>
                                <input v-model="createForm.password" type="password" class="mt-1 w-full rounded-md border-gray-300 text-sm" required />
                                <p v-if="createForm.errors.password" class="mt-1 text-xs text-red-600">{{ createForm.errors.password }}</p>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600">Konfirmasi Password</label>
                                <input v-model="createForm.password_confirmation" type="password" class="mt-1 w-full rounded-md border-gray-300 text-sm" required />
                            </div>
                            <button type="submit" :disabled="createForm.processing" class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
                                Simpan Karyawan
                            </button>
                        </form>
                    </div>

                    <div class="xl:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <h3 class="text-lg font-semibold text-gray-900">Daftar Karyawan</h3>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Cari nama / ID / email"
                                    class="rounded-md border-gray-300 text-sm w-56"
                                />
                                <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" @click="applyFilter">
                                    Cari
                                </button>
                                <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" @click="resetFilter">
                                    Reset
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-gray-500 border-b border-gray-100">
                                    <tr>
                                        <th class="py-2 pe-4 font-medium">ID Number</th>
                                        <th class="py-2 pe-4 font-medium">Nama</th>
                                        <th class="py-2 pe-4 font-medium">Email</th>
                                        <th class="py-2 pe-4 font-medium">Dibuat</th>
                                        <th class="py-2 pe-4 font-medium">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    <tr v-for="employee in employees.data" :key="employee.id">
                                        <td class="py-3 pe-4">{{ employee.id_number }}</td>
                                        <td class="py-3 pe-4">{{ employee.full_name }}</td>
                                        <td class="py-3 pe-4">{{ employee.email }}</td>
                                        <td class="py-3 pe-4">{{ formatDateTime(employee.created_at) }}</td>
                                        <td class="py-3 pe-4">
                                            <div class="flex items-center gap-2">
                                                <button type="button" class="rounded-md border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" @click="startEdit(employee)">
                                                    Edit
                                                </button>
                                                <button type="button" class="rounded-md border border-red-200 bg-red-50 px-2 py-1 text-xs text-red-700 hover:bg-red-100" @click="removeEmployee(employee)">
                                                    Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="employees.data.length === 0">
                                        <td colspan="5" class="py-4 text-center text-gray-500">Data karyawan belum ada.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="employees.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
                            <button
                                v-for="(link, index) in employees.links"
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

                <div v-if="editingEmployee" class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <h3 class="text-lg font-semibold text-gray-900">Edit Karyawan: {{ editingEmployee.full_name }}</h3>
                    <form class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submitEdit">
                        <div>
                            <label class="block text-sm text-gray-600">ID Number</label>
                            <input v-model="editForm.id_number" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" required />
                            <p v-if="editForm.errors.id_number" class="mt-1 text-xs text-red-600">{{ editForm.errors.id_number }}</p>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600">Nama Lengkap</label>
                            <input v-model="editForm.full_name" type="text" class="mt-1 w-full rounded-md border-gray-300 text-sm" required />
                            <p v-if="editForm.errors.full_name" class="mt-1 text-xs text-red-600">{{ editForm.errors.full_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600">Email</label>
                            <input v-model="editForm.email" type="email" class="mt-1 w-full rounded-md border-gray-300 text-sm" required />
                            <p v-if="editForm.errors.email" class="mt-1 text-xs text-red-600">{{ editForm.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600">Password Baru (Opsional)</label>
                            <input v-model="editForm.password" type="password" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                            <p v-if="editForm.errors.password" class="mt-1 text-xs text-red-600">{{ editForm.errors.password }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm text-gray-600">Konfirmasi Password Baru</label>
                            <input v-model="editForm.password_confirmation" type="password" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                        </div>
                        <div class="md:col-span-2 flex items-center gap-2">
                            <button type="submit" :disabled="editForm.processing" class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
                                Simpan Perubahan
                            </button>
                            <button type="button" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="cancelEdit">
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
