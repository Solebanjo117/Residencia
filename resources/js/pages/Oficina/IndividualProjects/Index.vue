<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type Project = {
    id: number;
    title: string;
    type_label: string;
    status: string;
    teacher: { name: string; email: string } | null;
    semester: { name: string } | null;
    show_url: string;
    docx_editor_url: string | null;
};

defineProps<{
    projects: Project[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Proyectos Individuales', href: '/oficina/proyectos-individuales' },
];
</script>

<template>
    <Head title="Revision de Proyectos Individuales" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold text-slate-900">Proyectos Individuales</h1>
                <p class="text-sm text-slate-500">Revision de proyectos enviados por docentes.</p>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Proyecto</th>
                            <th class="px-4 py-3">Docente</th>
                            <th class="px-4 py-3">Semestre</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="project in projects" :key="project.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ project.title }}</p>
                                <p class="text-xs text-slate-500">{{ project.type_label }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ project.teacher?.name ?? 'Sin docente' }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ project.semester?.name ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">
                                    {{ project.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700" :href="project.show_url">
                                    Revisar
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="projects.length === 0" class="p-8 text-center text-sm text-slate-500">
                    No hay proyectos individuales registrados.
                </div>
            </section>
        </div>
    </AppLayout>
</template>

