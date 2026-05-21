<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Download, FileText } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

declare global {
    interface Window {
        DocsAPI?: {
            DocEditor: new (placeholderId: string, config: unknown) => {
                destroyEditor?: () => void;
            };
        };
    }
}

const props = defineProps<{
    file: {
        id: number;
        name: string;
        download_url: string;
        folder_url: string;
        can_edit: boolean;
    };
    onlyoffice: {
        enabled: boolean;
        api_url: string | null;
        config: Record<string, unknown> | null;
        load_error: string | null;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestor de Archivos', href: '/files/manager' },
    { title: props.file.name, href: `/files/${props.file.id}/onlyoffice` },
];

const editorError = ref<string | null>(props.onlyoffice.load_error);
let editorInstance: { destroyEditor?: () => void } | null = null;

function loadScript(src: string): Promise<void> {
    return new Promise((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(
            `script[src="${src}"]`,
        );

        if (existing) {
            resolve();
            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () =>
            reject(new Error('No se pudo cargar OnlyOffice Docs API.'));
        document.head.appendChild(script);
    });
}

onMounted(async () => {
    if (!props.onlyoffice.enabled || !props.onlyoffice.api_url) {
        editorError.value = 'OnlyOffice no esta configurado.';
        return;
    }

    if (!props.onlyoffice.config) {
        editorError.value =
            props.onlyoffice.load_error || 'No se pudo preparar el documento.';
        return;
    }

    try {
        await loadScript(props.onlyoffice.api_url);

        if (!window.DocsAPI?.DocEditor) {
            editorError.value =
                'OnlyOffice cargo, pero no expuso el editor DocsAPI.';
            return;
        }

        editorInstance = new window.DocsAPI.DocEditor(
            'onlyoffice-editor',
            props.onlyoffice.config,
        );
    } catch (error) {
        editorError.value =
            error instanceof Error
                ? error.message
                : 'No se pudo iniciar OnlyOffice.';
    }
});

onBeforeUnmount(() => {
    editorInstance?.destroyEditor?.();
});
</script>

<template>
    <Head :title="`OnlyOffice - ${file.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-screen flex-col bg-slate-50">
            <div
                class="flex flex-col gap-3 border-b border-slate-200 bg-white px-4 py-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="min-w-0">
                    <div class="mb-1 flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"
                        >
                            <FileText class="h-3.5 w-3.5" />
                            OnlyOffice
                        </span>
                        <span
                            class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700"
                        >
                            {{ file.can_edit ? 'Edicion DOCX' : 'Vista DOCX' }}
                        </span>
                    </div>
                    <h1 class="truncate text-lg font-semibold text-slate-900">
                        {{ file.name }}
                    </h1>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="file.folder_url"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Volver
                    </Link>
                    <a
                        :href="file.download_url"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        <Download class="h-4 w-4" />
                        Descargar
                    </a>
                </div>
            </div>

            <div
                v-if="editorError"
                class="m-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"
            >
                <div class="mb-1 flex items-center gap-2 font-semibold">
                    <AlertTriangle class="h-4 w-4" />
                    No se pudo abrir OnlyOffice
                </div>
                <p>{{ editorError }}</p>
            </div>

            <div
                v-show="!editorError"
                id="onlyoffice-editor"
                class="min-h-[calc(100vh-8rem)] flex-1"
            ></div>
        </div>
    </AppLayout>
</template>
