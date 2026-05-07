<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-9 items-center justify-center overflow-hidden rounded-full bg-white shadow-sm ring-1 ring-sidebar-border"
    >
        <AppLogoIcon class="size-9 object-contain" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm leading-tight">
        <span class="mb-0.5 truncate leading-tight font-semibold">
            Residencia
        </span>
        <span class="truncate text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
            Tecnológico de Piedras Negras
        </span>
    </div>
</template>
