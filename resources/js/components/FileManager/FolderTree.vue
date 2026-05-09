<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, ChevronDown, Folder, FolderOpen } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    node: any;
    level?: number;
    expandedState: Record<string, boolean>;
    activeFolderId?: number | null;
    hasInternalDrag?: boolean;
}>();

const emit = defineEmits<{
    (event: 'toggle-folder', folderId: string | number): void;
    (event: 'drop-on-folder', folderId: number): void;
    (event: 'folder-action', action: string, folder: any): void;
}>();

const page = usePage();
const nodeId = computed(() => String(props.node.id));
const isVirtualNode = computed(() => Boolean(props.node.is_virtual));
const hasChildren = computed(() => props.node.children && props.node.children.length > 0);
const isOpen = computed(() => isVirtualNode.value || Boolean(props.expandedState[nodeId.value]));
const isActive = computed(() => {
    if (isVirtualNode.value) {
        return false;
    }

    if (props.activeFolderId != null) {
        return props.activeFolderId === props.node.id;
    }

    return page.url === `/files/folders/${props.node.id}`;
});

const isDragOver = ref(false);

const toggle = () => {
    if (hasChildren.value && !isVirtualNode.value) {
        emit('toggle-folder', props.node.id);
    }
};

const onDragOver = (event: DragEvent) => {
    if (isVirtualNode.value) return;

    if (event.dataTransfer?.types?.includes('Files') && !props.hasInternalDrag) {
        return;
    }

    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
    isDragOver.value = true;
};

const onDragLeave = () => {
    isDragOver.value = false;
};

const onDrop = (event: DragEvent) => {
    isDragOver.value = false;
    if (isVirtualNode.value) return;

    if (event.dataTransfer?.types?.includes('Files') && !props.hasInternalDrag) {
        event.preventDefault();
        return;
    }

    event.preventDefault();
    emit('drop-on-folder', Number(props.node.id));
};
</script>

<template>
    <div class="pl-2">
        <div
            class="flex items-center gap-2 rounded px-2 py-1 hover:bg-gray-100"
            :class="{ 'ring-2 ring-blue-400 bg-blue-50': isDragOver }"
            @dragover="onDragOver($event)"
            @dragleave="onDragLeave"
            @drop="onDrop"
        >
            <button
                type="button"
                class="flex h-5 w-5 items-center justify-center rounded hover:bg-gray-200"
                :disabled="!hasChildren || isVirtualNode"
                @click.stop="toggle"
            >
                <component
                    v-if="hasChildren"
                    :is="isOpen ? ChevronDown : ChevronRight"
                    class="h-4 w-4 text-gray-500"
                />
            </button>

            <Link
                v-if="!isVirtualNode"
                :href="`/files/folders/${node.id}`"
                class="flex flex-1 items-center gap-2 text-sm text-gray-700 hover:text-blue-600"
                :class="{ 'font-semibold': isActive }"
            >
                <component :is="isOpen ? FolderOpen : Folder" class="h-4 w-4 text-yellow-500" />
                {{ node.name }}
            </Link>

            <div
                v-else
                class="flex flex-1 items-center gap-2 text-sm font-semibold text-gray-700"
            >
                <component :is="isOpen ? FolderOpen : Folder" class="h-4 w-4 text-yellow-500" />
                {{ node.name }}
            </div>
        </div>

        <div v-if="isOpen && hasChildren" class="ml-4 border-l border-gray-200">
            <FolderTree
                v-for="child in node.children"
                :key="child.id"
                :node="child"
                :level="(level || 0) + 1"
                :expanded-state="expandedState"
                :active-folder-id="activeFolderId"
                :has-internal-drag="hasInternalDrag"
                @toggle-folder="emit('toggle-folder', $event)"
                @drop-on-folder="emit('drop-on-folder', $event)"
                @folder-action="emit('folder-action', $event)"
            />
        </div>
    </div>
</template>