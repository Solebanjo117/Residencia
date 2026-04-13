<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import {
    AlertTriangle,
    ArrowLeft,
    Clock3,
    Download,
    FileText,
    History,
    ListOrdered,
    List,
    Table2,
    AlignLeft,
    AlignCenter,
    AlignRight,
    Save,
    User,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    file: {
        id: number;
        name: string;
        mime_type: string | null;
        uploaded_at: string | null;
        uploaded_by: string | null;
        last_edited_at: string | null;
        last_edited_by: string | null;
        download_url: string;
        folder_url: string;
        is_current_version: boolean;
        can_edit: boolean;
    };
    document: {
        html: string;
        header_html: string;
        footer_html: string;
        warnings: string[];
        stats: {
            paragraphs: number;
            headings: number;
            list_items: number;
            images: number;
            tables: number;
            unsupported_blocks: number;
        } | null;
        version_history: Array<{
            id: number;
            file_name: string;
            uploaded_at: string | null;
            uploaded_by: string | null;
            last_edited_at: string | null;
            last_edited_by: string | null;
            is_current_version: boolean;
            editor_source: string | null;
            download_url: string;
            editor_url: string;
        }>;
        load_error: string | null;
        sections: {
            has_header: boolean;
            has_footer: boolean;
        };
    };
    capabilities: {
        can_edit: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestor de Archivos', href: '/files/manager' },
    { title: props.file.name, href: `/files/${props.file.id}/docx` },
];

const editorRef = ref<HTMLDivElement | null>(null);
const headerEditorRef = ref<HTMLDivElement | null>(null);
const footerEditorRef = ref<HTMLDivElement | null>(null);
const editorHtml = ref(props.document.html || '<p><br></p>');
const headerHtml = ref(props.document.header_html || '<p><br></p>');
const footerHtml = ref(props.document.footer_html || '<p><br></p>');
const hasUnsavedChanges = ref(false);
const activeSection = ref<'header' | 'body' | 'footer'>('body');

const saveForm = useForm({
    html: props.document.html || '',
    header_html: props.document.header_html || '',
    footer_html: props.document.footer_html || '',
    save_mode: 'replace_current',
});

const readOnly = computed(() => !props.capabilities.can_edit || Boolean(props.document.load_error));

const compatibilityNotice = computed(() => [
    'Esta fase conserva mejor la tipografia explicita del DOCX: fuente, tamano, color, negritas, cursivas y subrayado.',
    'Las imagenes incrustadas se muestran dentro del editor y se conservan al guardar mientras sigan presentes en el documento.',
    'Las listas simples y las tablas basicas se guardan como estructura real dentro del DOCX; tablas complejas, comentarios nativos y layout avanzado pueden simplificarse.',
    'Guardar crea una revision segura; el archivo base no se pierde.',
]);

const formattedUploadedAt = computed(() => formatDateTime(props.file.uploaded_at));
const formattedEditedAt = computed(() => formatDateTime(props.file.last_edited_at));

function initializeEditor() {
    nextTick(() => {
        if (editorRef.value) {
            editorRef.value.innerHTML = editorHtml.value || '<p><br></p>';
        }

        if (headerEditorRef.value) {
            headerEditorRef.value.innerHTML = headerHtml.value || '<p><br></p>';
        }

        if (footerEditorRef.value) {
            footerEditorRef.value.innerHTML = footerHtml.value || '<p><br></p>';
        }
    });
}

function syncEditorHtml(section: 'header' | 'body' | 'footer' = activeSection.value) {
    if (section === 'header') {
        headerHtml.value = headerEditorRef.value?.innerHTML || '';
    } else if (section === 'footer') {
        footerHtml.value = footerEditorRef.value?.innerHTML || '';
    } else {
        editorHtml.value = editorRef.value?.innerHTML || '';
    }

    hasUnsavedChanges.value = true;
}

function currentEditorElement() {
    if (activeSection.value === 'header') {
        return headerEditorRef.value;
    }

    if (activeSection.value === 'footer') {
        return footerEditorRef.value;
    }

    return editorRef.value;
}

function focusEditor() {
    currentEditorElement()?.focus();
}

function exec(command: string, value?: string) {
    if (readOnly.value) {
        return;
    }

    focusEditor();
    document.execCommand(command, false, value);
    syncEditorHtml();
}

function insertSimpleTable() {
    if (readOnly.value) {
        return;
    }

    focusEditor();
    document.execCommand(
        'insertHTML',
        false,
        '<table class="docx-table" data-docx-kind="table"><tbody><tr><td><p>Celda 1</p></td><td><p>Celda 2</p></td></tr><tr><td><p>Celda 3</p></td><td><p>Celda 4</p></td></tr></tbody></table><p><br></p>',
    );
    syncEditorHtml();
}

function save(mode: 'replace_current' | 'new_version') {
    if (readOnly.value || !editorRef.value) {
        return;
    }

    saveForm.html = editorRef.value.innerHTML;
    saveForm.header_html = headerEditorRef.value?.innerHTML || '';
    saveForm.footer_html = footerEditorRef.value?.innerHTML || '';
    saveForm.save_mode = mode;

    saveForm.post(`/files/${props.file.id}/docx`, {
        preserveScroll: true,
        onSuccess: () => {
            hasUnsavedChanges.value = false;
        },
    });
}

function formatDateTime(value: string | null) {
    if (!value) {
        return 'Sin registro';
    }

    return new Date(value).toLocaleString('es-MX', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

watch(
    () => [props.document.html, props.document.header_html, props.document.footer_html] as const,
    ([bodyValue, headerValue, footerValue]) => {
        editorHtml.value = bodyValue || '<p><br></p>';
        headerHtml.value = headerValue || '<p><br></p>';
        footerHtml.value = footerValue || '<p><br></p>';
        hasUnsavedChanges.value = false;
        initializeEditor();
    },
);

onMounted(() => {
    initializeEditor();
});
</script>

<template>
    <Head :title="`Editor DOCX - ${file.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-slate-50">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                                DOCX Editor MVP
                            </span>
                            <span
                                v-if="readOnly"
                                class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                            >
                                Solo lectura
                            </span>
                            <span
                                v-if="hasUnsavedChanges"
                                class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700"
                            >
                                Cambios sin guardar
                            </span>
                        </div>
                        <h1 class="text-2xl font-semibold text-slate-900">{{ file.name }}</h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Primera fase de edicion textual para documentos .docx dentro del gestor de archivos.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="file.folder_url"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            <ArrowLeft class="h-4 w-4" />
                            Volver al gestor
                        </Link>
                        <a
                            :href="file.download_url"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            <Download class="h-4 w-4" />
                            Descargar
                        </a>
                        <button
                            v-if="capabilities.can_edit && !document.load_error"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            :disabled="saveForm.processing"
                            @click="save('new_version')"
                        >
                            <History class="h-4 w-4" />
                            Guardar como nueva version
                        </button>
                        <button
                            v-if="capabilities.can_edit && !document.load_error"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                            :disabled="saveForm.processing"
                            @click="save('replace_current')"
                        >
                            <Save class="h-4 w-4" />
                            Guardar
                        </button>
                    </div>
                </div>

                <div class="mb-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div class="space-y-4">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="mb-3 flex flex-wrap items-center gap-3 text-sm text-slate-600">
                                <span class="inline-flex items-center gap-2">
                                    <FileText class="h-4 w-4 text-slate-400" />
                                    {{ file.mime_type || 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' }}
                                </span>
                                <span class="inline-flex items-center gap-2">
                                    <Clock3 class="h-4 w-4 text-slate-400" />
                                    Subido: {{ formattedUploadedAt }}
                                </span>
                                <span class="inline-flex items-center gap-2">
                                    <User class="h-4 w-4 text-slate-400" />
                                    {{ file.uploaded_by || 'Sin usuario' }}
                                </span>
                            </div>

                            <div v-if="file.last_edited_at" class="text-xs text-slate-500">
                                Ultima edicion: {{ formattedEditedAt }} por {{ file.last_edited_by || 'Sin usuario' }}
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Compatibilidad real de esta fase</h2>
                            <ul class="space-y-2 text-sm text-slate-700">
                                <li v-for="notice in compatibilityNotice" :key="notice" class="flex items-start gap-2">
                                    <span class="mt-1 h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                    <span>{{ notice }}</span>
                                </li>
                            </ul>
                        </div>

                        <div
                            v-if="document.warnings.length > 0"
                            class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 shadow-sm"
                        >
                            <div class="mb-2 flex items-center gap-2 font-semibold">
                                <AlertTriangle class="h-4 w-4" />
                                Advertencias de compatibilidad detectadas
                            </div>
                            <ul class="space-y-2">
                                <li v-for="warning in document.warnings" :key="warning" class="flex items-start gap-2">
                                    <span class="mt-1 h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                    <span>{{ warning }}</span>
                                </li>
                            </ul>
                        </div>

                        <div
                            v-if="saveForm.errors.docx"
                            class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm"
                        >
                            <div class="mb-2 flex items-center gap-2 font-semibold">
                                <AlertTriangle class="h-4 w-4" />
                                No se pudieron guardar los cambios
                            </div>
                            <p>{{ saveForm.errors.docx }}</p>
                        </div>

                        <div v-if="document.load_error" class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800 shadow-sm">
                            <div class="mb-2 flex items-center gap-2 font-semibold">
                                <AlertTriangle class="h-4 w-4" />
                                No se pudo abrir el documento en el editor
                            </div>
                            <p>{{ document.load_error }}</p>
                            <p class="mt-2 text-rose-700">
                                Puedes descargar el archivo original o reemplazarlo desde el gestor. No se modifico nada automaticamente.
                            </p>
                        </div>

                        <div v-else class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-200 px-4 py-3">
                                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Editor</h2>
                                        <p class="mt-1 text-xs text-slate-500">
                                            Edicion textual por secciones. El toolbar actua sobre la seccion que tenga el cursor activo.
                                        </p>
                                    </div>
                                    <div
                                        v-if="document.stats"
                                        class="flex flex-wrap items-center gap-2 text-[11px] font-semibold text-slate-500"
                                    >
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1">Parrafos: {{ document.stats.paragraphs }}</span>
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1">Titulos: {{ document.stats.headings }}</span>
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1">Listas: {{ document.stats.list_items }}</span>
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1">Imagenes: {{ document.stats.images }}</span>
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1">Tablas: {{ document.stats.tables }}</span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="readOnly" @click="exec('bold')">B</button>
                                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm italic text-slate-700 hover:bg-slate-50" :disabled="readOnly" @click="exec('italic')">I</button>
                                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm underline text-slate-700 hover:bg-slate-50" :disabled="readOnly" @click="exec('underline')">U</button>
                                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="readOnly" @click="exec('formatBlock', '<H1>')">H1</button>
                                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="readOnly" @click="exec('formatBlock', '<H2>')">H2</button>
                                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="readOnly" @click="exec('formatBlock', '<P>')">P</button>
                                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="readOnly" @click="exec('justifyLeft')"><AlignLeft class="h-4 w-4" /> Izq</button>
                                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="readOnly" @click="exec('justifyCenter')"><AlignCenter class="h-4 w-4" /> Centro</button>
                                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="readOnly" @click="exec('justifyRight')"><AlignRight class="h-4 w-4" /> Der</button>
                                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="readOnly" @click="exec('insertUnorderedList')"><List class="h-4 w-4" /> UL</button>
                                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="readOnly" @click="exec('insertOrderedList')"><ListOrdered class="h-4 w-4" /> OL</button>
                                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="readOnly" @click="insertSimpleTable"><Table2 class="h-4 w-4" /> Tabla</button>
                                </div>
                            </div>

                            <div class="p-4">
                                <div
                                    v-if="document.sections.has_header"
                                    class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4"
                                >
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <div>
                                            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Encabezado</h3>
                                            <p class="mt-1 text-xs text-slate-500">Edita el encabezado actual del documento.</p>
                                        </div>
                                        <span class="rounded-full border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600">Seccion</span>
                                    </div>
                                    <div
                                        ref="headerEditorRef"
                                        class="docx-editor min-h-[140px] rounded-xl border border-slate-200 bg-white px-5 py-4 text-[15px] leading-7 text-slate-800 outline-none"
                                        :contenteditable="readOnly ? 'false' : 'true'"
                                        spellcheck="true"
                                        @focus="activeSection = 'header'"
                                        @input="syncEditorHtml('header')"
                                    ></div>
                                </div>

                                <div
                                    ref="editorRef"
                                    class="docx-editor min-h-[560px] rounded-xl border border-slate-200 bg-white px-6 py-5 text-[15px] leading-7 text-slate-800 outline-none"
                                    :contenteditable="readOnly ? 'false' : 'true'"
                                    spellcheck="true"
                                    @focus="activeSection = 'body'"
                                    @input="syncEditorHtml('body')"
                                ></div>

                                <div
                                    v-if="document.sections.has_footer"
                                    class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4"
                                >
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <div>
                                            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pie de Pagina</h3>
                                            <p class="mt-1 text-xs text-slate-500">Edita el pie de pagina actual del documento.</p>
                                        </div>
                                        <span class="rounded-full border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600">Seccion</span>
                                    </div>
                                    <div
                                        ref="footerEditorRef"
                                        class="docx-editor min-h-[140px] rounded-xl border border-slate-200 bg-white px-5 py-4 text-[15px] leading-7 text-slate-800 outline-none"
                                        :contenteditable="readOnly ? 'false' : 'true'"
                                        spellcheck="true"
                                        @focus="activeSection = 'footer'"
                                        @input="syncEditorHtml('footer')"
                                    ></div>
                                </div>
                            </div>

                            <div class="border-t border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500">
                                <span v-if="readOnly">Este documento esta en modo solo lectura. Puedes descargarlo o volver al gestor.</span>
                                <span v-else>Guardar crea una revision segura del DOCX. Se intenta conservar la tipografia explicita, la alineacion del parrafo, las imagenes incrustadas, las listas simples, las tablas basicas y, cuando existen, el encabezado y pie de pagina editables.</span>
                            </div>
                        </div>
                    </div>

                    <aside class="space-y-4">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Historial de versiones</h2>
                            <div v-if="document.version_history.length === 0" class="text-sm text-slate-500">
                                No hay versiones registradas todavia.
                            </div>
                            <ul v-else class="space-y-3">
                                <li
                                    v-for="version in document.version_history"
                                    :key="version.id"
                                    class="rounded-xl border p-3"
                                    :class="version.is_current_version ? 'border-indigo-200 bg-indigo-50/70' : 'border-slate-200 bg-white'"
                                >
                                    <div class="mb-2 flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-semibold text-slate-900">{{ version.file_name }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ formatDateTime(version.last_edited_at || version.uploaded_at) }}</div>
                                        </div>
                                        <span
                                            class="inline-flex shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold"
                                            :class="version.is_current_version ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600'"
                                        >
                                            {{ version.is_current_version ? 'Actual' : 'Revision' }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        <div>Subido por: {{ version.uploaded_by || 'Sin usuario' }}</div>
                                        <div v-if="version.last_edited_by">Editado por: {{ version.last_edited_by }}</div>
                                    </div>
                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <Link
                                            :href="version.editor_url"
                                            class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            Abrir
                                        </Link>
                                        <a
                                            :href="version.download_url"
                                            class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            Descargar
                                        </a>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.docx-editor :deep(h1) {
    margin: 1rem 0 0.5rem;
    font-size: 1.875rem;
    line-height: 1.15;
    font-weight: 700;
}

.docx-editor :deep(h2) {
    margin: 0.9rem 0 0.45rem;
    font-size: 1.45rem;
    line-height: 1.2;
    font-weight: 700;
}

.docx-editor :deep(h3) {
    margin: 0.8rem 0 0.4rem;
    font-size: 1.2rem;
    line-height: 1.25;
    font-weight: 700;
}

.docx-editor :deep(p) {
    margin: 0 0 0.9rem;
}

.docx-editor :deep(ul),
.docx-editor :deep(ol) {
    margin: 0 0 1rem 1.5rem;
    padding-left: 1rem;
}

.docx-editor :deep(table.docx-table) {
    width: 100%;
    border-collapse: collapse;
    margin: 0 0 1rem;
}

.docx-editor :deep(table.docx-table td),
.docx-editor :deep(table.docx-table th) {
    min-width: 8rem;
    border: 1px solid rgb(203 213 225);
    padding: 0.65rem 0.75rem;
    vertical-align: top;
}

.docx-editor :deep(table.docx-table p:last-child) {
    margin-bottom: 0;
}

.docx-editor :deep(li) {
    margin: 0.25rem 0;
}

.docx-editor :deep(img) {
    display: block;
    max-width: 100%;
    height: auto;
    margin: 0.75rem 0;
    border-radius: 0.75rem;
}

.docx-editor :deep(span[data-docx-font-family]) {
    white-space: pre-wrap;
}

.docx-editor :deep([data-docx-unsupported]) {
    cursor: not-allowed;
}
</style>
