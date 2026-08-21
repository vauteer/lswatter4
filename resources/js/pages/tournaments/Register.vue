<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Link2, Shuffle, Trash2 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, useTemplateRef, watch } from 'vue';
import TournamentController from '@/actions/App/Http/Controllers/TournamentController';
import TournamentRegistrationController from '@/actions/App/Http/Controllers/TournamentRegistrationController';
import ConfirmActionDialog from '@/components/ConfirmActionDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PlayerCombobox from '@/components/PlayerCombobox.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/tournaments';
import type { SelectOption } from '@/types';

type TeamRow = {
    id: number;
    player1: string;
    player2: string;
};

const props = defineProps<{
    tournament: { id: number; name: string; registrationOpen: boolean };
    singlePlayers: SelectOption[];
    teams: TeamRow[];
    allPlayers: SelectOption[];
    registeredPlayerIds: number[];
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

const player1Id = ref<number | null>(null);
const player2Id = ref<number | null>(null);

// Anyone already taking part - individually or in a team - would only be
// rejected by the server, and the other field's pick would be rejected as
// "must be a different player", so rule both out in the dropdown instead.
function unavailableIds(otherFieldId: number | null): number[] {
    const registered = new Set(props.registeredPlayerIds);

    return props.allPlayers
        .filter(
            (player) => registered.has(player.id) || player.id === otherFieldId,
        )
        .map((player) => player.id);
}

const player1UnavailableIds = computed(() => unavailableIds(player2Id.value));
const player2UnavailableIds = computed(() => unavailableIds(player1Id.value));

function resetRegisterForm() {
    player1Combobox.value?.reset();
    player2Combobox.value?.reset();
    player1Combobox.value?.focus();
}

// Every registered team fields exactly two players, so the roster size is
// the two tables added up - no extra round trip needed for it.
const totalPlayers = computed(
    () => props.teams.length * 2 + props.singlePlayers.length,
);

const selectedForJoin = ref<number[]>([]);

// Registering the joined team (or unregistering a checked single player)
// removes them from `singlePlayers`, but doesn't otherwise touch this
// selection - drop ids that are no longer registered so a stale id can't
// keep the "join" button enabled while nothing looks checked.
watch(
    () => props.singlePlayers,
    (singlePlayers) => {
        const ids = new Set(singlePlayers.map((player) => player.id));
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
                :description="`${tournament.name} · ${$t('Players in total: :count', { count: String(totalPlayers) })}`"
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
                class="mt-2 grid gap-4 sm:grid-cols-2"
                @success="resetRegisterForm"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="player1">{{ $t('Player 1') }}</Label>
                    <PlayerCombobox
                        id="player1"
                        ref="player1Combobox"
                        v-model="player1Id"
                        name="player1_id"
                        new-name-field="new_player1_name"
                        :options="allPlayers"
                        :disabled-ids="player1UnavailableIds"
                        :disabled-label="$t('(already registered)')"
                        :placeholder="$t('Pick a player or type a new name')"
                    />
                    <InputError :message="errors.player1_id" />
                    <InputError :message="errors.new_player1_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="player2">{{ $t('Player 2 (optional)') }}</Label>
                    <PlayerCombobox
                        id="player2"
                        ref="player2Combobox"
                        v-model="player2Id"
                        name="player2_id"
                        new-name-field="new_player2_name"
                        :options="allPlayers"
                        :disabled-ids="player2UnavailableIds"
                        :disabled-label="$t('(already registered)')"
                        clearable
                        :clear-label="$t('No player')"
                        :placeholder="
                            $t('Leave empty to register a single player')
                        "
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
                :description="$t('Draw the fixtures to start the tournament.')"
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

        <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
                <Heading variant="small" :title="$t('Teams')" />
                <Badge variant="secondary">{{ teams.length }}</Badge>
            </div>

            <div
                class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-4 py-2 font-medium">
                                {{ $t('Team') }}
                            </th>
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
        </div>

        <Form
            v-bind="TournamentRegistrationController.join.form(tournament.id)"
            class="flex flex-col gap-2"
            v-slot="{ errors, processing }"
        >
            <div class="flex items-center gap-2">
                <Heading variant="small" :title="$t('Single players')" />
                <Badge variant="secondary">{{ singlePlayers.length }}</Badge>
            </div>

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
                        <tr v-if="singlePlayers.length === 0">
                            <td
                                colspan="3"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                {{ $t('No single players registered yet.') }}
                            </td>
                        </tr>
                        <tr
                            v-for="player in singlePlayers"
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
                class="mt-2 flex items-center gap-4"
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
