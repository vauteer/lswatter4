import { createInertiaApp } from '@inertiajs/vue3';
import { provideSSRWidth } from '@vueuse/core';
import { I18n, i18nVue } from 'laravel-vue-i18n';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import deTranslations from '../../lang/de.json';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const LOCALE = 'de';

const resolveTranslations = (lang: string) =>
    lang === 'de' ? deTranslations : {};

// laravel-vue-i18n always loads languages asynchronously in the browser
// (at least one microtask tick), even when resolve() returns data that's
// already in memory. That's normally fine, but trans() calls made at
// module-evaluation time - e.g. defineOptions({ layout: { breadcrumbs }})
// or the sidebar's nav item list - capture a plain string snapshot with no
// reactivity, so if that code runs before the microtask resolves, the
// text is permanently stuck untranslated. I18n.loadLanguage() (unlike
// loadLanguageAsync()) resolves synchronously when given a synchronous
// resolve(), so priming the shared instance here - before
// createInertiaApp() even runs - closes that race for good.
type I18nResolve = NonNullable<
    NonNullable<Parameters<typeof I18n.getSharedInstance>[0]>['resolve']
>;
I18n.getSharedInstance({
    lang: LOCALE,
    resolve: resolveTranslations as unknown as I18nResolve,
}).loadLanguage(LOCALE);

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
    withApp(app) {
        app.use(i18nVue, { lang: LOCALE, resolve: resolveTranslations });

        // The sidebar's mobile/desktop split (useMediaQuery in
        // ui/sidebar/SidebarProvider.vue) reads the real viewport width as
        // soon as it runs on the client. Without a fixed assumed width for
        // the server render and the client's first paint, that first
        // client-side read can disagree with what the server rendered,
        // causing a hydration mismatch. Fixing both to a desktop-sized
        // width keeps them in sync; the real width takes over right after.
        provideSSRWidth(1024, app);
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
