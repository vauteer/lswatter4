<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/Heading.vue';
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
import UserFormFields from '@/components/UserFormFields.vue';
import { index } from '@/routes/users';
import type { User } from '@/types';

type Props = {
    user: Pick<User, 'id' | 'name' | 'email' | 'admin'> & {
        deletable: boolean;
    };
    backPage: number | null;
    backSearch: string | null;
};

const props = defineProps<Props>();

const cancelHref = index({
    query: {
        page: props.backPage ?? undefined,
        search: props.backSearch ?? undefined,
    },
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: trans('Users'), href: index() },
            { title: trans('Edit user'), href: index() },
        ],
    },
});

const confirmingDeletion = ref(false);
</script>

<template>
    <Head :title="$t('Edit :name', { name: props.user.name })" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="$t('Edit user')"
            :description="
                $t('Update the account for :name', { name: props.user.name })
            "
        />

        <Form
            v-bind="UserController.update.form(props.user.id)"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <UserFormFields :user="props.user" :errors="errors" />

            <div class="flex items-center justify-between">
                <Tooltip v-if="!props.user.deletable">
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
                        <Link :href="cancelHref">{{ $t('Cancel') }}</Link>
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
                    v-bind="UserController.destroy.form(props.user.id)"
                    v-slot="{ processing }"
                >
                    <DialogHeader>
                        <DialogTitle>{{
                            $t('Delete :name?', { name: props.user.name })
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                $t(
                                    'This will permanently delete this user. This action cannot be undone.',
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
