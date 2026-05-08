<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { type NavItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();

const groupedItems = computed(() => {
    const groups = new Map<string, NavItem[]>();

    for (const item of props.items) {
        const section = item.section ?? 'Sistema';
        groups.set(section, [...(groups.get(section) ?? []), item]);
    }

    return Array.from(groups.entries()).map(([label, sectionItems]) => ({
        label,
        items: sectionItems,
    }));
});
</script>

<template>
    <SidebarGroup
        v-for="group in groupedItems"
        :key="group.label"
        class="px-2 py-0"
    >
        <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
