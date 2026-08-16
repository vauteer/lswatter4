<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

defineProps<{
    action: { action: string; method: 'post' | 'put' | 'patch' | 'delete' };
    title: string;
    description: string;
    confirmLabel: string;
    /** Label for the tooltip shown on the trigger. Omit for no tooltip. */
    tooltip?: string;
}>();
</script>

<template>
    <Dialog>
        <!--
            Nested-trigger composition: the tooltip's trigger wraps the
            dialog's trigger, which wraps the real button, so both
            primitives' click/hover behavior end up bound to that one
            element. `<Tooltip>`/`<Dialog>` themselves are just context
            providers with no root element of their own to forward
            attributes through, so they can't sit between a trigger and
            the element it needs to bind to.
        -->
        <Tooltip v-if="tooltip">
            <TooltipTrigger as-child>
                <DialogTrigger as-child>
                    <slot />
                </DialogTrigger>
            </TooltipTrigger>
            <TooltipContent>{{ tooltip }}</TooltipContent>
        </Tooltip>
        <DialogTrigger v-else as-child>
            <slot />
        </DialogTrigger>

        <DialogContent>
            <Form
                v-bind="action"
                :options="{ preserveScroll: true }"
                v-slot="{ processing }"
            >
                <DialogHeader class="space-y-3">
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription>{{ description }}</DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2 pt-4">
                    <DialogClose as-child>
                        <Button variant="secondary">{{ $t('Cancel') }}</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        {{ confirmLabel }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
