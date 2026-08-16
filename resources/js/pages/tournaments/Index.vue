<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, UserPlus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';
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
import { create, edit, index, register, show } from '@/routes/tournaments';

type TournamentRow = {
    id: number;
    name: string;
    start: string;
    creator: string;
    private: boolean;
    modifiable: boolean;
};

type PaginatedTournaments = {
    data: TournamentRow[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
};

const props = defineProps<{
    tournaments: PaginatedTournaments;
    filters: { search: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: trans('Tournaments'), href: index() }],
    },
});

const search = ref(props.filters.search);

watch(search, (value) => {
    router.get(
        index.url(),
        { search: value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
});

function formatStart(start: string): string {
    const [date, time] = start.split('T');
    const [year, month, day] = date.split('-');

    return `${day}.${month}.${year} ${time}`;
}
</script>

<template>
    <Head :title="$t('Tournaments')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="$t('Tournaments')"
                :description="$t('Browse and manage tournaments')"
            />
            <Button as-child variant="outline">
                <Link :href="create()">
                    <Plus class="size-4" />
                    {{ $t('New tournament') }}
                </Link>
            </Button>
        </div>

        <Input
            v-model="search"
            type="search"
            :placeholder="$t('Name')"
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
                            {{ $t('Start') }}
                        </th>
                        <th class="px-4 py-2 font-medium">
                            {{ $t('Creator') }}
                        </th>
                        <th class="px-4 py-2 font-medium">
                            {{ $t('Visibility') }}
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            {{ $t('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="tournaments.data.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            {{ $t('No tournaments found.') }}
                        </td>
                    </tr>
                    <tr
                        v-for="tournament in tournaments.data"
                        :key="tournament.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-2">
                            <Link
                                :href="show(tournament.id)"
                                class="font-medium hover:underline"
                            >
                                {{ tournament.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ formatStart(tournament.start) }}
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ tournament.creator }}
                        </td>
                        <td class="px-4 py-2">
                            <Badge variant="outline">
                                {{
                                    tournament.private
                                        ? $t('Private')
                                        : $t('Public')
                                }}
                            </Badge>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            as-child
                                        >
                                            <Link
                                                :href="show(tournament.id)"
                                                :aria-label="
                                                    $t('Show :name', {
                                                        name: tournament.name,
                                                    })
                                                "
                                            >
                                                <Eye class="size-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{{
                                        $t('Show')
                                    }}</TooltipContent>
                                </Tooltip>

                                <Tooltip v-if="tournament.modifiable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            as-child
                                        >
                                            <Link
                                                :href="register(tournament.id)"
                                                :aria-label="
                                                    $t(
                                                        'Register participants for :name',
                                                        {
                                                            name: tournament.name,
                                                        },
                                                    )
                                                "
                                            >
                                                <UserPlus class="size-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{{
                                        $t('Register')
                                    }}</TooltipContent>
                                </Tooltip>
                                <Tooltip v-if="tournament.modifiable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            as-child
                                        >
                                            <Link
                                                :href="edit(tournament.id)"
                                                :aria-label="
                                                    $t('Edit :name', {
                                                        name: tournament.name,
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
                                <div v-else class="size-8" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="tournaments.total > tournaments.data.length"
            class="flex items-center justify-center md:justify-between"
        >
            <p class="hidden text-sm text-muted-foreground md:block">
                {{
                    $t('Entries :from–:to of :total', {
                        from: String(tournaments.from ?? 0),
                        to: String(tournaments.to ?? 0),
                        total: String(tournaments.total),
                    })
                }}
            </p>
            <Pagination :links="tournaments.links" />
        </div>
    </div>
</template>
