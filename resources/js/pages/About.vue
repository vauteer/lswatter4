<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ExternalLink, Mail } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import Heading from '@/components/Heading.vue';
import { about } from '@/routes';

type Credit = {
    name: string;
    description: string;
    href: string;
};

type CreditGroup = {
    title: string;
    items: Credit[];
};

const props = defineProps<{
    appName: string;
    laravelVersion: string;
    phpVersion: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: trans('About'), href: about() }],
    },
});

const author = {
    name: 'Gerald Lindner',
    email: 'gerald@modehaus-lindner.de',
};

const creditGroups: CreditGroup[] = [
    {
        title: trans('Backend'),
        items: [
            {
                name: `Laravel ${props.laravelVersion}`,
                description: trans(
                    'The PHP framework this application is built on',
                ),
                href: 'https://laravel.com',
            },
            {
                name: `PHP ${props.phpVersion}`,
                description: trans('The language the server side runs on'),
                href: 'https://www.php.net',
            },
            {
                name: 'Inertia.js',
                description: trans(
                    'Connects the Laravel backend to the Vue frontend',
                ),
                href: 'https://inertiajs.com',
            },
            {
                name: 'Laravel Fortify',
                description: trans(
                    'Login, password reset and password confirmation',
                ),
                href: 'https://laravel.com/docs/fortify',
            },
            {
                name: 'Laravel Wayfinder',
                description: trans('Type-safe route helpers for the frontend'),
                href: 'https://github.com/laravel/wayfinder',
            },
            {
                name: 'FPDF',
                description: trans('Generates the printable table lists'),
                href: 'https://www.fpdf.org',
            },
            {
                name: 'Flysystem',
                description: trans('Stores the database backups off-site'),
                href: 'https://flysystem.thephpleague.com',
            },
        ],
    },
    {
        title: trans('Frontend'),
        items: [
            {
                name: 'Vue',
                description: trans(
                    'The component framework behind the user interface',
                ),
                href: 'https://vuejs.org',
            },
            {
                name: 'Tailwind CSS',
                description: trans('The styling of every page'),
                href: 'https://tailwindcss.com',
            },
            {
                name: 'Reka UI',
                description: trans(
                    'Accessible building blocks for menus, dialogs and more',
                ),
                href: 'https://reka-ui.com',
            },
            {
                name: 'Lucide',
                description: trans('The icon set'),
                href: 'https://lucide.dev',
            },
            {
                name: 'Vue Sonner',
                description: trans('The toast notifications'),
                href: 'https://vue-sonner.vercel.app',
            },
            {
                name: 'laravel-vue-i18n',
                description: trans(
                    'Brings the German translations to the frontend',
                ),
                href: 'https://github.com/xiCO2k/laravel-vue-i18n',
            },
            {
                name: 'VueUse',
                description: trans('Utilities for Vue components'),
                href: 'https://vueuse.org',
            },
            {
                name: 'Vite',
                description: trans('Bundles the frontend assets'),
                href: 'https://vite.dev',
            },
        ],
    },
    {
        title: trans('Development'),
        items: [
            {
                name: 'Laravel Vue Starter Kit',
                description: trans(
                    'The starting point this application grew from',
                ),
                href: 'https://github.com/laravel/vue-starter-kit',
            },
            {
                name: 'Pest',
                description: trans('Runs the automated tests'),
                href: 'https://pestphp.com',
            },
            {
                name: 'Laravel Pint, ESLint & Prettier',
                description: trans('Keep the code formatted consistently'),
                href: 'https://laravel.com/docs/pint',
            },
            {
                name: 'Laravel Telescope',
                description: trans('Insight into requests, queries and jobs'),
                href: 'https://laravel.com/docs/telescope',
            },
            {
                name: 'Log Viewer',
                description: trans('Reads the application log in the browser'),
                href: 'https://log-viewer.opcodes.io',
            },
        ],
    },
];
</script>

<template>
    <Head :title="$t('About')" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="$t('About')"
            :description="
                $t('Who made :name, and what it is built with', {
                    name: appName,
                })
            "
        />

        <div class="grid gap-4 md:grid-cols-2">
            <div
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <Heading :title="$t('The application')" variant="small" />

                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Tournament management: register players and teams, draw the rounds, record the results and keep the all-time rankings.',
                        )
                    }}
                </p>
            </div>

            <div
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <Heading :title="$t('Contact')" variant="small" />

                <p class="font-medium">{{ author.name }}</p>

                <a
                    class="flex w-fit items-center gap-2 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    :href="`mailto:${author.email}`"
                >
                    <Mail class="size-4 shrink-0" />
                    {{ author.email }}
                </a>
            </div>
        </div>

        <section
            v-for="group in creditGroups"
            :key="group.title"
            class="flex flex-col gap-3"
        >
            <Heading :title="group.title" variant="small" />

            <ul class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                <li
                    v-for="credit in group.items"
                    :key="credit.name"
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <a
                        class="flex w-fit items-center gap-1.5 font-medium underline-offset-4 hover:underline"
                        :href="credit.href"
                        target="_blank"
                        rel="noopener"
                    >
                        {{ credit.name }}
                        <ExternalLink class="size-3.5 shrink-0" />
                    </a>

                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ credit.description }}
                    </p>
                </li>
            </ul>
        </section>
    </div>
</template>
