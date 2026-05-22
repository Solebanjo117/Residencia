<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Folder,
    BookOpen,
    Calendar,
    FileText,
    File as FileIcon,
    Eye,
    Download,
    Trash2,
    History,
    Upload,
    RefreshCw,
    X,
    FolderPlus,
    Pencil,
    ArrowRightLeft,
    Move,
    AlertTriangle,
    Copy,
    Check,
    Users,
    ListChecks,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import FolderTree from '@/components/FileManager/FolderTree.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
    folderTree: any[];
    currentFolder: {
        id: number;
        name: string;
        parent_id?: number | null;
        display_path?: string;
        readable_url?: string;
        can_upload: boolean;
        can_create_folder?: boolean;
        can_rename?: boolean;
        can_move?: boolean;
        can_delete?: boolean;
        ancestors?: Array<{
            id: number;
            name: string;
            can_view: boolean;
            display_path?: string;
            readable_url?: string;
        }>;
    } | null;
    semesterName?: string | null;
    allowedExtensions?: string[];
    contents: {
        folders: any[];
        files: any[];
    };
}>();

type FileHistoryEntry = {
    id: number;
    action: string;
    label: string;
    at: string | null;
    actor_name: string;
    actor_email: string | null;
    metadata: {
        old_file_name?: string | null;
        new_file_name?: string | null;
        old_size_bytes?: number | null;
        new_size_bytes?: number | null;
        old_file_hash?: string | null;
        new_file_hash?: string | null;
        editor_source?: string | null;
        mime_type?: string | null;
    };
};

const expandedFoldersStorageKey = 'fileManager.expandedFolders';
const leftPanelWidthStorageKey = 'fileManager.leftPanelWidth';
const leftPanelMinWidth = 18;
const leftPanelMaxWidth = 50;

const containerRef = ref<HTMLElement | null>(null);
const expandedFolders = ref<Record<string, boolean>>({});
const leftPanelWidth = ref(25);
const isResizing = ref(false);
const resizeStartX = ref(0);
const resizeStartWidth = ref(25);

const loadExpandedFolders = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const rawValue = window.sessionStorage.getItem(expandedFoldersStorageKey);
    if (!rawValue) {
        return;
    }

    try {
        const parsed = JSON.parse(rawValue);
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            expandedFolders.value = parsed as Record<string, boolean>;
        }
    } catch {
        window.sessionStorage.removeItem(expandedFoldersStorageKey);
    }
};

const persistExpandedFolders = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.setItem(
        expandedFoldersStorageKey,
        JSON.stringify(expandedFolders.value),
    );
};

const setFolderExpanded = (folderId: string | number, isExpanded: boolean) => {
    const key = String(folderId);
    const nextState = { ...expandedFolders.value };

    if (isExpanded) {
        nextState[key] = true;
    } else {
        delete nextState[key];
    }

    expandedFolders.value = nextState;
    persistExpandedFolders();
};

const toggleFolderExpanded = (folderId: string | number) => {
    const key = String(folderId);
    setFolderExpanded(folderId, !expandedFolders.value[key]);
};

const expandAncestorsFromCurrentFolder = () => {
    if (!props.currentFolder?.ancestors?.length) {
        return;
    }

    const nextState = { ...expandedFolders.value };

    for (const ancestor of props.currentFolder.ancestors) {
        nextState[String(ancestor.id)] = true;
    }

    expandedFolders.value = nextState;
    persistExpandedFolders();
};

const loadLeftPanelWidth = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const rawValue = window.localStorage.getItem(leftPanelWidthStorageKey);
    if (!rawValue) {
        return;
    }

    const parsed = Number(rawValue);
    if (!Number.isFinite(parsed)) {
        return;
    }

    leftPanelWidth.value = Math.max(
        leftPanelMinWidth,
        Math.min(leftPanelMaxWidth, parsed),
    );
};

const persistLeftPanelWidth = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(
        leftPanelWidthStorageKey,
        String(leftPanelWidth.value),
    );
};

const onResizeMove = (event: MouseEvent) => {
    if (!isResizing.value || !containerRef.value) {
        return;
    }

    const bounds = containerRef.value.getBoundingClientRect();
    if (bounds.width <= 0) {
        return;
    }

    const deltaX = event.clientX - resizeStartX.value;
    const startingWidthPx = (resizeStartWidth.value / 100) * bounds.width;
    const nextWidthPercent = ((startingWidthPx + deltaX) / bounds.width) * 100;

    leftPanelWidth.value = Math.max(
        leftPanelMinWidth,
        Math.min(leftPanelMaxWidth, nextWidthPercent),
    );
};

const stopResizing = () => {
    if (isResizing.value) {
        isResizing.value = false;
        persistLeftPanelWidth();
    }

    if (typeof document !== 'undefined') {
        document.body.classList.remove('cursor-col-resize', 'select-none');
    }

    window.removeEventListener('mousemove', onResizeMove);
    window.removeEventListener('mouseup', stopResizing);
};

const startResizing = (event: MouseEvent) => {
    if (!containerRef.value) {
        return;
    }

    isResizing.value = true;
    resizeStartX.value = event.clientX;
    resizeStartWidth.value = leftPanelWidth.value;

    if (typeof document !== 'undefined') {
        document.body.classList.add('cursor-col-resize', 'select-none');
    }

    window.addEventListener('mousemove', onResizeMove);
    window.addEventListener('mouseup', stopResizing);
};

onMounted(() => {
    loadExpandedFolders();
    loadLeftPanelWidth();
    expandAncestorsFromCurrentFolder();
    window.addEventListener('click', closeFolderContextMenu);
    window.addEventListener('keydown', closeFolderContextMenuOnEscape);
});

onBeforeUnmount(() => {
    stopResizing();
    window.removeEventListener('click', closeFolderContextMenu);
    window.removeEventListener('keydown', closeFolderContextMenuOnEscape);
});

watch(
    () => props.currentFolder?.id,
    () => {
        expandAncestorsFromCurrentFolder();
    },
);

const uploadAccept = computed(() => {
    const extensions = props.allowedExtensions?.length
        ? props.allowedExtensions
        : ['docx', 'pdf', 'jpg', 'jpeg', 'png', 'webp'];

    return extensions.map((extension) => `.${extension}`).join(',');
});

const allowedExtensionsSet = computed(() => {
    const extensions = props.allowedExtensions?.length
        ? props.allowedExtensions
        : ['docx', 'pdf', 'jpg', 'jpeg', 'png', 'webp'];

    return new Set(extensions.map((e) => e.toLowerCase()));
});

const canUploadCurrentFolder = computed(() =>
    Boolean(props.currentFolder?.can_upload),
);
const canCreateFolder = computed(() =>
    Boolean(props.currentFolder?.can_create_folder),
);
const canRenameCurrentFolder = computed(() =>
    Boolean(props.currentFolder?.can_rename),
);
const canMoveCurrentFolder = computed(() =>
    Boolean(props.currentFolder?.can_move),
);
const canDeleteCurrentFolder = computed(() =>
    Boolean(props.currentFolder?.can_delete),
);

const isIndividualProjectsFolder = (folder: any) => {
    const normalizedName = String(folder?.name ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toUpperCase();

    return (
        normalizedName.includes('PROYECTOS INDIVIDUALES') ||
        normalizedName.includes('PROY IND')
    );
};

const folderColorOptions = [
    {
        key: 'yellow',
        label: 'Amarillo',
        card: 'border-gray-200 hover:border-blue-200 hover:bg-blue-50',
        icon: 'text-yellow-500',
        swatch: 'bg-yellow-400',
    },
    {
        key: 'blue',
        label: 'Azul',
        card: 'border-blue-200 bg-blue-50 hover:border-blue-300 hover:bg-blue-100',
        icon: 'text-blue-600',
        swatch: 'bg-blue-500',
    },
    {
        key: 'green',
        label: 'Verde',
        card: 'border-emerald-200 bg-emerald-50 hover:border-emerald-300 hover:bg-emerald-100',
        icon: 'text-emerald-600',
        swatch: 'bg-emerald-500',
    },
    {
        key: 'purple',
        label: 'Morado',
        card: 'border-purple-200 bg-purple-50 hover:border-purple-300 hover:bg-purple-100',
        icon: 'text-purple-600',
        swatch: 'bg-purple-500',
    },
    {
        key: 'red',
        label: 'Rojo',
        card: 'border-red-200 bg-red-50 hover:border-red-300 hover:bg-red-100',
        icon: 'text-red-600',
        swatch: 'bg-red-500',
    },
    {
        key: 'gray',
        label: 'Gris',
        card: 'border-slate-200 bg-slate-50 hover:border-slate-300 hover:bg-slate-100',
        icon: 'text-slate-500',
        swatch: 'bg-slate-500',
    },
];

const folderIconOptions = [
    { key: 'folder', label: 'Carpeta', component: Folder },
    { key: 'book', label: 'Libro', component: BookOpen },
    { key: 'file', label: 'Archivo', component: FileIcon },
    { key: 'calendar', label: 'Calendario', component: Calendar },
    { key: 'users', label: 'Grupo', component: Users },
    { key: 'checklist', label: 'Lista', component: ListChecks },
];

const folderColorOption = (folder: any) => {
    const fallback = isIndividualProjectsFolder(folder) ? 'green' : 'yellow';
    const key = folder?.color_key || fallback;

    return (
        folderColorOptions.find((option) => option.key === key) ||
        folderColorOptions[0]
    );
};

const folderCardClasses = (folder: any) => folderColorOption(folder).card;
const folderIconClasses = (folder: any) => folderColorOption(folder).icon;
const folderIconComponent = (folder: any) =>
    folderIconOptions.find(
        (option) => option.key === (folder?.icon_key || 'folder'),
    )?.component || Folder;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Gestor de Archivos',
        href: '/files/manager',
    },
    ...((props.currentFolder?.ancestors ?? []).map((ancestor) => ({
        title: ancestor.name,
        href: ancestor.can_view
            ? ancestor.readable_url || `/files/folders/${ancestor.id}`
            : undefined,
    })) as BreadcrumbItem[]),
    ...(props.currentFolder
        ? [
              {
                  title: props.currentFolder.name,
                  href:
                      props.currentFolder.readable_url ||
                      `/files/folders/${props.currentFolder.id}`,
              },
          ]
        : []),
];

const formatSize = (bytes: number) => {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatOptionalSize = (bytes?: number | null) => formatSize(bytes || 0);

const shortHash = (value?: string | null) => {
    if (!value) return '';

    return value.slice(0, 12);
};

const getStatusColor = (status: string | null) => {
    switch (status) {
        case 'OFFICE_APPROVED':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'FINAL_APPROVED':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'REJECTED':
            return 'bg-red-100 text-red-800 border-red-200';
        case 'DRAFT':
            return 'bg-gray-100 text-gray-800 border-gray-200';
        case 'SUBMITTED':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'NA':
            return 'bg-slate-100 text-slate-700 border-slate-200';
        default:
            return 'bg-gray-50 text-gray-500 border-gray-200';
    }
};

const statusLabel = (status: string | null) => {
    switch (status) {
        case 'OFFICE_APPROVED':
            return 'OFICINA';
        case 'FINAL_APPROVED':
            return 'FINAL';
        default:
            return status || 'Sin estado';
    }
};

const fileInput = ref<HTMLInputElement | null>(null);
const replaceFileInput = ref<HTMLInputElement | null>(null);
const fileToReplace = ref<number | null>(null);
const previewFile = ref<any | null>(null);
const historyFile = ref<any | null>(null);
const historyEntries = ref<FileHistoryEntry[]>([]);
const historyLoading = ref(false);
const historyError = ref('');

const uploadForm = useForm({
    file: null as File | null,
});

const replaceForm = useForm({
    file: null as File | null,
});

const uploadError = ref('');
const uploadSuccess = ref('');
const copiedFolderPath = ref(false);

const copyCurrentFolderPath = async () => {
    if (!props.currentFolder) {
        return;
    }

    const path =
        props.currentFolder.readable_url ||
        `/files/folders/${props.currentFolder.id}`;

    try {
        await navigator.clipboard.writeText(path);
        copiedFolderPath.value = true;
        window.setTimeout(() => {
            copiedFolderPath.value = false;
        }, 1800);
    } catch {
        uploadError.value = 'No se pudo copiar la ruta de la carpeta.';
    }
};

const uploadSingleFile = (file: File): Promise<void> => {
    return new Promise((resolve, reject) => {
        if (!props.currentFolder) {
            reject(new Error('No hay carpeta seleccionada.'));
            return;
        }

        uploadForm.file = file;
        uploadForm.post(`/files/folders/${props.currentFolder.id}/upload`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.reset();
                resolve();
            },
            onError: (errors: any) => {
                uploadForm.reset();
                reject(new Error(errors.file || 'Error al subir archivo.'));
            },
        });
    });
};

const triggerUpload = () => {
    if (!canUploadCurrentFolder.value) {
        return;
    }

    fileInput.value?.click();
};

const handleFileSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    uploadError.value = '';
    uploadSuccess.value = '';
    if (target.files && target.files.length > 0 && props.currentFolder) {
        uploadSingleFile(target.files[0])
            .then(() => {
                target.value = '';
                uploadSuccess.value = 'Archivo subido correctamente.';
                setTimeout(() => {
                    uploadSuccess.value = '';
                }, 3000);
                router.reload({ preserveScroll: true });
            })
            .catch((err: Error) => {
                target.value = '';
                uploadError.value = err.message;
            });
    }
};

const triggerReplace = (fileId: number) => {
    fileToReplace.value = fileId;
    replaceFileInput.value?.click();
};

const handleReplaceSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    uploadError.value = '';
    uploadSuccess.value = '';
    if (
        target.files &&
        target.files.length > 0 &&
        fileToReplace.value !== null
    ) {
        replaceForm.file = target.files[0];
        replaceForm.post(`/files/${fileToReplace.value}/replace`, {
            preserveScroll: true,
            onSuccess: () => {
                target.value = '';
                fileToReplace.value = null;
                replaceForm.reset();
                uploadSuccess.value = 'Archivo reemplazado correctamente.';
                setTimeout(() => {
                    uploadSuccess.value = '';
                }, 3000);
            },
            onError: (errors: any) => {
                target.value = '';
                fileToReplace.value = null;
                replaceForm.reset();
                uploadError.value =
                    errors.file || 'Error al reemplazar archivo.';
            },
        });
    }
};

const openPreview = (file: any) => {
    previewFile.value = file;
};

const closePreview = () => {
    previewFile.value = null;
};

const openChangeHistoryModal = async (file: any) => {
    historyFile.value = file;
    historyEntries.value = [];
    historyError.value = '';
    historyLoading.value = true;
    activeModal.value = 'changeHistory';

    try {
        const response = await fetch(`/files/${file.id}/history`, {
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('No se pudo cargar el registro de cambios.');
        }

        const payload = await response.json();
        historyEntries.value = payload.history || [];
    } catch (error: any) {
        historyError.value =
            error?.message || 'No se pudo cargar el registro de cambios.';
    } finally {
        historyLoading.value = false;
    }
};

// ---- External Drag-and-Drop Upload ----

const isExternalDragOver = ref(false);
const isUploadingDroppedFiles = ref(false);
const dropUploadProgress = ref({ total: 0, completed: 0 });
let externalDragCounter = 0;

const isExternalFileDrag = (event: DragEvent): boolean => {
    return !!event.dataTransfer?.types?.includes('Files');
};

const onContentDragEnter = (event: DragEvent) => {
    if (!props.currentFolder || !canUploadCurrentFolder.value) return;
    if (dragType.value) return;
    if (!isExternalFileDrag(event)) return;

    event.preventDefault();
    externalDragCounter++;
    isExternalDragOver.value = true;
};

const onContentDragOver = (event: DragEvent) => {
    if (!props.currentFolder || !canUploadCurrentFolder.value) return;
    if (dragType.value) return;
    if (!isExternalFileDrag(event)) return;

    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy';
    }
};

const onContentDragLeave = (event: DragEvent) => {
    if (!isExternalDragOver.value) return;
    event.preventDefault();
    externalDragCounter--;
    if (externalDragCounter <= 0) {
        externalDragCounter = 0;
        isExternalDragOver.value = false;
    }
};

const onContentDrop = async (event: DragEvent) => {
    event.preventDefault();
    isExternalDragOver.value = false;
    externalDragCounter = 0;

    if (!props.currentFolder || !canUploadCurrentFolder.value) {
        uploadError.value =
            'No tienes permiso para subir archivos en esta carpeta.';
        setTimeout(() => {
            uploadError.value = '';
        }, 4000);
        return;
    }

    if (dragType.value) return;

    const files = event.dataTransfer?.files;
    if (!files || files.length === 0) return;

    const validFiles: File[] = [];
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        if (file.size === 0) continue;

        const ext = file.name.includes('.')
            ? file.name.split('.').pop()!.toLowerCase()
            : '';
        if (ext && allowedExtensionsSet.value.has(ext)) {
            validFiles.push(file);
        }
    }

    if (validFiles.length === 0) {
        uploadError.value =
            'Formato no permitido. Formatos validos: ' +
            [...allowedExtensionsSet.value].join(', ');
        setTimeout(() => {
            uploadError.value = '';
        }, 4000);
        return;
    }

    isUploadingDroppedFiles.value = true;
    dropUploadProgress.value = { total: validFiles.length, completed: 0 };

    let succeeded = 0;
    let lastError = '';

    for (const file of validFiles) {
        try {
            await uploadSingleFile(file);
            succeeded++;
            dropUploadProgress.value.completed++;
        } catch (err: any) {
            lastError = err.message;
            dropUploadProgress.value.completed++;
        }
    }

    isUploadingDroppedFiles.value = false;

    if (succeeded === validFiles.length) {
        if (succeeded === 1) {
            uploadSuccess.value = 'Archivo subido correctamente.';
        } else {
            uploadSuccess.value = `${succeeded} archivos subidos correctamente.`;
        }
    } else if (succeeded > 0) {
        uploadSuccess.value = `Se subieron ${succeeded} de ${validFiles.length} archivos. Revisa los errores.`;
        if (lastError) {
            uploadError.value = lastError;
        }
    } else {
        uploadError.value = lastError || 'Error al subir archivos.';
    }

    setTimeout(() => {
        uploadSuccess.value = '';
        uploadError.value = '';
    }, 4000);

    router.reload({ preserveScroll: true });
};

// ---- Folder Management Modals ----

type ModalType =
    | 'createFolder'
    | 'editFolder'
    | 'moveFolder'
    | 'deleteFolder'
    | 'moveFile'
    | 'changeHistory'
    | null;

const activeModal = ref<ModalType>(null);
const processingAction = ref(false);
const modalError = ref('');

const createFolderForm = useForm({ name: '' });
const editFolderForm = useForm({
    name: '',
    icon_key: 'folder',
    color_key: 'yellow',
});
const moveFolderForm = useForm({ target_folder_id: '' });
const moveFileForm = useForm({ target_folder_id: '' });
const moveTargetId = ref('');

const selectedFolder = ref<any>(null);
const selectedFile = ref<any>(null);

const openCreateFolderModal = () => {
    createFolderForm.reset();
    modalError.value = '';
    activeModal.value = 'createFolder';
};

const openEditFolderModal = (folder?: any) => {
    const target = folder || props.currentFolder;
    if (!target || !target.can_rename) return;
    selectedFolder.value = target;
    editFolderForm.name = target.name;
    editFolderForm.icon_key = target.icon_key || 'folder';
    editFolderForm.color_key =
        target.color_key ||
        (isIndividualProjectsFolder(target) ? 'green' : 'yellow');
    modalError.value = '';
    activeModal.value = 'editFolder';
};

const openMoveFolderModal = (folder?: any) => {
    const target = folder || props.currentFolder;
    if (!target || !target.can_move) return;
    selectedFolder.value = target;
    moveTargetId.value = '';
    modalError.value = '';
    activeModal.value = 'moveFolder';
};

const openDeleteFolderModal = (folder?: any) => {
    const target = folder || props.currentFolder;
    if (!target || !target.can_delete) return;
    selectedFolder.value = target;
    modalError.value = '';
    activeModal.value = 'deleteFolder';
};

const openMoveFileModal = (file: any) => {
    if (!file.can_move) return;
    selectedFile.value = file;
    moveTargetId.value = '';
    modalError.value = '';
    activeModal.value = 'moveFile';
};

const closeModal = () => {
    activeModal.value = null;
    selectedFolder.value = null;
    selectedFile.value = null;
    historyFile.value = null;
    historyEntries.value = [];
    historyError.value = '';
    historyLoading.value = false;
    modalError.value = '';
    processingAction.value = false;
};

const submitCreateFolder = () => {
    if (!props.currentFolder) return;
    processingAction.value = true;
    createFolderForm.post(`/files/folders/${props.currentFolder.id}/folders`, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            router.reload({ preserveScroll: true });
        },
        onError: (errors: any) => {
            modalError.value = errors.name || 'Error al crear carpeta.';
            processingAction.value = false;
        },
        onFinish: () => {
            processingAction.value = false;
        },
    });
};

const submitEditFolder = () => {
    if (!selectedFolder.value) return;
    processingAction.value = true;
    editFolderForm.patch(`/files/folders/${selectedFolder.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            router.reload({ preserveScroll: true });
        },
        onError: (errors: any) => {
            modalError.value =
                errors.name ||
                errors.icon_key ||
                errors.color_key ||
                'Error al editar carpeta.';
            processingAction.value = false;
        },
        onFinish: () => {
            processingAction.value = false;
        },
    });
};

const submitMoveFolder = () => {
    if (!selectedFolder.value) return;
    processingAction.value = true;
    moveFolderForm.target_folder_id = moveTargetId.value;
    moveFolderForm.patch(`/files/folders/${selectedFolder.value.id}/move`, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            router.reload({ preserveScroll: true });
        },
        onError: (errors: any) => {
            modalError.value =
                errors.target_folder_id || 'Error al mover carpeta.';
            processingAction.value = false;
        },
        onFinish: () => {
            processingAction.value = false;
        },
    });
};

const submitMoveFile = () => {
    if (!selectedFile.value) return;
    processingAction.value = true;
    moveFileForm.target_folder_id = moveTargetId.value;
    moveFileForm.patch(`/files/${selectedFile.value.id}/move`, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            router.reload({ preserveScroll: true });
        },
        onError: (errors: any) => {
            modalError.value =
                errors.target_folder_id || 'Error al mover archivo.';
            processingAction.value = false;
        },
        onFinish: () => {
            processingAction.value = false;
        },
    });
};

const submitDeleteFolder = () => {
    if (!selectedFolder.value) return;
    processingAction.value = true;
    router.delete(`/files/folders/${selectedFolder.value.id}`, {
        preserveScroll: false,
        onSuccess: () => {
            closeModal();
        },
        onError: (errors: any) => {
            modalError.value = errors.folder || 'Error al eliminar carpeta.';
            processingAction.value = false;
        },
        onFinish: () => {
            processingAction.value = false;
        },
    });
};

const moveTargets = computed(() => {
    const targets: Array<{
        id: number | string;
        name: string;
        depth: number;
        disabled: boolean;
    }> = [];
    const excludeIds = new Set<number>();

    if (activeModal.value === 'moveFolder' && selectedFolder.value) {
        excludeIds.add(Number(selectedFolder.value.id));
        collectDescendantIds(props.folderTree, excludeIds);
    }

    function collectDescendantIds(nodes: any[], ids: Set<number>) {
        for (const node of nodes) {
            if (node.is_virtual) {
                collectDescendantIds(node.children || [], ids);
                continue;
            }
            ids.add(Number(node.id));
        }
    }

    function walk(nodes: any[], depth: number) {
        for (const node of nodes) {
            if (node.is_virtual) {
                walk(node.children || [], depth);
                continue;
            }
            targets.push({
                id: node.id,
                name: node.name,
                depth,
                disabled: excludeIds.has(Number(node.id)),
            });
            walk(node.children || [], depth + 1);
        }
    }

    walk(props.folderTree, 0);
    return targets;
});

// Internal drag-and-drop for move
const dragItem = ref<any>(null);
const dragType = ref<'file' | 'folder' | null>(null);
const folderContextMenu = ref<{
    visible: boolean;
    x: number;
    y: number;
    folder: any | null;
}>({
    visible: false,
    x: 0,
    y: 0,
    folder: null,
});

const onDragStart = (event: DragEvent, item: any, type: 'file' | 'folder') => {
    dragItem.value = item;
    dragType.value = type;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(item.id));
    }
};

const onDropOnFolder = (targetFolderId: number) => {
    if (dragItem.value) {
        // Internal move - handled by the move logic
    } else {
        return;
    }

    if (dragType.value === 'file') {
        if (!dragItem.value.can_move) return;
        moveTargetId.value = String(targetFolderId);
        moveFileForm.target_folder_id = String(targetFolderId);
        selectedFile.value = dragItem.value;
        processingAction.value = true;
        moveFileForm.patch(`/files/${dragItem.value.id}/move`, {
            preserveScroll: true,
            onSuccess: () => {
                dragItem.value = null;
                dragType.value = null;
                selectedFile.value = null;
                moveTargetId.value = '';
                router.reload({ preserveScroll: true });
            },
            onError: (errors: any) => {
                uploadError.value =
                    errors.target_folder_id || 'Error al mover archivo.';
                dragItem.value = null;
                dragType.value = null;
                moveTargetId.value = '';
            },
            onFinish: () => {
                processingAction.value = false;
            },
        });
    } else if (dragType.value === 'folder') {
        if (!dragItem.value.can_move) return;
        moveTargetId.value = String(targetFolderId);
        moveFolderForm.target_folder_id = String(targetFolderId);
        selectedFolder.value = dragItem.value;
        processingAction.value = true;
        moveFolderForm.patch(`/files/folders/${dragItem.value.id}/move`, {
            preserveScroll: true,
            onSuccess: () => {
                dragItem.value = null;
                dragType.value = null;
                selectedFolder.value = null;
                moveTargetId.value = '';
                router.reload({ preserveScroll: true });
            },
            onError: (errors: any) => {
                uploadError.value =
                    errors.target_folder_id || 'Error al mover carpeta.';
                dragItem.value = null;
                dragType.value = null;
                moveTargetId.value = '';
            },
            onFinish: () => {
                processingAction.value = false;
            },
        });
    }

    dragItem.value = null;
    dragType.value = null;
};

const openFolderContextMenu = (event: MouseEvent, folder: any) => {
    if (!folder.can_rename && !folder.can_move && !folder.can_delete) {
        return;
    }

    folderContextMenu.value = {
        visible: true,
        x: event.clientX,
        y: event.clientY,
        folder,
    };
};

const closeFolderContextMenu = () => {
    folderContextMenu.value.visible = false;
};

const closeFolderContextMenuOnEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        closeFolderContextMenu();
    }
};

const onFolderAction = (action: string, folder: any) => {
    closeFolderContextMenu();

    switch (action) {
        case 'edit':
        case 'rename':
            openEditFolderModal(folder);
            break;
        case 'move':
            openMoveFolderModal(folder);
            break;
        case 'delete':
            openDeleteFolderModal(folder);
            break;
    }
};
</script>

<template>
    <Head title="Gestor de Archivos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            ref="containerRef"
            class="flex h-[calc(100vh-4rem)] overflow-hidden"
        >
            <div
                v-if="folderContextMenu.visible && folderContextMenu.folder"
                class="fixed z-50 min-w-40 rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
                :style="{
                    left: `${folderContextMenu.x}px`,
                    top: `${folderContextMenu.y}px`,
                }"
                @click.stop
            >
                <button
                    v-if="folderContextMenu.folder.can_rename"
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                    @click="onFolderAction('edit', folderContextMenu.folder)"
                >
                    <Pencil class="h-4 w-4" />
                    Editar
                </button>
                <button
                    v-if="folderContextMenu.folder.can_move"
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                    @click="onFolderAction('move', folderContextMenu.folder)"
                >
                    <ArrowRightLeft class="h-4 w-4" />
                    Mover
                </button>
                <button
                    v-if="folderContextMenu.folder.can_delete"
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50"
                    @click="onFolderAction('delete', folderContextMenu.folder)"
                >
                    <Trash2 class="h-4 w-4" />
                    Eliminar
                </button>
            </div>

            <div
                class="shrink-0 overflow-y-auto border-r border-gray-200 bg-gray-50 p-4"
                :style="{ width: `${leftPanelWidth}%` }"
            >
                <h3 class="mb-4 px-2 font-semibold text-gray-700">Carpetas</h3>
                <FolderTree
                    v-for="root in folderTree"
                    :key="root.id"
                    :node="root"
                    :expanded-state="expandedFolders"
                    :active-folder-id="currentFolder?.id ?? null"
                    :has-internal-drag="!!dragType"
                    @toggle-folder="toggleFolderExpanded"
                    @drop-on-folder="onDropOnFolder"
                />
            </div>

            <div
                class="group relative w-1 shrink-0 cursor-col-resize bg-gray-200 transition-colors hover:bg-blue-300"
                :class="{ 'bg-blue-400': isResizing }"
                @mousedown.prevent="startResizing"
            >
                <div
                    class="absolute inset-y-0 left-1/2 w-[2px] -translate-x-1/2 bg-transparent group-hover:bg-blue-500"
                ></div>
            </div>

            <div
                class="relative flex min-w-0 flex-1 flex-col overflow-hidden bg-white"
                @dragenter="onContentDragEnter"
                @dragover="onContentDragOver"
                @dragleave="onContentDragLeave"
                @drop="onContentDrop"
            >
                <!-- External file drop overlay -->
                <div
                    v-if="isExternalDragOver"
                    class="absolute inset-0 z-30 flex items-center justify-center border-4 border-dashed border-blue-400 bg-blue-50/80"
                >
                    <div class="text-center">
                        <Upload class="mx-auto mb-2 h-10 w-10 text-blue-500" />
                        <p class="text-lg font-semibold text-blue-700">
                            Suelta archivos para subirlos a esta carpeta
                        </p>
                        <p
                            v-if="!canUploadCurrentFolder"
                            class="mt-1 text-sm text-red-600"
                        >
                            No tienes permiso para subir archivos en esta
                            carpeta.
                        </p>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between border-b border-gray-200 p-4"
                >
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">
                            {{
                                currentFolder
                                    ? currentFolder.name
                                    : 'Selecciona una carpeta'
                            }}
                        </h2>
                        <span
                            v-if="currentFolder?.display_path"
                            class="block max-w-[52rem] truncate text-xs text-gray-500"
                            :title="currentFolder.display_path"
                        >
                            {{ currentFolder.display_path }}
                        </span>
                        <span v-if="semesterName" class="text-xs text-gray-500">
                            Semestre: <b>{{ semesterName }}</b>
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div
                            v-if="
                                uploadForm.processing ||
                                processingAction ||
                                isUploadingDroppedFiles
                            "
                            class="flex items-center gap-2 text-sm text-blue-600"
                        >
                            <svg
                                class="h-4 w-4 animate-spin"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                    fill="none"
                                />
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                />
                            </svg>
                            <template v-if="isUploadingDroppedFiles"
                                >Subiendo {{ dropUploadProgress.completed }}/{{
                                    dropUploadProgress.total
                                }}...</template
                            >
                            <template v-else>Procesando...</template>
                        </div>
                        <template v-if="currentFolder">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                title="Copiar ruta legible de la carpeta"
                                @click="copyCurrentFolderPath"
                            >
                                <component
                                    :is="copiedFolderPath ? Check : Copy"
                                    class="mr-1.5 h-4 w-4"
                                />
                                {{
                                    copiedFolderPath ? 'Copiada' : 'Copiar ruta'
                                }}
                            </button>
                            <button
                                v-if="canCreateFolder"
                                type="button"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                title="Nueva carpeta"
                                @click="openCreateFolderModal"
                            >
                                <FolderPlus class="mr-1.5 h-4 w-4" />
                                Carpeta
                            </button>
                            <button
                                v-if="canRenameCurrentFolder"
                                type="button"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                title="Editar carpeta"
                                @click="openEditFolderModal()"
                            >
                                <Pencil class="mr-1.5 h-4 w-4" />
                                Editar
                            </button>
                            <button
                                v-if="canMoveCurrentFolder"
                                type="button"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                title="Mover carpeta"
                                @click="openMoveFolderModal()"
                            >
                                <ArrowRightLeft class="mr-1.5 h-4 w-4" />
                                Mover
                            </button>
                            <button
                                v-if="canDeleteCurrentFolder"
                                type="button"
                                class="inline-flex items-center rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100"
                                title="Eliminar carpeta"
                                @click="openDeleteFolderModal()"
                            >
                                <Trash2 class="mr-1.5 h-4 w-4" />
                                Eliminar
                            </button>
                        </template>
                        <button
                            v-if="
                                currentFolder &&
                                canUploadCurrentFolder &&
                                !uploadForm.processing &&
                                !isUploadingDroppedFiles
                            "
                            type="button"
                            @click="triggerUpload"
                            class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase ring-blue-300 transition duration-150 ease-in-out hover:bg-blue-700 focus:border-blue-900 focus:ring focus:outline-none active:bg-blue-900 disabled:opacity-25"
                        >
                            <Upload class="mr-2 h-4 w-4" />
                            Subir Archivo
                        </button>
                        <input
                            ref="fileInput"
                            type="file"
                            class="hidden"
                            :accept="uploadAccept"
                            @change="handleFileSelected"
                        />
                        <input
                            ref="replaceFileInput"
                            type="file"
                            class="hidden"
                            :accept="uploadAccept"
                            @change="handleReplaceSelected"
                        />
                    </div>
                </div>

                <div
                    v-if="uploadError"
                    class="mx-4 mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700"
                >
                    {{ uploadError }}
                </div>
                <div
                    v-if="uploadSuccess"
                    class="mx-4 mt-3 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700"
                >
                    {{ uploadSuccess }}
                </div>

                <div class="flex-1 overflow-y-auto p-6">
                    <div
                        v-if="!currentFolder"
                        class="flex h-full flex-col items-center justify-center text-gray-400"
                    >
                        <Folder class="mb-4 h-16 w-16 text-gray-300" />
                        <p>
                            Selecciona una carpeta del arbol para ver su
                            contenido
                        </p>
                    </div>

                    <div v-else>
                        <div v-if="contents.folders.length > 0" class="mb-8">
                            <h4
                                class="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase"
                            >
                                Subcarpetas
                            </h4>
                            <div
                                class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                            >
                                <div
                                    v-for="folder in contents.folders"
                                    :key="folder.id"
                                    class="group/folder relative flex items-center gap-3 rounded-lg border p-3 transition-colors"
                                    :class="folderCardClasses(folder)"
                                    draggable="true"
                                    @dragstart="
                                        onDragStart($event, folder, 'folder')
                                    "
                                    @contextmenu.prevent="
                                        openFolderContextMenu($event, folder)
                                    "
                                >
                                    <Link
                                        :href="
                                            folder.readable_url ||
                                            `/files/folders/${folder.id}`
                                        "
                                        class="flex flex-1 items-center gap-3 text-gray-700 hover:text-blue-600"
                                    >
                                        <component
                                            :is="folderIconComponent(folder)"
                                            class="h-8 w-8"
                                            :class="folderIconClasses(folder)"
                                        />
                                        <span class="truncate font-medium">{{
                                            folder.name
                                        }}</span>
                                    </Link>
                                    <div
                                        v-if="
                                            folder.can_rename ||
                                            folder.can_move ||
                                            folder.can_delete
                                        "
                                        class="flex items-center gap-1 opacity-0 transition-opacity group-hover/folder:opacity-100"
                                    >
                                        <button
                                            v-if="folder.can_rename"
                                            type="button"
                                            class="p-1 text-gray-400 hover:text-blue-600"
                                            title="Editar"
                                            @click.prevent="
                                                onFolderAction('edit', folder)
                                            "
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            v-if="folder.can_move"
                                            type="button"
                                            class="p-1 text-gray-400 hover:text-blue-600"
                                            title="Mover"
                                            @click.prevent="
                                                onFolderAction('move', folder)
                                            "
                                        >
                                            <ArrowRightLeft
                                                class="h-3.5 w-3.5"
                                            />
                                        </button>
                                        <button
                                            v-if="folder.can_delete"
                                            type="button"
                                            class="p-1 text-gray-400 hover:text-red-600"
                                            title="Eliminar"
                                            @click.prevent="
                                                onFolderAction('delete', folder)
                                            "
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="contents.files.length > 0">
                            <h4
                                class="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase"
                            >
                                Archivos
                            </h4>
                            <div
                                class="overflow-x-auto rounded-lg border border-gray-200 bg-white"
                            >
                                <table
                                    class="min-w-full divide-y divide-gray-200"
                                >
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                            >
                                                Nombre
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                            >
                                                Estado
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                            >
                                                Tamaño
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                            >
                                                Subido por
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                            >
                                                Fecha
                                            </th>
                                            <th
                                                class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase"
                                            >
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-gray-200 bg-white"
                                    >
                                        <tr
                                            v-for="file in contents.files"
                                            :key="file.id"
                                            class="hover:bg-gray-50"
                                            :draggable="file.can_move"
                                            @dragstart="
                                                file.can_move
                                                    ? onDragStart(
                                                          $event,
                                                          file,
                                                          'file',
                                                      )
                                                    : undefined
                                            "
                                        >
                                            <td
                                                class="px-6 py-4 whitespace-nowrap"
                                            >
                                                <div class="flex items-center">
                                                    <FileText
                                                        class="mr-3 h-5 w-5 text-gray-400"
                                                    />
                                                    <div class="min-w-0">
                                                        <span
                                                            class="block truncate text-sm font-medium text-gray-900"
                                                            >{{
                                                                file.name
                                                            }}</span
                                                        >
                                                        <span
                                                            v-if="
                                                                file.linked_from
                                                            "
                                                            class="mt-1 inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-indigo-700 uppercase"
                                                        >
                                                            Reutilizado de
                                                            {{
                                                                file.linked_from
                                                            }}
                                                        </span>
                                                        <span
                                                            v-if="
                                                                file.folder_path
                                                            "
                                                            class="mt-1 block max-w-[22rem] truncate text-xs text-gray-500"
                                                            :title="
                                                                file.folder_path
                                                            "
                                                        >
                                                            {{
                                                                file.folder_path
                                                            }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap"
                                            >
                                                <div
                                                    class="flex flex-wrap items-center gap-2"
                                                >
                                                    <span
                                                        v-if="file.status"
                                                        :class="[
                                                            'inline-flex rounded-full border px-2 py-1 text-xs leading-5 font-semibold',
                                                            getStatusColor(
                                                                file.status,
                                                            ),
                                                        ]"
                                                    >
                                                        {{
                                                            statusLabel(
                                                                file.status,
                                                            )
                                                        }}
                                                    </span>
                                                    <span
                                                        v-if="!file.status"
                                                        class="text-xs text-gray-400 italic"
                                                    >
                                                        Sin estado
                                                    </span>
                                                    <span
                                                        v-if="file.is_late"
                                                        class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-700"
                                                    >
                                                        EXT
                                                    </span>
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-sm whitespace-nowrap text-gray-500"
                                            >
                                                {{ formatSize(file.size) }}
                                            </td>
                                            <td
                                                class="px-6 py-4 text-sm whitespace-nowrap text-gray-500"
                                            >
                                                {{ file.uploaded_by }}
                                            </td>
                                            <td
                                                class="px-6 py-4 text-sm whitespace-nowrap text-gray-500"
                                            >
                                                {{ file.uploaded_at }}
                                            </td>
                                            <td
                                                class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                            >
                                                <div
                                                    class="flex justify-end gap-2"
                                                >
                                                    <Link
                                                        v-if="
                                                            file.is_docx &&
                                                            (file.onlyoffice_editor_url ||
                                                                file.docx_editor_url)
                                                        "
                                                        :href="
                                                            file.onlyoffice_editor_url ||
                                                            file.docx_editor_url
                                                        "
                                                        class="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                                                        :title="
                                                            file.can_edit_docx
                                                                ? 'Abrir editor DOCX'
                                                                : 'Ver documento DOCX'
                                                        "
                                                    >
                                                        {{
                                                            file.can_edit_docx
                                                                ? file.onlyoffice_editor_url
                                                                    ? 'Editar Word'
                                                                    : 'Editar DOCX'
                                                                : 'Ver DOCX'
                                                        }}
                                                    </Link>
                                                    <button
                                                        v-else-if="
                                                            file.can_preview
                                                        "
                                                        type="button"
                                                        class="p-1 text-slate-600 hover:text-slate-900"
                                                        title="Ver en la pagina"
                                                        @click="
                                                            openPreview(file)
                                                        "
                                                    >
                                                        <Eye class="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="p-1 text-slate-600 hover:text-slate-900"
                                                        title="Registro de cambios"
                                                        @click="
                                                            openChangeHistoryModal(
                                                                file,
                                                            )
                                                        "
                                                    >
                                                        <History
                                                            class="h-4 w-4"
                                                        />
                                                    </button>
                                                    <button
                                                        v-if="file.can_replace"
                                                        type="button"
                                                        class="p-1 text-amber-600 hover:text-amber-900"
                                                        title="Reemplazar"
                                                        @click="
                                                            triggerReplace(
                                                                file.id,
                                                            )
                                                        "
                                                    >
                                                        <RefreshCw
                                                            class="h-4 w-4"
                                                        />
                                                    </button>
                                                    <button
                                                        v-if="file.can_move"
                                                        type="button"
                                                        class="p-1 text-blue-600 hover:text-blue-900"
                                                        title="Mover archivo"
                                                        @click="
                                                            openMoveFileModal(
                                                                file,
                                                            )
                                                        "
                                                    >
                                                        <Move class="h-4 w-4" />
                                                    </button>
                                                    <a
                                                        :href="
                                                            file.download_url
                                                        "
                                                        class="p-1 text-blue-600 hover:text-blue-900"
                                                        title="Descargar"
                                                    >
                                                        <Download
                                                            class="h-4 w-4"
                                                        />
                                                    </a>
                                                    <button
                                                        v-if="file.can_delete"
                                                        type="button"
                                                        class="p-1 text-red-600 hover:text-red-900"
                                                        title="Eliminar"
                                                        @click="
                                                            router.delete(
                                                                `/files/${file.id}`,
                                                            )
                                                        "
                                                    >
                                                        <Trash2
                                                            class="h-4 w-4"
                                                        />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div
                            v-if="
                                contents.folders.length === 0 &&
                                contents.files.length === 0
                            "
                            class="py-12 text-center"
                        >
                            <p class="text-gray-500">
                                Esta carpeta esta vacia.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Folder Modal -->
        <div
            v-if="activeModal === 'createFolder'"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-4"
            @click.self="closeModal"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">
                    Nueva Carpeta
                </h3>
                <div
                    v-if="modalError"
                    class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                >
                    {{ modalError }}
                </div>
                <form @submit.prevent="submitCreateFolder">
                    <input
                        v-model="createFolderForm.name"
                        type="text"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                        placeholder="Nombre de la carpeta"
                        autofocus
                    />
                    <div class="mt-4 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            @click="closeModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="processingAction"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            Crear
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Folder Modal -->
        <div
            v-if="activeModal === 'editFolder'"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-4"
            @click.self="closeModal"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">
                    Editar carpeta
                </h3>
                <div
                    v-if="modalError"
                    class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                >
                    {{ modalError }}
                </div>
                <form class="space-y-5" @submit.prevent="submitEditFolder">
                    <label class="block">
                        <span
                            class="mb-1 block text-sm font-medium text-slate-700"
                        >
                            Nombre
                        </span>
                        <input
                            v-model="editFolderForm.name"
                            type="text"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                            autofocus
                        />
                    </label>

                    <div>
                        <span
                            class="mb-2 block text-sm font-medium text-slate-700"
                        >
                            Color
                        </span>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="option in folderColorOptions"
                                :key="option.key"
                                type="button"
                                class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium"
                                :class="
                                    editFolderForm.color_key === option.key
                                        ? 'border-blue-500 bg-blue-50 text-blue-800'
                                        : 'border-slate-200 text-slate-700 hover:bg-slate-50'
                                "
                                @click="editFolderForm.color_key = option.key"
                            >
                                <span
                                    class="h-4 w-4 rounded-full"
                                    :class="option.swatch"
                                ></span>
                                {{ option.label }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <span
                            class="mb-2 block text-sm font-medium text-slate-700"
                        >
                            Icono
                        </span>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="option in folderIconOptions"
                                :key="option.key"
                                type="button"
                                class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium"
                                :class="
                                    editFolderForm.icon_key === option.key
                                        ? 'border-blue-500 bg-blue-50 text-blue-800'
                                        : 'border-slate-200 text-slate-700 hover:bg-slate-50'
                                "
                                @click="editFolderForm.icon_key = option.key"
                            >
                                <component
                                    :is="option.component"
                                    class="h-4 w-4"
                                />
                                {{ option.label }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            @click="closeModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="processingAction"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Move Folder / Move File Modal -->
        <div
            v-if="activeModal === 'moveFolder' || activeModal === 'moveFile'"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-4"
            @click.self="closeModal"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">
                    {{
                        activeModal === 'moveFolder'
                            ? 'Mover Carpeta'
                            : 'Mover Archivo'
                    }}
                </h3>
                <div
                    v-if="modalError"
                    class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                >
                    {{ modalError }}
                </div>
                <form
                    @submit.prevent="
                        activeModal === 'moveFolder'
                            ? submitMoveFolder()
                            : submitMoveFile()
                    "
                >
                    <select
                        v-model="moveTargetId"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                        size="8"
                    >
                        <option value="" disabled>
                            Selecciona la carpeta destino...
                        </option>
                        <option
                            v-for="target in moveTargets"
                            :key="target.id"
                            :value="target.id"
                            :disabled="target.disabled"
                        >
                            {{ '\u00A0\u00A0'.repeat(target.depth)
                            }}{{ target.name }}
                        </option>
                    </select>
                    <div class="mt-4 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            @click="closeModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="processingAction"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            Mover
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Folder Confirmation Modal -->
        <div
            v-if="activeModal === 'deleteFolder'"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-4"
            @click.self="closeModal"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">
                    Eliminar Carpeta
                </h3>
                <div
                    v-if="modalError"
                    class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                >
                    {{ modalError }}
                </div>
                <div class="mb-4 flex items-start gap-3">
                    <AlertTriangle
                        class="mt-0.5 h-5 w-5 shrink-0 text-amber-500"
                    />
                    <p class="text-sm text-gray-600">
                        ¿Estas seguro de que deseas eliminar la carpeta
                        <b>{{ selectedFolder?.name }}</b
                        >? La carpeta debe estar vacia (sin subcarpetas ni
                        archivos).
                    </p>
                </div>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        @click="closeModal"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        :disabled="processingAction"
                        class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        @click="submitDeleteFolder"
                    >
                        Eliminar
                    </button>
                </div>
            </div>
        </div>

        <!-- Change History Modal -->
        <div
            v-if="activeModal === 'changeHistory'"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-4"
            @click.self="closeModal"
        >
            <div
                class="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
            >
                <div
                    class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4"
                >
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-slate-900">
                            Registro de cambios
                        </h3>
                        <p class="truncate text-sm text-slate-500">
                            {{ historyFile?.name || 'Archivo' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                        @click="closeModal"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4">
                    <div
                        v-if="historyLoading"
                        class="py-8 text-center text-sm text-slate-500"
                    >
                        Cargando registro...
                    </div>

                    <div
                        v-else-if="historyError"
                        class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                    >
                        {{ historyError }}
                    </div>

                    <div
                        v-else-if="historyEntries.length === 0"
                        class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                    >
                        Todavia no hay cambios registrados para este archivo.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="entry in historyEntries"
                            :key="entry.id"
                            class="rounded-lg border border-slate-200 bg-white px-4 py-3"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{ entry.label }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ entry.actor_name }}
                                        <span v-if="entry.actor_email">
                                            - {{ entry.actor_email }}
                                        </span>
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"
                                >
                                    {{ entry.at || 'Sin fecha' }}
                                </span>
                            </div>

                            <dl
                                class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-2"
                            >
                                <div v-if="entry.metadata.old_file_name">
                                    <dt class="font-medium text-slate-500">
                                        Archivo anterior
                                    </dt>
                                    <dd class="truncate text-slate-800">
                                        {{ entry.metadata.old_file_name }}
                                    </dd>
                                </div>
                                <div v-if="entry.metadata.new_file_name">
                                    <dt class="font-medium text-slate-500">
                                        Archivo nuevo
                                    </dt>
                                    <dd class="truncate text-slate-800">
                                        {{ entry.metadata.new_file_name }}
                                    </dd>
                                </div>
                                <div
                                    v-if="
                                        entry.metadata.old_size_bytes ||
                                        entry.metadata.new_size_bytes
                                    "
                                >
                                    <dt class="font-medium text-slate-500">
                                        Tamano
                                    </dt>
                                    <dd class="text-slate-800">
                                        <span
                                            v-if="
                                                entry.metadata.old_size_bytes
                                            "
                                        >
                                            {{
                                                formatOptionalSize(
                                                    entry.metadata
                                                        .old_size_bytes,
                                                )
                                            }}
                                            a
                                        </span>
                                        {{
                                            formatOptionalSize(
                                                entry.metadata.new_size_bytes ||
                                                    0,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div v-if="entry.metadata.editor_source">
                                    <dt class="font-medium text-slate-500">
                                        Origen
                                    </dt>
                                    <dd class="text-slate-800">
                                        {{ entry.metadata.editor_source }}
                                    </dd>
                                </div>
                                <div v-if="entry.metadata.old_file_hash">
                                    <dt class="font-medium text-slate-500">
                                        Hash anterior
                                    </dt>
                                    <dd class="font-mono text-slate-800">
                                        {{
                                            shortHash(
                                                entry.metadata.old_file_hash,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div v-if="entry.metadata.new_file_hash">
                                    <dt class="font-medium text-slate-500">
                                        Hash nuevo
                                    </dt>
                                    <dd class="font-mono text-slate-800">
                                        {{
                                            shortHash(
                                                entry.metadata.new_file_hash,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- File Preview Modal -->
        <div
            v-if="previewFile"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 p-4"
        >
            <div
                class="flex h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-200 px-5 py-4"
                >
                    <div class="min-w-0">
                        <h3
                            class="truncate text-base font-semibold text-slate-900"
                        >
                            {{ previewFile.name }}
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{ previewFile.mime_type || 'Archivo' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a
                            :href="previewFile.download_url"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            <Download class="h-4 w-4" />
                            Descargar
                        </a>
                        <button
                            v-if="previewFile.can_replace"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-100"
                            @click="triggerReplace(previewFile.id)"
                        >
                            <RefreshCw class="h-4 w-4" />
                            Reemplazar
                        </button>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                            @click="closePreview"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-hidden bg-slate-100">
                    <iframe
                        v-if="previewFile.mime_type === 'application/pdf'"
                        :src="previewFile.preview_url"
                        class="h-full w-full border-0"
                        title="Vista previa del archivo"
                    />
                    <div
                        v-else
                        class="flex h-full items-center justify-center bg-white p-6"
                    >
                        <img
                            v-if="previewFile.mime_type?.startsWith('image/')"
                            :src="previewFile.preview_url"
                            :alt="previewFile.name"
                            class="max-h-full max-w-full rounded-xl object-contain shadow"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
