<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Edit2, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

type Subject = {
    id: number;
    code: string;
    name: string;
    teaching_loads_count: number;
};

const props = defineProps<{
    subjects: {
        data: Subject[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
}>();

const isModalOpen = ref(false);
const editingSubject = ref<Subject | null>(null);

const form = useForm({
    code: '',
    name: '',
});

const openCreateModal = () => {
    editingSubject.value = null;
    form.reset();
    form.clearErrors();
    isModalOpen.value = true;
};

const openEditModal = (subject: Subject) => {
    editingSubject.value = subject;
    form.clearErrors();
    form.code = subject.code;
    form.name = subject.name;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingSubject.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (editingSubject.value) {
        form.put(`/admin/subjects/${editingSubject.value.id}`, {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    form.post('/admin/subjects', {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const deleteSubject = (subject: Subject) => {
    if (
        !confirm(
            `Eliminar la materia "${subject.name}"? Esta accion no se puede deshacer.`,
        )
    ) {
        return;
    }

    useForm({}).delete(`/admin/subjects/${subject.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Agregar Materias" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Agregar', href: '#' },
            { title: 'Materias', href: '/admin/subjects' },
        ]"
    >
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
                        Agregar
                    </p>
                    <h1 class="text-2xl font-bold text-gray-900">
                        Materias
                    </h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Alta, edicion y control de materias disponibles para cargas academicas.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center rounded-lg border border-transparent bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    @click="openCreateModal"
                >
                    <Plus class="mr-2 h-5 w-5" />
                    Agregar materia
                </button>
            </div>

            <div
                v-if="$page.props.errors.error"
                class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
            >
                {{ $page.props.errors.error }}
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Clave
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Nombre
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Cargas asociadas
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="subject in props.subjects.data"
                            :key="subject.id"
                            class="transition-colors hover:bg-gray-50"
                        >
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-semibold text-gray-900">
                                {{ subject.code }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                {{ subject.name }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                                    {{ subject.teaching_loads_count }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <div class="flex justify-end gap-3">
                                    <button
                                        type="button"
                                        class="text-indigo-600 hover:text-indigo-900"
                                        title="Editar"
                                        @click="openEditModal(subject)"
                                    >
                                        <Edit2 class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="text-red-600 hover:text-red-900 disabled:cursor-not-allowed disabled:opacity-40"
                                        title="Eliminar"
                                        :disabled="subject.teaching_loads_count > 0"
                                        @click="deleteSubject(subject)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="props.subjects.data.length === 0">
                            <td colspan="4" class="bg-gray-50 px-6 py-12 text-center text-gray-500">
                                No hay materias registradas. Comienza agregando una.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="props.subjects.links.length > 3"
                class="mt-4 flex items-center justify-center gap-1"
            >
                <template v-for="(link, i) in props.subjects.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded px-3 py-1 text-sm"
                        :class="link.active ? 'bg-blue-600 font-semibold text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                        preserve-state
                    >
                        <span v-html="link.label" />
                    </Link>
                    <span
                        v-else
                        class="px-3 py-1 text-sm text-gray-400"
                        v-html="link.label"
                    />
                </template>
            </div>

            <div
                v-if="isModalOpen"
                class="fixed inset-0 z-50 overflow-y-auto"
                aria-labelledby="modal-title"
                role="dialog"
                aria-modal="true"
            >
                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <div
                        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                        aria-hidden="true"
                        @click="closeModal"
                    ></div>

                    <div class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                        <form @submit.prevent="submitForm">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="mb-4">
                                    <h3 class="text-lg font-medium leading-6 text-gray-900" id="modal-title">
                                        {{ editingSubject ? 'Editar materia' : 'Agregar materia' }}
                                    </h3>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <label for="code" class="block text-sm font-medium text-gray-700">
                                            Clave
                                        </label>
                                        <input
                                            id="code"
                                            v-model="form.code"
                                            type="text"
                                            class="mt-1 block w-full rounded-md border-gray-300 uppercase focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                            required
                                        />
                                        <div v-if="form.errors.code" class="mt-1 text-xs text-red-500">
                                            {{ form.errors.code }}
                                        </div>
                                    </div>

                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700">
                                            Nombre de la materia
                                        </label>
                                        <input
                                            id="name"
                                            v-model="form.name"
                                            type="text"
                                            class="mt-1 block w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                            required
                                        />
                                        <div v-if="form.errors.name" class="mt-1 text-xs text-red-500">
                                            {{ form.errors.name }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex w-full justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 sm:ml-3 sm:w-auto sm:text-sm"
                                >
                                    {{ editingSubject ? 'Guardar cambios' : 'Agregar' }}
                                </button>
                                <button
                                    type="button"
                                    class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                    @click="closeModal"
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
