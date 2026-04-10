<script setup>
import { useGlobalNotify } from '@/composables/useGlobalNotify';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    setting: { type: Object, required: true },
    todayAttendance: { type: Object, default: null },
    approvedTodayOvertimes: { type: Array, required: true },
    recentAttendances: { type: Array, required: true },
    recentOvertimes: { type: Array, required: true },
});

const page = usePage();
const notify = useGlobalNotify();
const videoRef = ref(null);
const cameraLoading = ref(false);
const cameraReady = ref(false);
const cameraError = ref('');
const locationLoading = ref(false);
const locationError = ref('');
const currentPosition = ref(null);
const capturedPhoto = ref('');
const captureTimestamp = ref('');
const activeAction = ref('');
let mediaStream = null;

const overtimeForm = useForm({
    overtime_date: new Date().toISOString().slice(0, 10),
    planned_start: '',
    planned_end: '',
    reason: '',
});

const displayName = computed(() => {
    const user = page.props.auth?.user;
    return user?.full_name ?? user?.name ?? 'User';
});

const officeReady = computed(() => props.setting.is_configured);
const canClockIn = computed(() => !props.todayAttendance?.clock_in_at);
const canClockOut = computed(() => Boolean(props.todayAttendance?.clock_in_at) && !props.todayAttendance?.clock_out_at);
const hasCameraSupport = computed(() => typeof navigator !== 'undefined' && Boolean(navigator.mediaDevices?.getUserMedia));
const hasGeolocationSupport = computed(() => typeof navigator !== 'undefined' && Boolean(navigator.geolocation));

const summaryCards = computed(() => [
    {
        label: 'Status Hari Ini',
        value: props.todayAttendance?.clock_out_at ? 'Selesai Shift' : props.todayAttendance?.clock_in_at ? 'Sedang Bekerja' : 'Belum Presensi',
        hint: props.todayAttendance?.clock_in_at
            ? `Masuk ${formatTime(props.todayAttendance.clock_in_at)}${props.todayAttendance?.clock_out_at ? `, pulang ${formatTime(props.todayAttendance.clock_out_at)}` : ''}`
            : 'Siapkan GPS dan kamera sebelum presensi',
    },
    {
        label: 'Radius Kantor',
        value: `${props.setting.radius_meters ?? 100} m`,
        hint: officeReady.value ? 'Presensi diterima jika masih dalam jangkauan lokasi kantor' : 'Admin belum mengatur titik kantor',
    },
    {
        label: 'Jadwal Kerja',
        value: `${props.setting.check_in_time ?? '--:--'} - ${props.setting.check_out_time ?? '--:--'}`,
        hint: 'Jam acuan presensi normal',
    },
    {
        label: 'Lembur Approved Hari Ini',
        value: String(props.approvedTodayOvertimes.length),
        hint: props.approvedTodayOvertimes.length ? 'Bisa dipresensikan dari panel lembur di bawah' : 'Belum ada lembur aktif hari ini',
    },
]);

const formatDate = (value) => {
    if (!value) return '-';
    const [year, month, day] = value.split('-').map(Number);
    if (!year || !month || !day) return value;

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

const firstErrorMessage = (errors) => {
    const firstValue = Object.values(errors ?? {}).find((value) => value);
    if (Array.isArray(firstValue)) return firstValue[0];
    return firstValue ?? 'Terjadi kesalahan saat memproses permintaan.';
};

const stopCamera = () => {
    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => track.stop());
        mediaStream = null;
    }

    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }

    cameraReady.value = false;
};

const startCamera = async () => {
    if (!hasCameraSupport.value) {
        cameraError.value = 'Browser ini belum mendukung akses kamera.';
        throw new Error(cameraError.value);
    }

    if (mediaStream && cameraReady.value) {
        return;
    }

    cameraLoading.value = true;
    cameraError.value = '';

    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'user',
                width: { ideal: 1280 },
                height: { ideal: 720 },
            },
            audio: false,
        });

        await nextTick();

        if (!videoRef.value) {
            throw new Error('Preview kamera belum siap.');
        }

        videoRef.value.srcObject = mediaStream;
        await videoRef.value.play();
        cameraReady.value = true;
    } catch (error) {
        stopCamera();
        cameraError.value = error instanceof Error ? error.message : 'Kamera tidak bisa diakses.';
        throw error instanceof Error ? error : new Error(cameraError.value);
    } finally {
        cameraLoading.value = false;
    }
};

const ensureLocation = () => new Promise((resolve, reject) => {
    if (!hasGeolocationSupport.value) {
        locationError.value = 'Browser ini belum mendukung GPS/geolocation.';
        reject(new Error(locationError.value));
        return;
    }

    locationLoading.value = true;
    locationError.value = '';

    navigator.geolocation.getCurrentPosition(
        (position) => {
            currentPosition.value = {
                latitude: Number(position.coords.latitude),
                longitude: Number(position.coords.longitude),
                accuracy: Math.round(position.coords.accuracy ?? 0),
            };
            locationLoading.value = false;
            resolve(currentPosition.value);
        },
        (error) => {
            locationLoading.value = false;
            locationError.value = error.message || 'Lokasi tidak bisa diambil.';
            reject(new Error(locationError.value));
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
    );
});

const captureSnapshot = () => {
    if (!videoRef.value || !cameraReady.value) {
        throw new Error('Aktifkan kamera sebelum mengambil foto presensi.');
    }

    const canvas = document.createElement('canvas');
    const width = videoRef.value.videoWidth || 1280;
    const height = videoRef.value.videoHeight || 720;

    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');
    if (!context) {
        throw new Error('Gagal memproses frame kamera.');
    }

    context.drawImage(videoRef.value, 0, 0, width, height);
    capturedPhoto.value = canvas.toDataURL('image/jpeg', 0.92);
    captureTimestamp.value = new Date().toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });

    return capturedPhoto.value;
};

const postAction = (routeName, payload, successMessage, routeParams = undefined) => new Promise((resolve, reject) => {
    router.post(route(routeName, routeParams), payload, {
        preserveScroll: true,
        onSuccess: () => {
            notify.success(successMessage);
            resolve();
        },
        onError: (errors) => {
            const message = firstErrorMessage(errors);
            notify.error(message);
            reject(new Error(message));
        },
    });
});

const submitPresence = async (actionKey, routeName, successMessage, routeParams = undefined) => {
    if (!officeReady.value) {
        notify.error('Lokasi kantor belum dikonfigurasi admin.');
        return;
    }

    activeAction.value = actionKey;

    try {
        await startCamera();
        await ensureLocation();
        const photo = captureSnapshot();

        await postAction(routeName, {
            latitude: currentPosition.value.latitude,
            longitude: currentPosition.value.longitude,
            photo,
        }, successMessage, routeParams);
    } catch (error) {
        if (error instanceof Error && !cameraError.value && !locationError.value) {
            notify.error(error.message);
        }
    } finally {
        activeAction.value = '';
    }
};

const submitOvertimeRequest = () => {
    overtimeForm.post(route('employee.overtimes.store'), {
        preserveScroll: true,
        onSuccess: () => {
            notify.success('Pengajuan lembur berhasil dikirim.');
            overtimeForm.reset('planned_start', 'planned_end', 'reason');
            overtimeForm.overtime_date = new Date().toISOString().slice(0, 10);
        },
        onError: (errors) => {
            notify.error(firstErrorMessage(errors));
        },
    });
};

onBeforeUnmount(() => {
    stopCamera();
});
</script>

<template>
    <Head title="Home" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-amber-950 px-5 py-6 text-white shadow-xl shadow-slate-900/10 dark:border-slate-800">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-200/80">Portal Karyawan</p>
                        <h1 class="mt-3 text-2xl font-semibold sm:text-3xl">Presensi berbasis lokasi dan bukti wajah untuk {{ displayName }}</h1>
                        <p class="mt-3 max-w-xl text-sm text-slate-300">
                            Sistem akan mengambil GPS dan snapshot real-time dari kamera saat tombol presensi ditekan.
                        </p>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2 lg:min-w-[22rem] lg:max-w-md">
                        <div
                            v-for="card in summaryCards"
                            :key="card.label"
                            class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur"
                        >
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-300">{{ card.label }}</p>
                            <p class="mt-2 text-lg font-semibold text-white">{{ card.value }}</p>
                            <p class="mt-1 text-sm text-slate-300">{{ card.hint }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Ruang Presensi</p>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">GPS dan kamera aktif saat absen</h2>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                Klik absen masuk atau pulang. Sistem akan mengambil lokasi dan snapshot kamera secara langsung.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                :disabled="cameraLoading"
                                @click="startCamera"
                            >
                                {{ cameraLoading ? 'Mengaktifkan...' : cameraReady ? 'Kamera Aktif' : 'Aktifkan Kamera' }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                :disabled="locationLoading"
                                @click="ensureLocation().catch(() => {})"
                            >
                                {{ locationLoading ? 'Mengambil GPS...' : currentPosition ? 'Refresh Lokasi' : 'Ambil Lokasi' }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
                        <div class="relative overflow-hidden rounded-[1.5rem] border border-slate-200 bg-slate-950 dark:border-slate-800">
                            <video ref="videoRef" autoplay muted playsinline class="aspect-[4/3] w-full object-cover" />
                            <div v-if="!cameraReady" class="absolute inset-0 grid place-items-center bg-slate-950/85 px-6 text-center text-sm text-slate-300">
                                <div>
                                    <p class="font-medium text-white">Preview kamera belum aktif</p>
                                    <p class="mt-2">Izinkan akses kamera agar foto presensi diambil secara real-time.</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/50">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">Lokasi Saat Ini</p>
                                <p class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ currentPosition ? `${currentPosition.latitude.toFixed(6)}, ${currentPosition.longitude.toFixed(6)}` : 'Belum diambil' }}
                                </p>
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    Akurasi: {{ currentPosition?.accuracy ? `${currentPosition.accuracy} m` : '-' }}
                                </p>
                            </div>

                            <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/50">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">Snapshot Terakhir</p>
                                <img
                                    v-if="capturedPhoto"
                                    :src="capturedPhoto"
                                    alt="Snapshot presensi"
                                    class="mt-3 aspect-[4/3] w-full rounded-xl object-cover"
                                >
                                <div v-else class="mt-3 grid aspect-[4/3] place-items-center rounded-xl border border-dashed border-slate-300 text-sm text-slate-400 dark:border-slate-700">
                                    Belum ada snapshot
                                </div>
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Diambil: {{ captureTimestamp || '-' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-[1.25rem] bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canClockIn || activeAction === 'clock-out' || !officeReady"
                            @click="submitPresence('clock-in', 'employee.attendance.clock-in', 'Absen masuk berhasil disimpan.')"
                        >
                            {{ activeAction === 'clock-in' ? 'Memproses Absen Masuk...' : 'Absen Masuk' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-[1.25rem] bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-amber-400 dark:text-slate-950 dark:hover:bg-amber-300"
                            :disabled="!canClockOut || activeAction === 'clock-in' || !officeReady"
                            @click="submitPresence('clock-out', 'employee.attendance.clock-out', 'Absen pulang berhasil disimpan.')"
                        >
                            {{ activeAction === 'clock-out' ? 'Memproses Absen Pulang...' : 'Absen Pulang' }}
                        </button>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div class="rounded-[1.5rem] border border-slate-200 p-4 dark:border-slate-800">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">Absen Masuk</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ formatTime(todayAttendance?.clock_in_at) }}</p>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ todayAttendance?.clock_in_location || 'Belum ada lokasi masuk' }}</p>
                        </div>
                        <div class="rounded-[1.5rem] border border-slate-200 p-4 dark:border-slate-800">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">Absen Pulang</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ formatTime(todayAttendance?.clock_out_at) }}</p>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ todayAttendance?.clock_out_location || 'Belum ada lokasi pulang' }}</p>
                        </div>
                    </div>

                    <div v-if="cameraError || locationError || !officeReady" class="mt-4 space-y-2">
                        <p v-if="cameraError" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                            {{ cameraError }}
                        </p>
                        <p v-if="locationError" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                            {{ locationError }}
                        </p>
                        <p v-if="!officeReady" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                            Admin belum mengisi latitude dan longitude kantor, jadi validasi radius belum bisa dijalankan.
                        </p>
                    </div>
                </div>

                <div class="space-y-4">
                    <section class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Pengajuan Lembur</p>
                        <h2 class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">Buat request baru</h2>

                        <form class="mt-4 space-y-4" @submit.prevent="submitOvertimeRequest">
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Tanggal</label>
                                <input
                                    v-model="overtimeForm.overtime_date"
                                    type="date"
                                    class="mt-2 block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:ring-amber-500/10"
                                >
                                <p v-if="overtimeForm.errors.overtime_date" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ overtimeForm.errors.overtime_date }}</p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Jam Mulai</label>
                                    <input
                                        v-model="overtimeForm.planned_start"
                                        type="time"
                                        class="mt-2 block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:ring-amber-500/10"
                                    >
                                    <p v-if="overtimeForm.errors.planned_start" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ overtimeForm.errors.planned_start }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Jam Selesai</label>
                                    <input
                                        v-model="overtimeForm.planned_end"
                                        type="time"
                                        class="mt-2 block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:ring-amber-500/10"
                                    >
                                    <p v-if="overtimeForm.errors.planned_end" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ overtimeForm.errors.planned_end }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Alasan</label>
                                <textarea
                                    v-model="overtimeForm.reason"
                                    rows="4"
                                    class="mt-2 block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:ring-amber-500/10"
                                    placeholder="Contoh: penyelesaian target harian, closing laporan, atau kebutuhan operasional lapangan."
                                ></textarea>
                                <p v-if="overtimeForm.errors.reason" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ overtimeForm.errors.reason }}</p>
                            </div>

                            <button
                                type="submit"
                                :disabled="overtimeForm.processing"
                                class="inline-flex w-full items-center justify-center rounded-[1.25rem] bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-amber-400 dark:text-slate-950 dark:hover:bg-amber-300"
                            >
                                {{ overtimeForm.processing ? 'Mengirim Pengajuan...' : 'Kirim Pengajuan Lembur' }}
                            </button>
                        </form>
                    </section>

                    <section class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Presensi Lembur</p>
                                <h2 class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">Request approved hari ini</h2>
                            </div>
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                {{ approvedTodayOvertimes.length }} aktif
                            </span>
                        </div>

                        <div v-if="approvedTodayOvertimes.length" class="mt-4 space-y-3">
                            <article
                                v-for="overtime in approvedTodayOvertimes"
                                :key="overtime.id"
                                class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/50"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}</p>
                                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ overtime.reason || '-' }}</p>
                                    </div>
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                        {{ overtime.approval_status }}
                                    </span>
                                </div>

                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">Mulai Lembur</p>
                                        <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ formatTime(overtime.actual_start) }}</p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">Selesai Lembur</p>
                                        <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ formatTime(overtime.actual_end) }}</p>
                                    </div>
                                </div>

                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-[1.1rem] bg-sky-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="Boolean(overtime.actual_start) || activeAction === `overtime-finish-${overtime.id}` || !officeReady"
                                        @click="submitPresence(`overtime-start-${overtime.id}`, 'employee.overtimes.start', 'Absen mulai lembur berhasil disimpan.', overtime.id)"
                                    >
                                        {{ activeAction === `overtime-start-${overtime.id}` ? 'Memproses Mulai...' : 'Absen Mulai Lembur' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-[1.1rem] bg-fuchsia-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-fuchsia-700 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="!overtime.actual_start || Boolean(overtime.actual_end) || activeAction === `overtime-start-${overtime.id}` || !officeReady"
                                        @click="submitPresence(`overtime-finish-${overtime.id}`, 'employee.overtimes.finish', 'Absen selesai lembur berhasil disimpan.', overtime.id)"
                                    >
                                        {{ activeAction === `overtime-finish-${overtime.id}` ? 'Memproses Selesai...' : 'Absen Selesai Lembur' }}
                                    </button>
                                </div>
                            </article>
                        </div>

                        <div v-else class="mt-4 rounded-[1.5rem] border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            Belum ada lembur approved untuk hari ini.
                        </div>
                    </section>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                <section class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Riwayat Presensi Terakhir</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                <tr>
                                    <th class="py-2 pe-3 font-medium">Tanggal</th>
                                    <th class="py-2 pe-3 font-medium">Masuk</th>
                                    <th class="py-2 pe-3 font-medium">Pulang</th>
                                    <th class="py-2 pe-3 font-medium">Lokasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                                <tr v-for="attendance in recentAttendances" :key="attendance.id">
                                    <td class="py-3 pe-3 whitespace-nowrap">{{ formatDate(attendance.date) }}</td>
                                    <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(attendance.clock_in_at) }}</td>
                                    <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(attendance.clock_out_at) }}</td>
                                    <td class="py-3 pe-3">
                                        <p class="max-w-[220px] truncate" :title="attendance.clock_in_location || '-'">In: {{ attendance.clock_in_location || '-' }}</p>
                                        <p class="max-w-[220px] truncate" :title="attendance.clock_out_location || '-'">Out: {{ attendance.clock_out_location || '-' }}</p>
                                    </td>
                                </tr>
                                <tr v-if="!recentAttendances.length">
                                    <td colspan="4" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada data presensi.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Riwayat Lembur</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-slate-200 text-left text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                <tr>
                                    <th class="py-2 pe-3 font-medium">Tanggal</th>
                                    <th class="py-2 pe-3 font-medium">Rencana</th>
                                    <th class="py-2 pe-3 font-medium">Aktual</th>
                                    <th class="py-2 pe-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                                <tr v-for="overtime in recentOvertimes" :key="overtime.id">
                                    <td class="py-3 pe-3 whitespace-nowrap">{{ formatDate(overtime.overtime_date) }}</td>
                                    <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(overtime.planned_start) }} - {{ formatTime(overtime.planned_end) }}</td>
                                    <td class="py-3 pe-3 whitespace-nowrap">{{ formatTime(overtime.actual_start) }} - {{ formatTime(overtime.actual_end) }}</td>
                                    <td class="py-3 pe-3">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="overtime.approval_status === 'Approved'
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                : overtime.approval_status === 'Rejected'
                                                    ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'
                                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'"
                                        >
                                            {{ overtime.approval_status }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!recentOvertimes.length">
                                    <td colspan="4" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada riwayat lembur.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
