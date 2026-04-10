import { reactive } from 'vue';

const DEFAULT_DURATION = 4000;
export const GLOBAL_NOTIFY_EVENT = 'app:notify';

const state = reactive({
    notifications: [],
});

let nextId = 1;

const remove = (id) => {
    const index = state.notifications.findIndex((notification) => notification.id === id);

    if (index !== -1) {
        state.notifications.splice(index, 1);
    }
};

const push = (payload) => {
    const id = nextId++;
    const duration = typeof payload.duration === 'number' ? payload.duration : DEFAULT_DURATION;

    const notification = {
        id,
        title: payload.title ?? '',
        message: payload.message ?? '',
        type: payload.type ?? 'info',
        duration,
    };

    state.notifications.push(notification);

    if (duration > 0 && typeof window !== 'undefined') {
        window.setTimeout(() => remove(id), duration);
    }

    return id;
};

const normalizePayload = (payloadOrMessage, options = {}) => {
    if (typeof payloadOrMessage === 'string') {
        return { ...options, message: payloadOrMessage };
    }

    return payloadOrMessage ?? {};
};

const notify = (payloadOrMessage, options = {}) => {
    return push(normalizePayload(payloadOrMessage, options));
};

notify.success = (message, options = {}) => notify(message, { ...options, type: 'success' });
notify.error = (message, options = {}) => notify(message, { ...options, type: 'error' });
notify.warning = (message, options = {}) => notify(message, { ...options, type: 'warning' });
notify.info = (message, options = {}) => notify(message, { ...options, type: 'info' });
notify.remove = remove;
notify.clear = () => {
    state.notifications.splice(0, state.notifications.length);
};

const dispatchNotifyEvent = (payloadOrMessage, options = {}) => {
    const payload = normalizePayload(payloadOrMessage, options);

    if (typeof window === 'undefined') {
        return notify(payload);
    }

    window.dispatchEvent(new CustomEvent(GLOBAL_NOTIFY_EVENT, {
        detail: payload,
    }));

    return null;
};

dispatchNotifyEvent.success = (message, options = {}) => dispatchNotifyEvent(message, { ...options, type: 'success' });
dispatchNotifyEvent.error = (message, options = {}) => dispatchNotifyEvent(message, { ...options, type: 'error' });
dispatchNotifyEvent.warning = (message, options = {}) => dispatchNotifyEvent(message, { ...options, type: 'warning' });
dispatchNotifyEvent.info = (message, options = {}) => dispatchNotifyEvent(message, { ...options, type: 'info' });
export const emitGlobalNotify = dispatchNotifyEvent;

export const notificationController = {
    state,
    notify,
    remove,
};

export const useGlobalNotify = () => dispatchNotifyEvent;
