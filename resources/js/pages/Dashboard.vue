<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { dashboard } from '@/routes';
import { useAuth } from '@/composables/useAuth';

const { user, isDocente, isJefeOficina, isJefeDepto } = useAuth();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <div
                class="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-sidebar-accent/10"
            >
                <h2 class="mb-2 text-xl font-semibold">
                    Bienvenido, {{ user?.name }}
                </h2>
                <p class="text-gray-600 dark:text-gray-400">
                    Rol asignado:
                    <span v-if="isDocente" class="font-bold text-blue-600"
                        >Docente</span
                    >
                    <span
                        v-else-if="isJefeOficina"
                        class="font-bold text-green-600"
                        >Jefe de Oficina</span
                    >
                    <span
                        v-else-if="isJefeDepto"
                        class="font-bold text-purple-600"
                        >Jefe de Departamento</span
                    >
                    <span v-else class="text-gray-500">Sin Rol</span>
                </p>
            </div>
        </div>

        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <PlaceholderPattern />
                </div>
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <PlaceholderPattern />
                </div>
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <PlaceholderPattern />
                </div>
            </div>
            <div
                class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
        </div>
    </AppLayout>
</template>
