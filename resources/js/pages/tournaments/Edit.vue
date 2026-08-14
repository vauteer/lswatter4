<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import TournamentController from '@/actions/App/Http/Controllers/TournamentController';
import Heading from '@/components/Heading.vue';
import TournamentFormFields from '@/components/TournamentFormFields.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { index } from '@/routes/tournaments';

type Props = {
    tournament: {
        id: number;
        name: string;
        start: string;
        rounds: number;
        games: number;
        winpoints: number;
        private: boolean;
        deletable: boolean;
    };
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: trans('Tournaments'), href: index() },
            { title: trans('Edit tournament'), href: index() },
        ],
    },
});

const confirmingDeletion = ref(false);
</script>

<template>
    <Head :title="$t('Edit :name', { name: props.tournament.name })" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="$t('Edit tournament')"
            :description="
                $t('Update the tournament :name', {
                    name: props.tournament.name,
                })
            "
        />

        <Form
            v-bind="TournamentController.update.form(props.tournament.id)"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <TournamentFormFields
                :tournament="props.tournament"
                :errors="errors"
            />

            <div class="flex items-center justify-between">
                <Tooltip v-if="!props.tournament.deletable">
                    <TooltipTrigger as-child>
                        <span tabindex="0" class="inline-block">
                            <Button
                                type="button"
                                variant="destructive"
                                disabled
                                class="pointer-events-none"
                            >
                                {{ $t('Delete') }}
                            </Button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>{{
                        $t('Cannot be deleted')
                    }}</TooltipContent>
                </Tooltip>
                <Button
                    v-else
                    type="button"
                    variant="destructive"
                    @click="confirmingDeletion = true"
                >
                    {{ $t('Delete') }}
                </Button>

                <div class="flex items-center gap-4">
                    <Button variant="outline" :disabled="processing">{{
                        $t('Save')
                    }}</Button>
                    <Button variant="ghost" as-child>
                        <Link :href="index()">{{ $t('Cancel') }}</Link>
                    </Button>
                </div>
            </div>
        </Form>

        <Dialog
            :open="confirmingDeletion"
            @update:open="confirmingDeletion = $event"
        >
            <DialogContent>
                <Form
                    v-bind="
                        TournamentController.destroy.form(props.tournament.id)
                    "
                    v-slot="{ processing }"
                >
                    <DialogHeader>
                        <DialogTitle>{{
                            $t('Delete :name?', {
                                name: props.tournament.name,
                            })
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                $t(
                                    'This will permanently delete this tournament. This action cannot be undone.',
                                )
                            }}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="mt-4 gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary" type="button">{{
                                $t('Cancel')
                            }}</Button>
                        </DialogClose>
                        <Button variant="destructive" :disabled="processing">
                            {{ $t('Delete') }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
