<script setup>
import Modal from '@/Components/Modal.vue';
import { confirmDialogController } from '@/composables/useGlobalConfirm';
import { computed } from 'vue';

const { state, confirmNow, cancelNow } = confirmDialogController;

const confirmButtonClass = computed(() => {
    if (state.variant === 'warning') {
        return 'bg-amber-500 text-slate-950 hover:bg-amber-400 dark:bg-amber-400 dark:hover:bg-amber-300';
    }

    if (state.variant === 'primary') {
        return 'bg-slate-900 text-white hover:bg-slate-800 dark:bg-amber-400 dark:text-slate-950 dark:hover:bg-amber-300';
    }

    return 'bg-rose-600 text-white hover:bg-rose-700 dark:bg-rose-500 dark:hover:bg-rose-400';
});
</script>

<template>
    <Modal :show="state.show" :closeable="!state.isProcessing" max-width="md" @close="cancelNow">
        <div class="p-4 sm:p-6">
            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                {{ state.title }}
            </h3>
            <p class="mt-2 text-sm text-slate-600 whitespace-pre-line dark:text-slate-300">
                {{ state.message }}
            </p>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                    :disabled="state.isProcessing"
                    :class="state.isProcessing ? 'cursor-not-allowed opacity-60' : ''"
                    @click="cancelNow"
                >
                    {{ state.cancelText }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-70"
                    :class="confirmButtonClass"
                    :disabled="state.isProcessing"
                    @click="confirmNow"
                >
                    <span
                        v-if="state.isProcessing"
                        class="me-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent"
                        aria-hidden="true"
                    />
                    {{ state.isProcessing ? state.loadingText : state.confirmText }}
                </button>
            </div>
        </div>
    </Modal>
</template>
