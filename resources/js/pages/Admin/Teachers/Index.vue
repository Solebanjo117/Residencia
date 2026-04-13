<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref } from 'vue';
import {
    UserPlus,
    Edit2,
    Trash2,
    ShieldBan,
    CheckCircle2,
    FolderPlus,
} from 'lucide-vue-next';

const props = defineProps<{
    teachers: {
        data: any[];
        links: any[];
    };
    departments: any[];
}>();

const isModalOpen = ref(false);
const editingTeacher = ref<any | null>(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    department_ids: [] as number[],
    is_active: true,
});

const openCreateModal = () => {
    editingTeacher.value = null;
    form.reset();
    isModalOpen.value = true;
};

const openEditModal = (teacher: any) => {
    editingTeacher.value = teacher;
    form.name = teacher.name;
    form.email = teacher.email;
    form.password = ''; // Leave blank unless changing
    form.is_active = teacher.is_active;
    form.department_ids = teacher.departments.map((d: any) => d.id);
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
};

const submitForm = () => {
    if (editingTeacher.value) {
        form.put(`/admin/teachers/${editingTeacher.value.id}`, {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post('/admin/teachers', {
            onSuccess: () => closeModal(),
        });
    }
};

const toggleActive = (teacher: any) => {
    router.put(
        `/admin/teachers/${teacher.id}`,
        {
            ...teacher,
            is_active: !teacher.is_active,
            department_ids: teacher.departments.map((d: any) => d.id),
        },
        { preserveScroll: true },
    );
};

const generateFolders = (teacher: any) => {
    router.post(`/admin/teachers/${teacher.id}/generate-folders`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Administrar Docentes" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Admin', href: '#' },
            { title: 'Docentes', href: '/admin/teachers' },
        ]"
    >
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        Directorio de Docentes
                    </h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Gestiona los docentes registrados y su adscripción
                        departamental.
                    </p>
                </div>
                <button type="button" @click="openCreateModal"
                    class="inline-flex items-center rounded-lg border border-transparent bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none"
                >
                    <UserPlus class="mr-2 h-5 w-5" />
                    Registrar Docente
                </button>
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
                                Contacto
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Departamentos
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
                            v-for="teacher in teachers.data"
                            :key="teacher.id"
                            class="transition-colors hover:bg-gray-50"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ teacher.name }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">
                                    {{ teacher.email }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="dept in teacher.departments"
                                        :key="dept.id"
                                        class="inline-flex items-center rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800"
                                    >
                                        {{ dept.name }}
                                    </span>
                                    <span
                                        v-if="teacher.departments.length === 0"
                                        class="text-xs text-gray-400 italic"
                                        >Sin asignar</span
                                    >
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    v-if="teacher.is_active"
                                    class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800"
                                >
                                    Activo
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800"
                                >
                                    Inactivo
                                </span>
                            </td>
                            <td
                                class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                            >
                                <div class="flex justify-end gap-3">
                                    <button type="button" @click="openEditModal(teacher)"
                                        class="text-indigo-600 hover:text-indigo-900"
                                        title="Editar"
                                    >
                                        <Edit2 class="h-4 w-4" />
                                    </button>
                                    <button type="button" @click="generateFolders(teacher)"
                                        class="text-emerald-600 hover:text-emerald-900"
                                        title="Generar carpetas en todos los semestres"
                                    >
                                        <FolderPlus class="h-4 w-4" />
                                    </button>
                                    <button type="button" @click="toggleActive(teacher)"
                                        :class="
                                            teacher.is_active
                                                ? 'text-red-600 hover:text-red-900'
                                                : 'text-green-600 hover:text-green-900'
                                        "
                                        :title="
                                            teacher.is_active
                                                ? 'Desactivar'
                                                : 'Activar'
                                        "
                                    >
                                        <component
                                            :is="
                                                teacher.is_active
                                                    ? ShieldBan
                                                    : CheckCircle2
                                            "
                                            class="h-4 w-4"
                                        />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="teachers.data.length === 0">
                            <td
                                colspan="5"
                                class="bg-gray-50 px-6 py-12 text-center text-gray-500"
                            >
                                No hay docentes registrados. Comienza agregando uno.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="teachers.links.length > 3" class="mt-4 flex items-center justify-center gap-1">
                <template v-for="(link, i) in teachers.links" :key="i">
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
                                <div class="mb-4">
                                    <h3
                                        class="text-lg leading-6 font-medium text-gray-900"
                                        id="modal-title"
                                    >
                                        {{
                                            editingTeacher
                                                ? 'Editar Docente'
                                                : 'Registrar Nuevo Docente'
                                        }}
                                    </h3>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <label
                                            for="name"
                                            class="block text-sm font-medium text-gray-700"
                                            >Nombre Completo</label
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

                                    <div>
                                        <label
                                            for="email"
                                            class="block text-sm font-medium text-gray-700"
                                            >Correo Electrónico</label
                                        >
                                        <input
                                            type="email"
                                            id="email"
                                            v-model="form.email"
                                            class="mt-1 block w-full flex-1 rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                            required
                                        />
                                        <div
                                            v-if="form.errors.email"
                                            class="mt-1 text-xs text-red-500"
                                        >
                                            {{ form.errors.email }}
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            for="password"
                                            class="block text-sm font-medium text-gray-700"
                                            >Password
                                            {{
                                                editingTeacher
                                                    ? '(deja vacío para mantener la actual)'
                                                    : ''
                                            }}</label
                                        >
                                        <input
                                            type="password"
                                            id="password"
                                            v-model="form.password"
                                            class="mt-1 block w-full flex-1 rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                            :required="!editingTeacher"
                                        />
                                        <div
                                            v-if="form.errors.password"
                                            class="mt-1 text-xs text-red-500"
                                        >
                                            {{ form.errors.password }}
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            class="mb-2 block text-sm font-medium text-gray-700"
                                            >Departamentos (Opcional)</label
                                        >
                                        <div
                                            class="max-h-32 space-y-2 overflow-y-auto rounded-md border border-gray-200 p-2"
                                        >
                                            <label
                                                v-for="dept in departments"
                                                :key="dept.id"
                                                class="flex items-center"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :value="dept.id"
                                                    v-model="
                                                        form.department_ids
                                                    "
                                                    class="focus:ring-opacity-50 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                                                />
                                                <span
                                                    class="ml-2 text-sm text-gray-600"
                                                    >{{ dept.name }}</span
                                                >
                                            </label>
                                        </div>
                                        <div
                                            v-if="form.errors.department_ids"
                                            class="mt-1 text-xs text-red-500"
                                        >
                                            {{ form.errors.department_ids }}
                                        </div>
                                    </div>

                                    <div v-if="editingTeacher">
                                        <label class="flex items-center">
                                            <input
                                                type="checkbox"
                                                v-model="form.is_active"
                                                class="focus:ring-opacity-50 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                                            />
                                            <span
                                                class="ml-2 text-sm text-gray-600"
                                                >Active Account</span
                                            >
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6"
                            >
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex w-full justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none disabled:opacity-50 sm:ml-3 sm:w-auto sm:text-sm"
                                >
                                    {{
                                        editingTeacher
                                            ? 'Guardar Cambios'
                                            : 'Registrar'
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
