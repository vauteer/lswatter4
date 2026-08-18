<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { onMounted, ref, useTemplateRef } from 'vue';
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
    gamesNeeded: number;
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

const score = ref(props.fixture.score ?? '');

const scoreInput = useTemplateRef('scoreInput');

// The autofocus attribute is a no-op here: this page is reached via an
// Inertia client-side visit, not a full document parse, which is the
// only time browsers honor it.
onMounted(() => {
    scoreInput.value?.$el?.focus();
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
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(':count games needed', {
                            count: String(gamesNeeded),
                        })
                    }}
                </p>
                <div class="relative">
                    <Input
                        ref="scoreInput"
                        id="score"
                        name="score"
                        v-model="score"
                        autocomplete="off"
                        :placeholder="placeholder"
                        class="pr-9"
                    />
                    <button
                        v-if="score"
                        type="button"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground"
                        :aria-label="$t('Clear result')"
                        @click="score = ''"
                    >
                        <X class="size-4" />
                    </button>
                </div>
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
