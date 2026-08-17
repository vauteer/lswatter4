<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CircleCheck,
    DatabaseBackup,
    Medal,
    Merge,
    TriangleAlert,
    Users as UsersIcon,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as backupsIndex } from '@/routes/backups';
import { index as playersIndex } from '@/routes/players';
import { index as usersIndex } from '@/routes/users';

type RecentUser = {
    id: number;
    name: string;
    createdAgo: string;
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

defineProps<{
    duplicatePlayerCount: number;
    users: {
        total: number;
        recent: RecentUser[];
    };
    lastBackup: { date: string; ago: string; stale: boolean } | null;
    teamRanking: TeamRanking[];
    playerRanking: PlayerRanking[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: trans('Dashboard'), href: dashboard() }],
    },
});
</script>

<template>
    <Head :title="$t('Dashboard')" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="$t('Dashboard')"
            :description="$t('An overview of what needs your attention')"
        />

        <div class="grid gap-4 md:grid-cols-3">
            <div
                class="flex flex-col gap-3 rounded-xl border p-4"
                :class="
                    duplicatePlayerCount > 0
                        ? 'border-amber-500/30 bg-amber-500/5'
                        : 'border-sidebar-border/70 dark:border-sidebar-border'
                "
            >
                <div class="flex items-center gap-2 font-medium">
                    <TriangleAlert
                        v-if="duplicatePlayerCount > 0"
                        class="size-4 shrink-0 text-amber-600 dark:text-amber-400"
                    />
                    <CircleCheck
                        v-else
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                    {{ $t('Possible duplicate players') }}
                </div>

                <p
                    class="text-2xl font-bold"
                    :class="
                        duplicatePlayerCount > 0
                            ? 'text-amber-700 dark:text-amber-400'
                            : ''
                    "
                >
                    {{ duplicatePlayerCount }}
                </p>

                <p class="text-sm text-muted-foreground">
                    {{
                        duplicatePlayerCount > 0
                            ? $t(':count possible duplicates found', {
                                  count: String(duplicatePlayerCount),
                              })
                            : $t('No duplicates found.')
                    }}
                </p>

                <Button
                    v-if="duplicatePlayerCount > 0"
                    as-child
                    variant="outline"
                    size="sm"
                    class="mt-auto w-fit"
                >
                    <Link :href="playersIndex()">
                        <Merge class="size-4" />
                        {{ $t('Review and merge') }}
                    </Link>
                </Button>
            </div>

            <div
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="flex items-center gap-2 font-medium">
                    <UsersIcon class="size-4 shrink-0" />
                    {{ $t('Users') }}
                </div>

                <p class="text-2xl font-bold">{{ users.total }}</p>

                <p
                    v-if="users.recent.length > 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ $t('Most recently added') }}
                </p>

                <ul
                    v-if="users.recent.length > 0"
                    class="flex flex-col gap-1.5 text-sm"
                >
                    <li
                        v-for="user in users.recent"
                        :key="user.id"
                        class="flex items-center justify-between gap-2"
                    >
                        <span class="truncate">{{ user.name }}</span>
                        <span class="shrink-0 text-muted-foreground">{{
                            user.createdAgo
                        }}</span>
                    </li>
                </ul>

                <Button
                    as-child
                    variant="outline"
                    size="sm"
                    class="mt-auto w-fit"
                >
                    <Link :href="usersIndex()">
                        {{ $t('View all users') }}
                    </Link>
                </Button>
            </div>

            <div
                class="flex flex-col gap-3 rounded-xl border p-4"
                :class="
                    lastBackup === null || lastBackup.stale
                        ? 'border-amber-500/30 bg-amber-500/5'
                        : 'border-sidebar-border/70 dark:border-sidebar-border'
                "
            >
                <div class="flex items-center gap-2 font-medium">
                    <TriangleAlert
                        v-if="lastBackup === null || lastBackup.stale"
                        class="size-4 shrink-0 text-amber-600 dark:text-amber-400"
                    />
                    <DatabaseBackup v-else class="size-4 shrink-0" />
                    {{ $t('Backups') }}
                </div>

                <p
                    class="text-2xl font-bold"
                    :class="
                        lastBackup === null || lastBackup.stale
                            ? 'text-amber-700 dark:text-amber-400'
                            : ''
                    "
                >
                    {{ lastBackup ? lastBackup.ago : $t('None') }}
                </p>

                <p class="text-sm text-muted-foreground">
                    {{
                        lastBackup === null
                            ? $t('No backups yet.')
                            : lastBackup.stale
                              ? $t('This backup may be outdated.')
                              : lastBackup.date
                    }}
                </p>

                <Button
                    as-child
                    variant="outline"
                    size="sm"
                    class="mt-auto w-fit"
                >
                    <Link :href="backupsIndex()">
                        {{ $t('View backups') }}
                    </Link>
                </Button>
            </div>
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
                        <span class="w-5 shrink-0 text-right text-muted-foreground">{{
                            index + 1
                        }}</span>
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
                        <span class="w-5 shrink-0 text-right text-muted-foreground">{{
                            index + 1
                        }}</span>
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
