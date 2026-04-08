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
    if (!hasMapsKey.value) {
        return;
    }

    if (!mapContainerRef.value) {
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
});

onBeforeUnmount(() => {
    mapListeners.forEach((listener) => listener.remove());
    mapListeners.length = 0;
});
</script>

<template>
    <Head title="Pengaturan Lokasi & Jam Kerja" />

    <AuthenticatedLayout>
        <template #header>
            <div class="space-y-1">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengaturan Lokasi & Jam Kerja</h2>
                <p class="text-sm text-gray-500">Atur koordinat kantor, radius presensi, dan jam operasional kerja.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <div v-if="flashSuccess" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ flashSuccess }}
                </div>
                <div v-if="flashError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ flashError }}
                </div>

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submit">
                        <div class="md:col-span-2">
                            <label class="block text-sm text-gray-600">Pilih Lokasi Kantor di Google Maps</label>
                            <p class="mt-1 text-xs text-gray-500">
                                Klik di peta atau drag marker untuk mengisi latitude dan longitude otomatis.
                            </p>

                            <div v-if="!hasMapsKey" class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                Google Maps belum aktif. Isi variabel <code>VITE_GOOGLE_MAPS_API_KEY</code> di file <code>.env</code>,
                                lalu jalankan ulang server Vite.
                            </div>

                            <div v-else class="mt-2 relative">
                                <div ref="mapContainerRef" class="h-80 w-full rounded-lg border border-gray-300 bg-gray-100" />
                                <div v-if="mapLoading" class="absolute inset-0 grid place-items-center rounded-lg bg-white/70 text-sm text-gray-700">
                                    Memuat Google Maps...
                                </div>
                            </div>

                            <p v-if="mapError" class="mt-2 text-xs text-red-600">{{ mapError }}</p>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-600">Latitude Kantor</label>
                            <input v-model="form.latitude" type="number" step="any" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="-6.200000" />
                            <p v-if="form.errors.latitude" class="mt-1 text-xs text-red-600">{{ form.errors.latitude }}</p>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-600">Longitude Kantor</label>
                            <input v-model="form.longitude" type="number" step="any" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="106.816666" />
                            <p v-if="form.errors.longitude" class="mt-1 text-xs text-red-600">{{ form.errors.longitude }}</p>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-600">Radius Presensi (meter)</label>
                            <input v-model.number="form.radius_meters" type="number" min="1" class="mt-1 w-full rounded-md border-gray-300 text-sm" required />
                            <input v-model.number="form.radius_meters" type="range" min="10" max="5000" step="10" class="mt-2 w-full" />
                            <p class="mt-1 text-xs text-gray-500">Radius aktif: {{ form.radius_meters }} meter</p>
                            <p v-if="form.errors.radius_meters" class="mt-1 text-xs text-red-600">{{ form.errors.radius_meters }}</p>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-600">Jam Masuk</label>
                            <input v-model="form.check_in_time" type="time" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                            <p v-if="form.errors.check_in_time" class="mt-1 text-xs text-red-600">{{ form.errors.check_in_time }}</p>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-600">Jam Pulang</label>
                            <input v-model="form.check_out_time" type="time" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                            <p v-if="form.errors.check_out_time" class="mt-1 text-xs text-red-600">{{ form.errors.check_out_time }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <button type="submit" :disabled="form.processing" class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
                                Simpan Pengaturan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
