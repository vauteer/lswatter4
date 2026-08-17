<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Contact,
    DatabaseBackup,
    FileText,
    LayoutGrid,
    Telescope as TelescopeIcon,
    Trophy,
    Users,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onUnmounted } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as backupsIndex } from '@/routes/backups';
import { index as playersIndex } from '@/routes/players';
import { index as tournamentsIndex } from '@/routes/tournaments';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const page = usePage();

const { setOpenMobile } = useSidebar();

// Closes the mobile sidebar sheet after any navigation, so picking a menu
// item doesn't leave it covering the page it just opened.
const removeNavigateListener = router.on('navigate', () =>
    setOpenMobile(false),
);

onUnmounted(removeNavigateListener);

// AppSidebar lives in a persistent layout, so it isn't remounted between
// page visits (e.g. starting or ending impersonation) - this must stay
// reactive to page.props rather than being computed once at setup.
const mainNavItems = computed<NavItem[]>(() => [
    {
        title: trans('Tournaments'),
        href: tournamentsIndex(),
        icon: Trophy,
    },
    ...(page.props.auth.user
        ? [
              {
                  title: trans('Dashboard'),
                  href: dashboard(),
                  icon: LayoutGrid,
              },
          ]
        : []),
    ...(page.props.auth.user?.admin
        ? [
              {
                  title: trans('Users'),
                  href: usersIndex(),
                  icon: Users,
              },
              {
                  title: trans('Players'),
                  href: playersIndex(),
                  icon: Contact,
              },
              {
                  title: trans('Backups'),
                  href: backupsIndex(),
                  icon: DatabaseBackup,
              },
              {
                  title: trans('Logs'),
                  href: '/log-viewer',
                  icon: FileText,
                  external: true,
              },
              {
                  title: trans('Telescope'),
                  href: '/telescope',
                  icon: TelescopeIcon,
                  external: true,
              },
          ]
        : []),
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link
                            :href="
                                page.props.auth.user
                                    ? dashboard()
                                    : tournamentsIndex()
                            "
                        >
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="pb-12">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
