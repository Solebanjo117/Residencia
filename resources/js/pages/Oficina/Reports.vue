<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reportes',
        href: '/oficina/reportes',
    },
];
</script>

<template>
    <Head title="Reportes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <h1 class="mb-4 text-2xl font-bold">Reportes de Docentes</h1>
            <p class="text-gray-600">
                Generación de reportes de cumplimiento y seguimiento.
            </p>
        </div>
    </AppLayout>
</template>
