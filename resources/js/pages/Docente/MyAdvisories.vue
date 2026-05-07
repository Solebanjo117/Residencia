<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    BookOpen,
    CalendarClock,
    Clock,
    Edit2,
    MapPin,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type AdvisorySchedule = {
    id: number;
    teacher_user_id: number;
    teaching_load_id: number | null;
    semester_id: number;
    day_of_week: number;
    day_name: string;
    start_time: string;
    end_time: string;
    location: string | null;
    group_name: string | null;
    subject_name: string | null;
};

type TeachingLoad = {
    id: number;
    group_code?: string | null;
    group_name?: string | null;
    subject?: {
        name: string;
    } | null;
};

defineProps<{
    sessions: AdvisorySchedule[];
    teaching_loads: TeachingLoad[];
    semester: {
        id: number;
        name: string;
    } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mis Asesorias', href: '/docente/asesorias' },
];

const dayOptions = [
    { value: 1, label: 'Lunes' },
    { value: 2, label: 'Martes' },
    { value: 3, label: 'Miercoles' },
    { value: 4, label: 'Jueves' },
    { value: 5, label: 'Viernes' },
];

const showScheduleModal = ref(false);
const editingSchedule = ref<AdvisorySchedule | null>(null);

const form = useForm({
    teaching_load_id: '',
    day_of_week: 1,
    start_time: '17:00',
    end_time: '18:00',
    location: '',
});

const isEditing = computed(() => Boolean(editingSchedule.value));
const modalTitle = computed(() =>
    isEditing.value
        ? 'Editar horario de asesoria'
        : 'Registrar horario de asesoria',
);

function formatLoadOption(load: TeachingLoad) {
    const group = load.group_code ?? load.group_name;

    return group
        ? `${load.subject?.name ?? 'Materia'} (${group})`
        : (load.subject?.name ?? 'Materia');
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
    form.teaching_load_id = '';
    form.day_of_week = 1;
    form.start_time = '17:00';
    form.end_time = '18:00';
    form.location = '';
}

function openCreateModal() {
    editingSchedule.value = null;
    resetForm();
    showScheduleModal.value = true;
}

function openEditModal(schedule: AdvisorySchedule) {
    editingSchedule.value = schedule;
    resetForm();
    form.teaching_load_id = schedule.teaching_load_id
        ? String(schedule.teaching_load_id)
        : '';
    form.day_of_week = schedule.day_of_week;
    form.start_time = schedule.start_time;
    form.end_time = schedule.end_time;
    form.location = schedule.location ?? '';
    showScheduleModal.value = true;
}

function closeModal() {
    showScheduleModal.value = false;
    editingSchedule.value = null;
    resetForm();
}

function submitForm() {
    const options = {
        preserveScroll: true,
        onSuccess: closeModal,
    };

    if (!editingSchedule.value) {
        form.post('/docente/asesorias', options);
        return;
    }

    form.put(`/docente/asesorias/${editingSchedule.value.id}`, options);
}

function deleteSchedule(id: number) {
    if (confirm('Eliminar horario de asesoria?')) {
        useForm({}).delete(`/docente/asesorias/${id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Mis Asesorias" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-8 md:flex md:items-center md:justify-between">
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl"
                    >
                        Mis Asesorias
                    </h1>
                    <p
                        class="mt-2 flex items-center gap-2 text-sm text-gray-500"
                    >
                        <Clock class="h-4 w-4" />
                        Semestre activo:
                        <span class="font-semibold text-gray-900">
                            {{ semester?.name ?? 'Ninguno' }}
                        </span>
                    </p>
                </div>

                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <button
                        type="button"
                        :disabled="!semester"
                        class="inline-flex items-center gap-x-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                        @click="openCreateModal"
                    >
                        <Plus class="-ml-0.5 h-5 w-5" aria-hidden="true" />
                        Registrar horario
                    </button>
                </div>
            </div>

            <div
                v-if="sessions.length === 0"
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center"
            >
                <BookOpen
                    class="mx-auto h-12 w-12 text-gray-400"
                    aria-hidden="true"
                />
                <h3 class="mt-4 text-sm font-semibold text-gray-900">
                    No hay horarios de asesoria
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    Registra el dia y horario semanal en que atenderas
                    asesorias.
                </p>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="schedule in sessions"
                    :key="schedule.id"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">
                                {{ scheduleTitle(schedule) }}
                            </h2>
                            <p
                                class="mt-2 flex items-center gap-2 text-sm text-indigo-700"
                            >
                                <CalendarClock class="h-4 w-4" />
                                {{ schedule.day_name }},
                                {{ schedule.start_time }} -
                                {{ schedule.end_time }}
                            </p>
                            <p
                                v-if="schedule.location"
                                class="mt-1 flex items-center gap-2 text-sm text-gray-500"
                            >
                                <MapPin class="h-4 w-4" />
                                {{ schedule.location }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-slate-600 hover:bg-slate-50"
                                title="Editar horario"
                                @click="openEditModal(schedule)"
                            >
                                <Edit2 class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-red-600 hover:bg-red-50"
                                title="Eliminar horario"
                                @click="deleteSchedule(schedule.id)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </article>
            </div>
        </div>

        <div v-if="showScheduleModal" class="relative z-50">
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity"></div>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div
                    class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0"
                >
                    <form
                        class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg"
                        @submit.prevent="submitForm"
                    >
                        <div
                            class="border-b border-gray-100 bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4"
                        >
                            <h3
                                class="mb-4 text-lg leading-6 font-semibold text-gray-900"
                            >
                                {{ modalTitle }}
                            </h3>

                            <div class="space-y-4">
                                <div>
                                    <label
                                        class="block text-sm leading-6 font-medium text-gray-900"
                                        >Materia / grupo (opcional)</label
                                    >
                                    <select
                                        v-model="form.teaching_load_id"
                                        class="mt-2 block w-full rounded-md border-0 py-1.5 pr-10 pl-3 text-gray-900 ring-1 ring-gray-300 ring-inset focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                    >
                                        <option value="">
                                            Asesoria general / sin materia
                                        </option>
                                        <option
                                            v-for="load in teaching_loads"
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
                                        class="block text-sm leading-6 font-medium text-gray-900"
                                        >Dia semanal</label
                                    >
                                    <select
                                        v-model="form.day_of_week"
                                        required
                                        class="mt-2 block w-full rounded-md border-0 py-1.5 pr-10 pl-3 text-gray-900 ring-1 ring-gray-300 ring-inset focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
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
                                            class="block text-sm leading-6 font-medium text-gray-900"
                                            >Hora inicio</label
                                        >
                                        <input
                                            v-model="form.start_time"
                                            type="time"
                                            required
                                            class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 ring-1 ring-gray-300 ring-inset focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
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
                                            class="block text-sm leading-6 font-medium text-gray-900"
                                            >Hora fin</label
                                        >
                                        <input
                                            v-model="form.end_time"
                                            type="time"
                                            required
                                            class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 ring-1 ring-gray-300 ring-inset focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
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
                                        class="block text-sm leading-6 font-medium text-gray-900"
                                        >Lugar</label
                                    >
                                    <input
                                        v-model="form.location"
                                        type="text"
                                        placeholder="Ej. Cubiculo, aula o en linea"
                                        class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 ring-1 ring-gray-300 ring-inset placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
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
                            class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6"
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
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50 sm:mt-0 sm:w-auto"
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
