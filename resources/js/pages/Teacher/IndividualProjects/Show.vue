<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { FolderOpen, Send, Upload } from 'lucide-vue-next';
import DocxEditorPanel from '@/components/FileManager/DocxEditorPanel.vue';
import FolderPickerModal from '@/components/FileManager/FolderPickerModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    findFirstFolderId,
    findFolderPathById,
    folderPathLabel,
    type FolderTreeNode,
} from '@/lib/folderTree';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
    project: {
        id: number;
        title: string;
        type_label: string;
        status: string;
        folder: { id: number; name: string; url: string } | null;
        docx_file: { id: number; name: string } | null;
        docx_editor_url: string | null;
        docx_editor: {
            store_url: string;
            file: {
                id: number;
                name: string;
                mime_type: string | null;
                uploaded_at: string | null;
                uploaded_by: string | null;
                last_edited_at: string | null;
                last_edited_by: string | null;
                download_url: string;
                onlyoffice_url: string | null;
                folder_url: string;
                is_current_version: boolean;
                can_edit: boolean;
            };
            document: {
                html: string;
                header_html: string;
                footer_html: string;
                warnings: string[];
                safe_to_save: boolean;
                blocking_features: string[];
                stats: {
                    paragraphs: number;
                    headings: number;
                    list_items: number;
                    images: number;
                    tables: number;
                    unsupported_blocks: number;
                } | null;
                load_error: string | null;
                sections: {
                    has_header: boolean;
                    has_footer: boolean;
                };
            };
            capabilities: {
                can_edit: boolean;
            };
        } | null;
        review_comment: string | null;
        review_history: Array<{
            id: number;
            decision: string;
            comments: string | null;
            reviewed_at: string | null;
            reviewed_by: string | null;
        }>;
        can_submit: boolean;
        can_edit: boolean;
    };
    folderTree: FolderTreeNode[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Proyectos Individuales', href: '/docente/proyectos-individuales' },
    { title: props.project.title, href: `/docente/proyectos-individuales/${props.project.id}` },
];

const folderForm = useForm({
    folder_node_id: props.project.folder?.id ?? null,
});

const folderPickerOpen = ref(false);
const templatePickerOpen = ref(false);
const templateBrowseFolderId = ref<number | null>(
    findFirstFolderId(props.folderTree) as number | null,
);
const templateSelectedFileId = ref<number | null>(null);

const selectedFolderLabel = computed(() => {
    if (folderForm.folder_node_id == null) {
        return 'Crear carpeta sugerida';
    }

    return (
        folderPathLabel(
            findFolderPathById(props.folderTree, folderForm.folder_node_id),
        ) || 'Carpeta seleccionada'
    );
});

const uploadForm = useForm({
    file: null as File | null,
});
const submitForm = useForm({});
const templateForm = useForm({
    template_file_id: null as number | null,
});

function updateFolder() {
    folderForm.patch(`/docente/proyectos-individuales/${props.project.id}/folder`, {
        preserveScroll: true,
    });
}

function uploadDocx(event: Event) {
    const input = event.target as HTMLInputElement;
    uploadForm.file = input.files?.[0] ?? null;
    if (!uploadForm.file) return;

    uploadForm.post(`/docente/proyectos-individuales/${props.project.id}/docx`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset('file');
            input.value = '';
        },
    });
}

function applyTemplate() {
    if (templateSelectedFileId.value == null) {
        return;
    }

    if (
        props.project.docx_file &&
        !window.confirm(
            'Este proyecto ya tiene un DOCX principal. La plantilla seleccionada creará una nueva copia editable y reemplazará el DOCX actual.',
        )
    ) {
        return;
    }

    templateForm.template_file_id = templateSelectedFileId.value;
    templateForm.post(`/docente/proyectos-individuales/${props.project.id}/template`, {
        preserveScroll: true,
        onSuccess: () => {
            templateForm.reset('template_file_id');
            templateSelectedFileId.value = null;
        },
    });
}

function submitProject() {
    submitForm.post(`/docente/proyectos-individuales/${props.project.id}/submit`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="project.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ project.type_label }}</p>
                <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">{{ project.title }}</h1>
                        <p class="text-sm text-slate-500">Estado: {{ project.status }}</p>
                    </div>
                    <button
                        v-if="project.can_submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white"
                        @click="submitProject"
                    >
                        <Send class="h-4 w-4" />
                        Enviar a revision
                    </button>
                </div>
                <p v-if="project.review_comment" class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                    Comentario de revision: {{ project.review_comment }}
                </p>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-slate-900">
                        <FolderOpen class="h-5 w-5" />
                        Carpeta del proyecto
                    </h2>
                    <form class="space-y-3" @submit.prevent="updateFolder">
                        <div class="rounded-lg border border-slate-300 bg-slate-50 px-3 py-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Carpeta seleccionada
                            </p>
                            <p class="truncate text-sm font-medium text-slate-900">
                                {{ selectedFolderLabel }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                                :disabled="!project.can_edit || folderForm.processing"
                                type="button"
                                @click="folderPickerOpen = true"
                            >
                                Elegir carpeta
                            </button>
                            <button
                                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                                :disabled="!project.can_edit || folderForm.processing"
                            >
                                Guardar carpeta
                            </button>
                            <Link
                                v-if="project.folder"
                                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700"
                                :href="project.folder.url"
                            >
                                Abrir gestor
                            </Link>
                        </div>
                    </form>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold text-slate-900">
                        <Upload class="h-5 w-5" />
                        Formato DOCX
                    </h2>
                    <p class="mb-3 text-sm text-slate-500">
                        {{ project.docx_file?.name ?? 'Aun no hay DOCX principal.' }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <label
                            class="cursor-pointer rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white"
                            :class="{ 'opacity-50': !project.can_edit }"
                        >
                            Subir DOCX
                            <input
                                class="hidden"
                                type="file"
                                accept=".docx"
                                :disabled="!project.can_edit"
                                @change="uploadDocx"
                            />
                        </label>
                        <button
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 disabled:opacity-50"
                            :disabled="!project.can_edit"
                            type="button"
                            @click="templatePickerOpen = true"
                        >
                            Seleccionar formato de carpeta
                        </button>
                        <Link
                            v-if="project.docx_editor_url"
                            class="rounded-lg border border-blue-300 px-4 py-2 text-sm font-medium text-blue-700"
                            :href="project.docx_editor_url"
                        >
                            Editar Word
                        </Link>
                    </div>
                    <p v-if="uploadForm.errors.file" class="mt-2 text-sm text-red-600">{{ uploadForm.errors.file }}</p>
                </div>
            </section>

            <section
                v-if="project.docx_editor"
                class="space-y-4"
            >
                <DocxEditorPanel
                    :file="project.docx_editor.file"
                    :document="project.docx_editor.document"
                    :capabilities="project.docx_editor.capabilities"
                    :store-url="project.docx_editor.store_url"
                    heading="Editor DOCX"
                    description="Edita y guarda el formato principal del proyecto sin salir de esta pantalla."
                    back-label="Abrir gestor"
                    :allow-unsafe-rewrite="true"
                    unsafe-rewrite-notice="Este DOCX contiene estructura avanzada de Word. Puedes guardarlo porque estas editando una copia del proyecto; el archivo original no se modifica, pero algunos elementos no soportados pueden simplificarse en la copia."
                />
            </section>

            <section
                v-if="project.review_history.length > 0"
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            >
                <h2 class="text-lg font-semibold text-slate-900">Historial de revision</h2>
                <div class="mt-4 space-y-3">
                    <article
                        v-for="review in project.review_history"
                        :key="review.id"
                        class="rounded-lg border border-slate-200 p-3 text-sm"
                    >
                        <p class="font-medium text-slate-900">
                            {{ review.decision }} · {{ review.reviewed_by ?? 'Usuario' }}
                        </p>
                        <p class="text-slate-500">{{ review.reviewed_at }}</p>
                        <p v-if="review.comments" class="mt-2 text-slate-700">{{ review.comments }}</p>
                    </article>
                </div>
            </section>

            <FolderPickerModal
                v-model:open="folderPickerOpen"
                v-model:selected-folder-id="folderForm.folder_node_id"
                :folder-tree="folderTree"
                title="Seleccionar carpeta del proyecto"
                description="Haz click sobre una carpeta para seleccionarla. Usa doble click para entrar en ella y ver sus subcarpetas."
            />

            <FolderPickerModal
                v-model:open="templatePickerOpen"
                v-model:selected-folder-id="templateBrowseFolderId"
                v-model:selected-file-id="templateSelectedFileId"
                :folder-tree="folderTree"
                mode="template"
                title="Seleccionar formato de carpeta"
                description="Navega la carpeta de plantillas, selecciona un DOCX y confirma para crear una copia editable dentro del proyecto."
                @confirm="applyTemplate"
            />
        </div>
    </AppLayout>
</template>
