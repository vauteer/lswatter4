<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import PlayerController from '@/actions/App/Http/Controllers/PlayerController';
import Heading from '@/components/Heading.vue';
import PlayerFormFields from '@/components/PlayerFormFields.vue';
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
import { index } from '@/routes/players';

type Props = {
    player: {
        id: number;
        name: string;
        deletable: boolean;
    };
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: trans('Players'), href: index() },
            { title: trans('Edit player'), href: index() },
        ],
    },
});

const confirmingDeletion = ref(false);
</script>

<template>
    <Head :title="$t('Edit :name', { name: props.player.name })" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="$t('Edit player')"
            :description="
                $t('Update the player :name', { name: props.player.name })
            "
        />

        <Form
            v-bind="PlayerController.update.form(props.player.id)"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <PlayerFormFields :player="props.player" :errors="errors" />

            <div class="flex items-center justify-between">
                <Tooltip v-if="!props.player.deletable">
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
                    v-bind="PlayerController.destroy.form(props.player.id)"
                    v-slot="{ processing }"
                >
                    <DialogHeader>
                        <DialogTitle>{{
                            $t('Delete :name?', { name: props.player.name })
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                $t(
                                    'This will permanently delete this player. This action cannot be undone.',
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
