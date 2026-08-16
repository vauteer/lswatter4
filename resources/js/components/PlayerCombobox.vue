<script setup lang="ts">
import { Check, ChevronsUpDown, X } from '@lucide/vue';
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
import { computed, ref } from 'vue';
import { cn } from '@/lib/utils';
import type { SelectOption } from '@/types';

const props = defineProps<{
    /** Hidden input name carrying the selected player's id. */
    name: string;
    /** Hidden input name carrying a not-yet-existing player's typed name. */
    newNameField: string;
    options: SelectOption[];
    placeholder?: string;
    class?: string;
}>();

// This sentinel never reaches ComboboxRoot's own modelValue - selecting it
// is intercepted and handled entirely below - so it only has to be a
// value distinguishable from every real (positive) player id.
const CREATE_NEW_VALUE = -1;

const selectedId = ref<number | null>(null);
const searchText = ref('');
const newPlayerName = ref('');

const displayValue = (value: unknown) => {
    if (value === null || value === undefined) {
        // Reka resets the input's search text to this on blur. Falling
        // back to the pending create-name (empty string otherwise) keeps
        // a confirmed "create new" choice from vanishing when the field
        // loses focus.
        return newPlayerName.value;
    }

    return props.options.find((option) => option.id === value)?.name ?? '';
};

const hasExactMatch = computed(() =>
    props.options.some(
        (option) =>
            option.name.toLowerCase() === searchText.value.trim().toLowerCase(),
    ),
);

const showCreateOption = computed(
    () => searchText.value.trim() !== '' && !hasExactMatch.value,
);

const handleSelect = (value: unknown) => {
    selectedId.value = (value as number | null) ?? null;
    newPlayerName.value = '';
};

const handleCreateSelect = (event: Event) => {
    event.preventDefault();
    newPlayerName.value = searchText.value.trim();
    selectedId.value = null;
};

function reset() {
    selectedId.value = null;
    searchText.value = '';
    newPlayerName.value = '';
}

const hasValue = computed(
    () => selectedId.value !== null || newPlayerName.value !== '',
);

defineExpose({ reset });
</script>

<template>
    <ComboboxRoot
        :model-value="selectedId"
        open-on-click
        open-on-focus
        class="relative"
        @update:model-value="handleSelect"
    >
        <ComboboxAnchor
            :class="
                cn(
                    'flex h-9 w-full items-center gap-2 rounded-md border border-input bg-transparent px-3 py-1 shadow-xs outline-none focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50',
                    props.class,
                )
            "
        >
            <ComboboxInput
                v-model="searchText"
                class="w-full bg-transparent text-base outline-none placeholder:text-muted-foreground md:text-sm"
                :placeholder="placeholder"
                :display-value="displayValue"
            />
            <button
                v-if="hasValue"
                type="button"
                class="shrink-0 text-muted-foreground outline-none hover:text-foreground"
                :aria-label="$t('Clear selection')"
                @click.stop="reset"
            >
                <X class="size-4" />
            </button>
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
                        v-if="!showCreateOption"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        {{ $t('No results found.') }}
                    </ComboboxEmpty>
                    <ComboboxItem
                        v-for="option in options"
                        :key="option.id"
                        :value="option.id"
                        class="relative flex cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-none select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                    >
                        {{ option.name }}
                        <span
                            class="absolute right-2 flex size-3.5 items-center justify-center"
                        >
                            <ComboboxItemIndicator>
                                <Check class="size-4" />
                            </ComboboxItemIndicator>
                        </span>
                    </ComboboxItem>
                    <ComboboxItem
                        v-if="showCreateOption"
                        :key="searchText"
                        :value="CREATE_NEW_VALUE"
                        :text-value="searchText"
                        class="relative flex cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm text-primary outline-none select-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                        @select="handleCreateSelect"
                    >
                        {{ $t('Create ":name"', { name: searchText.trim() }) }}
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>

        <input type="hidden" :name="name" :value="selectedId ?? ''" />
        <input type="hidden" :name="newNameField" :value="newPlayerName" />
        <p v-if="newPlayerName" class="text-xs text-muted-foreground">
            {{
                $t('A new player ":name" will be created.', {
                    name: newPlayerName,
                })
            }}
        </p>
    </ComboboxRoot>
</template>
