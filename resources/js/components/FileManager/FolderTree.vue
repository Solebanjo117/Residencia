<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, ChevronDown, Folder, FolderOpen } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    node: any; // FolderNode type
    level?: number;
    expandedState: Record<number, boolean>;
    activeFolderId?: number | null;
}>();

const emit = defineEmits<{
    (event: 'toggle-folder', folderId: number): void;
}>();

const page = usePage();

const isOpen = computed(() => Boolean(props.expandedState[props.node.id]));
const hasChildren = computed(
    () => props.node.children && props.node.children.length > 0,
);

const isActive = computed(() => {
    if (props.activeFolderId != null) {
        return props.activeFolderId === props.node.id;
    }

    return page.url === `/files/folders/${props.node.id}`;
});

const toggle = () => {
    if (hasChildren.value) {
        emit('toggle-folder', props.node.id);
    }
};
</script>

<template>
    <div class="pl-2">
        <div class="flex items-center gap-2 rounded px-2 py-1 hover:bg-gray-100">
            <button
                type="button"
                class="flex h-5 w-5 items-center justify-center rounded hover:bg-gray-200"
                :disabled="!hasChildren"
                @click.stop="toggle"
            >
                <component
                    v-if="hasChildren"
                    :is="isOpen ? ChevronDown : ChevronRight"
                    class="h-4 w-4 text-gray-500"
                />
            </button>

            <Link
                :href="`/files/folders/${node.id}`"
                class="flex flex-1 items-center gap-2 text-sm text-gray-700 hover:text-blue-600"
                :class="{ 'font-semibold': isActive }"
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
                :expanded-state="expandedState"
                :active-folder-id="activeFolderId"
                @toggle-folder="emit('toggle-folder', $event)"
            />
        </div>
    </div>
</template>
