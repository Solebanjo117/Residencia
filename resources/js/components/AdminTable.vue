<script setup lang="ts">
defineProps<{
    emptyMessage?: string;
}>();
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="overflow-x-auto">
            <slot />
        </div>
        <slot name="footer" />
    </div>
</template>