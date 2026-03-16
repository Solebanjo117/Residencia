<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { FileStack, UploadCloud, Send, File, CheckCircle2, Clock, AlertTriangle, AlertCircle } from 'lucide-vue-next';

interface Task {
    id: number;
    teaching_load: {
        id: number;
        subject_name: string;
        group: string;
    };
    requirement: {
        item_id: number;
        item_name: string;
        is_mandatory: boolean;
    };
    submission: {
        status: string;
        files_count: number;
        files: any[];
    };
    window: {
        opens_at: string;
        closes_at: string;
        is_open: boolean;
    } | null;
}

const props = defineProps<{
    semester: any | null;
    tasks: Task[];
}>();

const selectedTask = ref<Task | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const uploadForm = useForm({
    file: null as File | null,
});

// Computed properties for grouping
const groupedTasks = computed(() => {
    const groups: Record<string, Task[]> = {};
    props.tasks.forEach(task => {
        const key = `${task.teaching_load.subject_name} - Grupo ${task.teaching_load.group}`;
        if (!groups[key]) groups[key] = [];
        groups[key].push(task);
    });
    return groups;
});

const selectTask = (task: Task) => {
    selectedTask.value = task;
};

const getStatusConfig = (status: string) => {
    switch(status) {
        case 'DRAFT': return { class: 'bg-gray-100 text-gray-800', label: 'Borrador', icon: Clock };
        case 'SUBMITTED': return { class: 'bg-blue-100 text-blue-800', label: 'Enviado', icon: Send };
        case 'APPROVED': return { class: 'bg-green-100 text-green-800', label: 'Aprobado', icon: CheckCircle2 };
        case 'REJECTED': return { class: 'bg-red-100 text-red-800', label: 'Rechazado (Requiere Corrección)', icon: AlertTriangle };
        default: return { class: 'bg-gray-100 text-gray-800', label: status, icon: Clock };
    }
};

const triggerUpload = () => {
    fileInput.value?.click();
};

const handleFileSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0 && selectedTask.value) {
        uploadForm.file = target.files[0];
        
        uploadForm.post(`/docente/evidencias/${selectedTask.value.id}/upload`, {
            preserveScroll: true,
            onSuccess: () => {
                target.value = '';
                uploadForm.reset();
                // Task data is refreshed automatically by Inertia, but we need to re-select it
                const updatedTask = props.tasks.find(t => t.id === selectedTask.value?.id);
                if (updatedTask) selectedTask.value = updatedTask;
            },
        });
    }
};

const submitEvidence = () => {
    if (!selectedTask.value) return;
    
    if (confirm('¿Estás seguro de enviar esta evidencia? Una vez enviada, no podrás modificar los archivos hasta que sea revisada.')) {
        router.post(`/docente/evidencias/${selectedTask.value.id}/submit`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                const updatedTask = props.tasks.find(t => t.id === selectedTask.value?.id);
                if (updatedTask) selectedTask.value = updatedTask;
            }
        });
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('es-ES', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatBytes = (bytes: number) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>

<template>
    <Head title="Mis Evidencias" />

    <AppLayout :breadcrumbs="[{ title: 'Mi Espacio', href: '/docente/evidencias' }]">
        
        <div class="px-6 py-8 mx-auto max-w-7xl h-[calc(100vh-4rem)] flex flex-col">
            
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                    <FileStack class="w-6 h-6 mr-3 text-indigo-600" />
                    Mis Entregas y Evidencias
                </h1>
                <p class="mt-1 text-sm text-gray-500">Sube tus documentos requeridos por la jefatura para el semestre actual.</p>
            </div>

            <div v-if="!semester" class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-md">
                <div class="flex">
                    <div class="ml-3"><p class="text-sm text-yellow-700">El semestre no está activo.</p></div>
                </div>
            </div>

            <div v-else class="flex flex-1 gap-6 overflow-hidden pb-8">
                
                <!-- Left Panel: Task List (Grouped by Subject) -->
                <div class="w-1/3 bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col overflow-hidden">
                    <div class="p-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="font-semibold text-gray-800">Materias Asignadas</h2>
                    </div>
                    <div class="overflow-y-auto flex-1 p-2">
                        
                        <div v-if="Object.keys(groupedTasks).length === 0" class="p-4 text-sm text-gray-500 text-center">
                            No tienes cargas académicas asignadas.
                        </div>

                        <div v-for="(groupTasks, subjectName) in groupedTasks" :key="subjectName" class="mb-4">
                            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider px-3 mb-2">{{ subjectName }}</h3>
                            <ul class="space-y-1">
                                <li v-for="task in groupTasks" :key="task.id">
                                    <button type="button" @click="selectTask(task)"
                                        class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors border"
                                        :class="selectedTask?.id === task.id ? 'bg-blue-50 border-blue-200 text-blue-700 ring-1 ring-inset ring-blue-500' : 'border-transparent hover:bg-gray-100 text-gray-700'"
                                    >
                                        <div class="font-medium flex justify-between items-center">
                                            <span class="truncate pr-2">{{ task.requirement.item_name }}</span>
                                            <span v-if="task.requirement.is_mandatory" class="text-[10px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded-sm shrink-0">Req</span>
                                        </div>
                                        <div class="flex justify-between items-center mt-1">
                                            <span 
                                                class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full"
                                                :class="getStatusConfig(task.submission.status).class"
                                            >
                                                {{ getStatusConfig(task.submission.status).label }}
                                            </span>
                                            <span class="text-xs text-gray-500 flex items-center">
                                                <File class="w-3 h-3 mr-1" /> {{ task.submission.files_count }}
                                            </span>
                                        </div>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Right Panel: Task Details & Upload Container -->
                <div class="w-2/3 bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col overflow-hidden relative">
                    
                    <div v-if="!selectedTask" class="flex-1 flex flex-col items-center justify-center text-gray-400 p-8 text-center">
                        <FileStack class="w-16 h-16 text-gray-200 mb-4" />
                        <p class="text-lg font-medium text-gray-600">Selecciona un Documento</p>
                        <p class="text-sm mt-1">Haz clic en un entregable de la lista izquierda para ver sus detalles y subir archivos.</p>
                    </div>

                    <div v-else class="flex flex-col h-full">
                        <!-- Header Details -->
                        <div class="p-6 border-b border-gray-200 bg-white">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900">{{ selectedTask.requirement.item_name }}</h2>
                                    <p class="text-sm text-gray-500 mt-1">{{ selectedTask.teaching_load.subject_name }} - Grupo {{ selectedTask.teaching_load.group }}</p>
                                </div>
                                <span 
                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider"
                                    :class="getStatusConfig(selectedTask.submission.status).class"
                                >
                                    <component :is="getStatusConfig(selectedTask.submission.status).icon" class="w-4 h-4 mr-1.5" />
                                    {{ getStatusConfig(selectedTask.submission.status).label }}
                                </span>
                            </div>

                            <div v-if="selectedTask.window" class="bg-blue-50 border border-blue-100 rounded-lg p-3 flex items-start">
                                <Clock class="w-5 h-5 text-blue-500 mr-2 shrink-0 mt-0.5" />
                                <div class="text-sm text-blue-800">
                                    <span class="font-semibold">Ventana de Recepción:</span> 
                                    Abre {{ formatDate(selectedTask.window.opens_at) }} y Cierra {{ formatDate(selectedTask.window.closes_at) }}.
                                    <div class="mt-1">
                                        Estado temporal: 
                                        <span class="font-bold" :class="selectedTask.window.is_open ? 'text-green-600' : 'text-red-500'">
                                            {{ selectedTask.window.is_open ? 'ABIERTA' : 'CERRADA' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="bg-red-50 border border-red-100 rounded-lg p-3 flex items-start">
                                <AlertCircle class="w-5 h-5 text-red-500 mr-2 shrink-0 mt-0.5" />
                                <div class="text-sm text-red-800">
                                    No hay una ventana de recepción configurada o activa para este documento actualmente.
                                </div>
                            </div>
                        </div>

                        <!-- Main Action Area (Files list and Uploads) -->
                        <div class="flex-1 overflow-y-auto p-6 bg-gray-50">
                            
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-sm font-semibold tracking-wider text-gray-500 uppercase">Archivos Adjuntos</h3>
                                
                                <button type="button" v-if="(selectedTask.submission.status === 'DRAFT' || selectedTask.submission.status === 'REJECTED') && selectedTask.window?.is_open"
                                    @click="triggerUpload"
                                    :disabled="uploadForm.processing"
                                    class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50"
                                >
                                    <UploadCloud class="w-4 h-4 mr-1.5" />
                                    Subir Archivo
                                </button>
                                <input type="file" ref="fileInput" class="hidden" accept=".docx,.pdf,.zip,.rar" @change="handleFileSelected" />
                            </div>

                            <ul v-if="selectedTask.submission.files.length > 0" class="bg-white border border-gray-200 rounded-lg divide-y divide-gray-100 shadow-sm">
                                <li v-for="file in selectedTask.submission.files" :key="file.id" class="p-4 flex items-center justify-between hover:bg-gray-50 transition">
                                    <div class="flex items-center min-w-0">
                                        <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600 mr-4 shrink-0">
                                            <File class="w-5 h-5" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">{{ file.file_name }}</p>
                                            <p class="text-xs text-gray-500 mt-0.5">{{ formatBytes(file.size) }} • Subido el {{ formatDate(file.uploaded_at) }}</p>
                                        </div>
                                    </div>
                                    <!-- Add delete/replace buttons here if needed later -->
                                </li>
                            </ul>
                            
                            <div v-else class="text-center py-10 bg-white border border-dashed border-gray-300 rounded-xl">
                                <UploadCloud class="w-10 h-10 text-gray-300 mx-auto mb-2" />
                                <p class="text-sm text-gray-500">No hay archivos adjuntos.</p>
                            </div>

                        </div>

                        <!-- Footer Actions -->
                        <div v-if="selectedTask.submission.status === 'DRAFT' || selectedTask.submission.status === 'REJECTED'" class="p-4 border-t border-gray-200 bg-white flex justify-end">
                            <button type="button" @click="submitEvidence"
                                :disabled="!selectedTask.window?.is_open || selectedTask.submission.files.length === 0"
                                class="inline-flex items-center px-5 py-2.5 bg-indigo-600 border border-transparent rounded-lg font-semibold text-sm text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50 transition"
                                :class="{'opacity-50 cursor-not-allowed': !selectedTask.window?.is_open || selectedTask.submission.files.length === 0}"
                            >
                                <Send class="w-4 h-4 mr-2 -ml-1" />
                                Enviar Evidencia a Revisión
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>
