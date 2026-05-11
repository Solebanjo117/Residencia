<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen,
    CalendarClock,
    AlertCircle,
    FileStack,
    ArrowRight,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
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
        minute: '2-digit',
    });
};

const isWindowOpen = (opensAt: string) => {
    return new Date() >= new Date(opensAt);
};
</script>

<template>
    <Head title="Panel de Docente" />

    <AppLayout
        :breadcrumbs="[{ title: 'Panel de Control', href: '/dashboard' }]"
    >
        <div class="mx-auto max-w-7xl px-6 py-8">
            <!-- Header section -->
            <div
                class="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">
                        Panel de Control Docente
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Bienvenido al sistema. Tu carga actual es para el
                        semestre:
                        <span class="font-semibold text-slate-800">{{
                            semester?.name || 'No disponible'
                        }}</span>
                    </p>
                </div>

                <Link
                    href="/files/manager"
                    class="inline-flex items-center rounded-lg border border-transparent bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                >
                    <FileStack class="mr-2 h-5 w-5" />
                    Mi Expediente / Entregas
                </Link>
            </div>

            <div
                v-if="!semester"
                class="mb-8 rounded-md border-l-4 border-yellow-400 bg-yellow-50 p-4"
            >
                <div class="flex">
                    <AlertCircle class="h-5 w-5 text-yellow-400" />
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            No hay un semestre activo configurado en el sistema
                            actualmente.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stats & Progress -->
            <div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Progress Card -->
                <div
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2"
                >
                    <h2
                        class="mb-4 flex items-center text-base font-semibold text-slate-900"
                    >
                        <FileStack class="mr-2 h-5 w-5 text-indigo-500" />
                        Progreso de Entregas (Semestre Actual)
                    </h2>
                    <div class="mb-2 flex items-end justify-between">
                        <div>
                            <span class="text-3xl font-bold text-slate-900"
                                >{{ progress.percentage }}%</span
                            >
                            <span
                                class="ml-2 text-sm font-medium text-slate-500"
                                >Completado</span
                            >
                        </div>
                        <div class="text-sm font-medium text-slate-600">
                            {{ progress.submitted }} de
                            {{ progress.total }} obligatorios
                        </div>
                    </div>
                    <div class="h-3 w-full rounded-full bg-slate-200">
                        <div
                            class="h-3 rounded-full bg-indigo-600 transition-all duration-500"
                            :style="{ width: `${progress.percentage}%` }"
                        ></div>
                    </div>
                    <p class="mt-4 text-xs text-slate-500">
                        El cálculo de progreso se basa en los lineamientos
                        requeridos por tu departamento.
                    </p>
                </div>

                <!-- Fast Info -->
                <div
                    class="flex flex-col justify-center rounded-xl border border-transparent bg-gradient-to-br from-blue-600 to-indigo-700 p-6 text-center text-white shadow-sm"
                >
                    <div class="mb-2 text-blue-200">
                        Total Materias/Grupos Asignados
                    </div>
                    <div class="mb-4 text-5xl font-extrabold tracking-tight">
                        {{ teachingLoads.length }}
                    </div>
                    <Link
                        href="#cargas"
                        class="flex items-center justify-center text-sm font-medium text-white hover:text-blue-100"
                    >
                        Ver detalle <ArrowRight class="ml-1 h-4 w-4" />
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <!-- Calendar / Windows -->
                <div>
                    <h2
                        class="mb-4 flex items-center text-lg font-bold text-slate-900"
                    >
                        <CalendarClock class="mr-2 h-5 w-5 text-blue-500" />
                        Fechas Límite (Ventanas)
                    </h2>
                    <div
                        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div
                            v-if="upcomingWindows.length === 0"
                            class="p-6 text-center text-sm text-slate-500"
                        >
                            No hay ventanas de entrega programadas o activas en
                            este momento.
                        </div>
                        <ul v-else class="divide-y divide-slate-200">
                            <li
                                v-for="win in upcomingWindows"
                                :key="win.id"
                                class="p-4 transition hover:bg-slate-50"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div
                                            class="mb-1 flex items-center gap-2"
                                        >
                                            <span
                                                class="rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase"
                                                :class="
                                                    isWindowOpen(win.opens_at)
                                                        ? 'bg-green-100 text-green-700'
                                                        : 'bg-yellow-100 text-yellow-800'
                                                "
                                            >
                                                {{
                                                    isWindowOpen(win.opens_at)
                                                        ? 'ABIERTA'
                                                        : 'PROGRAMADA'
                                                }}
                                            </span>
                                        </div>
                                        <h3
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{ win.evidence_item.name }}
                                        </h3>
                                        <div
                                            class="mt-1 flex items-center gap-4 text-xs text-slate-500"
                                        >
                                            <span
                                                ><strong
                                                    class="font-medium text-slate-700"
                                                    >Abre:</strong
                                                >
                                                {{
                                                    formatDate(win.opens_at)
                                                }}</span
                                            >
                                            <span
                                                ><strong
                                                    class="font-medium text-slate-700"
                                                    >Cierra:</strong
                                                >
                                                {{
                                                    formatDate(win.closes_at)
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Teaching Loads -->
                <div id="cargas">
                    <h2
                        class="mb-4 flex items-center text-lg font-bold text-slate-900"
                    >
                        <BookOpen class="mr-2 h-5 w-5 text-indigo-500" />
                        Carga Académica y Grupos
                    </h2>
                    <div
                        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div
                            v-if="teachingLoads.length === 0"
                            class="p-6 text-center text-sm text-slate-500"
                        >
                            No tienes materias o grupos asignados para este
                            semestre.
                        </div>
                        <ul v-else class="divide-y divide-slate-200">
                            <li
                                v-for="load in teachingLoads"
                                :key="load.id"
                                class="p-4 transition hover:bg-slate-50"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{ load.subject.name }}
                                        </h3>
                                        <div
                                            class="mt-1 flex items-center gap-3 text-xs text-slate-500"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1.5"
                                                ><span
                                                    class="h-2 w-2 rounded-full bg-blue-400"
                                                ></span>
                                                Clave:
                                                {{ load.subject.code }}</span
                                            >
                                            <span
                                                class="inline-flex items-center gap-1.5"
                                                ><span
                                                    class="h-2 w-2 rounded-full bg-indigo-400"
                                                ></span>
                                                Grupo:
                                                {{ load.group_name }}</span
                                            >
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div
                                            class="mb-1 rounded border bg-white px-2 py-0.5 text-xs font-medium text-slate-600 shadow-sm"
                                        >
                                            Alumnos:
                                            {{ load.student_count || 0 }}
                                        </div>
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
