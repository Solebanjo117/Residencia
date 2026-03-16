<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { ref, watch } from 'vue';
import { ShieldAlert, Search, Database } from 'lucide-vue-next';

const props = defineProps<{
    logs: {
        id: number;
        action: string;
        entity_type: string;
        entity_id: number;
        at: string;
        user_name: string;
        user_email: string;
    }[]
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Auditoría del Sistema', href: '/admin/audits' },
];

const searchQuery = ref('');

let timeoutToken: any = null;

watch(searchQuery, (newVal) => {
    clearTimeout(timeoutToken);
    timeoutToken = setTimeout(() => {
        router.get('/admin/audits', { search: newVal }, { preserveState: true, replace: true });
    }, 400);
});

function formatDate(dateStr: string) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
</script>

<template>
    <Head title="Auditoría" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-8 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-8 md:flex md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <ShieldAlert class="w-8 h-8 text-indigo-600" />
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Registro de Auditoría</h1>
                        <p class="mt-1 text-sm text-gray-500">
                            Trazabilidad inamovible de los eventos del sistema. Últimos 200 registros.
                        </p>
                    </div>
                </div>
                
                <div class="mt-4 md:mt-0 relative w-full md:w-72">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Search class="h-4 w-4 text-gray-400" />
                    </div>
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="block w-full pl-10 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm rounded-lg"
                        placeholder="Buscar por usuario o acción..."
                    />
                </div>
            </div>

            <!-- Table -->
            <div class="mt-8 flow-root">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 rounded-2xl">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Fecha y Hora</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Usuario</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Acción Relevante</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Entidad de Destino</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white font-mono text-xs">
                                    <tr v-if="logs.length === 0">
                                        <td colspan="4" class="py-10 text-center text-sm text-gray-500">
                                            <Database class="mx-auto h-8 w-8 text-gray-300 mb-2" />
                                            No se encontraron registros de auditoría.
                                        </td>
                                    </tr>
                                    <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50">
                                        <td class="whitespace-nowrap py-3 pl-4 pr-3 text-gray-500 sm:pl-6">
                                            {{ formatDate(log.at) }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-gray-900 font-sans text-sm">
                                            <div class="font-medium">{{ log.user_name }}</div>
                                            <div class="text-gray-500 font-normal text-xs">{{ log.user_email }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3">
                                            <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                                                {{ log.action }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-gray-600">
                                            <span v-if="log.entity_type">{{ log.entity_type }} #{{ log.entity_id }}</span>
                                            <span v-else>-</span>
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
