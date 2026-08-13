<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    player?: { name: string };
    errors: Partial<Record<'name', string>>;
}>();
</script>

<template>
    <div class="grid gap-2">
        <Label for="name">{{ $t('Name') }}</Label>
        <Input
            id="name"
            name="name"
            :default-value="player?.name"
            required
            autocomplete="off"
            :placeholder="$t('Player name')"
        />
        <InputError :message="errors.name" />
    </div>
</template>
