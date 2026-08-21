<script setup lang="ts">
import { Check, ChevronsUpDown } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { computed, ref, useTemplateRef, watch } from 'vue';
import { cn } from '@/lib/utils';
import type { SelectOption } from '@/types';

const props = withDefaults(
    defineProps<{
        /** Id of the rendered input, so a `<Label for="...">` can target it. */
        id?: string;
        /** Hidden input name carrying the selected player's id. Omit outside a form. */
        name?: string;
        /** Hidden input name carrying a not-yet-existing player's typed name. Omit outside a form. */
        newNameField?: string;
        options: SelectOption[];
        /** Options that are listed but cannot be picked, e.g. already registered players. */
        disabledIds?: number[];
        /** Suffix explaining why the `disabledIds` options can't be picked. */
        disabledLabel?: string;
        placeholder?: string;
        class?: string;
        /** Whether typing a name with no match offers to create it. */
        allowCreate?: boolean;
        /** Whether the dropdown offers an item to clear the current selection. */
        clearable?: boolean;
        clearLabel?: string;
        modelValue?: number | null;
    }>(),
    { allowCreate: true, disabledIds: () => [] },
);

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

// `players.name` is unique, so the typed text says everything on its own:
// text matching an option *is* that selection, any other non-empty text is
// a new player to create. Deriving the selection and both hidden inputs
// from it - instead of mirroring Reka's selection in a second piece of
// state - leaves nothing to keep in sync when the field is blurred, tabbed
// out of, or submitted with Enter.
const text = ref('');

const trimmedText = computed(() => text.value.trim());

const matchedOption = computed(
    () =>
        props.options.find(
            (option) =>
                option.name.toLowerCase() === trimmedText.value.toLowerCase(),
        ) ?? null,
);

const selectedId = computed(() => matchedOption.value?.id ?? null);

const newPlayerName = computed(() =>
    props.allowCreate &&
    trimmedText.value !== '' &&
    matchedOption.value === null
        ? trimmedText.value
        : '',
);

const nameOf = (id?: number | null) =>
    props.options.find((option) => option.id === id)?.name ?? '';

const isDisabled = (id: number) => props.disabledIds.includes(id);

watch(
    () => props.modelValue,
    (value) => {
        if ((value ?? null) !== selectedId.value) {
            text.value = nameOf(value);
        }
    },
    { immediate: true },
);

watch(selectedId, (value) => emit('update:modelValue', value));

const inputRef = useTemplateRef('inputRef');

const isOpen = ref(false);

// Reka pops the list open whenever the input gains focus, which is what the
// user wants when they tab or click in, but not when the component moves
// focus there itself - after picking an item, or after the form was
// submitted, the list has just done its job. Refuse those opens; the list
// still comes back as soon as the user types or clicks.
let keepClosedOnFocus = false;

function focusInput() {
    keepClosedOnFocus = true;
    isOpen.value = false;
    inputRef.value?.$el?.focus();
    keepClosedOnFocus = false;
}

const handleSelect = (value: unknown) => {
    // The "create" item carries the typed name itself; every other item
    // carries a player id, or null for the clear item.
    text.value =
        typeof value === 'string' ? value : nameOf(value as number | null);

    // Selecting an item natively focuses its (about to be unmounted) option
    // element instead of the input, so the browser resets focus to <body>
    // once it's removed - swallowing the next Tab press. Reclaim focus
    // immediately, before that removal happens.
    focusInput();
};

const handleOpenChange = (open: boolean) => {
    if (open && keepClosedOnFocus) {
        return;
    }

    isOpen.value = open;

    // Text naming neither an existing player nor a new one to create - a
    // half-typed name in a combobox that can't create - is not something
    // the form could submit, so don't leave it sitting there looking like
    // a selection.
    if (!open && selectedId.value === null && newPlayerName.value === '') {
        text.value = '';
    }
};

const highlighted = ref<{ ref: HTMLElement; value: unknown }>();

const handleHighlight = (
    item: { ref: HTMLElement; value: unknown } | undefined,
) => {
    highlighted.value = item;
};

// Reka handles Enter itself, but only while an item is really highlighted
// (see reka-ui's ListboxRoot#onKeydownEnter); otherwise the keypress falls
// through to the browser's submit-on-Enter. Since the hidden inputs already
// follow the typed text, picking the highlighted "create" item would add
// nothing, so let Enter submit in that case too rather than making the user
// press it twice.
const handleEnterCapture = (event: KeyboardEvent) => {
    const picksExistingOption =
        highlighted.value?.ref.isConnected === true &&
        highlighted.value.value !== trimmedText.value;

    const form = (event.target as HTMLElement).closest('form');

    if (picksExistingOption || !form) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    form.requestSubmit();
};

function reset() {
    text.value = '';
}

defineExpose({ reset, focus: focusInput });
</script>

<template>
    <ComboboxRoot
        :model-value="selectedId"
        :open="isOpen"
        open-on-click
        open-on-focus
        :reset-search-term-on-blur="false"
        :reset-search-term-on-select="false"
        class="relative"
        @update:model-value="handleSelect"
        @update:open="handleOpenChange"
        @highlight="handleHighlight"
    >
        <ComboboxAnchor
            :class="
                cn(
                    'flex h-9 w-full items-center gap-2 rounded-md border border-input bg-transparent px-3 py-1 shadow-xs outline-none focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                    props.class,
                )
            "
            @keydown.enter.capture="handleEnterCapture"
        >
            <ComboboxInput
                :id="id"
                ref="inputRef"
                v-model="text"
                class="w-full bg-transparent text-base outline-none placeholder:text-muted-foreground md:text-sm"
                :placeholder="placeholder"
            />
            <ComboboxTrigger>
                <ChevronsUpDown class="size-4 shrink-0 text-muted-foreground" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                :side-offset="4"
                class="z-50 max-h-64 w-[var(--reka-combobox-trigger-width)] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md"
            >
                <ComboboxViewport class="p-1">
                    <ComboboxEmpty
                        v-if="!newPlayerName"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        {{ $t('No results found.') }}
                    </ComboboxEmpty>
                    <ComboboxItem
                        v-if="clearable"
                        :value="null"
                        class="relative flex cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm text-muted-foreground outline-none select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                    >
                        {{ clearLabel ?? $t('All') }}
                        <span
                            class="absolute right-2 flex size-3.5 items-center justify-center"
                        >
                            <ComboboxItemIndicator>
                                <Check class="size-4" />
                            </ComboboxItemIndicator>
                        </span>
                    </ComboboxItem>
                    <ComboboxItem
                        v-for="option in options"
                        :key="option.id"
                        :value="option.id"
                        :disabled="isDisabled(option.id)"
                        class="relative flex cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-none select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                    >
                        {{ option.name }}
                        <span
                            v-if="disabledLabel && isDisabled(option.id)"
                            class="text-xs text-muted-foreground"
                        >
                            {{ disabledLabel }}
                        </span>
                        <span
                            class="absolute right-2 flex size-3.5 items-center justify-center"
                        >
                            <ComboboxItemIndicator>
                                <Check class="size-4" />
                            </ComboboxItemIndicator>
                        </span>
                    </ComboboxItem>
                    <ComboboxItem
                        v-if="newPlayerName"
                        :value="newPlayerName"
                        :text-value="newPlayerName"
                        class="relative flex cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm text-primary outline-none select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                    >
                        {{ $t('Create ":name"', { name: newPlayerName }) }}
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>

        <input
            v-if="name"
            type="hidden"
            :name="name"
            :value="selectedId ?? ''"
        />
        <input
            v-if="newNameField"
            type="hidden"
            :name="newNameField"
            :value="newPlayerName"
        />
        <p v-if="newPlayerName" class="text-xs text-muted-foreground">
            {{
                $t('A new player ":name" will be created.', {
                    name: newPlayerName,
                })
            }}
        </p>
    </ComboboxRoot>
</template>
