<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
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
            { title: 'Users', href: index() },
            { title: 'Edit user', href: '' },
        ],
    },
});

const confirmingDeletion = ref(false);
const deleteTooltip = computed(() =>
    props.user.deletable ? 'Delete user' : "You can't delete this account",
);
</script>

<template>
    <Head title="Edit user" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Edit user"
            :description="`Update ${props.user.name}'s account`"
        />

        <Form
            v-bind="UserController.update.form(props.user.id)"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <UserFormFields :user="props.user" :errors="errors" />

            <div class="flex items-center gap-4">
                <Button :disabled="processing">Save</Button>
            </div>
        </Form>

        <div
            class="max-w-xl border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border"
        >
            <Heading
                variant="small"
                title="Delete user"
                description="Permanently remove this account. This cannot be undone."
            />

            <Tooltip>
                <TooltipTrigger as-child>
                    <span>
                        <Button
                            variant="destructive"
                            :disabled="!props.user.deletable"
                            @click="confirmingDeletion = true"
                        >
                            Delete user
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
                            <DialogTitle
                                >Delete {{ props.user.name }}?</DialogTitle
                            >
                            <DialogDescription>
                                This will permanently delete the account. This
                                action cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="mt-4 gap-2">
                            <DialogClose as-child>
                                <Button variant="outline" type="button"
                                    >Cancel</Button
                                >
                            </DialogClose>
                            <Button
                                variant="destructive"
                                :disabled="processing"
                            >
                                Delete user
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
