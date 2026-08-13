<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Contact, DatabaseBackup, LayoutGrid, Users } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
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
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as backupsIndex } from '@/routes/backups';
import { index as playersIndex } from '@/routes/players';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const page = usePage();

// AppSidebar lives in a persistent layout, so it isn't remounted between
// page visits (e.g. starting or ending impersonation) - this must stay
// reactive to page.props rather than being computed once at setup.
const mainNavItems = computed<NavItem[]>(() => [
    {
        title: trans('Dashboard'),
        href: dashboard(),
        icon: LayoutGrid,
    },
    ...(page.props.auth.user.admin
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
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
