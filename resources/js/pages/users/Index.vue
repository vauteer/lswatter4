<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { LogIn, Pencil, Plus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import ImpersonationController from '@/actions/App/Http/Controllers/ImpersonationController';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { create, edit, index } from '@/routes/users';
import type { User } from '@/types';

type UserRow = Pick<User, 'id' | 'name' | 'email' | 'admin' | 'last_login_at'> & {
    impersonatable: boolean;
    modifiable: boolean;
};

type PaginatedUsers = {
    data: UserRow[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
};

const props = defineProps<{
    users: PaginatedUsers;
    filters: { search: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: trans('Users'), href: index() }],
    },
});

// So the edit page's Cancel button can return here instead of resetting
// to the first, unfiltered page.
const editQuery = computed(() => ({
    page: props.users.current_page,
    search: props.filters.search || undefined,
}));

const search = ref(props.filters.search);

watch(search, (value) => {
    router.get(
        index.url(),
        { search: value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
});
</script>

<template>
    <Head :title="$t('Users')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="$t('Users')"
                :description="
                    $t('Manage the accounts that can access this application')
                "
            />
            <Button as-child variant="outline">
                <Link :href="create()">
                    <Plus class="size-4" />
                    {{ $t('New user') }}
                </Link>
            </Button>
        </div>

        <Input
            v-model="search"
            type="search"
            :placeholder="$t('Name or email')"
            class="max-w-sm"
        />

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-sm">
                <thead
                    class="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border"
                >
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ $t('Name') }}</th>
                        <th class="px-4 py-2 font-medium">
                            {{ $t('Email address') }}
                        </th>
                        <th class="px-4 py-2 font-medium">{{ $t('Role') }}</th>
                        <th class="px-4 py-2 font-medium">
                            {{ $t('Last login') }}
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            {{ $t('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="users.data.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            {{ $t('No users found.') }}
                        </td>
                    </tr>
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-2">{{ user.name }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ user.email }}
                        </td>
                        <td class="px-4 py-2">
                            <Badge
                                :variant="user.admin ? 'default' : 'secondary'"
                            >
                                {{ user.admin ? $t('Admin') : $t('User') }}
                            </Badge>
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ user.last_login_at ?? $t('Never') }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <Tooltip v-if="user.impersonatable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    ImpersonationController.store(
                                                        user.id,
                                                    )
                                                "
                                                as="button"
                                                :aria-label="
                                                    $t('Log in as :name', {
                                                        name: user.name,
                                                    })
                                                "
                                            >
                                                <LogIn class="size-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{{
                                        $t('Log in as user')
                                    }}</TooltipContent>
                                </Tooltip>

                                <Tooltip v-if="user.modifiable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    edit(user.id, {
                                                        query: editQuery,
                                                    })
                                                "
                                                :aria-label="
                                                    $t('Edit :name', {
                                                        name: user.name,
                                                    })
                                                "
                                            >
                                                <Pencil class="size-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{{
                                        $t('Edit')
                                    }}</TooltipContent>
                                </Tooltip>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="users.total > users.data.length"
            class="flex items-center justify-center md:justify-between"
        >
            <p class="hidden text-sm text-muted-foreground md:block">
                {{
                    $t('Entries :from–:to of :total', {
                        from: String(users.from ?? 0),
                        to: String(users.to ?? 0),
                        total: String(users.total),
                    })
                }}
            </p>
            <Pagination :links="users.links" />
        </div>
    </div>
</template>
