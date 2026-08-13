<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
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
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: trans('Users'), href: index() },
            { title: trans('Edit user'), href: index() },
        ],
    },
});

const confirmingDeletion = ref(false);
const deleteTooltip = computed(() =>
    props.user.deletable ? trans('Delete user') : trans('Cannot be deleted'),
);
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

            <div class="flex items-center gap-4">
                <Button :disabled="processing">{{ $t('Save') }}</Button>
            </div>
        </Form>

        <div
            class="max-w-xl border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border"
        >
            <Heading
                variant="small"
                :title="$t('Delete user')"
                :description="
                    $t(
                        'Permanently remove this account. This cannot be undone.',
                    )
                "
            />

            <Tooltip>
                <TooltipTrigger as-child>
                    <span>
                        <Button
                            variant="destructive"
                            :disabled="!props.user.deletable"
                            @click="confirmingDeletion = true"
                        >
                            {{ $t('Delete user') }}
                        </Button>
                    </span>
                </TooltipTrigger>
                <TooltipContent>{{ deleteTooltip }}</TooltipContent>
            </Tooltip>

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
                                <Button variant="outline" type="button">{{
                                    $t('Cancel')
                                }}</Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                :disabled="processing"
                            >
                                {{ $t('Delete user') }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
