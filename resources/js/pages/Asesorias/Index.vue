<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarClock,
    Edit2,
    Plus,
    Printer,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

type ScheduleRow = {
    id: number;
    materia: string;
    docente: string;
    L: string;
    M: string;
    Mi: string;
    J: string;
    V: string;
    carrera: string;
    aula: string | null;
};

type AdvisorySchedule = {
    id: number;
    teacher_user_id: number;
    teacher_name: string | null;
    teaching_load_id: number | null;
    semester_id: number;
    day_of_week: number;
    day_name: string;
    start_time: string;
    end_time: string;
    location: string | null;
    subject_name: string | null;
    group_name: string | null;
};

type Teacher = {
    id: number;
    name: string;
    email: string;
};

type TeachingLoadOption = {
    id: number;
    teacher_user_id: number;
    subject_name: string | null;
    group_name: string | null;
};

const props = defineProps<{
    rows: ScheduleRow[];
    schedules: AdvisorySchedule[];
    teachers: Teacher[];
    teachingLoads: TeachingLoadOption[];
    semesters: string[];
    currentSemester: string;
    currentSemesterId: number | null;
    canManage: boolean;
}>();

const dayCols = [
    { key: 'L', label: 'LUNES' },
    { key: 'M', label: 'MARTES' },
    { key: 'Mi', label: 'MIÉRCOLES' },
    { key: 'J', label: 'JUEVES' },
    { key: 'V', label: 'VIERNES' },
] as const;

const dayOptions = [
    { value: 1, label: 'Lunes' },
    { value: 2, label: 'Martes' },
    { value: 3, label: 'Miércoles' },
    { value: 4, label: 'Jueves' },
    { value: 5, label: 'Viernes' },
];

const search = ref('');
const semester = ref(props.currentSemester);
const printTableRef = ref<HTMLElement | null>(null);
const showModal = ref(false);
const editingSchedule = ref<AdvisorySchedule | null>(null);

const form = useForm({
    teacher_user_id: '',
    teaching_load_id: '',
    semester_id: props.currentSemesterId ?? '',
    day_of_week: 1,
    start_time: '17:00',
    end_time: '18:00',
    location: '',
});

const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.rows.filter((row) => {
        if (!q) return true;

        return (
            row.docente.toLowerCase().includes(q) ||
            row.materia.toLowerCase().includes(q) ||
            row.carrera.toLowerCase().includes(q) ||
            (row.aula && row.aula.toLowerCase().includes(q))
        );
    });
});

const availableLoads = computed(() => {
    const teacherId = Number(form.teacher_user_id);

    if (!teacherId) return [];

    return props.teachingLoads.filter(
        (load) => load.teacher_user_id === teacherId,
    );
});

const isEditing = computed(() => Boolean(editingSchedule.value));
const modalTitle = computed(() =>
    isEditing.value
        ? 'Editar horario de asesoría'
        : 'Registrar horario de asesoría',
);

watch(semester, (newVal) => {
    if (newVal !== props.currentSemester) {
        router.get(
            '/asesorias-horarios',
            { semester: newVal },
            { preserveState: true },
        );
    }
});

watch(
    () => form.teacher_user_id,
    () => {
        const selectedLoad = props.teachingLoads.find(
            (load) => String(load.id) === String(form.teaching_load_id),
        );

        if (
            selectedLoad &&
            String(selectedLoad.teacher_user_id) !==
                String(form.teacher_user_id)
        ) {
            form.teaching_load_id = '';
        }
    },
);

function formatLoadOption(load: TeachingLoadOption) {
    return load.group_name
        ? `${load.subject_name ?? 'Materia'} (${load.group_name})`
        : (load.subject_name ?? 'Materia');
}

function scheduleTitle(schedule: AdvisorySchedule) {
    if (!schedule.subject_name) {
        return 'Asesoria general';
    }

    return schedule.group_name
        ? `${schedule.subject_name} (${schedule.group_name})`
        : schedule.subject_name;
}

function resetForm() {
    form.clearErrors();
    form.reset();
    form.teacher_user_id = '';
    form.teaching_load_id = '';
    form.semester_id = props.currentSemesterId ?? '';
    form.day_of_week = 1;
    form.start_time = '17:00';
    form.end_time = '18:00';
    form.location = '';
}

function openCreateModal() {
    editingSchedule.value = null;
    resetForm();
    showModal.value = true;
}

function openEditModal(schedule: AdvisorySchedule) {
    editingSchedule.value = schedule;
    resetForm();
    form.teacher_user_id = String(schedule.teacher_user_id);
    form.teaching_load_id = schedule.teaching_load_id
        ? String(schedule.teaching_load_id)
        : '';
    form.semester_id = schedule.semester_id;
    form.day_of_week = schedule.day_of_week;
    form.start_time = schedule.start_time;
    form.end_time = schedule.end_time;
    form.location = schedule.location ?? '';
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editingSchedule.value = null;
    resetForm();
}

function submitForm() {
    const options = {
        preserveScroll: true,
        onSuccess: closeModal,
    };

    if (!editingSchedule.value) {
        form.post('/asesorias-horarios', options);
        return;
    }

    form.put(`/asesorias-horarios/${editingSchedule.value.id}`, options);
}

function deleteSchedule(schedule: AdvisorySchedule) {
    if (!confirm('¿Eliminar este horario de asesoría?')) return;

    useForm({}).delete(`/asesorias-horarios/${schedule.id}`, {
        preserveScroll: true,
    });
}

function printSchedule() {
    const tableEl = printTableRef.value;
    if (!tableEl) return;

    const clone = tableEl.cloneNode(true) as HTMLElement;
    const printWindow = window.open('', '_blank', 'width=1000,height=700');
    if (!printWindow) return;

    printWindow.document.open();
    printWindow.document.write(`<!doctype html>
<html lang="es"><head><meta charset="utf-8"/><title>Horario de Asesorías</title>
<style>
@page{size:A4 landscape;margin:12mm}html,body{font-family:Arial,sans-serif;color:#111}
body{padding-bottom:42mm}h1{font-size:18px;margin:0 0 4px;text-align:center}p{font-size:12px;margin:0 0 12px;color:#444;text-align:center}
table{width:100%;border-collapse:collapse;margin-top:8px}th,td{border:1px solid #d1d5db;padding:6px 10px;font-size:11px;vertical-align:middle}
th{background:#f3f4f6;text-transform:uppercase;letter-spacing:.03em;font-weight:700;text-align:center}
td{text-align:left}.day-cell{text-align:center}tr:nth-child(even){background:#f9fafb}
.signature-footer{position:fixed;left:12mm;right:12mm;bottom:12mm;display:flex;justify-content:space-between;gap:24px}
.signature-block{width:32%;text-align:center}.signature-line{border-top:1px solid #111;padding-top:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
</style></head><body>
<h1>Horario de Asesorías Docentes</h1>
<p>Semestre: ${semester.value}</p>
${clone.outerHTML}
<div class="signature-footer">
<div class="signature-block"><div class="signature-line">Firma de Academia</div></div>
<div class="signature-block"><div class="signature-line">Firma de Jefe de Departamento</div></div>
</div>
</body></html>`);
    printWindow.document.close();
    printWindow.onload = () => {
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    };
}
</script>

<template>
    <Head title="Horario de Asesorías" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Asesorías - Horarios', href: '/asesorias-horarios' },
        ]"
    >
        <div class="min-h-screen bg-slate-50">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div
                    class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">
                            Horario de Asesorías
                        </h1>
                        <p class="mt-1 text-sm text-slate-600">
                            Horarios semanales de asesoría por docente, con o
                            sin materia asociada.
                        </p>
                    </div>

                    <button
                        v-if="canManage"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                        @click="openCreateModal"
                    >
                        <Plus class="h-4 w-4" />
                        Nuevo horario
                    </button>
                </div>

                <div
                    class="toolbar mb-4 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:flex-row md:items-center md:justify-between"
                >
                    <div class="flex flex-1 flex-col gap-3 md:flex-row">
                        <div class="relative w-full max-w-md">
                            <Search
                                class="pointer-events-none absolute top-2.5 left-3 h-4 w-4 text-slate-400"
                            />
                            <input
                                v-model="search"
                                type="text"
                                class="w-full rounded-lg border border-slate-200 bg-white py-2 pr-4 pl-10 text-sm outline-none focus:border-slate-300"
                                placeholder="Buscar por docente, materia o aula"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="hidden text-sm text-slate-500 md:inline"
                            >
                                Semestre
                            </span>
                            <select
                                v-model="semester"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-slate-300"
                            >
                                <option
                                    v-for="semesterName in semesters"
                                    :key="semesterName"
                                    :value="semesterName"
                                >
                                    {{ semesterName }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        @click="printSchedule"
                    >
                        <Printer class="h-4 w-4" />
                        Imprimir horario
                    </button>
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="overflow-x-auto">
                        <table ref="printTableRef" class="w-full table-auto">
                            <thead class="bg-slate-50">
                                <tr
                                    class="text-[11px] font-semibold tracking-wide text-slate-600 uppercase"
                                >
                                    <th
                                        class="min-w-[180px] px-4 py-3 text-left"
                                    >
                                        Materia
                                    </th>
                                    <th
                                        class="min-w-[150px] px-4 py-3 text-left"
                                    >
                                        Docente
                                    </th>
                                    <th
                                        v-for="day in dayCols"
                                        :key="day.key"
                                        class="min-w-[100px] px-3 py-3 text-center"
                                    >
                                        {{ day.label }}
                                    </th>
                                    <th class="px-4 py-3 text-left">Grupo</th>
                                    <th class="px-4 py-3 text-left">Lugar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="row in filteredRows"
                                    :key="row.id"
                                    class="hover:bg-slate-50/60"
                                >
                                    <td
                                        class="px-4 py-3 text-sm text-slate-800"
                                    >
                                        {{ row.materia }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-sm font-semibold text-slate-900"
                                    >
                                        {{ row.docente }}
                                    </td>
                                    <td
                                        v-for="day in dayCols"
                                        :key="day.key"
                                        class="day-cell px-3 py-3 text-center"
                                    >
                                        <span
                                            v-if="row[day.key]"
                                            class="inline-block rounded-md bg-indigo-50 px-2 py-1 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200"
                                        >
                                            {{ row[day.key] }}
                                        </span>
                                        <span v-else class="text-slate-300"
                                            >-</span
                                        >
                                    </td>
                                    <td
                                        class="px-4 py-3 text-sm text-slate-700"
                                    >
                                        {{ row.carrera }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-sm text-slate-600"
                                    >
                                        {{ row.aula || '-' }}
                                    </td>
                                </tr>
                                <tr v-if="filteredRows.length === 0">
                                    <td
                                        colspan="9"
                                        class="px-4 py-10 text-center text-sm text-slate-500"
                                    >
                                        No hay horarios de asesoría registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <section
                    v-if="canManage"
                    class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <CalendarClock class="h-5 w-5 text-indigo-600" />
                        <h2 class="text-sm font-semibold text-slate-900">
                            Edición de horarios
                        </h2>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="schedule in schedules"
                            :key="schedule.id"
                            class="rounded-lg border border-slate-200 p-4"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{ schedule.teacher_name }}
                                    </p>
                                    <p class="mt-1 text-sm text-slate-600">
                                        {{ scheduleTitle(schedule) }}
                                    </p>
                                    <p class="mt-1 text-sm text-indigo-700">
                                        {{ schedule.day_name }},
                                        {{ schedule.start_time }} -
                                        {{ schedule.end_time }}
                                    </p>
                                    <p
                                        v-if="schedule.location"
                                        class="mt-1 text-xs text-slate-500"
                                    >
                                        {{ schedule.location }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="rounded-lg p-1.5 text-slate-600 hover:bg-slate-50"
                                        title="Editar"
                                        @click="openEditModal(schedule)"
                                    >
                                        <Edit2 class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg p-1.5 text-red-600 hover:bg-red-50"
                                        title="Eliminar"
                                        @click="deleteSchedule(schedule)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </div>

        <div v-if="showModal" class="relative z-50">
            <div class="fixed inset-0 bg-slate-500/75 transition-opacity"></div>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div
                    class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0"
                >
                    <form
                        class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-xl"
                        @submit.prevent="submitForm"
                    >
                        <div
                            class="border-b border-slate-100 bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4"
                        >
                            <h3
                                class="mb-4 text-lg leading-6 font-semibold text-slate-900"
                            >
                                {{ modalTitle }}
                            </h3>

                            <div class="space-y-4">
                                <div>
                                    <label
                                        class="block text-sm leading-6 font-medium text-slate-900"
                                        >Docente</label
                                    >
                                    <select
                                        v-model="form.teacher_user_id"
                                        required
                                        class="mt-2 block w-full rounded-md border-0 py-1.5 pr-10 pl-3 text-slate-900 ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                    >
                                        <option value="" disabled>
                                            Selecciona docente
                                        </option>
                                        <option
                                            v-for="teacher in teachers"
                                            :key="teacher.id"
                                            :value="teacher.id"
                                        >
                                            {{ teacher.name }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="form.errors.teacher_user_id"
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{ form.errors.teacher_user_id }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        class="block text-sm leading-6 font-medium text-slate-900"
                                        >Materia / grupo (opcional)</label
                                    >
                                    <select
                                        v-model="form.teaching_load_id"
                                        class="mt-2 block w-full rounded-md border-0 py-1.5 pr-10 pl-3 text-slate-900 ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                    >
                                        <option value="">
                                            Asesoría general / sin materia
                                        </option>
                                        <option
                                            v-for="load in availableLoads"
                                            :key="load.id"
                                            :value="load.id"
                                        >
                                            {{ formatLoadOption(load) }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="form.errors.teaching_load_id"
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{ form.errors.teaching_load_id }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        class="block text-sm leading-6 font-medium text-slate-900"
                                        >Día semanal</label
                                    >
                                    <select
                                        v-model="form.day_of_week"
                                        required
                                        class="mt-2 block w-full rounded-md border-0 py-1.5 pr-10 pl-3 text-slate-900 ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                    >
                                        <option
                                            v-for="day in dayOptions"
                                            :key="day.value"
                                            :value="day.value"
                                        >
                                            {{ day.label }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="form.errors.day_of_week"
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{ form.errors.day_of_week }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label
                                            class="block text-sm leading-6 font-medium text-slate-900"
                                            >Hora inicio</label
                                        >
                                        <input
                                            v-model="form.start_time"
                                            type="time"
                                            required
                                            class="mt-2 block w-full rounded-md border-0 py-1.5 text-slate-900 ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                        />
                                        <p
                                            v-if="form.errors.start_time"
                                            class="mt-1 text-xs text-red-600"
                                        >
                                            {{ form.errors.start_time }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-sm leading-6 font-medium text-slate-900"
                                            >Hora fin</label
                                        >
                                        <input
                                            v-model="form.end_time"
                                            type="time"
                                            required
                                            class="mt-2 block w-full rounded-md border-0 py-1.5 text-slate-900 ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                        />
                                        <p
                                            v-if="form.errors.end_time"
                                            class="mt-1 text-xs text-red-600"
                                        >
                                            {{ form.errors.end_time }}
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <label
                                        class="block text-sm leading-6 font-medium text-slate-900"
                                        >Lugar</label
                                    >
                                    <input
                                        v-model="form.location"
                                        type="text"
                                        placeholder="Ej. Cubículo, aula o en línea"
                                        class="mt-2 block w-full rounded-md border-0 py-1.5 text-slate-900 ring-1 ring-slate-300 ring-inset placeholder:text-slate-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                    />
                                    <p
                                        v-if="form.errors.location"
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{ form.errors.location }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div
                            class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6"
                        >
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 sm:ml-3 sm:w-auto"
                            >
                                {{
                                    isEditing
                                        ? 'Guardar cambios'
                                        : 'Guardar horario'
                                }}
                            </button>
                            <button
                                type="button"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-slate-300 ring-inset hover:bg-slate-50 sm:mt-0 sm:w-auto"
                                @click="closeModal"
                            >
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    .toolbar {
        display: none !important;
    }
}
</style>
