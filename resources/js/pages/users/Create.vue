<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import UserFormFields from '@/components/UserFormFields.vue';
import { index } from '@/routes/users';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: index() },
            { title: 'New user', href: '' },
        ],
    },
});
</script>

<template>
    <Head title="New user" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="New user"
            description="Create a new account and email them a link to set their password"
        />

        <Form
            v-bind="UserController.store.form()"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <UserFormFields :errors="errors" />

            <div class="flex items-center gap-4">
                <Button :disabled="processing">Create user</Button>
            </div>
        </Form>
    </div>
</template>
