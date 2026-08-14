<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import TournamentController from '@/actions/App/Http/Controllers/TournamentController';
import Heading from '@/components/Heading.vue';
import TournamentFormFields from '@/components/TournamentFormFields.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/tournaments';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: trans('Tournaments'), href: index() },
            { title: trans('New tournament'), href: create() },
        ],
    },
});
</script>

<template>
    <Head :title="$t('New tournament')" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="$t('New tournament')"
            :description="$t('Add a new tournament')"
        />

        <Form
            v-bind="TournamentController.store.form()"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <TournamentFormFields :errors="errors" />

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
