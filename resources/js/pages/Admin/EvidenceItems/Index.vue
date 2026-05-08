<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Edit2, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

type EvidenceCategory = {
    id: number;
    name: string;
    description: string | null;
    items_count: number;
};

type EvidenceItem = {
    id: number;
    category_id: number;
    name: string;
    description: string | null;
    requires_subject: boolean;
    active: boolean;
    category: EvidenceCategory;
    requirements_count: number;
    submissions_count: number;
};

const props = defineProps<{
    categories: EvidenceCategory[];
    items: {
        data: EvidenceItem[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
}>();

const isModalOpen = ref(false);
const editingItem = ref<EvidenceItem | null>(null);

const form = useForm({
    category_id: props.categories[0]?.id ?? '',
    name: '',
    description: '',
    requires_subject: true,
    active: true,
});

const canSubmit = computed(() => props.categories.length > 0);

const openCreateModal = () => {
    editingItem.value = null;
    form.reset();
    form.clearErrors();
    form.category_id = props.categories[0]?.id ?? '';
    form.requires_subject = true;
    form.active = true;
    isModalOpen.value = true;
};

const openEditModal = (item: EvidenceItem) => {
    editingItem.value = item;
    form.clearErrors();
    form.category_id = item.category_id;
    form.name = item.name;
    form.description = item.description ?? '';
    form.requires_subject = item.requires_subject;
    form.active = item.active;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingItem.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (editingItem.value) {
        form.put(`/admin/evidence-items/${editingItem.value.id}`, {
            preserveScroll: true,
            onSuccess: closeModal,
        });
        return;
    }

    form.post('/admin/evidence-items', {
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const deleteItem = (item: EvidenceItem) => {
    if (
        !confirm(
            `Eliminar el rubro "${item.name}"? Esta accion no se puede deshacer.`,
        )
    ) {
        return;
    }

    useForm({}).delete(`/admin/evidence-items/${item.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Rubros de Evidencia" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Agregar', href: '#' },
            { title: 'Rubros de evidencia', href: '/admin/evidence-items' },
        ]"
    >
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div>
                    <p
                        class="text-sm font-semibold uppercase tracking-wide text-blue-600"
                    >
                        Agregar
                    </p>
                    <h1 class="text-2xl font-bold text-gray-900">
                        Rubros de evidencia
                    </h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Alta y control de los documentos que despues se activan
                        en la matriz por semestre y departamento.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center rounded-lg border border-transparent bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canSubmit"
                    @click="openCreateModal"
                >
                    <Plus class="mr-2 h-5 w-5" />
                    Agregar rubro
                </button>
            </div>

            <div
                v-if="props.categories.length === 0"
                class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800"
            >
                No existen categorias de evidencia. Ejecuta las migraciones o el
                bootstrap institucional antes de crear rubros.
            </div>

            <div
                v-if="$page.props.errors.error"
                class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
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
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                Rubro
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                Categoria
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                Alcance
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                Estado
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                Uso
                            </th>
                            <th
                                class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="item in props.items.data"
                            :key="item.id"
                            class="transition-colors hover:bg-gray-50"
                        >
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <div class="font-semibold">{{ item.name }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ item.description || 'Sin descripcion' }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                {{ item.category?.name }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                {{
                                    item.requires_subject
                                        ? 'Por carga/materia'
                                        : 'General del docente'
                                }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    :class="
                                        item.active
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-slate-100 text-slate-600'
                                    "
                                >
                                    {{ item.active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                {{ item.requirements_count }} matriz /
                                {{ item.submissions_count }} evidencias
                            </td>
                            <td
                                class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium"
                            >
                                <div class="flex justify-end gap-3">
                                    <button
                                        type="button"
                                        class="text-indigo-600 hover:text-indigo-900"
                                        title="Editar"
                                        @click="openEditModal(item)"
                                    >
                                        <Edit2 class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="text-red-600 hover:text-red-900 disabled:cursor-not-allowed disabled:opacity-40"
                                        title="Eliminar"
                                        :disabled="
                                            item.requirements_count > 0 ||
                                            item.submissions_count > 0
                                        "
                                        @click="deleteItem(item)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="props.items.data.length === 0">
                            <td
                                colspan="6"
                                class="bg-gray-50 px-6 py-12 text-center text-gray-500"
                            >
                                No hay rubros registrados. Comienza agregando
                                los documentos que usara la matriz.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="props.items.links.length > 3"
                class="mt-4 flex items-center justify-center gap-1"
            >
                <template v-for="(link, i) in props.items.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded px-3 py-1 text-sm"
                        :class="
                            link.active
                                ? 'bg-blue-600 font-semibold text-white'
                                : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                        "
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
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                                <h3
                                    id="modal-title"
                                    class="mb-4 text-lg font-medium leading-6 text-gray-900"
                                >
                                    {{
                                        editingItem
                                            ? 'Editar rubro'
                                            : 'Agregar rubro'
                                    }}
                                </h3>

                                <div class="space-y-4">
                                    <div>
                                        <label
                                            for="category_id"
                                            class="block text-sm font-medium text-gray-700"
                                        >
                                            Categoria
                                        </label>
                                        <select
                                            id="category_id"
                                            v-model="form.category_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                            required
                                        >
                                            <option
                                                v-for="category in props.categories"
                                                :key="category.id"
                                                :value="category.id"
                                            >
                                                {{ category.name }}
                                            </option>
                                        </select>
                                        <div
                                            v-if="form.errors.category_id"
                                            class="mt-1 text-xs text-red-500"
                                        >
                                            {{ form.errors.category_id }}
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            for="name"
                                            class="block text-sm font-medium text-gray-700"
                                        >
                                            Nombre del rubro
                                        </label>
                                        <input
                                            id="name"
                                            v-model="form.name"
                                            type="text"
                                            class="mt-1 block w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
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
                                            for="description"
                                            class="block text-sm font-medium text-gray-700"
                                        >
                                            Descripcion
                                        </label>
                                        <textarea
                                            id="description"
                                            v-model="form.description"
                                            rows="3"
                                            class="mt-1 block w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        ></textarea>
                                        <div
                                            v-if="form.errors.description"
                                            class="mt-1 text-xs text-red-500"
                                        >
                                            {{ form.errors.description }}
                                        </div>
                                    </div>

                                    <label
                                        class="flex items-center gap-2 text-sm text-gray-700"
                                    >
                                        <input
                                            v-model="form.requires_subject"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        Requiere materia/carga academica
                                    </label>

                                    <label
                                        class="flex items-center gap-2 text-sm text-gray-700"
                                    >
                                        <input
                                            v-model="form.active"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        Activo para matrices y ventanas
                                    </label>
                                </div>
                            </div>

                            <div
                                class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6"
                            >
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex w-full justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 sm:ml-3 sm:w-auto sm:text-sm"
                                >
                                    {{
                                        editingItem
                                            ? 'Guardar cambios'
                                            : 'Agregar'
                                    }}
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
