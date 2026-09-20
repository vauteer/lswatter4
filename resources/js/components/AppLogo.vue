<script setup lang="ts">
import iconLogo from '@/images/lswatter4-icon.svg';
import fullLogo from '@/images/lswatter4.svg';

withDefaults(
    defineProps<{
        iconOnly?: boolean;
    }>(),
    {
        iconOnly: false,
    },
);
</script>

<template>
    <img
        :src="iconOnly ? iconLogo : fullLogo"
        alt="LSWatter 4 Logo"
        class="block h-8 w-auto dark:brightness-75 dark:invert"
    />
</template>
