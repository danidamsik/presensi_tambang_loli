<script setup>
import { GLOBAL_NOTIFY_EVENT, notificationController } from '@/composables/useGlobalNotify';
import { computed, onBeforeUnmount, onMounted } from 'vue';

const { state, remove, notify } = notificationController;

const handleNotifyEvent = (event) => {
    notify(event.detail ?? {});
};

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    window.addEventListener(GLOBAL_NOTIFY_EVENT, handleNotifyEvent);
});

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return;
    }

    window.removeEventListener(GLOBAL_NOTIFY_EVENT, handleNotifyEvent);
});

const notificationClass = (type) => {
    if (type === 'success') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-200';
    }

    if (type === 'error') {
        return 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/15 dark:text-rose-200';
    }

    if (type === 'warning') {
        return 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-200';
    }

    return 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/15 dark:text-sky-200';
};

const iconClass = computed(() => ({
    success: 'text-emerald-500 dark:text-emerald-300',
    error: 'text-rose-500 dark:text-rose-300',
    warning: 'text-amber-500 dark:text-amber-300',
    info: 'text-sky-500 dark:text-sky-300',
}));

const iconPath = (type) => {
    if (type === 'success') {
        return 'M9 12.75L11.25 15L15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
    }

    if (type === 'error') {
        return 'M12 9v3.75m0 3.75h.007v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z';
    }

    if (type === 'warning') {
        return 'M12 9v3.75m0 3.75h.007v.008H12v-.008zM10.29 3.86l-7.68 13.3A1.5 1.5 0 003.9 19.5h16.2a1.5 1.5 0 001.29-2.34l-7.68-13.3a1.5 1.5 0 00-2.6 0z';
    }

    return 'M11.25 11.25l.041-.02a.75.75 0 011.09.66v3.11a.75.75 0 001.5 0v-3.11a2.25 2.25 0 00-3.272-1.996l-.041.02a.75.75 0 00.682 1.336zM12 9a.75.75 0 100-1.5.75.75 0 000 1.5zM21 12a9 9 0 11-18 0 9 9 0 0118 0z';
};
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed inset-0 z-[70] flex items-start justify-end p-4 sm:p-6">
            <TransitionGroup
                enter-active-class="transform transition duration-300 ease-out"
                enter-from-class="translate-x-8 opacity-0"
                enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transform transition duration-200 ease-in"
                leave-from-class="translate-x-0 opacity-100"
                leave-to-class="translate-x-8 opacity-0"
                tag="div"
                class="flex w-full max-w-sm flex-col gap-3"
            >
                <article
                    v-for="notification in state.notifications"
                    :key="notification.id"
                    class="pointer-events-auto rounded-xl border p-4 shadow-lg backdrop-blur"
                    :class="notificationClass(notification.type)"
                >
                    <div class="flex items-start gap-3">
                        <svg
                            class="mt-0.5 h-5 w-5 shrink-0"
                            :class="iconClass[notification.type] ?? iconClass.info"
                            viewBox="0 0 24 24"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                :d="iconPath(notification.type)"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>

                        <div class="min-w-0 flex-1">
                            <p v-if="notification.title" class="text-sm font-semibold">
                                {{ notification.title }}
                            </p>
                            <p class="text-sm" :class="notification.title ? 'mt-1' : ''">
                                {{ notification.message }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-md p-1 text-current/70 transition hover:bg-black/5 hover:text-current dark:hover:bg-white/10"
                            @click="remove(notification.id)"
                        >
                            <span class="sr-only">Tutup notifikasi</span>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M6 6L18 18M6 18L18 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                </article>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
