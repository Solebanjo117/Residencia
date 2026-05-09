<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Edit2, ShieldBan, UserPlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

type Role = {
    id: number;
    name: string;
};

type Department = {
    id: number;
    name: string;
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    role_id: number;
    role?: Role;
    departments: Department[];
    linked_teacher_user_id?: number | null;
    linked_teacher?: {
        id: number;
        name: string;
        email: string;
    } | null;
};

const props = defineProps<{
    users: {
        data: UserRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    roles: Role[];
    departments: Department[];
    teachers: Array<{ id: number; name: string; email: string }>;
}>();

const isModalOpen = ref(false);
const editingUser = ref<UserRow | null>(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    role_id: null as number | null,
    linked_teacher_user_id: null as number | null,
    department_ids: [] as number[],
    is_active: true,
});

const docenteRole = computed(() => props.roles.find((role) => role.name === 'DOCENTE'));
const isDocenteRoleSelected = computed(() => form.role_id === docenteRole.value?.id);

const roleLabel = (role?: Role) => {
    if (!role) return 'Sin rol';
    if (role.name === 'JEFE_DEPTO') return 'Jefe Depto';
    if (role.name === 'JEFE_OFICINA') return 'Jefe Oficina';
    return 'Docente';
};

const openCreateModal = () => {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    form.role_id = docenteRole.value?.id ?? props.roles[0]?.id ?? null;
    form.is_active = true;
    isModalOpen.value = true;
};

const openEditModal = (user: UserRow) => {
    editingUser.value = user;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.password = '';
    form.role_id = user.role_id;
    form.linked_teacher_user_id = user.linked_teacher_user_id ?? null;
    form.department_ids = user.departments.map((department) => department.id);
    form.is_active = user.is_active;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingUser.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (isDocenteRoleSelected.value) {
        form.linked_teacher_user_id = null;
    }

    if (editingUser.value) {
        form.put(`/admin/users/${editingUser.value.id}`, {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    form.post('/admin/users', {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const deactivateUser = (user: UserRow) => {
    if (!confirm(`Desactivar el usuario "${user.name}"?`)) return;

    router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Agregar Usuarios" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Admin', href: '#' },
            { title: 'Agregar', href: '#' },
            { title: 'Usuarios', href: '/admin/users' },
        ]"
    >
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Agregar</p>
                    <h1 class="text-2xl font-bold text-gray-900">Usuarios</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Crea cuentas de acceso, asigna roles, departamentos y relaciona cuentas administrativas con un docente si aplica.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
                    @click="openCreateModal"
                >
                    <UserPlus class="mr-2 h-5 w-5" />
                    Registrar Usuario
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Usuario</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Rol</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Departamentos</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Docente asociado</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900">{{ user.name }}</div>
                                <div class="text-sm text-gray-500">{{ user.email }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ roleLabel(user.role) }}</td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="department in user.departments"
                                        :key="department.id"
                                        class="rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800"
                                    >
                                        {{ department.name }}
                                    </span>
                                    <span v-if="user.departments.length === 0" class="text-xs italic text-gray-400">Sin asignar</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <template v-if="user.linked_teacher">
                                    <div class="font-medium">{{ user.linked_teacher.name }}</div>
                                    <div class="text-xs text-gray-500">{{ user.linked_teacher.email }}</div>
                                </template>
                                <span v-else class="text-xs italic text-gray-400">No aplica</span>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="user.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                >
                                    {{ user.is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="text-indigo-600 hover:text-indigo-900" title="Editar" @click="openEditModal(user)">
                                        <Edit2 class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="text-red-600 hover:text-red-900" title="Desactivar" @click="deactivateUser(user)">
                                        <ShieldBan class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td colspan="6" class="bg-gray-50 px-6 py-12 text-center text-gray-500">
                                No hay usuarios registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="users.links.length > 3" class="mt-4 flex items-center justify-center gap-1">
                <template v-for="(link, i) in users.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded px-3 py-1 text-sm"
                        :class="link.active ? 'bg-blue-600 font-semibold text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                        preserve-state
                    >
                        <span v-html="link.label" />
                    </Link>
                    <span v-else class="px-3 py-1 text-sm text-gray-400" v-html="link.label" />
                </template>
            </div>

            <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="closeModal"></div>

                    <div class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl sm:my-8 sm:w-full sm:max-w-lg">
                        <form @submit.prevent="submitForm">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <h3 class="mb-4 text-lg font-medium leading-6 text-gray-900">
                                    {{ editingUser ? 'Editar Usuario' : 'Registrar Nuevo Usuario' }}
                                </h3>

                                <div class="space-y-4">
                                    <div>
                                        <label for="user-name" class="block text-sm font-medium text-gray-700">Nombre</label>
                                        <input id="user-name" v-model="form.name" type="text" class="mt-1 block w-full rounded-md border-gray-300 sm:text-sm" required />
                                        <div v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</div>
                                    </div>

                                    <div>
                                        <label for="user-email" class="block text-sm font-medium text-gray-700">Correo electrónico</label>
                                        <input id="user-email" v-model="form.email" type="email" class="mt-1 block w-full rounded-md border-gray-300 sm:text-sm" required />
                                        <div v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</div>
                                    </div>

                                    <div>
                                        <label for="user-password" class="block text-sm font-medium text-gray-700">
                                            Contraseña {{ editingUser ? '(deja vacío para mantener)' : '' }}
                                        </label>
                                        <input id="user-password" v-model="form.password" type="password" class="mt-1 block w-full rounded-md border-gray-300 sm:text-sm" :required="!editingUser" />
                                        <div v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</div>
                                    </div>

                                    <div>
                                        <label for="user-role" class="block text-sm font-medium text-gray-700">Rol</label>
                                        <select id="user-role" v-model="form.role_id" class="mt-1 block w-full rounded-md border-gray-300 sm:text-sm" required>
                                            <option v-for="role in roles" :key="role.id" :value="role.id">{{ roleLabel(role) }}</option>
                                        </select>
                                        <div v-if="form.errors.role_id" class="mt-1 text-xs text-red-500">{{ form.errors.role_id }}</div>
                                    </div>

                                    <div v-if="!isDocenteRoleSelected">
                                        <label for="user-linked-teacher" class="block text-sm font-medium text-gray-700">Docente asociado (opcional)</label>
                                        <select id="user-linked-teacher" v-model="form.linked_teacher_user_id" class="mt-1 block w-full rounded-md border-gray-300 sm:text-sm">
                                            <option :value="null">Sin docente asociado</option>
                                            <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">
                                                {{ teacher.name }} - {{ teacher.email }}
                                            </option>
                                        </select>
                                        <p class="mt-1 text-xs text-gray-500">
                                            Si el rol es Docente, la cuenta ya representa directamente al docente.
                                        </p>
                                        <div v-if="form.errors.linked_teacher_user_id" class="mt-1 text-xs text-red-500">
                                            {{ form.errors.linked_teacher_user_id }}
                                        </div>
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-medium text-gray-700">Departamentos</label>
                                        <div class="max-h-32 space-y-2 overflow-y-auto rounded-md border border-gray-200 p-2">
                                            <label v-for="department in departments" :key="department.id" class="flex items-center">
                                                <input v-model="form.department_ids" type="checkbox" :value="department.id" class="rounded border-gray-300 text-blue-600" />
                                                <span class="ml-2 text-sm text-gray-600">{{ department.name }}</span>
                                            </label>
                                        </div>
                                    </div>

                                    <label v-if="editingUser" class="flex items-center">
                                        <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600" />
                                        <span class="ml-2 text-sm text-gray-600">Cuenta activa</span>
                                    </label>
                                </div>
                            </div>

                            <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex w-full justify-center rounded-md bg-blue-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-blue-700 disabled:opacity-50 sm:ml-3 sm:w-auto sm:text-sm"
                                >
                                    {{ editingUser ? 'Guardar Cambios' : 'Registrar' }}
                                </button>
                                <button
                                    type="button"
                                    class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
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
