<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref } from 'vue';
import { Building2, Edit2, Trash2 } from 'lucide-vue-next';

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

    <AppLayout :breadcrumbs="[{ title: 'Admin', href: '#' }, { title: 'Departamentos', href: '/admin/departments' }]">
        <div class="px-6 py-8 mx-auto max-w-7xl">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6 gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Departamentos</h1>
                    <p class="mt-1 text-sm text-gray-500">Administra los departamentos académicos (Sistemas, Electromecánica, etc).</p>
                </div>
                
                <button 
                    type="button" 
                    @click="openCreateModal"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-sm text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors shrink-0"
                >
                    <Building2 class="w-5 h-5 mr-2" />
                    Nuevo Departamento
                </button>
            </div>

            <div v-if="$page.props.errors.error" class="mb-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-md">
                {{ $page.props.errors.error }}
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre del Departamento</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="dept in departments" :key="dept.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900">{{ dept.name }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button type="button" @click="openEditModal(dept)" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                    <Edit2 class="w-4 h-4" />
                                </button>
                                <button type="button" @click="destroyDepartment(dept.id)" class="text-red-600 hover:text-red-900">
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="departments.length === 0">
                            <td colspan="2" class="px-6 py-12 text-center text-gray-500 bg-gray-50">
                                No se encontraron departamentos registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Modal for Create/Edit -->
            <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-center justify-center min-h-full p-4 text-center sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal"></div>
                    
                    <div class="relative bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full">
                        <form @submit.prevent="submitForm">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="mb-5">
                                    <h3 class="text-xl leading-8 font-bold text-gray-900 border-b pb-2">
                                        {{ editingDepartment ? 'Editar Departamento' : 'Nuevo Departamento' }}
                                    </h3>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nombre</label>
                                    <input 
                                        type="text" 
                                        v-model="form.name" 
                                        class="mt-1 block flex-1 w-full rounded-md sm:text-sm border-gray-300 focus:border-blue-500 focus:ring-blue-500" 
                                        placeholder="Ej. Ing. en Sistemas"
                                        required
                                    >
                                    <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t">
                                <button type="submit" :disabled="form.processing" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                                    {{ editingDepartment ? 'Guardar Cambios' : 'Crear' }}
                                </button>
                                <button type="button" @click="closeModal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
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
