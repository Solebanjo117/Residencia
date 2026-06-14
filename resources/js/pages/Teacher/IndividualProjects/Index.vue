<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { FileText, FolderOpen, Plus } from 'lucide-vue-next';
import FolderPickerModal from '@/components/FileManager/FolderPickerModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    findFolderByPathSegments,
    findFolderPathById,
    folderPathLabel,
    INDIVIDUAL_PROJECT_FOLDER_PATHS,
    type FolderTreeNode,
} from '@/lib/folderTree';
import { type BreadcrumbItem } from '@/types';

type Project = {
    id: number;
    title: string;
    type_label: string;
    status: string;
    show_url: string;
    folder: { name: string; url: string } | null;
    docx_editor_url: string | null;
};

const props = defineProps<{
    semester: { id: number; name: string } | null;
    types: Array<{ value: string; label: string }>;
    folderTree: FolderTreeNode[];
    projects: Project[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Proyectos Individuales', href: '/docente/proyectos-individuales' },
];

const form = useForm({
    semester_id: props.semester?.id ?? null,
    type: props.types[0]?.value ?? '',
    title: '',
    folder_node_id: null as number | null,
});

const folderPickerOpen = ref(false);

const suggestedFolderId = computed<number | null>(() => {
    const path = INDIVIDUAL_PROJECT_FOLDER_PATHS[form.type] ?? [];
    const folder = findFolderByPathSegments(props.folderTree, path);

    return typeof folder?.id === 'number' ? folder.id : null;
});

const selectedFolderLabel = computed(() => {
    if (form.folder_node_id == null) {
        return 'Crear carpeta sugerida';
    }

    const path = findFolderPathById(props.folderTree, form.folder_node_id);
    return folderPathLabel(path) || 'Carpeta seleccionada';
});

watch(
    suggestedFolderId,
    (folderId) => {
        form.folder_node_id = folderId;
    },
    { immediate: true },
);

function createProject() {
    form.post('/docente/proyectos-individuales', {
        preserveScroll: true,
        onSuccess: () => form.reset('title'),
    });
}
</script>

<template>
    <Head title="Proyectos Individuales" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">Proyectos Individuales</h1>
                        <p class="text-sm text-slate-500">
                            Crea proyectos, liga su carpeta y edita el formato DOCX desde el sistema.
                        </p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-600">
                        {{ semester?.name ?? 'Sin semestre' }}
                    </span>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-slate-900">
                    <Plus class="h-5 w-5" />
                    Nuevo proyecto
                </h2>

                <form class="grid gap-4 md:grid-cols-[220px_1fr_1fr_auto]" @submit.prevent="createProject">
                    <select
                        v-model="form.type"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        :disabled="!semester || form.processing"
                    >
                        <option v-for="type in types" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>

                    <input
                        v-model="form.title"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        placeholder="Nombre del proyecto"
                        :disabled="!semester || form.processing"
                    />

                    <div class="rounded-lg border border-slate-300 bg-slate-50 px-3 py-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Carpeta
                        </p>
                        <p class="truncate text-sm font-medium text-slate-900">
                            {{ selectedFolderLabel }}
                        </p>
                        <button
                            type="button"
                            class="mt-2 inline-flex items-center gap-2 text-sm font-medium text-blue-700"
                            :disabled="!semester || form.processing"
                            @click="folderPickerOpen = true"
                        >
                            <FolderOpen class="h-4 w-4" />
                            Elegir carpeta
                        </button>
                    </div>

                    <button
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="!semester || form.processing"
                    >
                        Crear
                    </button>
                </form>

                <p v-if="form.errors.title" class="mt-2 text-sm text-red-600">{{ form.errors.title }}</p>
            </section>

            <FolderPickerModal
                v-model:open="folderPickerOpen"
                v-model:selected-folder-id="form.folder_node_id"
                :folder-tree="folderTree"
                title="Seleccionar carpeta del proyecto"
                description="Haz click sobre una carpeta para seleccionarla. Usa doble click para entrar en ella y ver sus subcarpetas."
            />

            <section class="grid gap-4">
                <article
                    v-for="project in projects"
                    :key="project.id"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ project.type_label }}
                            </p>
                            <h2 class="text-lg font-semibold text-slate-900">{{ project.title }}</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Estado: <span class="font-medium text-slate-700">{{ project.status }}</span>
                            </p>
                            <p v-if="project.folder" class="text-sm text-slate-500">
                                Carpeta: {{ project.folder.name }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Link
                                class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700"
                                :href="project.show_url"
                            >
                                Abrir
                            </Link>
                            <Link
                                v-if="project.docx_editor_url"
                                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white"
                                :href="project.docx_editor_url"
                            >
                                <FileText class="h-4 w-4" />
                                Editar Word
                            </Link>
                        </div>
                    </div>
                </article>

                <div v-if="projects.length === 0" class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
                    No hay proyectos individuales registrados para este semestre.
                </div>
            </section>
        </div>
    </AppLayout>
</template>
