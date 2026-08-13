<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { User } from '@/types';

defineProps<{
    user?: Pick<User, 'name' | 'email' | 'admin'>;
    errors: Partial<Record<'name' | 'email' | 'admin', string>>;
}>();
</script>

<template>
    <div class="grid gap-2">
        <Label for="name">{{ $t('Name') }}</Label>
        <Input
            id="name"
            name="name"
            :default-value="user?.name"
            required
            autocomplete="name"
            :placeholder="$t('Full name')"
        />
        <InputError :message="errors.name" />
    </div>

    <div class="grid gap-2">
        <Label for="email">{{ $t('Email address') }}</Label>
        <Input
            id="email"
            type="email"
            name="email"
            :default-value="user?.email"
            required
            autocomplete="email"
            :placeholder="$t('Email address')"
        />
        <InputError :message="errors.email" />
    </div>

    <div class="grid gap-2">
        <div class="flex items-center gap-2">
            <input type="hidden" name="admin" value="0" />
            <Checkbox
                id="admin"
                name="admin"
                value="1"
                :default-value="user?.admin ?? false"
            />
            <Label for="admin" class="font-normal">{{ $t('Admin') }}</Label>
        </div>
        <InputError :message="errors.admin" />
    </div>
</template>
