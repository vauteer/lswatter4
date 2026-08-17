<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as tournamentsIndex } from '@/routes/tournaments';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
</script>

<template>
    <header
        class="shrink-0 border-b border-sidebar-border/70 transition-[width,height] ease-linear md:flex md:h-16 md:items-center md:px-4 md:group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
    >
        <!-- The desktop sidebar has its own logo in its header; this one
        only fills the top gap left behind once the sidebar collapses into
        the mobile sheet. -->
        <Link
            :href="
                page.props.auth.user?.admin ? dashboard() : tournamentsIndex()
            "
            class="flex h-14 items-center justify-center border-b border-sidebar-border/70 md:hidden"
        >
            <AppLogo />
        </Link>
        <div class="flex h-14 items-center gap-2 px-6 md:h-auto md:px-0">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
    </header>
</template>
