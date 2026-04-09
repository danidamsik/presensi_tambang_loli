<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    setting: {
        type: Object,
        required: true,
    },
});

const GOOGLE_MAPS_DEFAULT_LAT = -6.2;
const GOOGLE_MAPS_DEFAULT_LNG = 106.816666;
const googleMapsApiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY ?? '';

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const hasMapsKey = computed(() => googleMapsApiKey.trim().length > 0);
const mapContainerRef = ref(null);
const mapLoading = ref(false);
const mapError = ref('');
let mapResizeFrameId = null;
let mapResizeTimeoutId = null;

const form = useForm({
    latitude: props.setting.latitude ?? '',
    longitude: props.setting.longitude ?? '',
    radius_meters: Number(props.setting.radius_meters ?? 100),
    check_in_time: props.setting.check_in_time ?? '',
    check_out_time: props.setting.check_out_time ?? '',
});

let mapInstance = null;
let markerInstance = null;
let radiusCircleInstance = null;
const mapListeners = [];

const parseCoordinate = (value, fallback) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
};

const normalizeRadius = (value) => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed) || parsed < 1) {
        return 1;
    }

    return Math.round(parsed);
};

const getInitialPosition = () => ({
    lat: parseCoordinate(form.latitude, GOOGLE_MAPS_DEFAULT_LAT),
    lng: parseCoordinate(form.longitude, GOOGLE_MAPS_DEFAULT_LNG),
});

const currentLatitude = computed(() => parseCoordinate(form.latitude, GOOGLE_MAPS_DEFAULT_LAT).toFixed(6));
const currentLongitude = computed(() => parseCoordinate(form.longitude, GOOGLE_MAPS_DEFAULT_LNG).toFixed(6));

const summaryCards = computed(() => [
    {
        label: 'Radius Aktif',
        value: `${normalizeRadius(form.radius_meters)} m`,
        hint: 'Area valid presensi dari titik kantor',
        accent: 'from-slate-900 via-slate-800 to-slate-700 text-white',
    },
    {
        label: 'Jam Masuk',
        value: form.check_in_time || '--:--',
        hint: 'Waktu acuan check in harian',
        accent: 'from-sky-500 via-cyan-400 to-teal-200 text-sky-950',
    },
    {
        label: 'Jam Pulang',
        value: form.check_out_time || '--:--',
        hint: 'Waktu acuan check out harian',
        accent: 'from-amber-300 via-amber-200 to-orange-100 text-amber-950',
    },
]);

const inputClass = (hasError) => [
    'mt-2 block w-full rounded-2xl border bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-amber-400 focus:outline-none focus:ring-4 focus:ring-amber-100 dark:bg-slate-900/80 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-amber-500/10',
    hasError ? 'border-rose-300 dark:border-rose-500/40' : 'border-slate-200 dark:border-slate-700',
];

const handleLayoutResize = () => {
    if (!mapInstance || !window.google?.maps) {
        return;
    }

    const center = getInitialPosition();
    window.google.maps.event.trigger(mapInstance, 'resize');
    mapInstance.setCenter(center);
    syncMapMarkerAndCircle(center.lat, center.lng);
    updateRadiusCircle();
};

const scheduleLayoutResize = () => {
    if (typeof window === 'undefined') {
        return;
    }

    if (mapResizeFrameId) {
        window.cancelAnimationFrame(mapResizeFrameId);
    }

    if (mapResizeTimeoutId) {
        window.clearTimeout(mapResizeTimeoutId);
    }

    mapResizeFrameId = window.requestAnimationFrame(() => {
        handleLayoutResize();

        mapResizeTimeoutId = window.setTimeout(() => {
            handleLayoutResize();
        }, 120);
    });
};

const syncMapMarkerAndCircle = (lat, lng, moveCamera = false) => {
    if (!mapInstance || !markerInstance || !radiusCircleInstance) {
        return;
    }

    const latLng = { lat, lng };
    markerInstance.setPosition(latLng);
    radiusCircleInstance.setCenter(latLng);

    if (moveCamera) {
        mapInstance.panTo(latLng);
    }
};

const updateCoordinatesFromMap = (lat, lng, moveCamera = false) => {
    form.latitude = lat.toFixed(6);
    form.longitude = lng.toFixed(6);
    syncMapMarkerAndCircle(lat, lng, moveCamera);
};

const updateRadiusCircle = () => {
    form.radius_meters = normalizeRadius(form.radius_meters);

    if (radiusCircleInstance) {
        radiusCircleInstance.setRadius(form.radius_meters);
    }
};

const loadGoogleMapsApi = () => {
    if (window.google?.maps) {
        return Promise.resolve(window.google.maps);
    }

    if (!hasMapsKey.value) {
        return Promise.reject(new Error('Google Maps API key belum diatur.'));
    }

    if (window.__googleMapsApiPromise) {
        return window.__googleMapsApiPromise;
    }

    window.__googleMapsApiPromise = new Promise((resolve, reject) => {
        const scriptId = 'google-maps-js-api';
        const existingScript = document.getElementById(scriptId);

        if (existingScript) {
            existingScript.addEventListener('load', () => resolve(window.google.maps), { once: true });
            existingScript.addEventListener('error', () => reject(new Error('Gagal memuat script Google Maps.')), { once: true });
            return;
        }

        const script = document.createElement('script');
        script.id = scriptId;
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(googleMapsApiKey)}&loading=async`;
        script.async = true;
        script.defer = true;
        script.onload = () => {
            if (window.google?.maps) {
                resolve(window.google.maps);
                return;
            }

            reject(new Error('Google Maps tidak tersedia setelah script dimuat.'));
        };
        script.onerror = () => reject(new Error('Gagal memuat Google Maps. Cek API key atau koneksi internet.'));

        document.head.appendChild(script);
    });

    return window.__googleMapsApiPromise;
};

const initMap = async () => {
    if (!hasMapsKey.value || !mapContainerRef.value) {
        return;
    }

    mapLoading.value = true;
    mapError.value = '';

    try {
        const maps = await loadGoogleMapsApi();
        const center = getInitialPosition();

        mapInstance = new maps.Map(mapContainerRef.value, {
            center,
            zoom: 17,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false,
        });

        markerInstance = new maps.Marker({
            map: mapInstance,
            position: center,
            draggable: true,
            title: 'Lokasi Kantor',
        });

        radiusCircleInstance = new maps.Circle({
            map: mapInstance,
            center,
            radius: normalizeRadius(form.radius_meters),
            fillColor: '#0f172a',
            fillOpacity: 0.12,
            strokeColor: '#0f172a',
            strokeOpacity: 0.6,
            strokeWeight: 1,
        });

        mapListeners.push(
            mapInstance.addListener('click', (event) => {
                if (!event.latLng) {
                    return;
                }

                updateCoordinatesFromMap(event.latLng.lat(), event.latLng.lng());
            }),
        );

        mapListeners.push(
            markerInstance.addListener('dragend', (event) => {
                if (!event.latLng) {
                    return;
                }

                updateCoordinatesFromMap(event.latLng.lat(), event.latLng.lng());
            }),
        );
    } catch (error) {
        mapError.value = error instanceof Error ? error.message : 'Terjadi error saat memuat Google Maps.';
    } finally {
        mapLoading.value = false;
    }
};

const submit = () => {
    form.radius_meters = normalizeRadius(form.radius_meters);

    form.put(route('admin.settings.update'), {
        preserveScroll: true,
    });
};

watch(
    () => [form.latitude, form.longitude],
    ([lat, lng]) => {
        if (!mapInstance || !markerInstance || !radiusCircleInstance) {
            return;
        }

        const parsedLat = Number(lat);
        const parsedLng = Number(lng);

        if (!Number.isFinite(parsedLat) || !Number.isFinite(parsedLng)) {
            return;
        }

        syncMapMarkerAndCircle(parsedLat, parsedLng);
    },
);

watch(
    () => form.radius_meters,
    () => {
        updateRadiusCircle();
    },
);

onMounted(() => {
    initMap();

    if (typeof window !== 'undefined') {
        window.addEventListener('resize', scheduleLayoutResize);
        window.addEventListener('admin-layout:resize', scheduleLayoutResize);
    }
});

onBeforeUnmount(() => {
    mapListeners.forEach((listener) => listener.remove());
    mapListeners.length = 0;

    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', scheduleLayoutResize);
        window.removeEventListener('admin-layout:resize', scheduleLayoutResize);
    }

    if (typeof window !== 'undefined' && mapResizeFrameId) {
        window.cancelAnimationFrame(mapResizeFrameId);
    }

    if (typeof window !== 'undefined' && mapResizeTimeoutId) {
        window.clearTimeout(mapResizeTimeoutId);
    }
});
</script>

<template>
    <Head title="Pengaturan Lokasi & Jam Kerja" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-3">
                    <span class="inline-flex w-fit items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                        Admin Settings
                    </span>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl dark:text-slate-100">
                            Atur lokasi kantor dan jam kerja dengan presisi.
                        </h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-400">
                            Halaman ini mengontrol titik koordinat, radius presensi, dan waktu operasional agar data kehadiran tetap konsisten.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[23rem]">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/80">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Latitude</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ currentLatitude }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/80">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Longitude</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ currentLongitude }}</p>
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
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-300">Kontrol Presensi</p>
                            <h2 class="mt-4 text-3xl font-semibold leading-tight sm:text-4xl">
                                Kelola area valid absensi dan jadwal kerja dari satu panel.
                            </h2>
                            <p class="mt-4 text-sm leading-7 text-slate-300">
                                Gunakan peta untuk menetapkan titik kantor, atur radius presensi agar sesuai kondisi lapangan, lalu simpan jam masuk dan pulang sebagai acuan sistem.
                            </p>
                        </div>

                        <div class="rounded-3xl border border-white/10 bg-white/5 p-5 backdrop-blur">
                            <p class="text-xs uppercase tracking-[0.22em] text-slate-400">Panduan singkat</p>
                            <div class="mt-4 space-y-2 text-sm text-slate-200">
                                <p>Klik peta atau drag marker untuk mengubah koordinat.</p>
                                <p>Radius terlalu kecil berisiko membuat presensi gagal.</p>
                                <p>Jam kerja dipakai sebagai acuan keterlambatan dan operasional.</p>
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

            <section class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.75fr)]">
                <section class="min-w-0 rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Peta Lokasi</p>
                            <h3 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Titik kantor dan radius presensi</h3>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                Sesuaikan posisi kantor langsung dari peta agar koordinat lebih akurat.
                            </p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300">
                            Radius: {{ normalizeRadius(form.radius_meters) }} meter
                        </div>
                    </div>

                    <div v-if="!hasMapsKey" class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                        Google Maps belum aktif. Isi `VITE_GOOGLE_MAPS_API_KEY` di `.env`, lalu jalankan ulang Vite.
                    </div>

                    <div v-else class="mt-6 relative">
                        <div ref="mapContainerRef" class="h-[26rem] w-full rounded-[24px] border border-slate-200 bg-slate-100 dark:border-slate-800 dark:bg-slate-900" />
                        <div v-if="mapLoading" class="absolute inset-0 grid place-items-center rounded-[24px] bg-white/70 text-sm text-slate-700 dark:bg-slate-950/70 dark:text-slate-200">
                            Memuat Google Maps...
                        </div>
                    </div>

                    <p v-if="mapError" class="mt-3 text-sm text-rose-600 dark:text-rose-300">{{ mapError }}</p>

                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/80">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Latitude</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ currentLatitude }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/80">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Longitude</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ currentLongitude }}</p>
                        </div>
                    </div>
                </section>

                <div class="min-w-0 self-start space-y-6 xl:sticky xl:top-28">
                    <section class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Preview</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Ringkasan aktif</h3>
                        <div class="mt-5 space-y-3">
                            <div class="rounded-2xl bg-slate-950 px-4 py-4 text-white dark:bg-slate-900">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Jam operasional</p>
                                <p class="mt-2 text-2xl font-semibold">{{ form.check_in_time || '--:--' }} - {{ form.check_out_time || '--:--' }}</p>
                            </div>
                            <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-4 dark:border-slate-700">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Titik kantor</p>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    {{ currentLatitude }}, {{ currentLongitude }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_20px_80px_rgba(15,23,42,0.08)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/80">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400 dark:text-slate-500">Form Pengaturan</p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Update konfigurasi</h3>

                        <form class="mt-6 space-y-4" @submit.prevent="submit">
                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Latitude Kantor</label>
                                <input v-model="form.latitude" type="number" step="any" placeholder="-6.200000" :class="inputClass(!!form.errors.latitude)" />
                                <p v-if="form.errors.latitude" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ form.errors.latitude }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Longitude Kantor</label>
                                <input v-model="form.longitude" type="number" step="any" placeholder="106.816666" :class="inputClass(!!form.errors.longitude)" />
                                <p v-if="form.errors.longitude" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ form.errors.longitude }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Radius Presensi (meter)</label>
                                <input v-model.number="form.radius_meters" type="number" min="1" :class="inputClass(!!form.errors.radius_meters)" required />
                                <input v-model.number="form.radius_meters" type="range" min="10" max="5000" step="10" class="mt-4 w-full accent-amber-500" />
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Radius aktif: {{ normalizeRadius(form.radius_meters) }} meter</p>
                                <p v-if="form.errors.radius_meters" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ form.errors.radius_meters }}</p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Jam Masuk</label>
                                    <input v-model="form.check_in_time" type="time" :class="inputClass(!!form.errors.check_in_time)" />
                                    <p v-if="form.errors.check_in_time" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ form.errors.check_in_time }}</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Jam Pulang</label>
                                    <input v-model="form.check_out_time" type="time" :class="inputClass(!!form.errors.check_out_time)" />
                                    <p v-if="form.errors.check_out_time" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ form.errors.check_out_time }}</p>
                                </div>
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-amber-400 dark:text-slate-950 dark:hover:bg-amber-300"
                            >
                                {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                            </button>
                        </form>
                    </section>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
