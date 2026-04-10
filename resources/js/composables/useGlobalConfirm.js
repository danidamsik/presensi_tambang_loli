import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const defaults = {
    show: false,
    title: 'Konfirmasi',
    message: 'Apakah Anda yakin ingin melanjutkan?',
    confirmText: 'Ya, Lanjutkan',
    cancelText: 'Batal',
    variant: 'danger',
    waitForBackend: true,
    loadingText: 'Memproses...',
    isProcessing: false,
};

const state = reactive({ ...defaults });
let pendingResolve = null;
let hasStartedInertiaVisit = false;
let listenersBound = false;

const hideDialog = () => {
    Object.assign(state, defaults, { show: false, isProcessing: false });
    hasStartedInertiaVisit = false;
};

const settle = (result) => {
    const resolver = pendingResolve;
    pendingResolve = null;
    hideDialog();

    if (resolver) {
        resolver(result);
    }
};

const bindInertiaListeners = () => {
    if (listenersBound || typeof window === 'undefined') {
        return;
    }

    router.on('start', () => {
        if (!state.show || !state.isProcessing) {
            return;
        }

        hasStartedInertiaVisit = true;
    });

    router.on('finish', () => {
        if (!state.show || !state.isProcessing || !hasStartedInertiaVisit) {
            return;
        }

        hideDialog();
    });

    listenersBound = true;
};

const confirm = (options = {}) => {
    if (pendingResolve) {
        pendingResolve(false);
        pendingResolve = null;
    }

    bindInertiaListeners();
    Object.assign(state, defaults, options, { show: true, isProcessing: false });
    hasStartedInertiaVisit = false;

    return new Promise((resolve) => {
        pendingResolve = resolve;
    });
};

const confirmNow = () => {
    if (!pendingResolve || state.isProcessing) {
        return;
    }

    const resolver = pendingResolve;
    pendingResolve = null;
    resolver(true);

    if (state.waitForBackend) {
        state.isProcessing = true;
        return;
    }

    hideDialog();
};

const cancelNow = () => {
    if (state.isProcessing) {
        return;
    }

    settle(false);
};

const closeNow = () => {
    if (pendingResolve) {
        settle(false);
        return;
    }

    hideDialog();
};

export const confirmDialogController = {
    state,
    confirm,
    confirmNow,
    cancelNow,
    closeNow,
};

export const useGlobalConfirm = () => confirm;
