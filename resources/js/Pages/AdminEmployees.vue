<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    employees: { type: Object, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
});

const page = usePage();
const search = ref(props.filters.q ?? '');
const editingEmployee = ref(null);

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const hasActiveFilter = computed(() => search.value.trim().length > 0);
const totalFilteredEmployees = computed(() => props.employees.total ?? props.employees.data.length);

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

const summaryCards = computed(() => [
    {
        label: 'Total Karyawan',
        value: props.summary.totalEmployees,
        hint: 'Akun employee aktif',
        accent: 'from-slate-900 via-slate-800 to-slate-700 text-white',
    },
    {
        label: 'Akun Admin',
        value: props.summary.totalAdmins,
        hint: 'Pengguna akses admin',
        accent: 'from-sky-500 via-cyan-400 to-teal-200 text-sky-950',
    },
    {
        label: 'Hasil Pencarian',
        value: totalFilteredEmployees.value,
        hint: hasActiveFilter.value ? `Filter: "${search.value}"` : 'Semua data',
        accent: 'from-amber-300 via-amber-200 to-orange-100 text-amber-950',
    },
]);

const inputClass = (hasError) => [
    'mt-2 block w-full rounded-2xl border bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:bg-slate-900/80 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-amber-500/10',
    hasError ? 'border-rose-300 dark:border-rose-500/40' : 'border-slate-200 dark:border-slate-700',
];

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

const visitPage = (url) => {
    if (!url) return;

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head title="Kelola Karyawan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-3">
                    <span class="inline-flex w-fit items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                        Admin Employees
                    </span>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl dark:text-slate-100">
                            Kelola akun karyawan dengan rapi dan konsisten.
                        </h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-400">
                            Tampilan ini diselaraskan dengan dashboard admin agar alur tambah, cari, edit, dan hapus akun tetap terasa satu sistem.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[23rem]">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/80">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Status</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                            {{ hasActiveFilter ? 'Filter aktif' : 'Menampilkan semua data' }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/80">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Data Ditampilkan</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ totalFilteredEmployees }} akun</p>
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

            <section class="overflow-hidden rounded-[28px] bg-slate-950 text-white shadow-[0_30px_90px_rgba(15,23,42,0.18)] dark:bg-slate-900">
                <div class="grid gap-6 p-6 sm:p-8">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="max-w-2xl">
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-300">Manajemen Akun</p>
                            <h2 class="mt-4 text-3xl font-semibold leading-tight sm:text-4xl">
                                Susun data employee yang mudah dicari dan aman dikelola.
                            </h2>
                            <p class="mt-4 text-sm leading-7 text-slate-300">
                                Gunakan form di bawah untuk menambah akun baru, lalu manfaatkan pencarian untuk merapikan data yang sudah ada.
                            </p>
                        </div>

                        <div class="rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur">
                            <p class="text-xs uppercase tracking-[0.22em] text-slate-400">Catatan</p>
                            <div class="mt-4 space-y-2 text-sm text-slate-200">
                                <p>Pastikan `ID Number` unik.</p>
                                <p>Gunakan email aktif untuk akses login.</p>
                                <p>Ganti password hanya saat dibutuhkan.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
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
            </section>

            <section class="grid items-start gap-6 xl:grid-cols-[minmax(340px,0.9fr)_minmax(0,1.4fr)]">
                <div class="self-start space-y-6 xl:sticky xl:top-28">
                    <section class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Form Input</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Tambah karyawan</h3>

                        <form class="mt-6 space-y-4" @submit.prevent="submitCreate">
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">ID Number</label>
                                <input v-model="createForm.id_number" type="text" :class="inputClass(!!createForm.errors.id_number)" required />
                                <p v-if="createForm.errors.id_number" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ createForm.errors.id_number }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nama Lengkap</label>
                                <input v-model="createForm.full_name" type="text" :class="inputClass(!!createForm.errors.full_name)" required />
                                <p v-if="createForm.errors.full_name" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ createForm.errors.full_name }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                                <input v-model="createForm.email" type="email" :class="inputClass(!!createForm.errors.email)" required />
                                <p v-if="createForm.errors.email" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ createForm.errors.email }}</p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Password</label>
                                    <input v-model="createForm.password" type="password" :class="inputClass(!!createForm.errors.password)" required />
                                    <p v-if="createForm.errors.password" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ createForm.errors.password }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Konfirmasi Password</label>
                                    <input v-model="createForm.password_confirmation" type="password" :class="inputClass(false)" required />
                                </div>
                            </div>
                            <button
                                type="submit"
                                :disabled="createForm.processing"
                                class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-amber-400 dark:text-slate-950 dark:hover:bg-amber-300"
                            >
                                {{ createForm.processing ? 'Menyimpan...' : 'Simpan Karyawan' }}
                            </button>
                        </form>
                    </section>

                    <section class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Panduan Cepat</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Agar input lebih rapi</h3>
                        <div class="mt-5 space-y-3">
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/80">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Gunakan ID yang konsisten</p>
                                <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Pastikan format `ID Number` mengikuti pola internal perusahaan agar mudah dicari.</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/80">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Cek data sebelum tambah</p>
                                <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Gunakan pencarian di panel kanan untuk menghindari duplikasi akun employee.</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/80">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Password hanya seperlunya</p>
                                <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Saat edit data, isi password baru hanya jika memang ingin melakukan reset kredensial.</p>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Daftar Data</p>
                            <h3 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Daftar karyawan</h3>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300">
                            {{ totalFilteredEmployees }} data ditemukan
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col gap-3 lg:flex-row">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari nama / ID / email"
                            :class="inputClass(false)"
                            class="mt-0 lg:flex-1"
                            @keyup.enter="applyFilter"
                        />
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="applyFilter">
                            Cari
                        </button>
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="resetFilter">
                            Reset
                        </button>
                    </div>

                    <div class="mt-6 space-y-3 md:hidden">
                        <article
                            v-for="employee in employees.data"
                            :key="employee.id"
                            class="rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/80"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-slate-100">{{ employee.full_name }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ employee.email }}</p>
                                </div>
                                <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-600 shadow-sm dark:bg-slate-800 dark:text-slate-300">
                                    {{ employee.id_number }}
                                </span>
                            </div>
                            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Dibuat: {{ formatDateTime(employee.created_at) }}</p>
                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <button type="button" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-white dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="startEdit(employee)">
                                    Edit
                                </button>
                                <button type="button" class="rounded-2xl bg-rose-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-rose-700" @click="removeEmployee(employee)">
                                    Hapus
                                </button>
                            </div>
                        </article>
                        <div v-if="employees.data.length === 0" class="rounded-3xl border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            Data karyawan belum ada.
                        </div>
                    </div>

                    <div class="mt-6 hidden overflow-x-auto md:block">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                <tr>
                                    <th class="py-3 pe-4 font-medium">ID Number</th>
                                    <th class="py-3 pe-4 font-medium">Nama</th>
                                    <th class="py-3 pe-4 font-medium">Email</th>
                                    <th class="py-3 pe-4 font-medium">Dibuat</th>
                                    <th class="py-3 pe-4 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                                <tr v-for="employee in employees.data" :key="employee.id">
                                    <td class="py-4 pe-4 font-medium text-slate-900 dark:text-slate-100">{{ employee.id_number }}</td>
                                    <td class="py-4 pe-4">{{ employee.full_name }}</td>
                                    <td class="py-4 pe-4">{{ employee.email }}</td>
                                    <td class="py-4 pe-4">{{ formatDateTime(employee.created_at) }}</td>
                                    <td class="py-4 pe-4">
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="rounded-2xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="startEdit(employee)">
                                                Edit
                                            </button>
                                            <button type="button" class="rounded-2xl bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:hover:bg-rose-500/20" @click="removeEmployee(employee)">
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="employees.data.length === 0">
                                    <td colspan="5" class="py-8 text-center text-slate-500 dark:text-slate-400">Data karyawan belum ada.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="employees.links?.length > 3" class="mt-6 flex flex-wrap gap-2">
                        <button
                            v-for="(link, index) in employees.links"
                            :key="index"
                            type="button"
                            :disabled="!link.url || link.active"
                            class="inline-flex min-w-[2.75rem] items-center justify-center rounded-2xl border px-3 py-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                            :class="link.active
                                ? 'border-slate-950 bg-slate-950 text-white dark:border-amber-400 dark:bg-amber-400 dark:text-slate-950'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'"
                            @click="visitPage(link.url)"
                            v-html="link.label"
                        />
                    </div>
                </section>
            </section>

            <section v-if="editingEmployee" class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Edit Data</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Edit karyawan: {{ editingEmployee.full_name }}</h3>
                    </div>
                    <button type="button" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="cancelEdit">
                        Tutup Panel Edit
                    </button>
                </div>

                <form class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="submitEdit">
                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">ID Number</label>
                        <input v-model="editForm.id_number" type="text" :class="inputClass(!!editForm.errors.id_number)" required />
                        <p v-if="editForm.errors.id_number" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ editForm.errors.id_number }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Nama Lengkap</label>
                        <input v-model="editForm.full_name" type="text" :class="inputClass(!!editForm.errors.full_name)" required />
                        <p v-if="editForm.errors.full_name" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ editForm.errors.full_name }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                        <input v-model="editForm.email" type="email" :class="inputClass(!!editForm.errors.email)" required />
                        <p v-if="editForm.errors.email" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ editForm.errors.email }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Password Baru</label>
                        <input v-model="editForm.password" type="password" :class="inputClass(!!editForm.errors.password)" />
                        <p v-if="editForm.errors.password" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ editForm.errors.password }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Konfirmasi Password Baru</label>
                        <input v-model="editForm.password_confirmation" type="password" :class="inputClass(false)" />
                    </div>
                    <div class="md:col-span-2 flex flex-col gap-3 sm:flex-row">
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-amber-400 dark:text-slate-950 dark:hover:bg-amber-300"
                        >
                            {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                        </button>
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" @click="cancelEdit">
                            Batal
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
