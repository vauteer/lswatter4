<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ListOrdered, Medal, Pencil, Plus, UserPlus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import PlayerCombobox from '@/components/PlayerCombobox.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { create, edit, index, register, show } from '@/routes/tournaments';
import type { SelectOption } from '@/types';

type TournamentRow = {
    id: number;
    name: string;
    start: string;
    creator: string;
    private: boolean;
    modifiable: boolean;
    registrationOpen: boolean;
    drawn: boolean;
};

type PaginatedTournaments = {
    data: TournamentRow[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
};

type TeamRanking = {
    id: number;
    player1: string;
    player2: string;
    played: number;
    won: number;
    lost: number;
};

type PlayerRanking = {
    id: number;
    name: string;
    played: number;
    won: number;
    lost: number;
};

const props = defineProps<{
    tournaments: PaginatedTournaments;
    filters: { search: string; player_id: number | null };
    players: SelectOption[];
    teamRanking: TeamRanking[];
    playerRanking: PlayerRanking[];
}>();

const page = usePage();

defineOptions({
    layout: {
        breadcrumbs: [{ title: trans('Tournaments'), href: index() }],
    },
});

// So the edit page's Cancel button can return here instead of resetting
// to the first, unfiltered page.
const editQuery = computed(() => ({
    page: props.tournaments.current_page,
    search: props.filters.search || undefined,
}));

const search = ref(props.filters.search);
const playerId = ref(props.filters.player_id);

watch([search, playerId], ([searchValue, playerIdValue]) => {
    router.get(
        index.url(),
        {
            search: searchValue || undefined,
            player_id: playerIdValue ?? undefined,
        },
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
            <Button v-if="page.props.auth.user" as-child variant="outline">
                <Link :href="create()">
                    <Plus class="size-4" />
                    {{ $t('New tournament') }}
                </Link>
            </Button>
        </div>

        <div class="flex flex-wrap gap-2">
            <Input
                v-model="search"
                type="search"
                :placeholder="$t('Name')"
                class="max-w-sm"
            />
            <PlayerCombobox
                v-model="playerId"
                :options="players"
                :allow-create="false"
                clearable
                :clear-label="$t('All players')"
                :placeholder="$t('Player')"
                class="max-w-sm"
            />
        </div>

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
                        <th
                            v-if="page.props.auth.user"
                            class="hidden px-4 py-2 font-medium md:table-cell"
                        >
                            {{ $t('Creator') }}
                        </th>
                        <th
                            v-if="page.props.auth.user"
                            class="hidden px-4 py-2 font-medium md:table-cell"
                        >
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
                            :colspan="page.props.auth.user ? 5 : 3"
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
                                v-if="tournament.drawn"
                                :href="show(tournament.id)"
                                class="font-medium hover:underline"
                            >
                                {{ tournament.name }}
                            </Link>
                            <span v-else class="font-medium">
                                {{ tournament.name }}
                            </span>
                        </td>
                        <td
                            class="px-4 py-2 text-muted-foreground tabular-nums"
                        >
                            {{ formatStart(tournament.start) }}
                        </td>
                        <td
                            v-if="page.props.auth.user"
                            class="hidden px-4 py-2 text-muted-foreground md:table-cell"
                        >
                            {{ tournament.creator }}
                        </td>
                        <td
                            v-if="page.props.auth.user"
                            class="hidden px-4 py-2 md:table-cell"
                        >
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
                                <Tooltip
                                    v-if="
                                        tournament.modifiable &&
                                        tournament.registrationOpen
                                    "
                                >
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

                                <Tooltip v-if="tournament.drawn">
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
                                                <ListOrdered class="size-4" />
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
                                                :href="
                                                    edit(tournament.id, {
                                                        query: editQuery,
                                                    })
                                                "
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

        <div class="grid gap-4 md:grid-cols-2">
            <div
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="flex items-center gap-2 font-medium">
                    <Medal class="size-4 shrink-0" />
                    {{ $t('All-time team ranking') }}
                </div>

                <ol
                    v-if="teamRanking.length > 0"
                    class="flex flex-col gap-1.5 text-sm"
                >
                    <li
                        v-for="(team, index) in teamRanking"
                        :key="team.id"
                        class="flex items-center gap-2"
                    >
                        <span
                            class="w-5 shrink-0 text-right text-muted-foreground"
                            >{{ index + 1 }}</span
                        >
                        <span class="min-w-0 flex-1 truncate">
                            {{ team.player1 }} / {{ team.player2 }}
                        </span>
                        <span class="shrink-0 text-muted-foreground">
                            {{ team.won }}:{{ team.lost }}
                        </span>
                    </li>
                </ol>
                <p v-else class="text-sm text-muted-foreground">
                    {{ $t('No games played yet.') }}
                </p>
            </div>

            <div
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="flex items-center gap-2 font-medium">
                    <Medal class="size-4 shrink-0" />
                    {{ $t('All-time player ranking') }}
                </div>

                <ol
                    v-if="playerRanking.length > 0"
                    class="flex flex-col gap-1.5 text-sm"
                >
                    <li
                        v-for="(player, index) in playerRanking"
                        :key="player.id"
                        class="flex items-center gap-2"
                    >
                        <span
                            class="w-5 shrink-0 text-right text-muted-foreground"
                            >{{ index + 1 }}</span
                        >
                        <span class="min-w-0 flex-1 truncate">{{
                            player.name
                        }}</span>
                        <span class="shrink-0 text-muted-foreground">
                            {{ player.won }}:{{ player.lost }}
                        </span>
                    </li>
                </ol>
                <p v-else class="text-sm text-muted-foreground">
                    {{ $t('No games played yet.') }}
                </p>
            </div>
        </div>
    </div>
</template>
