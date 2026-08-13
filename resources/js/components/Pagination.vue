<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

defineProps<{
    links: PaginationLink[];
}>();
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap items-center gap-1">
        <template v-for="(link, index) in links" :key="index">
            <span
                v-if="link.url === null"
                v-html="link.label"
                :class="
                    cn(
                        buttonVariants({ variant: 'ghost', size: 'sm' }),
                        'pointer-events-none opacity-50',
                    )
                "
            />
            <Link
                v-else
                :href="link.url"
                preserve-scroll
                :class="
                    cn(
                        buttonVariants({
                            variant: link.active ? 'default' : 'ghost',
                            size: 'sm',
                        }),
                    )
                "
            >
                <span v-html="link.label" />
            </Link>
        </template>
    </nav>
</template>
