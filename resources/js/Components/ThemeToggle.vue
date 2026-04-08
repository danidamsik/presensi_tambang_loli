<script setup>
import { useTheme } from '@/theme';

const { isDark, toggleTheme } = useTheme();

defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700 dark:focus:ring-offset-slate-900"
        :class="compact ? 'h-9 w-9 p-0' : ''"
        @click="toggleTheme"
    >
        <svg
            v-if="isDark"
            class="h-4 w-4"
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                d="M12 3V4M12 20V21M4.22 4.22L4.93 4.93M19.07 19.07L19.78 19.78M3 12H4M20 12H21M4.22 19.78L4.93 19.07M19.07 4.93L19.78 4.22M16 12C16 14.21 14.21 16 12 16C9.79 16 8 14.21 8 12C8 9.79 9.79 8 12 8C14.21 8 16 9.79 16 12Z"
                stroke="currentColor"
                stroke-width="1.6"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </svg>
        <svg
            v-else
            class="h-4 w-4"
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                d="M21 12.79C20.84 14.49 20.2 16.11 19.16 17.47C18.12 18.83 16.73 19.88 15.13 20.5C13.53 21.12 11.78 21.29 10.09 21C8.4 20.71 6.81 19.98 5.48 18.89C4.15 17.8 3.13 16.39 2.54 14.79C1.95 13.18 1.81 11.45 2.15 9.77C2.49 8.09 3.3 6.53 4.5 5.27C5.7 4.01 7.24 3.13 8.93 2.71C8.13 3.78 7.74 5.11 7.84 6.46C7.94 7.81 8.52 9.07 9.48 10.02C10.43 10.98 11.69 11.56 13.04 11.66C14.39 11.76 15.72 11.37 16.79 10.57C17.32 11.27 17.65 12.11 17.74 12.99C17.83 13.87 17.68 14.76 17.31 15.56"
                stroke="currentColor"
                stroke-width="1.6"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </svg>
        <span v-if="!compact" class="ms-2">
            {{ isDark ? 'Light' : 'Dark' }}
        </span>
    </button>
</template>
