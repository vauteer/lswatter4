<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { create, edit, index } from '@/routes/players';

type PlayerRow = {
    id: number;
    name: string;
    modifiable: boolean;
};

type PaginatedPlayers = {
    data: PlayerRow[];
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    players: PaginatedPlayers;
    filters: { search: string };
}>();

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
                                <Tooltip v-if="player.modifiable">
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            as-child
                                        >
                                            <Link
                                                :href="edit(player.id)"
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

        <Pagination :links="players.links" />
    </div>
</template>
