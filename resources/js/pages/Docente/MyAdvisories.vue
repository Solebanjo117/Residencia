<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { 
    CalendarDays, 
    Clock, 
    FileText, 
    Plus,
    Trash2,
    BookOpen
} from 'lucide-vue-next';
import { ref } from 'vue';

declare const route: any;

const props = defineProps<{
    sessions: any[];
    teaching_loads: any[];
    semester: {
        id: number;
        name: string;
    } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mis Asesorías', href: '/docente/asesorias' },
];

const showCreateModal = ref(false);

const form = useForm({
    teaching_load_id: '',
    session_date: new Date().toISOString().split('T')[0],
    topic: '',
    duration_minutes: 60,
    notes: '',
    files: [] as File[]
});

function handleFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files) {
        form.files = Array.from(target.files);
    }
}

function submitForm() {
    form.post(route('docente.asesorias.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        }
    });
}

function deleteSession(id: number) {
    if (confirm('¿Eliminar registro de asesoría?')) {
        useForm({}).delete(route('docente.asesorias.destroy', id), {
            preserveScroll: true
        });
    }
}
</script>

<template>
    <Head title="Mis Asesorías" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-8 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8 md:flex md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Mis Asesorías</h1>
                    <p class="mt-2 text-sm text-gray-500 flex items-center gap-2">
                        <Clock class="w-4 h-4" />
                        Semestre Activo: 
                        <span class="font-semibold text-gray-900">{{ semester?.name ?? 'Ninguno' }}</span>
                    </p>
                </div>
                
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <button type="button" @click="showCreateModal = true"
                        :disabled="!semester"
                        class="inline-flex items-center gap-x-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                    >
                        <Plus class="-ml-0.5 h-5 w-5" aria-hidden="true" />
                        Registrar Sesión
                    </button>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="sessions.length === 0" class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-white py-16 px-6 text-center">
                <BookOpen class="mx-auto h-12 w-12 text-gray-400" aria-hidden="true" />
                <h3 class="mt-4 text-sm font-semibold text-gray-900">No hay asesorías</h3>
                <p class="mt-1 text-sm text-gray-500">Comienza registrando las asesorías impartidas a los alumnos.</p>
            </div>

            <!-- List -->
            <div v-else class="flow-root">
                <ul role="list" class="-mb-8">
                    <li v-for="(session, sessionIdx) in sessions" :key="session.id">
                        <div class="relative pb-8">
                            <span v-if="sessionIdx !== sessions.length - 1" class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                            <div class="relative flex items-start space-x-3">
                                <div class="relative">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50 ring-8 ring-white">
                                        <CalendarDays class="h-5 w-5 text-indigo-600" aria-hidden="true" />
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1 bg-white border border-gray-200 shadow-sm rounded-xl p-5 hover:shadow-md transition-shadow">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ session.subject_name }} ({{ session.group_name }})
                                            </div>
                                            <p class="mt-0.5 text-sm text-gray-500 flex items-center gap-2">
                                                <span>{{ new Date(session.session_date).toLocaleDateString('es-MX', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                                                &middot;
                                                <span>{{ session.duration_minutes }} min</span>
                                            </p>
                                        </div>
                                        <button type="button" @click="deleteSession(session.id)"
                                            class="text-red-600 hover:bg-red-50 p-1.5 rounded-lg"
                                            title="Eliminar registro"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                    <div class="mt-2 text-sm text-gray-700">
                                        <span class="font-semibold block mb-1">Tema: {{ session.topic }}</span>
                                        <p v-if="session.notes" class="text-gray-600 italic">"{{ session.notes }}"</p>
                                    </div>

                                    <!-- Files -->
                                    <div v-if="session.files && session.files.length > 0" class="mt-4 flex flex-wrap gap-2">
                                        <a 
                                            v-for="file in session.files" 
                                            :key="file.id"
                                            :href="'/storage/' + file.stored_relative_path"
                                            target="_blank"
                                            class="inline-flex items-center gap-1.5 rounded bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 hover:bg-gray-100"
                                        >
                                            <FileText class="w-3 h-3 text-indigo-500" />
                                            {{ file.file_name }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreateModal" class="relative z-50">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <form @submit.prevent="submitForm" class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4 border-b border-gray-100">
                            <h3 class="text-lg font-semibold leading-6 text-gray-900 mb-4">Registrar Nueva Asesoría</h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium leading-6 text-gray-900">Materia / Grupo</label>
                                    <select v-model="form.teaching_load_id" required class="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                        <option value="" disabled>Seleccione materia...</option>
                                        <option v-for="load in teaching_loads" :key="load.id" :value="load.id">
                                            {{ load.subject.name }} ({{ load.group_name }})
                                        </option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium leading-6 text-gray-900">Fecha</label>
                                        <input type="date" v-model="form.session_date" required class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium leading-6 text-gray-900">Duración (Minutos)</label>
                                        <input type="number" min="1" v-model="form.duration_minutes" required class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium leading-6 text-gray-900">Tema Abordado</label>
                                    <input type="text" v-model="form.topic" required placeholder="Ej. Resolución de dudas Unidad 1" class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium leading-6 text-gray-900">Notas adicionales (opcional)</label>
                                    <textarea v-model="form.notes" rows="2" class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium leading-6 text-gray-900">Evidencia Física (Opcional)</label>
                                    <div class="mt-2 text-sm text-gray-500">
                                        <input type="file" multiple @change="handleFileChange" accept=".pdf,.jpg,.png,.doc,.docx" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button type="submit" :disabled="form.processing" class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 sm:ml-3 sm:w-auto">
                                Guardar Registro
                            </button>
                            <button type="button" @click="showCreateModal = false" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
