<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuth } from '@/composables/useAuth';
import { ArrowUpRight, CalendarClock, CircleAlert, LayoutGrid, Rocket, ShieldCheck } from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';

interface OverviewMetric {
    key: string;
    label: string;
    value: number | string;
    description: string;
    tone: 'blue' | 'green' | 'amber' | 'red' | 'slate';
}

interface QuickAction {
    title: string;
    description: string;
    href: string;
}

interface UpcomingDeadline {
    id: number;
    item_name: string;
    opens_at: string | null;
    closes_at: string | null;
    is_open: boolean;
}

const props = defineProps<{
    semester: {
        id: number;
        name: string;
        status: string;
    } | null;
    overview: OverviewMetric[];
    quickActions: QuickAction[];
    upcomingDeadlines: UpcomingDeadline[];
}>();

const { user, isDocente, isJefeOficina, isJefeDepto } = useAuth();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const roleLabel = computed(() => {
    if (isDocente.value) return 'Docente';
    if (isJefeOficina.value) return 'Jefe de Oficina';
    if (isJefeDepto.value) return 'Jefe de Departamento';
    return 'Usuario';
});

const roleMessage = computed(() => {
    if (isDocente.value) return 'Monitorea tus entregas y responde a tiempo las ventanas activas.';
    if (isJefeOficina.value) return 'Prioriza revisiones pendientes y mantén continuidad del dictamen institucional.';
    if (isJefeDepto.value) return 'Controla configuración de ventanas y cobertura de requerimientos por departamento.';
    return 'Consulta los indicadores principales del sistema.';
});

const toneClasses: Record<OverviewMetric['tone'], string> = {
    blue: 'border-blue-200 bg-blue-50 text-blue-700',
    green: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    amber: 'border-amber-200 bg-amber-50 text-amber-700',
    red: 'border-rose-200 bg-rose-50 text-rose-700',
    slate: 'border-slate-200 bg-slate-50 text-slate-700',
};

const formatDate = (value: string | null) => {
    if (!value) return 'Sin fecha';

    return new Date(value).toLocaleDateString('es-ES', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const daysRemaining = (closesAt: string | null): string => {
    if (!closesAt) return 'Sin fecha de cierre';

    const ms = new Date(closesAt).getTime() - Date.now();
    const days = Math.ceil(ms / (1000 * 60 * 60 * 24));

    if (days <= 0) return 'Cierra hoy';
    if (days === 1) return 'Cierra en 1 día';
    return `Cierra en ${days} días`;
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-6 py-8">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-700 p-6 text-white shadow-sm">
                <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold tracking-wide uppercase">
                            <ShieldCheck class="h-4 w-4" />
                            {{ roleLabel }}
                        </div>
                        <h1 class="text-2xl font-bold leading-tight md:text-3xl">Panel Institucional ASAD</h1>
                        <p class="mt-2 max-w-2xl text-sm text-slate-200">{{ roleMessage }}</p>
                    </div>

                    <div class="rounded-xl border border-white/20 bg-white/10 p-4 text-sm backdrop-blur">
                        <div class="text-slate-200">Semestre activo</div>
                        <div class="mt-1 text-lg font-semibold">{{ props.semester?.name ?? 'No configurado' }}</div>
                    </div>
                </div>
            </section>

            <section v-if="!props.semester" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
                <div class="flex items-start gap-3">
                    <CircleAlert class="mt-0.5 h-5 w-5" />
                    <div>
                        <p class="font-semibold">No hay semestre activo configurado</p>
                        <p class="text-sm">Puedes avanzar con configuración base en semestres, ventanas y requerimientos antes de operar el flujo completo.</p>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-6">
                <article
                    v-for="metric in props.overview"
                    :key="metric.key"
                    class="rounded-xl border p-4 shadow-sm"
                    :class="toneClasses[metric.tone]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="text-xs font-semibold tracking-wide uppercase">{{ metric.label }}</div>
                        <LayoutGrid class="h-4 w-4" />
                    </div>
                    <div class="mt-3 text-3xl leading-none font-bold">{{ metric.value }}</div>
                    <p class="mt-3 text-xs leading-relaxed">{{ metric.description }}</p>
                </article>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="flex items-center gap-2 text-lg font-semibold text-slate-900">
                            <Rocket class="h-5 w-5 text-slate-600" />
                            Acciones Rápidas
                        </h2>
                    </div>

                    <div class="grid gap-3">
                        <Link
                            v-for="action in props.quickActions"
                            :key="action.href"
                            :href="action.href"
                            class="group rounded-xl border border-slate-200 bg-slate-50 p-4 transition hover:border-slate-300 hover:bg-slate-100"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-slate-900">{{ action.title }}</h3>
                                    <p class="mt-1 text-sm text-slate-600">{{ action.description }}</p>
                                </div>
                                <ArrowUpRight class="h-4 w-4 text-slate-500 transition group-hover:text-slate-700" />
                            </div>
                        </Link>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="flex items-center gap-2 text-lg font-semibold text-slate-900">
                            <CalendarClock class="h-5 w-5 text-slate-600" />
                            Próximos Cierres
                        </h2>
                    </div>

                    <div v-if="props.upcomingDeadlines.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">
                        No hay ventanas activas o programadas para mostrar en este momento.
                    </div>

                    <ul v-else class="space-y-3">
                        <li v-for="deadline in props.upcomingDeadlines" :key="deadline.id" class="rounded-xl border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ deadline.item_name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Apertura: {{ formatDate(deadline.opens_at) }}</p>
                                    <p class="text-xs text-slate-500">Cierre: {{ formatDate(deadline.closes_at) }}</p>
                                </div>
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold" :class="deadline.is_open ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                    {{ deadline.is_open ? 'Abierta' : 'Programada' }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs font-medium text-slate-700">{{ daysRemaining(deadline.closes_at) }}</p>
                        </li>
                    </ul>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
