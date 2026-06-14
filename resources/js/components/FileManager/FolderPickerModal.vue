<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Check, FileText, Folder, Plus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import FolderTree from '@/components/FileManager/FolderTree.vue';
import {
    findFolderPathById,
    folderPathLabel,
    type FolderTreeNode,
} from '@/lib/folderTree';

type PickerMode = 'folder' | 'template';

type FolderContentFile = {
    id: number;
    name: string;
    size?: number | null;
    uploaded_at?: string | null;
    uploaded_by?: string | null;
    mime_type?: string | null;
    folder_name?: string | null;
    folder_path?: string | null;
    docx_editor_url?: string | null;
    download_url?: string | null;
};

const props = defineProps<{
    open: boolean;
    folderTree: FolderTreeNode[];
    selectedFolderId: number | null;
    selectedFileId?: number | null;
    mode?: PickerMode;
    title?: string;
    description?: string;
    contentsEndpointBase?: string;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
    (event: 'update:selectedFolderId', value: number | null): void;
    (event: 'update:selectedFileId', value: number | null): void;
    (event: 'confirm'): void;
}>();

const isOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

const isTemplateMode = computed(() => props.mode === 'template');

const expandedFolders = ref<Record<string, boolean>>({});
const draftSelectedFolderId = ref<number | null>(props.selectedFolderId);
const draftSelectedFile = ref<FolderContentFile | null>(null);
const folderFiles = ref<FolderContentFile[]>([]);
const isLoadingFiles = ref(false);
const fileLoadError = ref('');

const selectedPath = computed(() =>
    findFolderPathById(props.folderTree, draftSelectedFolderId.value),
);

const selectedPathLabel = computed(() =>
    draftSelectedFolderId.value == null
        ? 'Crear carpeta sugerida'
        : folderPathLabel(selectedPath.value) || 'Carpeta seleccionada',
);

const selectedPathTrail = computed(
    () => selectedPath.value?.filter((node) => !node.is_virtual) ?? [],
);

const rootLabel = computed(() =>
    selectedPath.value?.length
        ? selectedPath.value[0]?.is_virtual
            ? selectedPath.value[0]?.name
            : 'Carpetas'
        : 'Carpetas',
);

const selectedFileLabel = computed(() => {
    if (!draftSelectedFile.value) {
        return 'Ningún DOCX seleccionado';
    }

    return draftSelectedFile.value.folder_path
        ? `${draftSelectedFile.value.folder_path} / ${draftSelectedFile.value.name}`
        : draftSelectedFile.value.name;
});

function syncExpandedState() {
    const nextState: Record<string, boolean> = {};

    for (const node of selectedPath.value ?? []) {
        nextState[String(node.id)] = true;
    }

    expandedFolders.value = nextState;
}

function syncDraftSelection() {
    draftSelectedFolderId.value = props.selectedFolderId;
    if (isTemplateMode.value && props.selectedFileId != null) {
        draftSelectedFile.value =
            folderFiles.value.find((file) => file.id === props.selectedFileId) ||
            (draftSelectedFile.value?.id === props.selectedFileId
                ? draftSelectedFile.value
                : null);
    } else {
        draftSelectedFile.value = null;
    }
    syncExpandedState();
}

async function loadFilesForFolder(folderId: number | null) {
    if (!isTemplateMode.value || folderId == null) {
        folderFiles.value = [];
        fileLoadError.value = '';
        return;
    }

    const base = props.contentsEndpointBase || '/files/folders';
    isLoadingFiles.value = true;
    fileLoadError.value = '';

    try {
        const response = await fetch(`${base}/${folderId}/contents`, {
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('No se pudo cargar la carpeta seleccionada.');
        }

        const payload = await response.json();
        folderFiles.value = Array.isArray(payload?.contents?.files)
            ? payload.contents.files
            : [];

        if (props.selectedFileId != null) {
            draftSelectedFile.value =
                folderFiles.value.find((file) => file.id === props.selectedFileId) ||
                draftSelectedFile.value;
        }
    } catch (error: any) {
        folderFiles.value = [];
        fileLoadError.value =
            error?.message || 'No se pudo cargar la carpeta seleccionada.';
    } finally {
        isLoadingFiles.value = false;
    }
}

function toggleExpanded(folderId: string | number) {
    const key = String(folderId);
    expandedFolders.value = {
        ...expandedFolders.value,
        [key]: !expandedFolders.value[key],
    };
}

function handleSelectFolder(folderId: number | null) {
    draftSelectedFolderId.value = folderId;
    emit('update:selectedFolderId', folderId);
}

function handleSelectFile(file: FolderContentFile) {
    draftSelectedFile.value = file;
    emit('update:selectedFileId', file.id);
}

function handleConfirm() {
    if (isTemplateMode.value) {
        if (!draftSelectedFile.value) {
            return;
        }

        emit('update:selectedFileId', draftSelectedFile.value.id);
    } else {
        emit('update:selectedFolderId', draftSelectedFolderId.value);
    }

    emit('confirm');
    isOpen.value = false;
}

watch(
    () => isOpen.value,
    (value) => {
        if (value) {
            syncDraftSelection();
            void loadFilesForFolder(draftSelectedFolderId.value);
        }
    },
);

watch(
    () => props.selectedFolderId,
    (folderId) => {
        draftSelectedFolderId.value = folderId;
        syncExpandedState();
        void loadFilesForFolder(folderId);
    },
);

watch(
    () => props.selectedFileId,
    (fileId) => {
        if (!isTemplateMode.value || fileId == null) {
            draftSelectedFile.value = null;
            return;
        }

        const match = folderFiles.value.find((file) => file.id === fileId);
        draftSelectedFile.value = match || null;
    },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="flex max-h-[90vh] flex-col sm:max-w-5xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Folder class="h-5 w-5 text-slate-600" />
                    {{ title || 'Seleccionar carpeta' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        description ||
                        'Haz click para seleccionar y doble click para entrar en una carpeta.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 space-y-4 overflow-y-auto pr-1">
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ isTemplateMode ? 'Carpeta actual' : 'Ruta seleccionada' }}
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm font-medium text-slate-900">
                        <template v-if="selectedPathTrail.length">
                            <span
                                v-for="(node, index) in selectedPathTrail"
                                :key="node.id"
                                class="inline-flex items-center gap-2"
                            >
                                <span v-if="index > 0" class="text-slate-300">/</span>
                                <span class="rounded-full bg-white px-2.5 py-1 text-slate-700">
                                    {{ node.name }}
                                </span>
                            </span>
                        </template>
                        <span v-else class="text-slate-500">
                            Crear carpeta sugerida
                        </span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        {{ selectedPathLabel }}
                    </p>
                </div>

                <button
                    v-if="!isTemplateMode"
                    type="button"
                    class="flex w-full items-center justify-between rounded-xl border px-4 py-3 text-left transition"
                    :class="
                        draftSelectedFolderId == null
                            ? 'border-blue-300 bg-blue-50 ring-2 ring-blue-400'
                            : 'border-slate-200 bg-white hover:bg-slate-50'
                    "
                    @click="handleSelectFolder(null)"
                >
                    <span class="flex items-center gap-3">
                        <span class="rounded-lg bg-emerald-100 p-2 text-emerald-700">
                            <Plus class="h-4 w-4" />
                        </span>
                        <span>
                            <span class="block text-sm font-medium text-slate-900">
                                Crear carpeta sugerida
                            </span>
                            <span class="block text-xs text-slate-500">
                                Se creara la ruta institucional si no existe.
                            </span>
                        </span>
                    </span>
                    <Check v-if="draftSelectedFolderId == null" class="h-4 w-4 text-blue-600" />
                </button>

                <div class="grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <div class="mb-3 flex items-center justify-between px-1">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {{ rootLabel }}
                                </p>
                                <p class="text-sm text-slate-500">
                                    Click para navegar, doble click para expandir.
                                </p>
                            </div>
                        </div>

                        <FolderTree
                            v-for="root in folderTree"
                            :key="root.id"
                            :node="root"
                            :expanded-state="expandedFolders"
                            :selected-folder-id="draftSelectedFolderId"
                            :selection-mode="true"
                            @toggle-folder="toggleExpanded"
                            @select-folder="handleSelectFolder"
                        />
                    </div>

                    <div v-if="isTemplateMode" class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="mb-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Formatos DOCX
                            </p>
                            <p class="text-sm text-slate-500">
                                Selecciona un archivo DOCX para copiarlo al proyecto.
                            </p>
                        </div>

                        <div
                            v-if="isLoadingFiles"
                            class="rounded-lg border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500"
                        >
                            Cargando archivos...
                        </div>

                        <div
                            v-else-if="fileLoadError"
                            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                        >
                            {{ fileLoadError }}
                        </div>

                        <div
                            v-else-if="folderFiles.length === 0"
                            class="rounded-lg border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500"
                        >
                            Esta carpeta no contiene DOCX plantilla visible.
                        </div>

                        <div v-else class="space-y-2">
                            <button
                                v-for="file in folderFiles"
                                :key="file.id"
                                type="button"
                                class="flex w-full items-start justify-between rounded-lg border px-3 py-3 text-left transition"
                                :class="
                                    draftSelectedFile?.id === file.id
                                        ? 'border-blue-300 bg-blue-50 ring-2 ring-blue-400'
                                        : 'border-slate-200 bg-white hover:bg-slate-50'
                                "
                                @click="handleSelectFile(file)"
                            >
                                <span class="flex min-w-0 items-start gap-3">
                                    <span class="rounded-lg bg-blue-100 p-2 text-blue-700">
                                        <FileText class="h-4 w-4" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-slate-900">
                                            {{ file.name }}
                                        </span>
                                        <span class="block text-xs text-slate-500">
                                            {{ file.folder_path || selectedPathLabel }}
                                        </span>
                                    </span>
                                </span>
                                <Check
                                    v-if="draftSelectedFile?.id === file.id"
                                    class="mt-1 h-4 w-4 text-blue-600"
                                />
                            </button>
                        </div>

                        <div class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            <span class="font-semibold">Seleccionado:</span>
                            {{ selectedFileLabel }}
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-4 border-t border-slate-200 pt-4">
                <Button variant="outline" @click="isOpen = false">Cancelar</Button>
                <Button
                    class="min-w-48"
                    :disabled="isTemplateMode && !draftSelectedFile"
                    @click="handleConfirm"
                >
                    {{ isTemplateMode ? 'Usar formato seleccionado' : 'Usar carpeta seleccionada' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
