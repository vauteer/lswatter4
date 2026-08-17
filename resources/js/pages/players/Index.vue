<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronDown,
    Merge,
    Pencil,
    Plus,
    TriangleAlert,
    Trophy,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import PlayerController from '@/actions/App/Http/Controllers/PlayerController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { create, edit, index } from '@/routes/players';
import { index as tournamentsIndex } from '@/routes/tournaments';

type PlayerRow = {
    id: number;
    name: string;
    modifiable: boolean;
};

type PaginatedPlayers = {
    data: PlayerRow[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
};

type DuplicateCandidate = {
    id: number;
    name: string;
};

const props = defineProps<{
    players: PaginatedPlayers;
    filters: { search: string };
    duplicateGroups: DuplicateCandidate[][];
}>();

const duplicatesOpen = ref(false);

type GroupSelection = {
    keepId: number;
    excludedIds: Set<number>;
};

// One entry per duplicate group, tracking which player to keep and which
// ones (if any) the admin has ruled out as not actually being duplicates.
const groupSelections = ref<GroupSelection[]>(
    props.duplicateGroups.map((group) => ({
        keepId: group[0].id,
        excludedIds: new Set<number>(),
    })),
);

function isExcluded(groupIndex: number, playerId: number): boolean {
    const selection = groupSelections.value[groupIndex];

    return selection.keepId !== playerId && selection.excludedIds.has(playerId);
}

function setKeeper(groupIndex: number, playerId: number): void {
    groupSelections.value[groupIndex].keepId = playerId;
}

function onIncludeToggle(
    groupIndex: number,
    playerId: number,
    event: Event,
): void {
    const included = (event.target as HTMLInputElement).checked;
    const selection = groupSelections.value[groupIndex];

    if (included) {
        selection.excludedIds.delete(playerId);
    } else {
        selection.excludedIds.add(playerId);
    }
}

function includedCount(groupIndex: number): number {
    return props.duplicateGroups[groupIndex].filter(
        (player) => !isExcluded(groupIndex, player.id),
    ).length;
}

// So the edit page's Cancel button can return here instead of resetting
// to the first, unfiltered page.
const editQuery = computed(() => ({
    page: props.players.current_page,
    search: props.filters.search || undefined,
}));

defineOptions({
    layout: {
        breadcrumbs: [{ title: trans('Players'), href: index() }],
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
</script>

<template>
    <Head :title="$t('Players')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="$t('Players')"
                :description="
                    $t('Manage the players available for tournaments')
                "
            />
            <Button as-child variant="outline">
                <Link :href="create()">
                    <Plus class="size-4" />
                    {{ $t('New player') }}
                </Link>
            </Button>
        </div>

        <Input
            v-model="search"
            type="search"
            :placeholder="$t('Name')"
            class="max-w-sm"
        />

        <Collapsible
            v-if="duplicateGroups.length > 0"
            v-model:open="duplicatesOpen"
            class="rounded-xl border border-amber-500/30 bg-amber-500/5"
        >
            <CollapsibleTrigger
                class="flex w-full items-center justify-between gap-2 p-4 text-left text-sm"
            >
                <span class="flex items-center gap-2 font-medium">
                    <TriangleAlert
                        class="size-4 text-amber-600 dark:text-amber-400"
                    />
                    {{
                        $t(':count possible duplicates found', {
                            count: String(duplicateGroups.length),
                        })
                    }}
                </span>
                <ChevronDown
                    class="size-4 shrink-0 text-muted-foreground transition-transform"
                    :class="duplicatesOpen ? 'rotate-180' : ''"
                />
            </CollapsibleTrigger>
            <CollapsibleContent class="flex flex-col gap-2 px-4 pb-4 text-sm">
                <Form
                    v-for="(group, groupIndex) in duplicateGroups"
                    :key="groupIndex"
                    v-bind="PlayerController.mergeDuplicates.form()"
                    v-slot="{ errors, processing }"
                    class="flex flex-col gap-2 rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 dark:border-sidebar-border"
                >
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                        <div
                            v-for="player in group"
                            :key="player.id"
                            class="flex items-center gap-1.5"
                        >
                            <input
                                type="checkbox"
                                :checked="!isExcluded(groupIndex, player.id)"
                                :disabled="
                                    groupSelections[groupIndex].keepId ===
                                    player.id
                                "
                                class="size-3.5 accent-primary"
                                :aria-label="
                                    $t('Merge :name', { name: player.name })
                                "
                                @change="
                                    onIncludeToggle(
                                        groupIndex,
                                        player.id,
                                        $event,
                                    )
                                "
                            />
                            <input
                                type="radio"
                                name="keep_id"
                                :value="player.id"
                                :checked="
                                    groupSelections[groupIndex].keepId ===
                                    player.id
                                "
                                :disabled="isExcluded(groupIndex, player.id)"
                                class="size-3.5 accent-primary"
                                :aria-label="
                                    $t('Keep :name', { name: player.name })
                                "
                                @change="setKeeper(groupIndex, player.id)"
                            />
                            <input
                                v-if="!isExcluded(groupIndex, player.id)"
                                type="hidden"
                                name="player_ids[]"
                                :value="player.id"
                            />
                            <Link
                                :href="edit(player.id, { query: editQuery })"
                                class="hover:underline"
                                :class="
                                    isExcluded(groupIndex, player.id)
                                        ? 'text-muted-foreground line-through'
                                        : ''
                                "
                                >{{ player.name }}</Link
                            >
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            :disabled="
                                processing || includedCount(groupIndex) < 2
                            "
                        >
                            <Merge class="size-4" />
                            {{ $t('Merge duplicates') }}
                        </Button>
                        <InputError :message="errors.keep_id" />
                        <InputError :message="errors.player_ids" />
                    </div>
                </Form>
            </CollapsibleContent>
        </Collapsible>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-sm">
                <thead
                    class="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border"
                >
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ $t('Name') }}</th>
                        <th class="px-4 py-2 text-right font-medium">
                            {{ $t('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="players.data.length === 0">
                        <td
                            colspan="2"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            {{ $t('No players found.') }}
                        </td>
                    </tr>
                    <tr
                        v-for="player in players.data"
                        :key="player.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-2">{{ player.name }}</td>
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
                                                :href="
                                                    tournamentsIndex({
                                                        query: {
                                                            player_id:
                                                                player.id,
                                                        },
                                                    })
                                                "
                                                :aria-label="
                                                    $t(
                                                        'Show tournaments played by :name',
                                                        { name: player.name },
                                                    )
                                                "
                                            >
                                                <Trophy class="size-4" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{{
                                        $t('Tournaments played')
                                    }}</TooltipContent>
                                </Tooltip>
                                <Tooltip v-if="player.modifiable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    edit(player.id, {
                                                        query: editQuery,
                                                    })
                                                "
                                                :aria-label="
                                                    $t('Edit :name', {
                                                        name: player.name,
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
            v-if="players.total > players.data.length"
            class="flex items-center justify-center md:justify-between"
        >
            <p class="hidden text-sm text-muted-foreground md:block">
                {{
                    $t('Entries :from–:to of :total', {
                        from: String(players.from ?? 0),
                        to: String(players.to ?? 0),
                        total: String(players.total),
                    })
                }}
            </p>
            <Pagination :links="players.links" />
        </div>
    </div>
</template>
