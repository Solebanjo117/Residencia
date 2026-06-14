<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChevronRight,
    ChevronDown,
    Folder,
    FolderOpen,
    BookOpen,
    Calendar,
    File as FileIcon,
    Users,
    ListChecks,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    node: any;
    level?: number;
    expandedState: Record<string, boolean>;
    activeFolderId?: number | null;
    hasInternalDrag?: boolean;
    selectionMode?: boolean;
    selectedFolderId?: number | string | null;
}>();

const emit = defineEmits<{
    (event: 'toggle-folder', folderId: string | number): void;
    (event: 'drop-on-folder', folderId: number): void;
    (event: 'folder-action', action: string, folder: any): void;
    (event: 'select-folder', folderId: number | null): void;
}>();

const page = usePage();
const nodeId = computed(() => String(props.node.id));
const isVirtualNode = computed(() => Boolean(props.node.is_virtual));
const hasChildren = computed(
    () => props.node.children && props.node.children.length > 0,
);
const isOpen = computed(
    () => isVirtualNode.value || Boolean(props.expandedState[nodeId.value]),
);
const isActive = computed(() => {
    if (isVirtualNode.value) {
        return false;
    }

    if (props.activeFolderId != null) {
        return props.activeFolderId === props.node.id;
    }

    return page.url === `/files/folders/${props.node.id}`;
});

const isSelected = computed(() => {
    if (!props.selectionMode || isVirtualNode.value) {
        return false;
    }

    return (
        props.selectedFolderId != null &&
        String(props.selectedFolderId) === String(props.node.id)
    );
});

const isDragOver = ref(false);

const isIndividualProjectsFolder = computed(() => {
    const normalizedName = String(props.node.name ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toUpperCase();

    return (
        normalizedName.includes('PROYECTOS INDIVIDUALES') ||
        normalizedName.includes('PROY IND')
    );
});

const folderColorOptions = {
    yellow: {
        row: '',
        icon: 'text-yellow-500',
    },
    blue: {
        row: 'border border-blue-200 bg-blue-50 hover:bg-blue-100',
        icon: 'text-blue-600',
    },
    green: {
        row: 'border border-emerald-200 bg-emerald-50 hover:bg-emerald-100',
        icon: 'text-emerald-600',
    },
    purple: {
        row: 'border border-purple-200 bg-purple-50 hover:bg-purple-100',
        icon: 'text-purple-600',
    },
    red: {
        row: 'border border-red-200 bg-red-50 hover:bg-red-100',
        icon: 'text-red-600',
    },
    gray: {
        row: 'border border-slate-200 bg-slate-50 hover:bg-slate-100',
        icon: 'text-slate-500',
    },
};

const folderIconOptions = {
    folder: Folder,
    book: BookOpen,
    file: FileIcon,
    calendar: Calendar,
    users: Users,
    checklist: ListChecks,
};

const resolvedColorKey = computed(() => {
    if (props.node.color_key) {
        return props.node.color_key;
    }

    return isIndividualProjectsFolder.value ? 'green' : 'yellow';
});
const folderRowClass = computed(
    () =>
        folderColorOptions[
            resolvedColorKey.value as keyof typeof folderColorOptions
        ]?.row || '',
);
const folderIconClass = computed(
    () =>
        folderColorOptions[
            resolvedColorKey.value as keyof typeof folderColorOptions
        ]?.icon || 'text-yellow-500',
);
const folderIconComponent = computed(
    () =>
        folderIconOptions[
            (props.node.icon_key || 'folder') as keyof typeof folderIconOptions
        ] || Folder,
);

const toggle = () => {
    if (hasChildren.value && !isVirtualNode.value) {
        emit('toggle-folder', props.node.id);
    }
};

const selectNode = () => {
    if (props.selectionMode) {
        if (isVirtualNode.value) {
            toggle();
            return;
        }

        emit('select-folder', Number(props.node.id));
    }
};

const handleDoubleClick = () => {
    if (hasChildren.value) {
        toggle();
    }
};

const onDragOver = (event: DragEvent) => {
    if (isVirtualNode.value) return;

    if (
        event.dataTransfer?.types?.includes('Files') &&
        !props.hasInternalDrag
    ) {
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

    if (
        event.dataTransfer?.types?.includes('Files') &&
        !props.hasInternalDrag
    ) {
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
            :class="[
                folderRowClass,
                {
                    'bg-blue-50 ring-2 ring-blue-400': isSelected,
                    'cursor-pointer': props.selectionMode,
                },
                { 'bg-blue-50 ring-2 ring-blue-400': isDragOver && !props.selectionMode },
            ]"
            @dragover="onDragOver($event)"
            @dragleave="onDragLeave"
            @drop="onDrop"
            @dblclick.stop="handleDoubleClick"
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
                v-if="!selectionMode && !isVirtualNode"
                :href="node.readable_url || `/files/folders/${node.id}`"
                class="flex flex-1 items-center gap-2 text-sm text-gray-700 hover:text-blue-600"
                :class="{ 'font-semibold': isActive }"
            >
                <component
                    :is="isOpen && !node.icon_key ? FolderOpen : folderIconComponent"
                    class="h-4 w-4"
                    :class="folderIconClass"
                />
                {{ node.name }}
            </Link>

            <button
                v-else-if="selectionMode && !isVirtualNode"
                type="button"
                class="flex flex-1 items-center gap-2 text-left text-sm text-gray-700 hover:text-blue-600"
                :class="{ 'font-semibold': isSelected }"
                @click="selectNode"
            >
                <component
                    :is="isOpen && !node.icon_key ? FolderOpen : folderIconComponent"
                    class="h-4 w-4"
                    :class="folderIconClass"
                />
                {{ node.name }}
            </button>

            <button
                v-else
                type="button"
                class="flex flex-1 items-center gap-2 text-left text-sm font-semibold text-gray-700"
                @click="selectNode"
            >
                <component
                    :is="isOpen ? FolderOpen : Folder"
                    class="h-4 w-4"
                    :class="folderIconClass"
                />
                {{ node.name }}
            </button>
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
                :selection-mode="selectionMode"
                :selected-folder-id="selectedFolderId"
                @toggle-folder="emit('toggle-folder', $event)"
                @drop-on-folder="emit('drop-on-folder', $event)"
                @folder-action="
                    (action, folder) => emit('folder-action', action, folder)
                "
                @select-folder="emit('select-folder', $event)"
            />
        </div>
    </div>
</template>
