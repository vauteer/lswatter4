<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Link2, Shuffle, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref, useTemplateRef, watch } from 'vue';
import TournamentController from '@/actions/App/Http/Controllers/TournamentController';
import TournamentRegistrationController from '@/actions/App/Http/Controllers/TournamentRegistrationController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PlayerCombobox from '@/components/PlayerCombobox.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { index, show } from '@/routes/tournaments';
import type { SelectOption } from '@/types';

type TeamRow = {
    id: number;
    player1: string;
    player2: string;
};

const props = defineProps<{
    tournament: { id: number; name: string; registrationOpen: boolean };
    players: SelectOption[];
    teams: TeamRow[];
    allPlayers: SelectOption[];
    canDraw: boolean;
    drawn: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: trans('Tournaments'), href: index() },
            { title: trans('Register participants'), href: index() },
        ],
    },
});

const player1Combobox = useTemplateRef('player1Combobox');
const player2Combobox = useTemplateRef('player2Combobox');

function resetRegisterForm() {
    player1Combobox.value?.reset();
    player2Combobox.value?.reset();
    player1Combobox.value?.focus();
}

const selectedForJoin = ref<number[]>([]);

// Registering the joined team (or unregistering a checked single player)
// removes them from `players`, but doesn't otherwise touch this selection -
// drop ids that are no longer registered so a stale id can't keep the
// "join" button enabled while nothing looks checked.
watch(
    () => props.players,
    (players) => {
        const ids = new Set(players.map((player) => player.id));
        selectedForJoin.value = selectedForJoin.value.filter((id) =>
            ids.has(id),
        );
    },
);

function toggleJoin(playerId: number, checked: boolean | 'indeterminate') {
    if (checked === true) {
        if (!selectedForJoin.value.includes(playerId)) {
            selectedForJoin.value.push(playerId);
        }
    } else {
        selectedForJoin.value = selectedForJoin.value.filter(
            (id) => id !== playerId,
        );
    }
}
</script>

<template>
    <Head :title="$t('Register participants')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                :title="$t('Register participants')"
                :description="tournament.name"
            />
            <Button as-child variant="ghost">
                <Link :href="show(tournament.id)">{{
                    $t('Back to tournament')
                }}</Link>
            </Button>
        </div>

        <div
            v-if="tournament.registrationOpen"
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <Heading
                variant="small"
                :title="$t('Register')"
                :description="
                    $t(
                        'Add a single player, or fill in both fields to register a team.',
                    )
                "
            />

            <Form
                v-bind="
                    TournamentRegistrationController.store.form(tournament.id)
                "
                class="grid gap-4 sm:grid-cols-2"
                @success="resetRegisterForm"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <PlayerCombobox
                        ref="player1Combobox"
                        name="player1_id"
                        new-name-field="new_player1_name"
                        :options="allPlayers"
                        :placeholder="$t('Player 1')"
                    />
                    <InputError :message="errors.player1_id" />
                    <InputError :message="errors.new_player1_name" />
                </div>

                <div class="grid gap-2">
                    <PlayerCombobox
                        ref="player2Combobox"
                        name="player2_id"
                        new-name-field="new_player2_name"
                        :options="allPlayers"
                        :placeholder="$t('Player 2 (optional)')"
                    />
                    <InputError :message="errors.player2_id" />
                    <InputError :message="errors.new_player2_name" />
                </div>

                <div class="sm:col-span-2">
                    <Button :disabled="processing">{{ $t('Register') }}</Button>
                </div>
            </Form>
        </div>
        <div
            v-else
            class="rounded-xl border border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border"
        >
            {{
                $t(
                    'Registration is closed because the tournament has already started.',
                )
            }}
        </div>

        <div
            v-if="canDraw"
            class="flex flex-col items-start justify-between gap-4 rounded-xl border border-primary/30 bg-primary/5 p-4 sm:flex-row sm:items-center"
        >
            <Heading
                variant="small"
                :title="drawn ? $t('Ready to redraw') : $t('Ready to draw')"
                :description="
                    $t(
                        'All teams are registered. Draw the fixtures to start the tournament.',
                    )
                "
            />
            <Form
                v-if="!drawn"
                v-bind="TournamentController.draw.form(tournament.id)"
                v-slot="{ processing }"
            >
                <Button type="submit" size="lg" :disabled="processing">
                    <Shuffle class="size-4" />
                    {{ $t('Draw') }}
                </Button>
            </Form>
            <ConfirmActionDialog
                v-else
                :action="TournamentController.draw.form(tournament.id)"
                :title="$t('Redraw tournament?')"
                :description="
                    $t(
                        'This discards all current fixtures and results and creates a new random draw. This action cannot be undone.',
                    )
                "
                :confirm-label="$t('Redraw')"
            >
                <Button size="lg" variant="outline">
                    <Shuffle class="size-4" />
                    {{ $t('Redraw') }}
                </Button>
            </ConfirmActionDialog>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-sm">
                <thead
                    class="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border"
                >
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ $t('Team') }}</th>
                        <th class="px-4 py-2 text-right font-medium">
                            {{ $t('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="teams.length === 0">
                        <td
                            colspan="2"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            {{ $t('No teams registered yet.') }}
                        </td>
                    </tr>
                    <tr
                        v-for="team in teams"
                        :key="team.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-2">
                            {{ team.player1 }} / {{ team.player2 }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end">
                                <ConfirmActionDialog
                                    v-if="tournament.registrationOpen"
                                    :action="
                                        TournamentRegistrationController.destroyTeam.form(
                                            {
                                                tournament: tournament.id,
                                                team: team.id,
                                            },
                                        )
                                    "
                                    :title="$t('Unregister team?')"
                                    :description="
                                        $t(
                                            ':team will no longer be registered for this tournament.',
                                            {
                                                team: `${team.player1} / ${team.player2}`,
                                            },
                                        )
                                    "
                                    :confirm-label="$t('Unregister')"
                                    :tooltip="$t('Unregister')"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        :aria-label="$t('Unregister team')"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </ConfirmActionDialog>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Form
            v-bind="TournamentRegistrationController.join.form(tournament.id)"
            v-slot="{ errors, processing }"
        >
            <div
                class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="w-10 px-4 py-2"></th>
                            <th class="px-4 py-2 font-medium">
                                {{ $t('Single player') }}
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                {{ $t('Actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="players.length === 0">
                            <td
                                colspan="3"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                {{ $t('No single players registered yet.') }}
                            </td>
                        </tr>
                        <tr
                            v-for="player in players"
                            :key="player.id"
                            class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                        >
                            <td class="px-4 py-2">
                                <Checkbox
                                    v-if="tournament.registrationOpen"
                                    name="player_ids[]"
                                    :value="player.id"
                                    :model-value="
                                        selectedForJoin.includes(player.id)
                                    "
                                    @update:model-value="
                                        (checked) =>
                                            toggleJoin(player.id, checked)
                                    "
                                    :aria-label="
                                        $t('Select :name to join into a team', {
                                            name: player.name,
                                        })
                                    "
                                />
                            </td>
                            <td class="px-4 py-2">{{ player.name }}</td>
                            <td class="px-4 py-2">
                                <div class="flex items-center justify-end">
                                    <ConfirmActionDialog
                                        v-if="tournament.registrationOpen"
                                        :action="
                                            TournamentRegistrationController.destroyPlayer.form(
                                                {
                                                    tournament: tournament.id,
                                                    player: player.id,
                                                },
                                            )
                                        "
                                        :title="$t('Unregister player?')"
                                        :description="
                                            $t(
                                                ':name will no longer be registered for this tournament.',
                                                { name: player.name },
                                            )
                                        "
                                        :confirm-label="$t('Unregister')"
                                        :tooltip="$t('Unregister')"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            :aria-label="
                                                $t('Unregister :name', {
                                                    name: player.name,
                                                })
                                            "
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </ConfirmActionDialog>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="tournament.registrationOpen"
                class="mt-4 flex items-center gap-4"
            >
                <Button
                    type="submit"
                    variant="outline"
                    :disabled="selectedForJoin.length !== 2 || processing"
                >
                    <Link2 class="size-4" />
                    {{ $t('Join selected into a team') }}
                </Button>
                <InputError :message="errors.player_ids" />
            </div>
        </Form>
    </div>
</template>
