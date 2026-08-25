<script setup lang="ts">
import type { AvatarImageProps } from "reka-ui"
import { AvatarImage } from "reka-ui"

const props = defineProps<AvatarImageProps>()

// object-cover below: the image is forced into a square box, and object-fit
// defaults to "fill", which stretches anything that is not already square
// instead of cropping it.
</script>

<template>
  <AvatarImage
    data-slot="avatar-image"
    v-bind="props"
    class="aspect-square size-full object-cover"
  >
    <slot />
  </AvatarImage>
</template>
