<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { BookOpen, CalendarClock, AlertCircle, FileStack, ArrowRight } from 'lucide-vue-next';

const props = defineProps<{
    semester: any | null;
    teachingLoads: any[];
    upcomingWindows: any[];
    progress: {
        total: number;
        submitted: number;
        percentage: number;
    };
}>();

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('es-ES', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const isWindowOpen = (opensAt: string) => {
    return new Date() >= new Date(opensAt);
};
</script>

<template>
    <Head title="Panel de Docente" />

    <AppLayout :breadcrumbs="[{ title: 'Panel de Control', href: '/dashboard' }]">
        <div class="px-6 py-8 mx-auto max-w-7xl">
            <!-- Header section -->
            <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Panel de Control Docente</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Bienvenido al sistema. Tu carga actual es para el semestre: 
                        <span class="font-semibold text-gray-800">{{ semester?.name || 'No disponible' }}</span>
                    </p>
                </div>
                
                <Link
                    href="/files/manager"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-sm text-white shadow-sm hover:bg-blue-700 transition"
                >
                    <FileStack class="w-5 h-5 mr-2" />
                    Mi Expediente / Entregas
                </Link>
            </div>

            <div v-if="!semester" class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-md mb-8">
                <div class="flex">
                    <AlertCircle class="h-5 w-5 text-yellow-400" />
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">No hay un semestre activo configurado en el sistema actualmente.</p>
                    </div>
                </div>
            </div>

            <!-- Stats & Progress -->
            <div class="grid grid-cols-1 gap-6 mb-8 lg:grid-cols-3">
                
                <!-- Progress Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 lg:col-span-2">
                    <h2 class="text-base font-semibold text-gray-900 mb-4 flex items-center">
                        <FileStack class="w-5 h-5 mr-2 text-indigo-500" />
                        Progreso de Entregas (Semestre Actual)
                    </h2>
                    <div class="flex items-end justify-between mb-2">
                        <div>
                            <span class="text-3xl font-bold text-gray-900">{{ progress.percentage }}%</span>
                            <span class="text-sm font-medium text-gray-500 ml-2">Completado</span>
                        </div>
                        <div class="text-sm font-medium text-gray-600">
                            {{ progress.submitted }} de {{ progress.total }} obligatorios
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div 
                            class="bg-indigo-600 h-3 rounded-full transition-all duration-500"
                            :style="{ width: `${progress.percentage}%` }"
                        ></div>
                    </div>
                    <p class="mt-4 text-xs text-gray-500">El cálculo de progreso se basa en los lineamientos requeridos por tu departamento.</p>
                </div>

                <!-- Fast Info -->
                <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl shadow-sm border border-transparent p-6 text-white text-center flex flex-col justify-center">
                    <div class="text-blue-200 mb-2">Total Materias/Grupos Asignados</div>
                    <div class="text-5xl font-extrabold tracking-tight mb-4">
                        {{ teachingLoads.length }}
                    </div>
                    <Link href="#cargas" class="text-sm font-medium text-white hover:text-blue-100 flex items-center justify-center">
                        Ver detalle <ArrowRight class="w-4 h-4 ml-1" />
                    </Link>
                </div>

            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                
                <!-- Calendar / Windows -->
                <div>
                    <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <CalendarClock class="w-5 h-5 mr-2 text-blue-500" />
                        Fechas Límite (Ventanas)
                    </h2>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div v-if="upcomingWindows.length === 0" class="p-6 text-center text-sm text-gray-500">
                            No hay ventanas de entrega programadas o activas en este momento.
                        </div>
                        <ul v-else class="divide-y divide-gray-200">
                            <li v-for="win in upcomingWindows" :key="win.id" class="p-4 hover:bg-gray-50 transition">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span 
                                                class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-full tracking-wider"
                                                :class="isWindowOpen(win.opens_at) ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-800'"
                                            >
                                                {{ isWindowOpen(win.opens_at) ? 'ABIERTA' : 'PROGRAMADA' }}
                                            </span>
                                        </div>
                                        <h3 class="text-sm font-semibold text-gray-900">{{ win.evidence_item.name }}</h3>
                                        <div class="mt-1 text-xs text-gray-500 flex items-center gap-4">
                                            <span><strong class="font-medium text-gray-700">Abre:</strong> {{ formatDate(win.opens_at) }}</span>
                                            <span><strong class="font-medium text-gray-700">Cierra:</strong> {{ formatDate(win.closes_at) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Teaching Loads -->
                <div id="cargas">
                    <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <BookOpen class="w-5 h-5 mr-2 text-indigo-500" />
                        Carga Académica y Grupos
                    </h2>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div v-if="teachingLoads.length === 0" class="p-6 text-center text-sm text-gray-500">
                            No tienes materias o grupos asignados para este semestre.
                        </div>
                        <ul v-else class="divide-y divide-gray-200">
                            <li v-for="load in teachingLoads" :key="load.id" class="p-4 hover:bg-gray-50 transition">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900">{{ load.subject.name }}</h3>
                                        <div class="mt-1 flex items-center gap-3 text-xs text-gray-500">
                                            <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-blue-400"></span> Clave: {{ load.subject.code }}</span>
                                            <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-indigo-400"></span> Grupo: {{ load.group_name }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs font-medium text-gray-600 mb-1 border px-2 py-0.5 rounded shadow-sm bg-white">Alumnos: {{ load.student_count || 0 }}</div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
