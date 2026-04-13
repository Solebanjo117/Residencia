<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, watch } from 'vue';
import { CalendarClock, Edit2, Trash2, Filter, AlertCircle } from 'lucide-vue-next';

const props = defineProps<{
    windows: {
        data: any[];
        links: any[];
    };
    semesters: any[];
    evidenceItems: any[];
    selectedSemester: string | null;
}>();

const isModalOpen = ref(false);
const editingWindow = ref<any | null>(null);
const filterSemester = ref(props.selectedSemester || (props.semesters.length > 0 ? props.semesters[0].id : ''));

watch(filterSemester, (newValue) => {
    if (newValue !== props.selectedSemester) {
        router.get('/admin/windows', { semester_id: newValue }, { preserveState: true, replace: true });
    }
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
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
};

const openCreateModal = () => {
    editingWindow.value = null;
    form.reset();
    form.semester_id = filterSemester.value;
    form.status = 'ACTIVE';
    isModalOpen.value = true;
};

const openEditModal = (win: any) => {
    editingWindow.value = win;
    form.semester_id = win.semester_id;
    form.evidence_item_id = win.evidence_item_id;
    form.opens_at = formatForInput(win.opens_at);
    form.closes_at = formatForInput(win.closes_at);
    form.status = win.status;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
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
    if (confirm('¿Seguro que deseas eliminar esta ventana de entrega? Si estaba activa, los docentes perderán acceso de inmediato.')) {
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
        minute: '2-digit'
    });
};

const getStatusColor = (status: string, closesAt: string) => {
    if (status !== 'ACTIVE') return 'bg-gray-100 text-gray-800';
    if (new Date(closesAt) < new Date()) return 'bg-red-100 text-red-800'; // Expired but technically active
    return 'bg-green-100 text-green-800';
};

const getStatusText = (status: string, opensAt: string, closesAt: string) => {
    if (status !== 'ACTIVE') return 'INACTIVO';
    const now = new Date();
    if (now < new Date(opensAt)) return 'PROGRAMADA';
    if (now > new Date(closesAt)) return 'CERRADA (Vencida)';
    return 'ABIERTA';
};
</script>

<template>
    <Head title="Ventanas de Entrega" />

    <AppLayout :breadcrumbs="[{ title: 'Admin', href: '#' }, { title: 'Ventanas de Entrega', href: '/admin/windows' }]">
        <div class="px-6 py-8 mx-auto max-w-7xl">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6 gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Ventanas de Entrega</h1>
                    <p class="mt-1 text-sm text-gray-500">Configura las fechas límite para que los docentes suban sus documentos.</p>
                </div>
                <div class="flex gap-3 items-center">
                    <div class="relative">
                        <select 
                            v-model="filterSemester"
                            class="appearance-none bg-white border border-gray-300 text-gray-700 py-2 pl-4 pr-10 rounded-lg leading-tight focus:outline-none focus:bg-white focus:border-blue-500 shadow-sm text-sm"
                        >
                            <option value="">Selecciona semestre...</option>
                            <option v-for="sem in semesters" :key="sem.id" :value="sem.id">
                                {{ sem.name }}
                            </option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500">
                            <Filter class="w-4 h-4" />
                        </div>
                    </div>

                    <button type="button" @click="openCreateModal"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-sm text-white shadow-sm hover:bg-blue-700 transition-colors shrink-0"
                    >
                        <CalendarClock class="w-5 h-5 mr-2" />
                        Nueva Ventana
                    </button>
                </div>
            </div>

            <!-- Table Container -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Documento</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Apertura</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cierre</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="win in windows.data" :key="win.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900">{{ win.evidence_item.name }}</div>
                                <div class="text-xs text-gray-500">Semestre: {{ win.semester.name }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600">{{ formatDate(win.opens_at) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600 font-medium">{{ formatDate(win.closes_at) }}</div>
                                <div v-if="new Date(win.closes_at) < new Date()" class="text-[10px] text-red-500 uppercase font-bold mt-0.5">Vencido</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span 
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold"
                                    :class="getStatusColor(win.status, win.closes_at)"
                                >
                                    {{ getStatusText(win.status, win.opens_at, win.closes_at) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button type="button" @click="openEditModal(win)" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                    <Edit2 class="w-4 h-4" />
                                </button>
                                <button type="button" @click="destroyWindow(win.id)" class="text-red-600 hover:text-red-900">
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="windows.data.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500 bg-gray-50">
                                No se han configurado ventanas de entrega para este semestre.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="windows.links.length > 3" class="mt-4 flex items-center justify-center gap-1">
                <template v-for="(link, i) in windows.links" :key="i">
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

            <!-- Modal -->
            <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-center justify-center min-h-full p-4 text-center sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal"></div>
                    
                    <div class="relative bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full">
                        <form @submit.prevent="submitForm">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="mb-5">
                                    <h3 class="text-xl leading-8 font-bold text-gray-900 border-b pb-2">
                                        {{ editingWindow ? 'Editar Ventana de Entrega' : 'Nueva Ventana de Entrega' }}
                                    </h3>
                                </div>
                                
                                <div class="space-y-4">
                                    
                                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <AlertCircle class="h-5 w-5 text-blue-400" />
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-blue-700">
                                                    Las ventanas definen cuando los docentes pueden subir archivos para un documento en específico (ej. "Acta Parcial 1"). Fuera de estas fechas, el sistema bloqueará la subida.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Semestre</label>
                                        <select v-model="form.semester_id" class="mt-1 block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 focus:border-blue-500 sm:text-sm" required>
                                            <option v-for="sem in semesters" :key="sem.id" :value="sem.id">
                                                {{ sem.name }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.semester_id" class="text-red-500 text-xs mt-1">{{ form.errors.semester_id }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Documento Requerido</label>
                                        <select v-model="form.evidence_item_id" class="mt-1 block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 focus:border-blue-500 sm:text-sm" required>
                                            <option value="" disabled>Selecciona un Documento...</option>
                                            <option v-for="item in evidenceItems" :key="item.id" :value="item.id">
                                                {{ item.name }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.evidence_item_id" class="text-red-500 text-xs mt-1">{{ form.errors.evidence_item_id }}</div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Apertura</label>
                                            <input type="datetime-local" v-model="form.opens_at" class="mt-1 flex-1 block w-full rounded-md sm:text-sm border-gray-300 focus:ring-blue-500 focus:border-blue-500" required>
                                            <div v-if="form.errors.opens_at" class="text-red-500 text-xs mt-1">{{ form.errors.opens_at }}</div>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Cierre Limite</label>
                                            <input type="datetime-local" v-model="form.closes_at" class="mt-1 flex-1 block w-full rounded-md sm:text-sm border-gray-300 focus:ring-blue-500 focus:border-blue-500" required>
                                            <div v-if="form.errors.closes_at" class="text-red-500 text-xs mt-1">{{ form.errors.closes_at }}</div>
                                        </div>
                                    </div>

                                    <div v-if="editingWindow" class="pt-2">
                                        <label class="flex items-center">
                                            <input type="checkbox" :checked="form.status === 'ACTIVE'" @change="handleStatusChange" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring h-4 w-4">
                                            <span class="ml-2 text-sm text-gray-700">Ventana Activa (Forzar cierre desmarcando)</span>
                                        </label>
                                    </div>

                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t">
                                <button type="submit" :disabled="form.processing" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                                    Guardar
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
