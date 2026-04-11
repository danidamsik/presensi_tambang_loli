<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isEmployee = computed(() => user.value?.role === 'Employee');
const roleLabel = computed(() => (user.value?.role === 'Employee' ? 'Karyawan' : user.value?.role ?? '-'));

const formatDate = (value) => {
    if (!value) return '-';

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Makassar',
    }).format(new Date(value));
};

const profileItems = computed(() => [
    { label: 'Nama Lengkap', value: user.value?.full_name ?? '-' },
    { label: 'ID Number', value: user.value?.id_number ?? '-' },
    { label: 'Email', value: user.value?.email ?? '-' },
    { label: 'Role', value: roleLabel.value },
    { label: 'Akun Dibuat', value: formatDate(user.value?.created_at) },
]);
</script>

<template>
    <Head title="Profil" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <template v-if="isEmployee">
                <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Profil Karyawan</p>
                    <h3 class="mt-2 text-base font-semibold text-slate-900 dark:text-slate-100">Informasi Diri</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Data akun Anda bersifat informasi. Jika ada data yang keliru, hubungi admin untuk perbaikan.
                    </p>

                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div
                            v-for="item in profileItems"
                            :key="item.label"
                            class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/60"
                        >
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">{{ item.label }}</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ item.value }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                    <UpdatePasswordForm class="max-w-xl" />
                </section>
            </template>

            <template v-else>
                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:p-8">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:p-8">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:p-8">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
