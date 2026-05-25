<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Database, ExternalLink, Search, ShieldAlert } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

defineProps<{
    logs: {
        id: number;
        action: string;
        entity_type: string;
        entity_id: number | null;
        at: string;
        user_name: string | null;
        user_email: string | null;
        entity_label: string;
        entity_url: string | null;
        target_status: 'linked' | 'missing' | 'unsupported' | 'none';
        change_summary: string | null;
    }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Auditoría del Sistema', href: '/admin/audits' },
];

const searchQuery = ref('');

let timeoutToken: any = null;

watch(searchQuery, (newVal) => {
    clearTimeout(timeoutToken);
    timeoutToken = setTimeout(() => {
        router.get(
            '/admin/audits',
            { search: newVal },
            { preserveState: true, replace: true },
        );
    }, 400);
});

function formatDate(dateStr: string) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('es-MX', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function targetStatusLabel(status: string) {
    if (status === 'missing') return 'Sin destino directo';
    if (status === 'unsupported') return 'Sin vista directa';
    if (status === 'none') return 'Sin entidad';

    return null;
}
</script>

<template>
    <Head title="Auditoría" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-8 md:flex md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <ShieldAlert class="h-8 w-8 text-indigo-600" />
                    <div>
                        <h1
                            class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl"
                        >
                            Registro de Auditoría
                        </h1>
                        <p class="mt-1 text-sm text-gray-500">
                            Trazabilidad inamovible de los eventos del sistema.
                            Últimos 200 registros.
                        </p>
                    </div>
                </div>

                <div class="relative mt-4 w-full md:mt-0 md:w-72">
                    <div
                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                    >
                        <Search class="h-4 w-4 text-gray-400" />
                    </div>
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="block w-full rounded-lg border-gray-300 pl-10 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        placeholder="Buscar por usuario o acción..."
                    />
                </div>
            </div>

            <!-- Table -->
            <div class="mt-8 flow-root">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div
                        class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8"
                    >
                        <div
                            class="ring-opacity-5 overflow-hidden rounded-2xl shadow ring-1 ring-black"
                        >
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            scope="col"
                                            class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-6"
                                        >
                                            Fecha y Hora
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                                        >
                                            Usuario
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                                        >
                                            Acción Relevante
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                                        >
                                            Resumen
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                                        >
                                            Entidad de Destino
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-gray-200 bg-white font-mono text-xs"
                                >
                                    <tr v-if="logs.length === 0">
                                        <td
                                            colspan="5"
                                            class="py-10 text-center text-sm text-gray-500"
                                        >
                                            <Database
                                                class="mx-auto mb-2 h-8 w-8 text-gray-300"
                                            />
                                            No se encontraron registros de
                                            auditoría.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="log in logs"
                                        :key="log.id"
                                        class="hover:bg-gray-50"
                                    >
                                        <td
                                            class="py-3 pr-3 pl-4 whitespace-nowrap text-gray-500 sm:pl-6"
                                        >
                                            {{ formatDate(log.at) }}
                                        </td>
                                        <td
                                            class="px-3 py-3 font-sans text-sm whitespace-nowrap text-gray-900"
                                        >
                                            <div class="font-medium">
                                                {{ log.user_name }}
                                            </div>
                                            <div
                                                class="text-xs font-normal text-gray-500"
                                            >
                                                {{ log.user_email }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <span
                                                class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-600/20 ring-inset"
                                            >
                                                {{ log.action }}
                                            </span>
                                        </td>
                                        <td
                                            class="max-w-xs px-3 py-3 whitespace-nowrap text-gray-600"
                                        >
                                            <span
                                                v-if="log.change_summary"
                                                class="block truncate"
                                                :title="log.change_summary"
                                            >
                                                {{ log.change_summary }}
                                            </span>
                                            <span v-else class="text-gray-400"
                                                >Sin resumen</span
                                            >
                                        </td>
                                        <td
                                            class="px-3 py-3 whitespace-nowrap text-gray-600"
                                        >
                                            <Link
                                                v-if="log.entity_url"
                                                :href="log.entity_url"
                                                class="inline-flex items-center gap-1 rounded-md px-1 py-0.5 font-sans text-sm font-medium text-indigo-700 hover:bg-indigo-50 hover:text-indigo-900 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
                                            >
                                                <span>{{
                                                    log.entity_label
                                                }}</span>
                                                <ExternalLink
                                                    class="h-3.5 w-3.5"
                                                />
                                            </Link>
                                            <div v-else>
                                                <span>{{
                                                    log.entity_label
                                                }}</span>
                                                <span
                                                    v-if="
                                                        targetStatusLabel(
                                                            log.target_status,
                                                        )
                                                    "
                                                    class="ml-2 inline-flex rounded-md bg-gray-100 px-2 py-0.5 font-sans text-[11px] font-medium text-gray-600"
                                                >
                                                    {{
                                                        targetStatusLabel(
                                                            log.target_status,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
