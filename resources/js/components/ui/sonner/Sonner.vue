<script setup lang="ts">
import { Toaster as Sonner, type ToasterProps } from 'vue-sonner'

const props = withDefaults(defineProps<ToasterProps>(), {
  position: 'top-right',
  richColors: true,
})
</script>

<template>
  <Sonner v-bind="props" class="toaster" />
</template>