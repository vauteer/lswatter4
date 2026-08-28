<script setup lang="ts">
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import { Pencil, Shuffle, Trophy, UserPlus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import TournamentController from '@/actions/App/Http/Controllers/TournamentController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit as editFixture } from '@/routes/fixtures';
import {
    edit as editTournament,
    index,
    lists,
    register,
    show,
} from '@/routes/tournaments';

type FixtureRow = {
    id: number;
    team1: string;
    team2: string;
    round: number;
    tableNumber: number;
    score: string | null;
    scoreGames: string;
    scorePoints: string;
    games: string[];
    editable: boolean;
};

type StandingRow = {
    id: number;
    player1: string;
    player2: string;
    won: number;
    lost: number;
    pointsWon: number;
    pointsLost: number;
};

const props = defineProps<{
    tournament: {
        id: number;
        name: string;
        start: string;
        rounds: number;
        games: number;
        winpoints: number;
        private: boolean;
        creator: string;
        modifiable: boolean;
        registrationOpen: boolean;
    };
    currentRound: number;
    standings: StandingRow[];
    fixtures: { data: FixtureRow[] };
    canDraw: boolean;
}>();

const page = usePage();

setLayoutProps({
    breadcrumbs: [
        { title: trans('Tournaments'), href: index() },
        { title: props.tournament.name, href: show(props.tournament.id) },
    ],
});

const rounds = computed(() =>
    Array.from({ length: props.tournament.rounds }, (_, i) => i + 1),
);

const hasEditableFixtures = computed(() =>
    props.fixtures.data.some((fixture) => fixture.editable),
);

function formatStart(start: string): string {
    const [date, time] = start.split('T');
    const [year, month, day] = date.split('-');

    return `${day}.${month}.${year} ${time}`;
}

function fixtureWinner(fixture: FixtureRow): 1 | 2 | 'tie' | null {
    if (!fixture.score) {
        return null;
    }

    const [team1Won, team2Won] = fixture.scoreGames.split(':').map(Number);

    if (team1Won === team2Won) {
        return 'tie';
    }

    return team1Won > team2Won ? 1 : 2;
}

function outcomeClass(fixture: FixtureRow, team: 1 | 2): string {
    const winner = fixtureWinner(fixture);

    if (winner === 'tie') {
        return team === 1
            ? 'text-violet-700 dark:text-violet-400'
            : 'text-blue-700 dark:text-blue-400';
    }

    if (winner === team) {
        return 'font-bold text-green-700 dark:text-green-400';
    }

    if (winner !== null) {
        return 'text-amber-700 dark:text-amber-400';
    }

    return '';
}

function gameWinner(game: string): 1 | 2 {
    const [points1, points2] = game.split('-').map(Number);

    return points1 > points2 ? 1 : 2;
}

function gameBadgeClass(fixture: FixtureRow, game: string): string {
    const winner = fixtureWinner(fixture);

    if (winner === null) {
        return '';
    }

    const team = gameWinner(game);

    if (winner === 'tie') {
        return team === 1
            ? 'border-transparent bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-400'
            : 'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-400';
    }

    return team === winner
        ? 'border-transparent bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-400'
        : 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-400';
}

/**
 * Same left-border-accent + tinted-background pattern as
 * lscraft5's orders/Index.vue: a `<tr>` background tint plus a 4px accent on
 * the first `<td>` (border on `<tr>` itself is unreliable cross-browser).
 */
type RankHighlight = 'gold' | 'silver' | 'bronze' | 'last';

const rankBackgroundClasses: Record<RankHighlight, string> = {
    gold: 'bg-amber-400/5 hover:bg-amber-400/10 dark:bg-amber-400/10 dark:hover:bg-amber-400/15',
    silver: 'bg-slate-400/5 hover:bg-slate-400/10 dark:bg-slate-400/10 dark:hover:bg-slate-400/15',
    bronze: 'bg-orange-500/5 hover:bg-orange-500/10 dark:bg-orange-500/10 dark:hover:bg-orange-500/15',
    last: 'bg-destructive/5 hover:bg-destructive/10 dark:bg-destructive/10 dark:hover:bg-destructive/15',
};

const rankAccentClasses: Record<RankHighlight, string> = {
    gold: 'border-l-4 border-l-amber-400',
    silver: 'border-l-4 border-l-slate-400',
    bronze: 'border-l-4 border-l-orange-500',
    last: 'border-l-4 border-l-destructive',
};

function rankHighlight(rank: number, total: number): RankHighlight | null {
    if (rank === 0) {
        return 'gold';
    }

    if (rank === 1) {
        return 'silver';
    }

    if (rank === 2) {
        return 'bronze';
    }

    if (rank === total - 1) {
        return 'last';
    }

    return null;
}

function rankBackground(rank: number, total: number): string {
    const highlight = rankHighlight(rank, total);

    return highlight ? rankBackgroundClasses[highlight] : '';
}

function rankAccent(rank: number, total: number): string {
    const highlight = rankHighlight(rank, total);

    return highlight
        ? rankAccentClasses[highlight]
        : 'border-l-4 border-l-transparent';
}
</script>

<template>
    <Head :title="tournament.name" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="tournament.name"
                :description="
                    $t(':start · Created by :creator', {
                        start: formatStart(tournament.start),
                        creator: tournament.creator,
                    })
                "
            />
            <div class="flex items-center gap-2">
                <Button
                    v-if="page.props.auth.user"
                    as-child
                    variant="outline"
                    class="hidden md:inline-flex"
                >
                    <a
                        :href="
                            lists({
                                tournament: tournament.id,
                                round: currentRound,
                            }).url
                        "
                        target="_blank"
                    >
                        {{ $t('Table lists') }}
                    </a>
                </Button>
                <Button
                    v-if="tournament.modifiable && tournament.registrationOpen"
                    as-child
                    variant="outline"
                >
                    <Link :href="register(tournament.id)">
                        <UserPlus class="size-4" />
                        {{ $t('Register participants') }}
                    </Link>
                </Button>
                <ConfirmActionDialog
                    v-if="tournament.modifiable && canDraw"
                    :action="TournamentController.draw.form(tournament.id)"
                    :title="$t('Redraw tournament?')"
                    :description="
                        $t(
                            'This discards all current fixtures and results and creates a new random draw. This action cannot be undone.',
                        )
                    "
                    :confirm-label="$t('Redraw')"
                >
                    <Button variant="outline">
                        <Shuffle class="size-4" />
                        {{ $t('Redraw') }}
                    </Button>
                </ConfirmActionDialog>
                <Button v-if="tournament.modifiable" as-child variant="outline">
                    <Link :href="editTournament(tournament.id)">
                        {{ $t('Edit tournament') }}
                    </Link>
                </Button>
            </div>
        </div>

        <div v-if="rounds.length > 1" class="flex flex-wrap items-center gap-2">
            <Button
                v-for="round in rounds"
                :key="round"
                as-child
                size="sm"
                :variant="round === currentRound ? 'secondary' : 'outline'"
            >
                <Link :href="show(tournament.id, { query: { round } })">
                    {{ $t('Round :round', { round: String(round) }) }}
                </Link>
            </Button>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-sm">
                <thead
                    class="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border"
                >
                    <tr>
                        <th class="px-2 py-2 text-center font-medium md:px-4">
                            {{ $t('Table') }}
                        </th>
                        <th
                            class="w-full px-2 py-2 font-medium md:w-auto md:px-4"
                        >
                            {{ $t('Pairing') }}
                        </th>
                        <th class="hidden px-4 py-2 font-medium md:table-cell">
                            {{ $t('Result') }}
                        </th>
                        <th class="px-2 py-2 text-center font-medium md:px-4">
                            {{ $t('Games : Points') }}
                        </th>
                        <th
                            v-if="hasEditableFixtures"
                            class="px-2 py-2 text-right font-medium md:px-4"
                        >
                            {{ $t('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="fixtures.data.length === 0">
                        <td
                            :colspan="hasEditableFixtures ? 5 : 4"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            {{ $t('No fixtures for this round yet.') }}
                        </td>
                    </tr>
                    <tr
                        v-for="fixture in fixtures.data"
                        :key="fixture.id"
                        class="border-b border-sidebar-border/70 last:border-0 odd:bg-muted/40 dark:border-sidebar-border"
                    >
                        <td
                            class="px-4 py-2 text-left font-bold md:text-center"
                        >
                            {{ fixture.tableNumber }}
                        </td>
                        <td class="w-full px-2 py-2 md:w-auto md:px-4">
                            <div
                                class="flex items-center gap-1"
                                :class="outcomeClass(fixture, 1)"
                            >
                                <Trophy
                                    v-if="fixtureWinner(fixture) === 1"
                                    class="-ml-5 size-3.5 shrink-0"
                                />
                                {{ fixture.team1 }}
                            </div>
                            <div
                                class="flex items-center gap-1"
                                :class="outcomeClass(fixture, 2)"
                            >
                                <Trophy
                                    v-if="fixtureWinner(fixture) === 2"
                                    class="-ml-5 size-3.5 shrink-0"
                                />
                                {{ fixture.team2 }}
                            </div>
                        </td>
                        <td class="hidden px-4 py-2 md:table-cell">
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="(game, index) in fixture.games"
                                    :key="index"
                                    variant="outline"
                                    :class="gameBadgeClass(fixture, game)"
                                >
                                    {{ game }}
                                </Badge>
                            </div>
                        </td>
                        <td class="px-2 py-2 text-center md:px-4">
                            <div class="font-bold">
                                {{ fixture.scoreGames }}
                            </div>
                            <div class="text-muted-foreground">
                                {{ fixture.scorePoints }}
                            </div>
                        </td>
                        <td
                            v-if="hasEditableFixtures"
                            class="px-2 py-2 md:px-4"
                        >
                            <div class="flex items-center justify-end gap-1">
                                <Tooltip v-if="fixture.editable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            as-child
                                        >
                                            <Link
                                                :href="editFixture(fixture.id)"
                                                :aria-label="$t('Edit result')"
                                            >
                                                <Pencil class="size-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{{
                                        $t('Edit result')
                                    }}</TooltipContent>
                                </Tooltip>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-sm">
                <thead
                    class="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border"
                >
                    <tr>
                        <th class="px-2 py-2 text-center font-medium md:px-4">
                            {{ $t('Rank') }}
                        </th>
                        <th
                            class="w-full px-2 py-2 font-medium md:w-auto md:px-4"
                        >
                            {{ $t('Team') }}
                        </th>
                        <th
                            class="hidden px-4 py-2 text-center font-medium md:table-cell"
                        >
                            {{ $t('Points') }}
                        </th>
                        <th class="px-2 py-2 text-center font-medium md:px-4">
                            {{ $t('Difference') }}
                        </th>
                        <th class="px-2 py-2 text-center font-medium md:px-4">
                            {{ $t('Games') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="standings.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            {{ $t('No standings yet.') }}
                        </td>
                    </tr>
                    <tr
                        v-for="(standing, rank) in standings"
                        :key="standing.id"
                        :class="[
                            'border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border',
                            rankBackground(rank, standings.length),
                        ]"
                    >
                        <td
                            :class="[
                                'px-2 py-2 text-center font-bold md:px-4',
                                rankAccent(rank, standings.length),
                            ]"
                        >
                            {{ rank + 1 }}
                        </td>
                        <td
                            class="w-full px-2 py-2 font-bold md:w-auto md:px-4"
                        >
                            <div>{{ standing.player1 }}</div>
                            <div>{{ standing.player2 }}</div>
                        </td>
                        <td class="hidden px-4 py-2 text-center md:table-cell">
                            {{ standing.pointsWon }} : {{ standing.pointsLost }}
                        </td>
                        <td class="px-2 py-2 text-center font-semibold md:px-4">
                            {{ standing.pointsWon - standing.pointsLost }}
                        </td>
                        <td class="px-2 py-2 text-center font-semibold md:px-4">
                            {{ standing.won }} : {{ standing.lost }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="px-4 py-2 text-center text-sm text-muted-foreground">
                {{
                    $t(
                        'Sorted by games won, point difference, then points scored',
                    )
                }}
            </p>
        </div>
    </div>
</template>
