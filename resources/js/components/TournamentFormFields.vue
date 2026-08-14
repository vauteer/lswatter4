<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    tournament?: {
        name: string;
        start: string;
        rounds: number;
        games: number;
        winpoints: number;
        private: boolean;
    };
    errors: Partial<
        Record<
            'name' | 'start' | 'rounds' | 'games' | 'winpoints' | 'private',
            string
        >
    >;
}>();
</script>

<template>
    <div class="grid gap-2">
        <Label for="name">{{ $t('Name') }}</Label>
        <Input
            id="name"
            name="name"
            :default-value="tournament?.name"
            required
            autocomplete="off"
            :placeholder="$t('Tournament name')"
        />
        <InputError :message="errors.name" />
    </div>

    <div class="grid gap-2">
        <Label for="start">{{ $t('Start') }}</Label>
        <Input
            id="start"
            type="datetime-local"
            name="start"
            :default-value="tournament?.start"
            required
        />
        <InputError :message="errors.start" />
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div class="grid gap-2">
            <Label for="rounds">{{ $t('Rounds') }}</Label>
            <Input
                id="rounds"
                type="number"
                name="rounds"
                min="2"
                max="9"
                :default-value="tournament?.rounds ?? 3"
                required
            />
            <InputError :message="errors.rounds" />
        </div>

        <div class="grid gap-2">
            <Label for="games">{{ $t('Games') }}</Label>
            <Input
                id="games"
                type="number"
                name="games"
                min="2"
                max="9"
                :default-value="tournament?.games ?? 4"
                required
            />
            <InputError :message="errors.games" />
        </div>

        <div class="grid gap-2">
            <Label for="winpoints">{{ $t('Winning points') }}</Label>
            <Input
                id="winpoints"
                type="number"
                name="winpoints"
                min="11"
                max="21"
                :default-value="tournament?.winpoints ?? 11"
                required
            />
            <InputError :message="errors.winpoints" />
        </div>
    </div>

    <div class="grid gap-2">
        <div class="flex items-center gap-2">
            <input type="hidden" name="private" value="0" />
            <Checkbox
                id="private"
                name="private"
                value="1"
                :default-value="tournament?.private ?? false"
            />
            <Label for="private" class="font-normal">{{ $t('Private') }}</Label>
        </div>
        <InputError :message="errors.private" />
    </div>
</template>
