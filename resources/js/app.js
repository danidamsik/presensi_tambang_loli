import './bootstrap';
import '../css/app.css';

import GlobalConfirmModal from '@/Components/GlobalConfirmModal.vue';
import GlobalNotification from '@/Components/GlobalNotification.vue';
import { useGlobalConfirm } from '@/composables/useGlobalConfirm';
import { useGlobalNotify } from '@/composables/useGlobalNotify';
import { Fragment, createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './theme';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

initializeTheme();

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const confirm = useGlobalConfirm();
        const notify = useGlobalNotify();

        const app = createApp({
            render: () => h(Fragment, [h(App, props), h(GlobalConfirmModal), h(GlobalNotification)]),
        });

        app.config.globalProperties.$confirm = confirm;
        app.config.globalProperties.$notify = notify;
        app.provide('confirmDialog', confirm);
        app.provide('notify', notify);

        return app
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
