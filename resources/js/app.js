import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';

// Keep CSRF token and Axios headers continuously in sync on every Inertia navigation
router.on('navigate', (event) => {
    const token = event.detail?.page?.props?.csrf_token;
    if (token) {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
        if (window.axios) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        }
    }
});

const appName = import.meta.env.VITE_APP_NAME || 'SIMOD Enterprise';

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const pinia = createPinia();
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .mount(el);
    },
    progress: {
        color: '#f59e0b',
        showSpinner: true,
    },
});
