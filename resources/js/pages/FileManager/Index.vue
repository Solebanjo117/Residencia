<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import FolderTree from '@/components/FileManager/FolderTree.vue';
import {
    Folder,
    FileText,
    Download,
    Trash2,
    Upload,
    RefreshCw,
} from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';
import { ref, computed } from 'vue';

const props = defineProps<{
    folderTree: any[];
    currentFolder: any | null;
    semesterName?: string | null;
    allowedExtensions?: string[];
    contents: {
        folders: any[];
        files: any[];
    };
}>();

const uploadAccept = computed(() => {
    const extensions = props.allowedExtensions?.length
        ? props.allowedExtensions
        : ['docx', 'pdf', 'jpg', 'jpeg', 'png', 'webp'];

    return extensions.map((extension) => `.${extension}`).join(',');
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Gestor de Archivos',
        href: '/files/manager',
    },
    ...(props.currentFolder
        ? [
              {
                  title: props.currentFolder.name,
                  href: `/files/folders/${props.currentFolder.id}`,
              },
          ]
        : []),
];

const formatSize = (bytes: number) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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

const uploadForm = useForm({
    file: null as File | null,
});

const replaceForm = useForm({
    file: null as File | null,
});

const triggerUpload = () => {
    fileInput.value?.click();
};

const uploadError = ref('');
const uploadSuccess = ref('');

const handleFileSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    uploadError.value = '';
    uploadSuccess.value = '';
    if (target.files && target.files.length > 0 && props.currentFolder) {
        uploadForm.file = target.files[0];
        uploadForm.post(`/files/folders/${props.currentFolder.id}/upload`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                target.value = '';
                uploadForm.reset();
                uploadSuccess.value = 'Archivo subido correctamente.';
                setTimeout(() => { uploadSuccess.value = ''; }, 3000);
            },
            onError: (errors: any) => {
                target.value = '';
                uploadForm.reset();
                uploadError.value = errors.file || 'Error al subir archivo.';
            },
        });
    }
};

const triggerReplace = (fileId: number) => {
    fileToReplace.value = fileId;
    replaceFileInput.value?.click();
};

const handleReplaceSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
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
            },
        });
    }
};
</script>

<template>
    <Head title="Gestor de Archivos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-[calc(100vh-4rem)] overflow-hidden">
            <!-- Left Panel: Folder Tree -->
            <div
                class="w-1/4 overflow-y-auto border-r border-gray-200 bg-gray-50 p-4"
            >
                <h3 class="mb-4 px-2 font-semibold text-gray-700">Carpetas</h3>
                <FolderTree
                    v-for="root in folderTree"
                    :key="root.id"
                    :node="root"
                />
            </div>

            <!-- Right Panel: Content -->
            <div class="flex w-3/4 flex-col overflow-hidden bg-white">
                <!-- Toolbar / Header -->
                <div
                    class="flex items-center justify-between border-b border-gray-200 p-4"
                >
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">
                            {{ currentFolder ? currentFolder.name : 'Selecciona una carpeta' }}
                        </h2>
                        <span v-if="semesterName" class="text-xs text-gray-500">
                            Semestre: <b>{{ semesterName }}</b>
                        </span>
                    </div>
                    <div v-if="uploadForm.processing" class="flex items-center gap-2 text-sm text-blue-600">
                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Subiendo...
                    </div>
                    <button type="button" v-if="currentFolder && !uploadForm.processing"
                        @click="triggerUpload"
                        class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase ring-blue-300 transition duration-150 ease-in-out hover:bg-blue-700 focus:border-blue-900 focus:ring focus:outline-none active:bg-blue-900 disabled:opacity-25"
                    >
                        <Upload class="mr-2 h-4 w-4" />
                        Subir Archivo
                    </button>
                    <!-- Hidden inputs for file picking -->
                    <input
                        type="file"
                        class="hidden"
                        ref="fileInput"
                        :accept="uploadAccept"
                        @change="handleFileSelected"
                    />
                    <input
                        type="file"
                        class="hidden"
                        ref="replaceFileInput"
                        :accept="uploadAccept"
                        @change="handleReplaceSelected"
                    />
                </div>

                <!-- Alerts -->
                <div v-if="uploadError" class="mx-4 mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                    {{ uploadError }}
                </div>
                <div v-if="uploadSuccess" class="mx-4 mt-3 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">
                    {{ uploadSuccess }}
                </div>

                <!-- Content Grid/List -->
                <div class="flex-1 overflow-y-auto p-6">
                    <div
                        v-if="!currentFolder"
                        class="flex h-full flex-col items-center justify-center text-gray-400"
                    >
                        <Folder class="mb-4 h-16 w-16 text-gray-300" />
                        <p>Selecciona una carpeta del árbol para ver su contenido</p>
                    </div>

                    <div v-else>
                        <!-- Subfolders -->
                        <div v-if="contents.folders.length > 0" class="mb-8">
                            <h4
                                class="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase"
                            >
                                Subcarpetas
                            </h4>
                            <div
                                class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                            >
                                <Link
                                    v-for="folder in contents.folders"
                                    :key="folder.id"
                                    :href="`/files/folders/${folder.id}`"
                                    class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 transition-colors hover:border-blue-200 hover:bg-blue-50"
                                >
                                    <Folder class="h-8 w-8 text-yellow-500" />
                                    <span
                                        class="truncate font-medium text-gray-700"
                                        >{{ folder.name }}</span
                                    >
                                </Link>
                            </div>
                        </div>

                        <!-- Files -->
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
                                        >
                                            <td
                                                class="px-6 py-4 whitespace-nowrap"
                                            >
                                                <div class="flex items-center">
                                                    <FileText
                                                        class="mr-3 h-5 w-5 text-gray-400"
                                                    />
                                                    <span
                                                        class="text-sm font-medium text-gray-900"
                                                        >{{ file.name }}</span
                                                    >
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap"
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
                                                    {{ statusLabel(file.status) }}
                                                </span>
                                                <span v-if="file.is_late" class="ml-2 inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-700">
                                                    EXT
                                                </span>
                                                <span
                                                    v-else
                                                    class="text-xs text-gray-400 italic"
                                                    >Sin estado</span
                                                >
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
                                                    <button type="button" v-if="file.can_delete"
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
                                                    <button type="button" v-if="file.can_delete"
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
                            <p class="text-gray-500">Esta carpeta está vacía.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
