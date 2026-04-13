<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, watch } from 'vue';
import {
    BookOpen,
    Edit2,
    Trash2,
    GraduationCap,
    Filter,
} from 'lucide-vue-next';

const props = defineProps<{
    teachingLoads: {
        data: any[];
        links: any[];
    };
    semesters: any[];
    teachers: any[];
    subjects: any[];
    selectedSemester: string | null;
}>();

const isModalOpen = ref(false);
const editingLoad = ref<any | null>(null);

const filterSemester = ref(props.selectedSemester || '');

// Watch for filter changes to reload data
watch(filterSemester, (newValue) => {
    router.get(
        '/admin/teaching-loads',
        { semester_id: newValue },
        { preserveState: true, replace: true },
    );
});

const form = useForm({
    teacher_user_id: '',
    semester_id: '',
    subject_id: '',
    group_code: '',
    hours_per_week: '' as string | number,
});

const openCreateModal = () => {
    editingLoad.value = null;
    form.reset();
    if (filterSemester.value) {
        form.semester_id = filterSemester.value; // Pre-fill if a semester is filtered
    }
    isModalOpen.value = true;
};

const openEditModal = (load: any) => {
    editingLoad.value = load;
    form.teacher_user_id = load.teacher_user_id;
    form.semester_id = load.semester_id;
    form.subject_id = load.subject_id;
    form.group_code = load.group_code;
    form.hours_per_week = load.hours_per_week;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
};

const submitForm = () => {
    if (editingLoad.value) {
        form.put(`/admin/teaching-loads/${editingLoad.value.id}`, {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post('/admin/teaching-loads', {
            onSuccess: () => closeModal(),
        });
    }
};

const destroyLoad = (id: number) => {
    if (confirm('¿Seguro que deseas eliminar esta asignación de carga docente?')) {
        router.delete(`/admin/teaching-loads/${id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Cargas Académicas" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Admin', href: '#' },
            { title: 'Cargas Académicas', href: '/admin/teaching-loads' },
        ]"
    >
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div
                class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        Gestión de Cargas Académicas
                    </h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Asigna materias y grupos a docentes por semestre.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <select
                            v-model="filterSemester"
                            class="appearance-none rounded-lg border border-gray-300 bg-white py-2 pr-10 pl-4 text-sm leading-tight text-gray-700 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none"
                        >
                            <option value="">Todos los semestres</option>
                            <option
                                v-for="sem in semesters"
                                :key="sem.id"
                                :value="sem.id"
                            >
                                {{ sem.name }}
                            </option>
                        </select>
                        <div
                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500"
                        >
                            <Filter class="h-4 w-4" />
                        </div>
                    </div>

                    <button type="button" @click="openCreateModal"
                        class="inline-flex shrink-0 items-center rounded-lg border border-transparent bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700"
                    >
                        <BookOpen class="mr-2 h-5 w-5" />
                        Asignar Carga
                    </button>
                </div>
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
                                Docente
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Semestre
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Materia
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Grupo
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Horas
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
                            v-for="load in teachingLoads.data"
                            :key="load.id"
                            class="transition-colors hover:bg-gray-50"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div
                                        class="mr-3 flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-700"
                                    >
                                        <GraduationCap class="h-5 w-5" />
                                    </div>
                                    <div
                                        class="text-sm font-medium text-gray-900"
                                    >
                                        {{ load.teacher.name }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-700">
                                    {{ load.semester.name }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div
                                    class="text-sm font-semibold text-gray-900"
                                >
                                    {{ load.subject.name }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ load.subject.code }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center rounded-full border border-purple-200 bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800"
                                >
                                    {{ load.group_code }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600">
                                    {{ load.hours_per_week || '-' }} hrs/sem
                                </div>
                            </td>
                            <td
                                class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                            >
                                <button type="button" @click="openEditModal(load)"
                                    class="mr-3 text-indigo-600 hover:text-indigo-900"
                                >
                                    <Edit2 class="h-4 w-4" />
                                </button>
                                <button type="button" @click="destroyLoad(load.id)"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="teachingLoads.data.length === 0">
                            <td
                                colspan="6"
                                class="bg-gray-50 px-6 py-12 text-center text-gray-500"
                            >
                                No se encontraron cargas académicas para este filtro.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="teachingLoads.links.length > 3" class="mt-4 flex items-center justify-center gap-1">
                <template v-for="(link, i) in teachingLoads.links" :key="i">
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

                    <div
                        class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg"
                    >
                        <form @submit.prevent="submitForm">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="mb-5">
                                    <h3
                                        class="border-b pb-2 text-xl leading-8 font-bold text-gray-900"
                                    >
                                        {{
                                            editingLoad
                                                ? 'Editar Asignación'
                                                : 'Asignar Carga Académica'
                                        }}
                                    </h3>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700"
                                            >Docente</label
                                        >
                                        <select
                                            v-model="form.teacher_user_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 py-2 pr-10 pl-3 text-base focus:border-blue-500 sm:text-sm"
                                            required
                                        >
                                            <option value="" disabled>
                                                Selecciona un docente...
                                            </option>
                                            <option
                                                v-for="teacher in teachers"
                                                :key="teacher.id"
                                                :value="teacher.id"
                                            >
                                                {{ teacher.name }} ({{
                                                    teacher.email
                                                }})
                                            </option>
                                        </select>
                                        <div
                                            v-if="form.errors.teacher_user_id"
                                            class="mt-1 text-xs text-red-500"
                                        >
                                            {{ form.errors.teacher_user_id }}
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700"
                                            >Semestre</label
                                        >
                                        <select
                                            v-model="form.semester_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 py-2 pr-10 pl-3 text-base focus:border-blue-500 sm:text-sm"
                                            required
                                        >
                                            <option value="" disabled>
                                                Selecciona semestre...
                                            </option>
                                            <option
                                                v-for="sem in semesters"
                                                :key="sem.id"
                                                :value="sem.id"
                                            >
                                                {{ sem.name }}
                                            </option>
                                        </select>
                                        <div
                                            v-if="form.errors.semester_id"
                                            class="mt-1 text-xs text-red-500"
                                        >
                                            {{ form.errors.semester_id }}
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700"
                                            >Materia</label
                                        >
                                        <select
                                            v-model="form.subject_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 py-2 pr-10 pl-3 text-base focus:border-blue-500 sm:text-sm"
                                            required
                                        >
                                            <option value="" disabled>
                                                Selecciona materia...
                                            </option>
                                            <option
                                                v-for="sub in subjects"
                                                :key="sub.id"
                                                :value="sub.id"
                                            >
                                                {{ sub.code }} - {{ sub.name }}
                                            </option>
                                        </select>
                                        <div
                                            v-if="form.errors.subject_id"
                                            class="mt-1 text-xs text-red-500"
                                        >
                                            {{ form.errors.subject_id }}
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-gray-700"
                                                >Clave de Grupo</label
                                            >
                                            <input
                                                type="text"
                                                v-model="form.group_code"
                                                class="mt-1 block w-full flex-1 rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                placeholder="ej. 5A"
                                                required
                                            />
                                            <div
                                                v-if="form.errors.group_code"
                                                class="mt-1 text-xs text-red-500"
                                            >
                                                {{ form.errors.group_code }}
                                            </div>
                                        </div>
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-gray-700"
                                                >Horas por Semana (Opcional)</label
                                            >
                                            <input
                                                type="number"
                                                v-model="form.hours_per_week"
                                                class="mt-1 block w-full flex-1 rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                min="1"
                                                max="40"
                                            />
                                            <div
                                                v-if="
                                                    form.errors.hours_per_week
                                                "
                                                class="mt-1 text-xs text-red-500"
                                            >
                                                {{ form.errors.hours_per_week }}
                                            </div>
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
                                        editingLoad
                                            ? 'Guardar Cambios'
                                            : 'Asignar Carga'
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
