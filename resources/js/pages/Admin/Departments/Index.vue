<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { Building2, Edit2, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    departments: any[];
}>();

const isModalOpen = ref(false);
const editingDepartment = ref<any | null>(null);

const form = useForm({
    name: '',
});

const openCreateModal = () => {
    editingDepartment.value = null;
    form.reset();
    isModalOpen.value = true;
};

const openEditModal = (department: any) => {
    editingDepartment.value = department;
    form.name = department.name;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
};

const submitForm = () => {
    if (editingDepartment.value) {
        form.put(`/admin/departments/${editingDepartment.value.id}`, {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post('/admin/departments', {
            onSuccess: () => closeModal(),
        });
    }
};

const destroyDepartment = (id: number) => {
    if (confirm('¿Estás seguro de que deseas eliminar este departamento?')) {
        router.delete(`/admin/departments/${id}`);
    }
};
</script>

<template>
    <Head title="Directorio de Departamentos" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Admin', href: '#' },
            { title: 'Departamentos', href: '/admin/departments' },
        ]"
    >
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div
                class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        Departamentos
                    </h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Administra los departamentos académicos (Sistemas,
                        Electromecánica, etc).
                    </p>
                </div>

                <button
                    type="button"
                    @click="openCreateModal"
                    class="inline-flex shrink-0 items-center rounded-lg border border-transparent bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none"
                >
                    <Building2 class="mr-2 h-5 w-5" />
                    Nuevo Departamento
                </button>
            </div>

            <div
                v-if="$page.props.errors.error"
                class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-600"
            >
                {{ $page.props.errors.error }}
            </div>

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
                                Nombre del Departamento
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
                            v-for="dept in departments"
                            :key="dept.id"
                            class="transition-colors hover:bg-gray-50"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div
                                    class="text-sm font-semibold text-gray-900"
                                >
                                    {{ dept.name }}
                                </div>
                            </td>
                            <td
                                class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                            >
                                <button
                                    type="button"
                                    @click="openEditModal(dept)"
                                    class="mr-3 text-indigo-600 hover:text-indigo-900"
                                >
                                    <Edit2 class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="destroyDepartment(dept.id)"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="departments.length === 0">
                            <td
                                colspan="2"
                                class="bg-gray-50 px-6 py-12 text-center text-gray-500"
                            >
                                No se encontraron departamentos registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
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
                        class="bg-opacity-75 fixed inset-0 bg-gray-500 transition-opacity"
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
                                            editingDepartment
                                                ? 'Editar Departamento'
                                                : 'Nuevo Departamento'
                                        }}
                                    </h3>
                                </div>

                                <div>
                                    <label
                                        for="dept-name"
                                        class="block text-sm font-medium text-gray-700"
                                        >Nombre</label
                                    >
                                    <input
                                        id="dept-name"
                                        type="text"
                                        v-model="form.name"
                                        class="mt-1 block w-full flex-1 rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        placeholder="Ej. Ing. en Sistemas"
                                        required
                                    />
                                    <div
                                        v-if="form.errors.name"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.name }}
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
                                        editingDepartment
                                            ? 'Guardar Cambios'
                                            : 'Crear'
                                    }}
                                </button>
                                <button
                                    type="button"
                                    @click="closeModal"
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
