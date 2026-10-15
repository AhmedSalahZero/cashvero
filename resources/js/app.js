import '../css/app.css';
import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { checkPermission } from './composables/usePermissions';
import { applyLocale, i18n } from './i18n';

createInertiaApp({
    title: (title) => `${title} - CashVero`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const initialLocale = props.initialPage.props.locale || 'en';
        applyLocale(initialLocale);

        router.on('navigate', (event) => {
            applyLocale(event.detail.page.props.locale || 'en');
        });

        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n);

        /**
         * Global `$can` — the same check as the usePermissions()
         * composable, usable straight from any template without an
         * import:
         *
         *     <button v-if="$can('cash_expense.delete')">Delete</button>
         *
         * Reads the live page props each call, so it stays correct
         * after every Inertia visit (a user whose role changed sees the
         * new set on their next navigation).
         */
        app.config.globalProperties.$can = (key) =>
            checkPermission(router.page?.props ?? props.initialPage.props, key);

        /**
         * `v-can` directive — removes the element entirely when the
         * permission is missing, rather than merely hiding it.
         *
         *     <button v-can="'money_received.delete'">Delete</button>
         *     <button v-can:any="['a.view', 'b.view']">…</button>
         *     <button v-can:all="['a.view', 'a.update']">…</button>
         *
         * Note this is a convenience for simple cases. Where an element
         * needs other conditions too, use v-if with $can so the whole
         * condition reads in one place.
         */
        app.directive('can', {
            mounted(el, binding) {
                const keys = binding.value;
                const pageProps = router.page?.props ?? props.initialPage.props;

                let allowed;
                if (binding.arg === 'all' && Array.isArray(keys)) {
                    allowed = keys.every((k) => checkPermission(pageProps, k));
                } else {
                    allowed = checkPermission(pageProps, keys);
                }

                if (!allowed) {
                    el.parentNode?.removeChild(el);
                }
            },
        });

        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
