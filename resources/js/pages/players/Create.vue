<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import PlayerController from '@/actions/App/Http/Controllers/PlayerController';
import Heading from '@/components/Heading.vue';
import PlayerFormFields from '@/components/PlayerFormFields.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/players';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: trans('Players'), href: index() },
            { title: trans('New player'), href: create() },
        ],
    },
});
</script>

<template>
    <Head :title="$t('New player')" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="$t('New player')"
            :description="$t('Add a new player')"
        />

        <Form
            v-bind="PlayerController.store.form()"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <PlayerFormFields :errors="errors" />

            <div class="flex items-center gap-4">
                <Button variant="outline" :disabled="processing">{{
                    $t('Save')
                }}</Button>
                <Button variant="ghost" as-child>
                    <Link :href="index()">{{ $t('Cancel') }}</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
