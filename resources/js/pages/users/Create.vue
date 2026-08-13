<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import UserFormFields from '@/components/UserFormFields.vue';
import { create, index } from '@/routes/users';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: trans('Users'), href: index() },
            { title: trans('New user'), href: create() },
        ],
    },
});
</script>

<template>
    <Head :title="$t('New user')" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="$t('New user')"
            :description="
                $t(
                    'Create a new account and email them a link to set their password',
                )
            "
        />

        <Form
            v-bind="UserController.store.form()"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <UserFormFields :errors="errors" />

            <div class="flex items-center gap-4">
                <Button :disabled="processing">{{ $t('Save') }}</Button>
            </div>
        </Form>
    </div>
</template>
