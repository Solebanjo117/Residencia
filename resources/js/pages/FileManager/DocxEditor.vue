<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DocxEditorPanel from '@/components/FileManager/DocxEditorPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

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
        allow_unsafe_rewrite: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestor de Archivos', href: '/files/manager' },
    { title: props.file.name, href: `/files/${props.file.id}/docx` },
];
</script>

<template>
    <Head :title="`Editor DOCX - ${file.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-slate-50">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <DocxEditorPanel
                    :file="file"
                    :document="document"
                    :capabilities="capabilities"
                    :store-url="`/files/${file.id}/docx`"
                    :heading="file.name"
                    :allow-unsafe-rewrite="capabilities.allow_unsafe_rewrite"
                />
            </div>
        </div>
    </AppLayout>
</template>
