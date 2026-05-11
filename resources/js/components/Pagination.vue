<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    links: PaginationLink[];
}>();

const visibleLinks = computed(() => {
    return props.links;
});

function decodeLabel(label: string): string {
    if (label === '&laquo;') return '\u00AB';
    if (label === '&raquo;') return '\u00BB';
    return label;
}
</script>

<template>
    <nav
        v-if="visibleLinks.length > 3"
        class="mt-4 flex items-center justify-center gap-1"
        aria-label="Paginación"
    >
        <template v-for="(link, i) in visibleLinks" :key="i">
            <Link
                v-if="link.url"
                :href="link.url"
                class="rounded px-3 py-1 text-sm"
                :class="
                    link.active
                        ? 'bg-primary font-semibold text-primary-foreground'
                        : 'border border-border bg-background text-foreground hover:bg-accent'
                "
                preserve-state
            >
                {{ decodeLabel(link.label) }}
            </Link>
            <span v-else class="px-3 py-1 text-sm text-muted-foreground">
                {{ decodeLabel(link.label) }}
            </span>
        </template>
    </nav>
</template>
