<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { 
    CheckCircle, 
    XCircle, 
    FileText, 
    Download,
    Eye,
    ChevronLeft,
    Clock,
    AlertTriangle,
    MessageSquareX
} from 'lucide-vue-next';
import { ref } from 'vue';

declare const route: any;

const props = defineProps<{
    teacher: {
        id: number;
        name: string;
        email: string;
    };
    teaching_loads: any[];
    semester: {
        id: number;
        name: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pendientes Revisión', href: '/oficina/revisiones' },
    { title: props.teacher.name, href: '#' },
];

/** Modal for Rejection */
const showRejectModal = ref(false);
const rejectingSubmissionId = ref<number | null>(null);

const rejectForm = useForm({
    status: 'REJECTED',
    comments: ''
});

function openRejectModal(submissionId: number) {
    rejectingSubmissionId.value = submissionId;
    rejectForm.comments = '';
    showRejectModal.value = true;
}

function closeRejectModal() {
    showRejectModal.value = false;
    rejectingSubmissionId.value = null;
    rejectForm.reset();
}

function submitRejection() {
    if (!rejectingSubmissionId.value) return;
    
    rejectForm.post(route('oficina.revisiones.status', rejectingSubmissionId.value), {
        preserveScroll: true,
        onSuccess: () => {
            closeRejectModal();
        }
    });
}

/** Approve form */
const approveForm = useForm({
    status: 'APPROVED',
    comments: ''
});

function approveSubmission(submissionId: number) {
    if (!confirm('¿Está seguro de aprobar este documento?')) return;
    
    approveForm.post(route('oficina.revisiones.status', submissionId), {
        preserveScroll: true
    });
}

function statusColor(status: string) {
    const map: Record<string, string> = {
        'DRAFT': 'bg-gray-100 text-gray-800 ring-gray-600/20',
        'SUBMITTED': 'bg-blue-100 text-blue-800 ring-blue-600/20',
        'APPROVED': 'bg-green-100 text-green-800 ring-green-600/20',
        'REJECTED': 'bg-red-100 text-red-800 ring-red-600/20',
    };
    return map[status] || 'bg-gray-100 text-gray-800 ring-gray-600/20';
}
</script>

<template>
    <Head :title="'Revisión: ' + teacher.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-8 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8 flex flex-col items-start gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        :href="route('oficina.revisiones')"
                        class="p-2 text-gray-400 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        <ChevronLeft class="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                            {{ teacher.name }}
                        </h1>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ teacher.email }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Teaching Loads Mapping -->
            <div class="space-y-8">
                <div 
                    v-for="load in teaching_loads" 
                    :key="load.id"
                    class="bg-white border border-gray-200 shadow-sm rounded-2xl overflow-hidden"
                >
                    <div class="border-b border-gray-200 bg-gray-50/50 px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">
                                {{ load.subject.name }}
                            </h2>
                            <p class="text-sm text-gray-500">
                                Grupo: {{ load.group_name }} &middot; Semestre activo
                            </p>
                        </div>
                    </div>

                    <div class="px-6 py-5">
                        <div v-if="!load.submissions || load.submissions.length === 0" class="text-center py-6">
                            <p class="text-sm text-gray-500">No hay envíos registrados para este grupo.</p>
                        </div>

                        <div v-else class="space-y-6">
                            <div 
                                v-for="sub in load.submissions" 
                                :key="sub.id"
                                class="border border-gray-100 rounded-xl p-5 hover:bg-gray-50/50 transition-colors"
                            >
                                <div class="flex flex-col lg:flex-row lg:items-start gap-6">
                                    <!-- Requirement Info -->
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h3 class="text-base font-semibold text-gray-900">
                                                {{ sub.evidence_item.name }}
                                            </h3>
                                            <span 
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                                :class="statusColor(sub.status)"
                                            >
                                                {{ sub.status }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600">{{ sub.evidence_item.description }}</p>

                                        <div class="mt-4 flex items-center gap-2 text-sm text-gray-500">
                                            <Clock class="w-4 h-4" />
                                            Última actualización: {{ new Date(sub.last_updated_at).toLocaleString('es-MX') }}
                                        </div>
                                    </div>

                                    <!-- Files Attached -->
                                    <div class="lg:w-1/3">
                                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Archivos ({{ sub.files?.length || 0 }})</h4>
                                        <ul class="space-y-2">
                                            <li v-for="file in sub.files" :key="file.id" class="flex items-center justify-between p-2 rounded-lg bg-white border border-gray-200">
                                                <div class="flex items-center gap-3 overflow-hidden">
                                                    <FileText class="w-4 h-4 text-gray-400 shrink-0" />
                                                    <span class="text-sm font-medium text-gray-700 truncate" :title="file.file_name">
                                                        {{ file.file_name }}
                                                    </span>
                                                </div>
                                                <a 
                                                    :href="'/storage/' + file.stored_relative_path" 
                                                    target="_blank"
                                                    class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-md shrink-0"
                                                    title="Descargar"
                                                >
                                                    <Download class="w-4 h-4" />
                                                </a>
                                            </li>
                                            <li v-if="!sub.files || sub.files.length === 0" class="text-xs text-gray-500 italic">
                                                Sin archivos subidos.
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Actions Panel -->
                                    <div class="lg:w-64 pt-4 lg:pt-0 lg:border-l lg:border-gray-100 lg:pl-6 flex flex-col justify-center border-t border-gray-100 mt-4 lg:mt-0">
                                        <div v-if="sub.status === 'SUBMITTED'" class="flex flex-col gap-2">
                                            <button type="button" @click="approveSubmission(sub.id)"
                                                :disabled="approveForm.processing"
                                                class="inline-flex w-full justify-center items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:focus:ring-2 focus:ring-offset-2 hover:bg-green-500"
                                            >
                                                <CheckCircle class="w-4 h-4" />
                                                Aprobar
                                            </button>
                                            <button type="button" @click="openRejectModal(sub.id)"
                                                class="inline-flex w-full justify-center items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-inset ring-red-300 hover:bg-red-50"
                                            >
                                                <XCircle class="w-4 h-4" />
                                                Rechazar
                                            </button>
                                        </div>
                                        <div v-else-if="sub.status === 'APPROVED'" class="text-center">
                                            <CheckCircle class="w-8 h-8 text-green-500 mx-auto mb-2" />
                                            <p class="text-sm font-medium text-green-700">Evidencia Aprobada</p>
                                        </div>
                                        <div v-else-if="sub.status === 'REJECTED'" class="text-center">
                                            <AlertTriangle class="w-8 h-8 text-red-500 mx-auto mb-2" />
                                            <p class="text-sm font-medium text-red-700">Rechazada</p>
                                            <p class="text-xs text-red-500 mt-1 leading-tight">El docente ha sido notificado para corregir.</p>
                                        </div>
                                        <div v-else class="text-center text-sm text-gray-500">
                                            No lista para revisar
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rejection Modal -->
        <div v-if="showRejectModal" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <MessageSquareX class="h-6 w-6 text-red-600" aria-hidden="true" />
                                </div>
                                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                                    <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Motivo de Rechazo</h3>
                                    <div class="mt-2 text-sm text-gray-500 mb-4">
                                        Describe por qué esta evidencia no es válida. Al confirmar, el sistema automáticamente habilitará a este docente para re-subir el archivo (tarda ~3 días de gracia).
                                    </div>
                                    
                                    <textarea
                                        v-model="rejectForm.comments"
                                        rows="4"
                                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-red-600 sm:text-sm sm:leading-6"
                                        placeholder="Falta firma, formato incorrecto..."
                                        required
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button type="button" @click="submitRejection"
                                :disabled="rejectForm.processing || !rejectForm.comments"
                                class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 disabled:opacity-50 sm:ml-3 sm:w-auto"
                            >
                                Confirmar Rechazo
                            </button>
                            <button type="button" @click="closeRejectModal"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
