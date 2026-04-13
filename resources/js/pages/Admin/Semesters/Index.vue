<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref } from 'vue';
import {
    CalendarPlus,
    Edit2,
    Trash2,
    CalendarHeart,
    CalendarOff,
} from 'lucide-vue-next';

const props = defineProps<{
    semesters: {
        data: any[];
        links: any[];
    };
    academicPeriods: any[];
}>();

const isModalOpen = ref(false);
const editingSemester = ref<any | null>(null);

const form = useForm({
    name: '',
    start_date: '',
    end_date: '',
    status: 'OPEN',
    academic_period_id: '' as string | null,
});

const openCreateModal = () => {
    editingSemester.value = null;
    form.reset();
    isModalOpen.value = true;
};

const openEditModal = (semester: any) => {
    editingSemester.value = semester;
    form.name = semester.name;
    form.start_date = semester.start_date;
    form.end_date = semester.end_date;
    form.status = semester.status;
    form.academic_period_id = semester.academic_period_id;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
};

const submitForm = () => {
    if (editingSemester.value) {
        form.put(`/admin/semesters/${editingSemester.value.id}`, {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post('/admin/semesters', {
            onSuccess: () => closeModal(),
        });
    }
};

const destroySemester = (id: number) => {
    if (
        confirm(
            '¿Seguro que deseas eliminar este semestre? Esta acción no se puede deshacer si tiene cargas activas asociadas.',
        )
    ) {
        router.delete(`/admin/semesters/${id}`);
    }
};

const formatDate = (dateString: string) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Administrar Semestres" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Admin', href: '#' },
            { title: 'Semestres', href: '/admin/semesters' },
        ]"
    >
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        Semestres Académicos
                    </h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Configura los semestres globales y su vigencia institucional.
                    </p>
                </div>
                <button type="button" @click="openCreateModal"
                    class="inline-flex items-center rounded-lg border border-transparent bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none"
                >
                    <CalendarPlus class="mr-2 h-5 w-5" />
                    Nuevo Semestre
                </button>
            </div>

            <div
                v-if="$page.props.errors.error"
                class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-600"
            >
                {{ $page.props.errors.error }}
            </div>

            <!-- Table Container -->
            <div
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
            >
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Nombre
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Periodo Académico
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Vigencia
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Estado
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="semester in semesters.data"
                            :key="semester.id"
                            class="transition-colors hover:bg-gray-50"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div
                                    class="text-sm font-semibold text-gray-900"
                                >
                                    {{ semester.name }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">
                                    {{
                                        semester.academic_period
                                            ? semester.academic_period.name
                                            : 'Sin asignar'
                                    }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-700">
                                    {{ formatDate(semester.start_date) }} -
                                    {{ formatDate(semester.end_date) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    v-if="semester.status === 'OPEN'"
                                    class="inline-flex items-center rounded-full border border-green-200 bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800"
                                >
                                    <CalendarHeart class="mr-1 h-3 w-3" /> ABIERTO
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-full border border-red-200 bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800"
                                >
                                    <CalendarOff class="mr-1 h-3 w-3" /> CERRADO
                                </span>
                            </td>
                            <td
                                class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                            >
                                <button type="button" @click="openEditModal(semester)"
                                    class="mr-3 text-indigo-600 hover:text-indigo-900"
                                    title="Editar"
                                >
                                    <Edit2 class="h-4 w-4" />
                                </button>
                                <button type="button" @click="destroySemester(semester.id)"
                                    class="text-red-600 hover:text-red-900"
                                    title="Eliminar"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="semesters.data.length === 0">
                            <td
                                colspan="5"
                                class="bg-gray-50 px-6 py-12 text-center text-gray-500"
                            >
                                Aún no hay semestres configurados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="semesters.links.length > 3" class="mt-4 flex items-center justify-center gap-1">
                <template v-for="(link, i) in semesters.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="px-3 py-1 rounded text-sm"
                        :class="link.active ? 'bg-blue-600 text-white font-semibold' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'"
                        v-html="link.label"
                        preserve-state
                    />
                    <span v-else class="px-3 py-1 text-sm text-gray-400" v-html="link.label" />
                </template>
            </div>

            <!-- Modal for Create/Edit -->
            <div
                v-if="isModalOpen"
                class="fixed inset-0 z-50 overflow-y-auto"
                aria-labelledby="modal-title"
                role="dialog"
                aria-modal="true"
            >
                <div
                    class="flex min-h-full items-center justify-center p-4 text-center sm:p-0"
                >
                    <div
                        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                        aria-hidden="true"
                        @click="closeModal"
                    ></div>

                    <!-- Modal Body -->
                    <div
                        class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg"
                    >
                        <form @submit.prevent="submitForm">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="mb-5">
                                    <h3
                                        class="border-b pb-2 text-xl leading-8 font-bold text-gray-900"
                                        id="modal-title"
                                    >
                                        {{
                                            editingSemester
                                                ? 'Editar Semestre'
                                                : 'Crear Semestre'
                                        }}
                                    </h3>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <label
                                            for="name"
                                            class="block text-sm font-medium text-gray-700"
                                            >Nombre (ej. Ago-Dic 2026)</label
                                        >
                                        <input
                                            type="text"
                                            id="name"
                                            v-model="form.name"
                                            class="mt-1 block w-full flex-1 rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                            required
                                        />
                                        <div
                                            v-if="form.errors.name"
                                            class="mt-1 text-xs text-red-500"
                                        >
                                            {{ form.errors.name }}
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label
                                                for="start_date"
                                                class="block text-sm font-medium text-gray-700"
                                                >Fecha de Inicio</label
                                            >
                                            <input
                                                type="date"
                                                id="start_date"
                                                v-model="form.start_date"
                                                class="mt-1 block w-full flex-1 rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                required
                                            />
                                            <div
                                                v-if="form.errors.start_date"
                                                class="mt-1 text-xs text-red-500"
                                            >
                                                {{ form.errors.start_date }}
                                            </div>
                                        </div>
                                        <div>
                                            <label
                                                for="end_date"
                                                class="block text-sm font-medium text-gray-700"
                                                >Fecha de Fin</label
                                            >
                                            <input
                                                type="date"
                                                id="end_date"
                                                v-model="form.end_date"
                                                class="mt-1 block w-full flex-1 rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                required
                                            />
                                            <div
                                                v-if="form.errors.end_date"
                                                class="mt-1 text-xs text-red-500"
                                            >
                                                {{ form.errors.end_date }}
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            for="academic_period"
                                            class="block text-sm font-medium text-gray-700"
                                            >Periodo Académico (Opcional)</label
                                        >
                                        <select
                                            id="academic_period"
                                            v-model="form.academic_period_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 py-2 pr-10 pl-3 text-base focus:border-blue-500 focus:ring-blue-500 focus:outline-none sm:text-sm"
                                        >
                                            <option value="">
                                                -- Sin periodo asignado --
                                            </option>
                                            <option
                                                v-for="period in academicPeriods"
                                                :key="period.id"
                                                :value="period.id"
                                            >
                                                {{ period.name }}
                                            </option>
                                        </select>
                                        <div
                                            v-if="
                                                form.errors.academic_period_id
                                            "
                                            class="mt-1 text-xs text-red-500"
                                        >
                                            {{ form.errors.academic_period_id }}
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            for="status"
                                            class="block text-sm font-medium text-gray-700"
                                            >Estado</label
                                        >
                                        <select
                                            id="status"
                                            v-model="form.status"
                                            class="mt-1 block w-full rounded-md border-gray-300 py-2 pr-10 pl-3 text-base focus:border-blue-500 focus:ring-blue-500 focus:outline-none sm:text-sm"
                                        >
                                            <option value="OPEN">
                                                ABIERTO (Activo y visible)
                                            </option>
                                            <option value="CLOSED">
                                                CERRADO (Archivado)
                                            </option>
                                        </select>
                                        <div
                                            v-if="form.errors.status"
                                            class="mt-1 text-xs text-red-500"
                                        >
                                            {{ form.errors.status }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="border-t bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6"
                            >
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex w-full justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none disabled:opacity-50 sm:ml-3 sm:w-auto sm:text-sm"
                                >
                                    {{
                                        editingSemester
                                            ? 'Guardar Cambios'
                                            : 'Crear'
                                    }}
                                </button>
                                <button type="button" @click="closeModal"
                                    class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
