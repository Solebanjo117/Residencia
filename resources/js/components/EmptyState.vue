<script setup lang="ts">
import { FileQuestion } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        icon?: typeof FileQuestion;
    }>(),
    {
        title: 'Sin resultados',
        description: '',
        icon: () => FileQuestion,
    },
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center px-6 py-12 text-center"
    >
        <component :is="icon" class="mb-3 h-10 w-10 text-muted-foreground/50" />
        <p class="text-sm font-medium text-muted-foreground">{{ title }}</p>
        <p v-if="description" class="mt-1 text-xs text-muted-foreground/70">
            {{ description }}
        </p>
        <slot />
    </div>
</template>
