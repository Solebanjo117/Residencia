<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import {
    AlertTriangle,
    CheckCircle,
    ChevronLeft,
    Clock,
    Download,
    FileText,
    MessageSquareX,
    ShieldCheck,
    XCircle,
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
    { title: 'Pendientes Revision', href: '/oficina/revisiones' },
    { title: props.teacher.name, href: '#' },
];

const showRejectModal = ref(false);
const rejectingSubmissionId = ref<number | null>(null);

const rejectForm = useForm({
    status: 'REJECTED',
    comments: '',
});

const approveForm = useForm({
    status: 'APPROVED',
    comments: '',
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
        onSuccess: closeRejectModal,
    });
}

function approveSubmission(submissionId: number) {
    if (!confirm('Se aprobara esta evidencia en nombre de oficina. Continuar?')) return;

    approveForm.post(route('oficina.revisiones.status', submissionId), {
        preserveScroll: true,
    });
}

function statusColor(status: string) {
    const map: Record<string, string> = {
        DRAFT: 'bg-slate-100 text-slate-800 ring-slate-600/20',
        SUBMITTED: 'bg-blue-100 text-blue-800 ring-blue-600/20',
        APPROVED: 'bg-green-100 text-green-800 ring-green-600/20',
        REJECTED: 'bg-rose-100 text-rose-800 ring-rose-600/20',
        NA: 'bg-slate-100 text-slate-700 ring-slate-600/20',
    };

    return map[status] || 'bg-slate-100 text-slate-800 ring-slate-600/20';
}
</script>

<template>
    <Head :title="'Revision: ' + teacher.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-8 flex flex-col items-start gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        :href="route('oficina.revisiones')"
                        class="rounded-lg border border-slate-200 bg-white p-2 text-slate-400 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        <ChevronLeft class="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ teacher.name }}</h1>
                        <p class="mt-1 text-sm text-slate-500">{{ teacher.email }}</p>
                    </div>
                </div>
            </div>

            <div class="space-y-8">
                <div v-for="load in teaching_loads" :key="load.id" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col justify-between gap-4 border-b border-slate-200 bg-slate-50/50 px-6 py-4 sm:flex-row sm:items-center">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">{{ load.subject.name }}</h2>
                            <p class="text-sm text-slate-500">Grupo: {{ load.group_name }} · {{ semester.name }}</p>
                        </div>
                    </div>

                    <div class="px-6 py-5">
                        <div v-if="!load.submissions || load.submissions.length === 0" class="py-6 text-center">
                            <p class="text-sm text-slate-500">No hay envios registrados para este grupo.</p>
                        </div>

                        <div v-else class="space-y-6">
                            <div v-for="sub in load.submissions" :key="sub.id" class="rounded-xl border border-slate-100 p-5 transition-colors hover:bg-slate-50/50">
                                <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
                                    <div class="flex-1">
                                        <div class="mb-2 flex flex-wrap items-center gap-3">
                                            <h3 class="text-base font-semibold text-slate-900">{{ sub.evidence_item.name }}</h3>
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset" :class="statusColor(sub.status)">
                                                {{ sub.status }}
                                            </span>
                                            <span v-if="sub.submitted_late" class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">
                                                Extemporanea
                                            </span>
                                            <span v-if="sub.final_approved_at" class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-200">
                                                Liberada
                                            </span>
                                        </div>
                                        <p class="text-sm text-slate-600">{{ sub.evidence_item.description }}</p>

                                        <div class="mt-4 flex items-center gap-2 text-sm text-slate-500">
                                            <Clock class="h-4 w-4" />
                                            Ultima actualizacion: {{ new Date(sub.last_updated_at).toLocaleString('es-MX') }}
                                        </div>

                                        <div v-if="sub.office_reviewer || sub.final_approver" class="mt-4 grid gap-3 md:grid-cols-2">
                                            <div v-if="sub.office_reviewer" class="rounded-lg border border-green-100 bg-green-50 p-3 text-sm text-green-800">
                                                <div class="text-xs font-semibold uppercase">Aprobado por oficina</div>
                                                <div class="mt-1 font-medium">{{ sub.office_reviewer.name }}</div>
                                                <div class="text-xs">{{ sub.office_reviewed_at }}</div>
                                            </div>
                                            <div v-if="sub.final_approver" class="rounded-lg border border-emerald-100 bg-emerald-50 p-3 text-sm text-emerald-800">
                                                <div class="text-xs font-semibold uppercase">Visto bueno final</div>
                                                <div class="mt-1 font-medium">{{ sub.final_approver.name }}</div>
                                                <div class="text-xs">{{ sub.final_approved_at }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="lg:w-1/3">
                                        <h4 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Archivos ({{ sub.files?.length || 0 }})</h4>
                                        <ul class="space-y-2">
                                            <li v-for="file in sub.files" :key="file.id" class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-2">
                                                <div class="flex items-center gap-3 overflow-hidden">
                                                    <FileText class="h-4 w-4 shrink-0 text-slate-400" />
                                                    <span class="truncate text-sm font-medium text-slate-700" :title="file.file_name">
                                                        {{ file.file_name }}
                                                    </span>
                                                </div>
                                                <a
                                                    :href="`/files/${file.id}/download`"
                                                    class="rounded-md p-1.5 text-indigo-600 hover:bg-indigo-50"
                                                    title="Descargar"
                                                >
                                                    <Download class="h-4 w-4" />
                                                </a>
                                            </li>
                                            <li v-if="!sub.files || sub.files.length === 0" class="text-xs italic text-slate-500">
                                                Sin archivos subidos.
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="mt-4 flex flex-col justify-center border-t border-slate-100 pt-4 lg:mt-0 lg:w-72 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                                        <div v-if="sub.status === 'SUBMITTED'" class="flex flex-col gap-2">
                                            <button
                                                type="button"
                                                @click="approveSubmission(sub.id)"
                                                :disabled="approveForm.processing"
                                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 focus:ring-2 focus:ring-offset-2"
                                            >
                                                <CheckCircle class="h-4 w-4" />
                                                Aprobar oficina
                                            </button>
                                            <button
                                                type="button"
                                                @click="openRejectModal(sub.id)"
                                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-rose-600 shadow-sm ring-1 ring-inset ring-rose-300 hover:bg-rose-50"
                                            >
                                                <XCircle class="h-4 w-4" />
                                                Rechazar
                                            </button>
                                        </div>
                                        <div v-else-if="sub.status === 'APPROVED' && sub.final_approved_at" class="text-center">
                                            <ShieldCheck class="mx-auto mb-2 h-8 w-8 text-emerald-500" />
                                            <p class="text-sm font-medium text-emerald-700">Liberada por jefatura</p>
                                        </div>
                                        <div v-else-if="sub.status === 'APPROVED'" class="text-center">
                                            <CheckCircle class="mx-auto mb-2 h-8 w-8 text-green-500" />
                                            <p class="text-sm font-medium text-green-700">Aprobada por oficina</p>
                                            <p class="mt-1 text-xs text-green-600">Pendiente de visto bueno final.</p>
                                        </div>
                                        <div v-else-if="sub.status === 'REJECTED'" class="text-center">
                                            <AlertTriangle class="mx-auto mb-2 h-8 w-8 text-rose-500" />
                                            <p class="text-sm font-medium text-rose-700">Rechazada</p>
                                            <p class="mt-1 text-xs leading-tight text-rose-500">El docente fue notificado para corregir.</p>
                                        </div>
                                        <div v-else class="text-center text-sm text-slate-500">
                                            No lista para revisar
                                        </div>
                                    </div>
                                </div>

                                <div v-if="sub.reviews?.length" class="mt-4 rounded-xl border border-slate-100 bg-slate-50 p-4">
                                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Historial de revision</h4>
                                    <ul class="space-y-2">
                                        <li v-for="review in sub.reviews" :key="`${review.stage}-${review.reviewed_at}`" class="rounded-lg border border-white bg-white px-3 py-2 text-sm">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                                    {{ review.stage === 'FINAL' ? 'FINAL' : 'OFICINA' }}
                                                </span>
                                                <span class="font-semibold" :class="review.decision === 'APPROVE' ? 'text-green-700' : 'text-rose-700'">
                                                    {{ review.decision === 'APPROVE' ? 'Aprobado' : 'Rechazado' }}
                                                </span>
                                                <span class="text-xs text-slate-500">{{ review.reviewed_at }}</span>
                                            </div>
                                            <div class="mt-1 text-xs text-slate-500">{{ review.reviewer?.name }}</div>
                                            <div v-if="review.comments" class="mt-1 text-sm text-slate-700">{{ review.comments }}</div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showRejectModal" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-500 bg-opacity-75 transition-opacity"></div>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <MessageSquareX class="h-6 w-6 text-rose-600" />
                                </div>
                                <div class="mt-3 w-full text-center sm:ml-4 sm:mt-0 sm:text-left">
                                    <h3 id="modal-title" class="text-base font-semibold leading-6 text-slate-900">Motivo de rechazo</h3>
                                    <div class="mb-4 mt-2 text-sm text-slate-500">
                                        Describe por que esta evidencia no es valida. El sistema habilitara reenvio por el docente.
                                    </div>

                                    <textarea
                                        v-model="rejectForm.comments"
                                        rows="4"
                                        class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-rose-600 sm:text-sm sm:leading-6"
                                        placeholder="Falta firma, formato incorrecto..."
                                        required
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button
                                type="button"
                                @click="submitRejection"
                                :disabled="rejectForm.processing || !rejectForm.comments"
                                class="inline-flex w-full justify-center rounded-md bg-rose-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-500 disabled:opacity-50 sm:ml-3 sm:w-auto"
                            >
                                Confirmar rechazo
                            </button>
                            <button
                                type="button"
                                @click="closeRejectModal"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto"
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
