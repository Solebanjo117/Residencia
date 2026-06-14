<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
    project: {
        id: number;
        title: string;
        type_label: string;
        status: string;
        teacher: { name: string; email: string } | null;
        folder: { name: string; url: string } | null;
        docx_file: { name: string } | null;
        docx_editor_url: string | null;
        review_comment: string | null;
        review_history: Array<{
            id: number;
            decision: string;
            comments: string | null;
            reviewed_at: string | null;
            reviewed_by: string | null;
        }>;
        can_review: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Proyectos Individuales', href: '/oficina/proyectos-individuales' },
    { title: props.project.title, href: `/oficina/proyectos-individuales/${props.project.id}` },
];

const form = useForm({
    review_comment: '',
});

function approve() {
    form.post(`/oficina/proyectos-individuales/${props.project.id}/approve`, {
        preserveScroll: true,
    });
}

function reject() {
    form.post(`/oficina/proyectos-individuales/${props.project.id}/reject`, {
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
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ project.title }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ project.teacher?.name ?? 'Sin docente' }} · Estado {{ project.status }}
                </p>
                <p v-if="project.review_comment" class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                    Ultimo comentario: {{ project.review_comment }}
                </p>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Archivos</h2>
                    <p class="mt-2 text-sm text-slate-500">
                        DOCX principal: {{ project.docx_file?.name ?? 'No asociado' }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Link
                            v-if="project.folder"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700"
                            :href="project.folder.url"
                        >
                            Abrir carpeta
                        </Link>
                        <Link
                            v-if="project.docx_editor_url"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white"
                            :href="project.docx_editor_url"
                        >
                            Abrir DOCX
                        </Link>
                    </div>
                </div>

                <form class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent>
                    <h2 class="text-lg font-semibold text-slate-900">Revision</h2>
                    <textarea
                        v-model="form.review_comment"
                        class="mt-4 min-h-28 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        placeholder="Comentario para el docente"
                        :disabled="!project.can_review"
                    />
                    <p v-if="form.errors.review_comment" class="mt-2 text-sm text-red-600">
                        {{ form.errors.review_comment }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                            :disabled="!project.can_review || form.processing"
                            @click="approve"
                        >
                            Aprobar
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                            :disabled="!project.can_review || form.processing"
                            @click="reject"
                        >
                            Solicitar correccion
                        </button>
                    </div>
                </form>
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
        </div>
    </AppLayout>
</template>
