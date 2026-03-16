<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { 
    CheckCircle, 
    Clock, 
    AlertCircle, 
    Eye,
    ChevronRight,
    Search,
    UserCircle2
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

declare const route: any;

const props = defineProps<{
    teachers: {
        id: number;
        name: string;
        email: string;
        total_pending: number;
        pending_groups: {
            load_id: number;
            subject: string;
            group: string;
            pending_count: number;
        }[];
    }[];
    semester: {
        id: number;
        name: string;
    } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pendientes Revisión', href: '/oficina/revisiones' },
];

const searchQuery = ref('');

const filteredTeachers = computed(() => {
    if (!searchQuery.value) return props.teachers;
    const lowerQ = searchQuery.value.toLowerCase();
    return props.teachers.filter(t => 
        t.name.toLowerCase().includes(lowerQ) || 
        t.email.toLowerCase().includes(lowerQ)
    );
});
</script>

<template>
    <Head title="Pendientes Revisión" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-8 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <!-- Header section -->
            <div class="mb-8 md:flex md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                        Aprobación de Evidencias
                    </h1>
                    <p class="mt-2 text-sm text-gray-500 flex items-center gap-2">
                        <Clock class="w-4 h-4" />
                        Semestre Activo: 
                        <span class="font-semibold text-gray-900">{{ semester?.name ?? 'Ninguno' }}</span>
                    </p>
                </div>
                
                <div class="mt-4 md:mt-0 flex flex-col md:flex-row gap-4 items-center">
                    <div class="relative w-full md:w-64">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <Search class="h-4 w-4 text-gray-400" />
                        </div>
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="block w-full pl-10 border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm rounded-lg"
                            placeholder="Buscar docente..."
                        />
                    </div>
                </div>
            </div>

            <div v-if="!semester" class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 flex gap-3">
                <AlertCircle class="w-5 h-5 text-yellow-600 shrink-0 mt-0.5" />
                <div>
                    <h3 class="text-sm font-medium text-yellow-800">No hay semestre activo</h3>
                    <p class="mt-2 text-sm text-yellow-700">
                        Vaya a Configuración y active un Semestre Académico para que los docentes puedan realizar envíos.
                    </p>
                </div>
            </div>
            
            <div v-else-if="teachers.length === 0" class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 py-16 px-6 text-center">
                <div class="rounded-full bg-green-100 p-3 mb-4">
                    <CheckCircle class="h-8 w-8 text-green-600" />
                </div>
                <h3 class="text-lg font-medium text-gray-900">Todo al día</h3>
                <p class="mt-2 text-sm text-gray-500 max-w-sm">
                    No hay evidencias pendientes en estado "SUBMITTED" esperando revisión de jefatura.
                </p>
            </div>

            <!-- Teachers List -->
            <div v-else class="grid gap-6 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="teacher in filteredTeachers"
                    :key="teacher.id"
                    class="relative flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-all hover:shadow-md"
                >
                    <div class="p-5 border-b border-gray-100">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="bg-blue-50 p-2.5 rounded-full ring-4 ring-white shrink-0">
                                    <UserCircle2 class="w-6 h-6 text-blue-600" />
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-base font-semibold leading-6 text-gray-900 truncate">
                                        {{ teacher.name }}
                                    </h3>
                                    <p class="text-sm text-gray-500 truncate">
                                        {{ teacher.email }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 p-5 bg-gray-50/50">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-sm font-medium text-gray-700">
                                Por revisar:
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/20">
                                {{ teacher.total_pending }} archivos
                            </span>
                        </div>
                        
                        <div class="space-y-3">
                            <div v-for="grp in teacher.pending_groups.slice(0, 3)" :key="grp.load_id" class="text-sm border-l-2 border-amber-400 pl-3">
                                <p class="text-gray-900 font-medium truncate" :title="grp.subject">
                                    {{ grp.subject }}
                                </p>
                                <p class="text-gray-500 text-xs">
                                    Grupo: {{ grp.group }} &middot; <span class="text-amber-600">{{ grp.pending_count }} pt.</span>
                                </p>
                            </div>
                            <div v-if="teacher.pending_groups.length > 3" class="text-xs text-gray-500 font-medium pl-3">
                                + {{ teacher.pending_groups.length - 3 }} materias más...
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 bg-gray-50 px-5 py-3">
                        <Link
                            :href="route('oficina.revisiones.show', teacher.id)"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        >
                            Auditar Portafolio
                            <ChevronRight class="h-4 w-4 text-gray-400" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
