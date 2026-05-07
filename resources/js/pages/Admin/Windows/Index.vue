<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    CalendarClock,
    Edit2,
    Trash2,
    Filter,
    AlertCircle,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    windows: {
        data: any[];
        links: any[];
    };
    semesters: any[];
    evidenceItems: any[];
    selectedSemester: string | null;
    selectedStatus?: string | null;
}>();

const isModalOpen = ref(false);
const editingWindow = ref<any | null>(null);
const filterSemester = ref(
    props.selectedSemester ||
        (props.semesters.length > 0 ? props.semesters[0].id : ''),
);
const filterStatus = ref(props.selectedStatus || '');

const statusOptions = [
    { value: '', label: 'Todos los estados' },
    { value: 'OPEN', label: 'Abiertas' },
    { value: 'UPCOMING', label: 'Programadas' },
    { value: 'CLOSED', label: 'Cerradas' },
    { value: 'INACTIVE', label: 'Inactivas' },
];

const applyWindowFilters = () => {
    router.get(
        '/admin/windows',
        {
            semester_id: filterSemester.value || undefined,
            status: filterStatus.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

watch([filterSemester, filterStatus], () => {
    applyWindowFilters();
});

const form = useForm({
    semester_id: filterSemester.value,
    evidence_item_id: '',
    opens_at: '',
    closes_at: '',
    status: 'ACTIVE',
});

// Helper for date-local formatting
const formatForInput = (dateString: string) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16);
};

const openCreateModal = () => {
    editingWindow.value = null;
    form.reset();
    form.clearErrors();
    form.semester_id = filterSemester.value;
    form.status = 'ACTIVE';
    isModalOpen.value = true;
};

const openEditModal = (win: any) => {
    editingWindow.value = win;
    form.clearErrors();
    form.semester_id = win.semester_id;
    form.evidence_item_id = win.evidence_item_id;
    form.opens_at = formatForInput(win.opens_at);
    form.closes_at = formatForInput(win.closes_at);
    form.status = win.status;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.clearErrors();
    form.reset();
};

const handleStatusChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    form.status = target.checked ? 'ACTIVE' : 'INACTIVE';
};

const submitForm = () => {
    if (editingWindow.value) {
        form.put(`/admin/windows/${editingWindow.value.id}`, {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post('/admin/windows', {
            onSuccess: () => closeModal(),
        });
    }
};

const destroyWindow = (id: number) => {
    if (
        confirm(
            '¿Seguro que deseas eliminar esta ventana de entrega? Si estaba activa, los docentes perderán acceso de inmediato.',
        )
    ) {
        router.delete(`/admin/windows/${id}`, { preserveScroll: true });
    }
};

// Formatting helpers
const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const paginationLabel = (label: string) =>
    label.replace('&laquo;', '‹').replace('&raquo;', '›');

const getStatusColor = (status: string, opensAt: string, closesAt: string) => {
    if (status !== 'ACTIVE') return 'bg-gray-100 text-gray-800';
    const now = new Date();
    if (now < new Date(opensAt)) return 'bg-blue-100 text-blue-800';
    if (now > new Date(closesAt)) return 'bg-red-100 text-red-800';
    return 'bg-green-100 text-green-800';
};

const getStatusText = (status: string, opensAt: string, closesAt: string) => {
    if (status !== 'ACTIVE') return 'INACTIVO';
    const now = new Date();
    if (now < new Date(opensAt)) return 'PROGRAMADA';
    if (now > new Date(closesAt)) return 'CERRADA';
    return 'ABIERTA';
};
</script>

<template>
    <Head title="Ventanas de Entrega" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Admin', href: '#' },
            { title: 'Ventanas de Entrega', href: '/admin/windows' },
        ]"
    >
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div
                class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        Ventanas de Entrega
                    </h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Configura aperturas, cierres y disponibilidad de entrega
                        por evidencia.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <select
                            v-model="filterSemester"
                            class="appearance-none rounded-lg border border-gray-300 bg-white py-2 pr-10 pl-4 text-sm leading-tight text-gray-700 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none"
                        >
                            <option value="">Selecciona semestre...</option>
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

                    <div class="relative">
                        <select
                            v-model="filterStatus"
                            class="appearance-none rounded-lg border border-gray-300 bg-white py-2 pr-10 pl-4 text-sm leading-tight text-gray-700 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none"
                        >
                            <option
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <div
                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500"
                        >
                            <Filter class="h-4 w-4" />
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex shrink-0 items-center rounded-lg border border-transparent bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700"
                    >
                        <CalendarClock class="mr-2 h-5 w-5" />
                        Nueva Ventana
                    </button>
                </div>
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
                                Documento
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Apertura
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                            >
                                Cierre
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
                            v-for="win in windows.data"
                            :key="win.id"
                            class="transition-colors hover:bg-gray-50"
                        >
                            <td class="px-6 py-4">
                                <div
                                    class="text-sm font-semibold text-gray-900"
                                >
                                    {{ win.evidence_item.name }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    Semestre: {{ win.semester.name }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600">
                                    {{ formatDate(win.opens_at) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-600">
                                    {{ formatDate(win.closes_at) }}
                                </div>
                                <div
                                    v-if="new Date(win.closes_at) < new Date()"
                                    class="mt-0.5 text-[10px] font-bold text-red-500 uppercase"
                                >
                                    Vencido
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold"
                                    :class="
                                        getStatusColor(
                                            win.status,
                                            win.opens_at,
                                            win.closes_at,
                                        )
                                    "
                                >
                                    {{
                                        getStatusText(
                                            win.status,
                                            win.opens_at,
                                            win.closes_at,
                                        )
                                    }}
                                </span>
                            </td>
                            <td
                                class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                            >
                                <button
                                    type="button"
                                    @click="openEditModal(win)"
                                    class="mr-3 text-indigo-600 hover:text-indigo-900"
                                >
                                    <Edit2 class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="destroyWindow(win.id)"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="windows.data.length === 0">
                            <td
                                colspan="5"
                                class="bg-gray-50 px-6 py-12 text-center text-gray-500"
                            >
                                No se han configurado ventanas de entrega para
                                este semestre.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="windows.links.length > 3"
                class="mt-4 flex items-center justify-center gap-1"
            >
                <template v-for="(link, i) in windows.links" :key="i">
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
                        {{ paginationLabel(link.label) }}
                    </Link>
                    <span v-else class="px-3 py-1 text-sm text-gray-400">
                        {{ paginationLabel(link.label) }}
                    </span>
                </template>
            </div>

            <!-- Modal -->
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
                                            editingWindow
                                                ? 'Editar Ventana de Entrega'
                                                : 'Nueva Ventana de Entrega'
                                        }}
                                    </h3>
                                </div>

                                <div class="space-y-4">
                                    <div
                                        class="mb-4 border-l-4 border-blue-400 bg-blue-50 p-4"
                                    >
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <AlertCircle
                                                    class="h-5 w-5 text-blue-400"
                                                />
                                            </div>
                                            <div class="ml-3">
                                                <p
                                                    class="text-sm text-blue-700"
                                                >
                                                    Las ventanas definen cuando
                                                    los docentes pueden subir
                                                    archivos para un documento
                                                    en específico (ej. "Acta
                                                    Parcial 1"). Fuera de estas
                                                    fechas, el sistema bloqueará
                                                    la subida.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="
                                            Object.keys(form.errors).length > 0
                                        "
                                        class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                                    >
                                        Revisa los datos de la ventana. Si
                                        existe solape, ajusta apertura/cierre o
                                        inactiva la ventana anterior.
                                    </div>

                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700"
                                            >Semestre</label
                                        >
                                        <select
                                            v-model="form.semester_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 py-2 pr-10 pl-3 focus:border-blue-500 sm:text-sm"
                                            required
                                        >
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
                                            >Documento Requerido</label
                                        >
                                        <select
                                            v-model="form.evidence_item_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 py-2 pr-10 pl-3 focus:border-blue-500 sm:text-sm"
                                            required
                                        >
                                            <option value="" disabled>
                                                Selecciona un Documento...
                                            </option>
                                            <option
                                                v-for="item in evidenceItems"
                                                :key="item.id"
                                                :value="item.id"
                                            >
                                                {{ item.name }}
                                            </option>
                                        </select>
                                        <div
                                            v-if="form.errors.evidence_item_id"
                                            class="mt-1 text-xs text-red-500"
                                        >
                                            {{ form.errors.evidence_item_id }}
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-gray-700"
                                                >Apertura</label
                                            >
                                            <input
                                                type="datetime-local"
                                                v-model="form.opens_at"
                                                class="mt-1 block w-full flex-1 rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                required
                                            />
                                            <div
                                                v-if="form.errors.opens_at"
                                                class="mt-1 text-xs text-red-500"
                                            >
                                                {{ form.errors.opens_at }}
                                            </div>
                                        </div>
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-gray-700"
                                                >Cierre Limite</label
                                            >
                                            <input
                                                type="datetime-local"
                                                v-model="form.closes_at"
                                                class="mt-1 block w-full flex-1 rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                                required
                                            />
                                            <div
                                                v-if="form.errors.closes_at"
                                                class="mt-1 text-xs text-red-500"
                                            >
                                                {{ form.errors.closes_at }}
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="editingWindow" class="pt-2">
                                        <label class="flex items-center">
                                            <input
                                                type="checkbox"
                                                :checked="
                                                    form.status === 'ACTIVE'
                                                "
                                                @change="handleStatusChange"
                                                class="h-4 w-4 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring"
                                            />
                                            <span
                                                class="ml-2 text-sm text-gray-700"
                                                >Ventana Activa (Forzar cierre
                                                desmarcando)</span
                                            >
                                        </label>
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
                                    Guardar
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
