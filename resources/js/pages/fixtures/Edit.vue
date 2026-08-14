<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import FixtureController from '@/actions/App/Http/Controllers/FixtureController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/tournaments';

type Props = {
    fixture: {
        id: number;
        tournamentId: number;
        round: number;
        score: string | null;
        team1: string;
        team2: string;
    };
    placeholder: string;
};

const props = defineProps<Props>();

const backHref = show(props.fixture.tournamentId, {
    query: { round: props.fixture.round },
});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: trans('Tournaments'), href: index() },
            { title: trans('Edit result'), href: index() },
        ],
    },
});
</script>

<template>
    <Head :title="$t('Edit result')" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="$t('Edit result')"
            :description="
                $t(':team1 vs. :team2', {
                    team1: props.fixture.team1,
                    team2: props.fixture.team2,
                })
            "
        />

        <Form
            v-bind="FixtureController.update.form(props.fixture.id)"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="score">{{ $t('Result') }}</Label>
                <Input
                    id="score"
                    name="score"
                    :default-value="fixture.score ?? ''"
                    autocomplete="off"
                    autofocus
                    :placeholder="placeholder"
                />
                <InputError :message="errors.score" />
            </div>

            <div class="flex items-center gap-4">
                <Button variant="outline" :disabled="processing">{{
                    $t('Save')
                }}</Button>
                <Button variant="ghost" as-child>
                    <Link :href="backHref">{{ $t('Cancel') }}</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
