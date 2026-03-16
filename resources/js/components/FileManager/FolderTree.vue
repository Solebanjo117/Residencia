<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, ChevronDown, Folder, FolderOpen } from 'lucide-vue-next';
import { ref, computed } from 'vue';

const props = defineProps<{
    node: any; // FolderNode type
    level?: number;
}>();

const isOpen = ref(false);
const hasChildren = computed(
    () => props.node.children && props.node.children.length > 0,
);

const toggle = () => {
    if (hasChildren.value) {
        isOpen.value = !isOpen.value;
    }
};
</script>

<template>
    <div class="pl-2">
        <div
            class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-gray-100"
            @click="toggle"
        >
            <component
                :is="
                    hasChildren ? (isOpen ? ChevronDown : ChevronRight) : 'div'
                "
                class="h-4 w-4 text-gray-500"
            />

            <Link
                :href="`/files/folders/${node.id}`"
                class="flex flex-1 items-center gap-2 text-sm text-gray-700 hover:text-blue-600"
                :class="{
                    'font-semibold': $page.url === `/files/folders/${node.id}`,
                }"
            >
                <component
                    :is="isOpen ? FolderOpen : Folder"
                    class="h-4 w-4 text-yellow-500"
                />
                {{ node.name }}
            </Link>
        </div>

        <div v-if="isOpen && hasChildren" class="ml-4 border-l border-gray-200">
            <FolderTree
                v-for="child in node.children"
                :key="child.id"
                :node="child"
                :level="(level || 0) + 1"
            />
        </div>
    </div>
</template>
