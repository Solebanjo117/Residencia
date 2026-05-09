<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Search, SlidersHorizontal } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface SemesterOption {
    id: number;
    name: string;
}

interface ReportRow {
    teacher_id: number;
    teacher_name: string;
    teacher_email: string;
    loads_count: number;
    total_submissions: number;
    draft_count: number;
    submitted_count: number;
    office_approved_count: number;
    final_approved_count: number;
    rejected_count: number;
    na_count: number;
    late_count: number;
    delayed_count: number;
    compliance: number;
}

const props = defineProps<{
    rows: ReportRow[];
    summary: {
        teachers: number;
        submissions: number;
        submitted: number;
        office_approved: number;
        final_approved: number;
        rejected: number;
        late: number;
        na: number;
    };
    filters: {
        semester_id: number | null;
        search: string;
        status_focus: string;
    };
    semesters: SemesterOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reportes',
        href: '/oficina/reportes',
    },
];

const selectedSemester = ref<number | null>(props.filters.semester_id);
const search = ref(props.filters.search ?? '');
const statusFocus = ref(props.filters.status_focus ?? 'all');

const statusOptions = [
    { value: 'all', label: 'Todos' },
    { value: 'pending_review', label: 'Con pendientes de revisión' },
    { value: 'office_approved', label: 'Aprobadas' },
    { value: 'final_approved', label: 'Liberadas' },
    { value: 'late', label: 'Extemporáneas' },
    { value: 'no_apply', label: 'Con no aplica' },
    { value: 'delayed', label: 'Con atraso' },
    { value: 'no_submissions', label: 'Sin entregas' },
];

const reportQuery = computed(() => ({
    semester_id: selectedSemester.value,
    search: search.value,
    status_focus: statusFocus.value,
}));

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    if (selectedSemester.value !== null)
        params.set('semester_id', String(selectedSemester.value));
    if (search.value.trim() !== '') params.set('search', search.value.trim());
    if (statusFocus.value !== 'all')
        params.set('status_focus', statusFocus.value);
    params.set('export', 'csv');

    return `/oficina/reportes?${params.toString()}`;
});

const applyFilters = () => {
    router.get('/oficina/reportes', reportQuery.value, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    selectedSemester.value = props.filters.semester_id;
    search.value = '';
    statusFocus.value = 'all';
    applyFilters();
};
</script>

<template>
    <Head title="Reportes administrativos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-6 py-8">
            <section
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            >
                <div
                    class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">
                            Reportes Docentes
                        </h1>
                        <p class="mt-1 text-sm text-slate-600">
                            Consolidado operativo de revisión, liberación final,
                            no aplica y entrega extemporánea.
                        </p>
                    </div>
                    <a
                        :href="exportUrl"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        <Download class="h-4 w-4" />
                        Exportar CSV
                    </a>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-4 xl:grid-cols-8">
                <article
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div
                        class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                    >
                        Docentes
                    </div>
                    <div class="mt-2 text-3xl font-bold text-slate-900">
                        {{ props.summary.teachers }}
                    </div>
                </article>
                <article
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div
                        class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                    >
                        Entregas
                    </div>
                    <div class="mt-2 text-3xl font-bold text-slate-900">
                        {{ props.summary.submissions }}
                    </div>
                </article>
                <article
                    class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm"
                >
                    <div
                        class="text-xs font-semibold tracking-wide text-amber-700 uppercase"
                    >
                        En revisión
                    </div>
                    <div class="mt-2 text-3xl font-bold text-amber-800">
                        {{ props.summary.submitted }}
                    </div>
                </article>
                <article
                    class="rounded-xl border border-green-200 bg-green-50 p-4 shadow-sm"
                >
                    <div
                        class="text-xs font-semibold tracking-wide text-green-700 uppercase"
                    >
                        Aprobadas
                    </div>
                    <div class="mt-2 text-3xl font-bold text-green-800">
                        {{ props.summary.office_approved }}
                    </div>
                </article>
                <article
                    class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm"
                >
                    <div
                        class="text-xs font-semibold tracking-wide text-emerald-700 uppercase"
                    >
                        Liberadas
                    </div>
                    <div class="mt-2 text-3xl font-bold text-emerald-800">
                        {{ props.summary.final_approved }}
                    </div>
                </article>
                <article
                    class="rounded-xl border border-rose-200 bg-rose-50 p-4 shadow-sm"
                >
                    <div
                        class="text-xs font-semibold tracking-wide text-rose-700 uppercase"
                    >
                        Rechazadas
                    </div>
                    <div class="mt-2 text-3xl font-bold text-rose-800">
                        {{ props.summary.rejected }}
                    </div>
                </article>
                <article
                    class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm"
                >
                    <div
                        class="text-xs font-semibold tracking-wide text-amber-700 uppercase"
                    >
                        Extemporáneas
                    </div>
                    <div class="mt-2 text-3xl font-bold text-amber-800">
                        {{ props.summary.late }}
                    </div>
                </article>
                <article
                    class="rounded-xl border border-slate-200 bg-slate-50 p-4 shadow-sm"
                >
                    <div
                        class="text-xs font-semibold tracking-wide text-slate-600 uppercase"
                    >
                        No aplica
                    </div>
                    <div class="mt-2 text-3xl font-bold text-slate-800">
                        {{ props.summary.na }}
                    </div>
                </article>
            </section>

            <section
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="mb-4 flex items-center gap-2 text-slate-900">
                    <SlidersHorizontal class="h-4 w-4" />
                    <h2 class="text-sm font-semibold tracking-wide uppercase">
                        Filtros
                    </h2>
                </div>

                <div class="grid gap-3 md:grid-cols-4">
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >Semestre</label
                        >
                        <select
                            v-model="selectedSemester"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option :value="null">Selecciona semestre</option>
                            <option
                                v-for="semester in props.semesters"
                                :key="semester.id"
                                :value="semester.id"
                            >
                                {{ semester.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >Búsqueda</label
                        >
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-2.5 left-2.5 h-4 w-4 text-slate-400"
                            />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Nombre o correo"
                                class="w-full rounded-lg border border-slate-300 py-2 pr-3 pl-9 text-sm"
                            />
                        </div>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >Vista</label
                        >
                        <select
                            v-model="statusFocus"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option
                                v-for="status in statusOptions"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button
                            type="button"
                            @click="applyFilters"
                            class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-900"
                        >
                            Aplicar
                        </button>
                        <button
                            type="button"
                            @click="resetFilters"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                        >
                            Limpiar
                        </button>
                    </div>
                </div>
            </section>

            <section
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col"
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-wide text-slate-600 uppercase"
                                >
                                    Docente
                                </th>
                                <th scope="col"
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-slate-600 uppercase"
                                >
                                    Cargas
                                </th>
                                <th scope="col"
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-slate-600 uppercase"
                                >
                                    Entregas
                                </th>
                                <th scope="col"
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-slate-600 uppercase"
                                >
                                    Borrador
                                </th>
                                <th scope="col"
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-slate-600 uppercase"
                                >
                                    Revisión
                                </th>
                                <th scope="col"
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-slate-600 uppercase"
                                >
                                    Aprobadas
                                </th>
                                <th scope="col"
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-slate-600 uppercase"
                                >
                                    Liberadas
                                </th>
                                <th scope="col"
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-slate-600 uppercase"
                                >
                                    Rechazadas
                                </th>
                                <th scope="col"
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-slate-600 uppercase"
                                >
                                    NA
                                </th>
                                <th scope="col"
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-slate-600 uppercase"
                                >
                                    Ext.
                                </th>
                                <th scope="col"
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-slate-600 uppercase"
                                >
                                    Atraso
                                </th>
                                <th scope="col"
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-slate-600 uppercase"
                                >
                                    Cumplimiento
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr
                                v-for="row in props.rows"
                                :key="row.teacher_id"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-4 py-3">
                                    <p
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{ row.teacher_name }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ row.teacher_email }}
                                    </p>
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-sm text-slate-700"
                                >
                                    {{ row.loads_count }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-sm text-slate-700"
                                >
                                    {{ row.total_submissions }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-sm text-slate-700"
                                >
                                    {{ row.draft_count }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-sm font-semibold text-amber-700"
                                >
                                    {{ row.submitted_count }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-sm font-semibold text-green-700"
                                >
                                    {{ row.office_approved_count }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-sm font-semibold text-emerald-700"
                                >
                                    {{ row.final_approved_count }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-sm font-semibold text-rose-700"
                                >
                                    {{ row.rejected_count }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-sm text-slate-700"
                                >
                                    {{ row.na_count }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-sm font-semibold text-amber-700"
                                >
                                    {{ row.late_count }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-sm text-slate-700"
                                >
                                    {{ row.delayed_count }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span
                                        class="inline-flex min-w-16 justify-center rounded-full px-2 py-1 text-xs font-semibold"
                                        :class="
                                            row.compliance >= 80
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : row.compliance >= 50
                                                  ? 'bg-amber-100 text-amber-700'
                                                  : 'bg-rose-100 text-rose-700'
                                        "
                                    >
                                        {{ row.compliance }}%
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="props.rows.length === 0"
                    class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500"
                >
                    No hay registros para los filtros seleccionados.
                </div>
            </section>
        </div>
    </AppLayout>
</template>
