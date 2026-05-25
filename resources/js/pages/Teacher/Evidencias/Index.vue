<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    Clock,
    File as FileIcon,
    FileStack,
    Pencil,
    Send,
    ShieldCheck,
    Trash2,
    UploadCloud,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface Task {
    id: number | null;
    teaching_load: {
        id: number;
        subject_name: string;
        group: string;
    };
    requirement: {
        item_id: number;
        item_name: string;
        is_mandatory: boolean;
        stage_order: number;
        stage_label: string;
    };
    submission: {
        status: string | null;
        ui_status: string;
        files_count: number;
        files: Array<{
            id: number;
            file_name: string;
            size: number;
            uploaded_at: string;
            download_url: string;
            mime_type: string | null;
            is_docx: boolean;
            editor_url: string | null;
            can_edit_docx: boolean;
            can_delete: boolean;
        }>;
        submitted_late: boolean;
        office_approved_at: string | null;
        office_approved_by: string | null;
        final_approved_at: string | null;
        final_approved_by: string | null;
        last_review: {
            stage: string;
            decision: string;
            comments: string | null;
            reviewed_at: string | null;
            reviewer_name: string | null;
        } | null;
        review_trail: Array<{
            stage: string;
            decision: string;
            comments: string | null;
            reviewed_at: string | null;
            reviewer_name: string | null;
        }>;
        latest_status_change: {
            from_status: string;
            to_status: string;
            reason: string | null;
            changed_at: string | null;
            changed_by_name: string | null;
        } | null;
    };
    availability: {
        code: string;
        label: string;
        is_available: boolean;
        is_late: boolean;
        is_future: boolean;
    };
    window: {
        opens_at: string;
        closes_at: string;
        state_code: string;
        state_label: string;
        is_open: boolean;
    } | null;
    can_initialize: boolean;
    can_upload: boolean;
    can_submit: boolean;
}

interface SemesterOption {
    id: number;
    name: string;
}

interface TeachingLoadOption {
    id: number;
    label: string;
}

const props = defineProps<{
    semester: any | null;
    tasks: Task[];
    allowedExtensions?: string[];
    semesters: SemesterOption[];
    selectedSemesterId: number | null;
    teachingLoads: TeachingLoadOption[];
    selectedTeachingLoadId: number | null;
    selectedEvidenceItemId?: number | null;
    selectedSubmissionId?: number | null;
}>();

const uploadAccept = computed(() => {
    const extensions = props.allowedExtensions?.length
        ? props.allowedExtensions
        : ['docx', 'pdf', 'jpg', 'jpeg', 'png', 'webp'];

    return extensions.map((extension) => `.${extension}`).join(',');
});

const selectedSemesterId = ref<number | null>(props.selectedSemesterId);
const selectedTeachingLoadId = ref<number | null>(props.selectedTeachingLoadId);

const handleSemesterChange = () => {
    router.get(
        '/docente/evidencias',
        { semester_id: selectedSemesterId.value ?? undefined },
        { preserveState: false },
    );
};

const handleTeachingLoadChange = () => {
    router.get(
        '/docente/evidencias',
        {
            semester_id: selectedSemesterId.value ?? undefined,
            teaching_load_id: selectedTeachingLoadId.value ?? undefined,
        },
        { preserveScroll: true },
    );
};

watch(
    () => props.selectedSemesterId,
    (val) => {
        selectedSemesterId.value = val ?? null;
    },
);

watch(
    () => props.selectedTeachingLoadId,
    (val) => {
        selectedTeachingLoadId.value = val ?? null;
    },
);

watch(
    () => props.tasks,
    () => {
        if (selectedTask.value) {
            const updatedTask = props.tasks.find(
                (task) => taskKey(task) === taskKey(selectedTask.value!),
            );
            if (!updatedTask) {
                selectedTask.value = null;
            }
        } else {
            selectTaskFromQuery();
        }
    },
);

const selectedTask = ref<Task | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const showSubmitConfirm = ref(false);
const submitProcessing = ref(false);
const showDeleteConfirm = ref(false);
const deleteProcessing = ref(false);
const deletingFileId = ref<number | null>(null);

const uploadForm = useForm({
    file: null as File | null,
});

const initForm = useForm({
    teaching_load_id: null as number | null,
    evidence_item_id: null as number | null,
});

const taskKey = (task: Task) =>
    `${task.teaching_load.id}-${task.requirement.item_id}`;

const selectTaskFromQuery = () => {
    if (
        (!props.selectedSubmissionId && !props.selectedEvidenceItemId) ||
        selectedTask.value
    ) {
        return;
    }

    selectedTask.value =
        props.tasks.find(
            (task) =>
                props.selectedSubmissionId &&
                task.id === props.selectedSubmissionId,
        ) ||
        props.tasks.find(
            (task) =>
                props.selectedTeachingLoadId &&
                task.teaching_load.id === props.selectedTeachingLoadId &&
                task.requirement.item_id === props.selectedEvidenceItemId,
        ) ||
        props.tasks.find(
            (task) => task.requirement.item_id === props.selectedEvidenceItemId,
        ) ||
        null;
};

onMounted(selectTaskFromQuery);

const groupedTasks = computed(() => {
    const groups: Record<string, Task[]> = {};
    props.tasks.forEach((task) => {
        const key = `${task.teaching_load.subject_name} - Grupo ${task.teaching_load.group}`;
        if (!groups[key]) groups[key] = [];
        groups[key].push(task);
    });

    Object.values(groups).forEach((tasks) => {
        tasks.sort((left, right) => {
            if (
                left.requirement.stage_order !== right.requirement.stage_order
            ) {
                return (
                    left.requirement.stage_order - right.requirement.stage_order
                );
            }

            return left.requirement.item_name.localeCompare(
                right.requirement.item_name,
            );
        });
    });

    return groups;
});

const selectTask = (task: Task) => {
    selectedTask.value = task;
};

const getStatusConfig = (task: Task) => {
    switch (task.submission.ui_status) {
        case 'VF':
            return {
                class: 'bg-emerald-100 text-emerald-800',
                label: 'Liberado',
                icon: ShieldCheck,
            };
        case 'AO':
            return {
                class: 'bg-green-100 text-green-800',
                label: 'Aprobado Oficina',
                icon: CheckCircle2,
            };
        case 'PA':
            return {
                class: 'bg-amber-100 text-amber-800',
                label: 'Pendiente',
                icon: Send,
            };
        case 'R':
            return {
                class: 'bg-rose-100 text-rose-800',
                label: 'Rechazado',
                icon: AlertTriangle,
            };
        case 'BL':
            return {
                class: 'bg-blue-100 text-blue-800',
                label: 'Bloqueado',
                icon: Clock,
            };
        case 'NA':
            return {
                class: 'bg-slate-100 text-slate-700',
                label: 'No aplica',
                icon: AlertCircle,
            };
        default:
            return {
                class: 'bg-slate-100 text-slate-700',
                label: 'Sin evidencia',
                icon: Clock,
            };
    }
};

const availabilityClasses: Record<string, string> = {
    OPEN: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    LATE: 'bg-amber-50 text-amber-700 border-amber-200',
    UNLOCKED: 'bg-amber-50 text-amber-700 border-amber-200',
    UPCOMING: 'bg-blue-50 text-blue-700 border-blue-200',
    NOT_CONFIGURED: 'bg-rose-50 text-rose-700 border-rose-200',
    NA: 'bg-slate-50 text-slate-700 border-slate-200',
};

const shouldShowAvailabilityBadge = (task: Task | null) =>
    !!task?.availability?.code && task.availability.code !== 'NOT_CONFIGURED';

const footerAvailabilityLabel = (task: Task | null) =>
    shouldShowAvailabilityBadge(task) ? (task?.availability.label ?? '') : '';

const latestRejectionReason = (task: Task | null) => {
    if (!task || task.submission.ui_status !== 'R') return '';

    const rejectedReview = task.submission.review_trail.find(
        (review) => review.decision === 'REJECT' && review.comments,
    );

    return (
        rejectedReview?.comments ||
        task.submission.last_review?.comments ||
        (task.submission.latest_status_change?.to_status === 'REJECTED'
            ? task.submission.latest_status_change.reason
            : '') ||
        ''
    );
};

const triggerUpload = () => {
    fileInput.value?.click();
};

const handleFileSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0 && selectedTask.value) {
        if (!selectedTask.value.id) {
            target.value = '';
            return;
        }

        uploadForm.file = target.files[0];

        uploadForm.post(`/docente/evidencias/${selectedTask.value.id}/upload`, {
            preserveScroll: true,
            onSuccess: () => {
                target.value = '';
                uploadForm.reset();
                const updatedTask = props.tasks.find(
                    (task) => task.id === selectedTask.value?.id,
                );
                if (updatedTask) selectedTask.value = updatedTask;
            },
        });
    }
};

const submitEvidence = () => {
    if (!selectedTask.value?.id) return;

    showSubmitConfirm.value = true;
};

const confirmSubmitEvidence = () => {
    if (!selectedTask.value?.id || submitProcessing.value) return;

    submitProcessing.value = true;
    router.post(
        `/docente/evidencias/${selectedTask.value.id}/submit`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showSubmitConfirm.value = false;
                const updatedTask = props.tasks.find(
                    (task) => task.id === selectedTask.value?.id,
                );
                if (updatedTask) selectedTask.value = updatedTask;
            },
            onFinish: () => {
                submitProcessing.value = false;
            },
        },
    );
};

const initSubmission = () => {
    if (!selectedTask.value) return;

    initForm.teaching_load_id = selectedTask.value.teaching_load.id;
    initForm.evidence_item_id = selectedTask.value.requirement.item_id;

    const selectedKey = taskKey(selectedTask.value);

    initForm.post('/docente/evidencias/init', {
        preserveScroll: true,
        onSuccess: () => {
            const updatedTask = props.tasks.find(
                (task) => taskKey(task) === selectedKey,
            );
            if (updatedTask) {
                selectedTask.value = updatedTask;
            }
        },
    });
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Sin fecha';

    return new Date(dateString).toLocaleDateString('es-ES', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatBytes = (bytes: number) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const requestDeleteFile = (fileId: number) => {
    deletingFileId.value = fileId;
    showDeleteConfirm.value = true;
};

const confirmDeleteFile = () => {
    if (!deletingFileId.value || deleteProcessing.value) return;

    deleteProcessing.value = true;
    router.delete(`/files/${deletingFileId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteConfirm.value = false;
            deletingFileId.value = null;
            const updatedTask = props.tasks.find(
                (task) => task.id === selectedTask.value?.id,
            );
            if (updatedTask) selectedTask.value = updatedTask;
        },
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
};
</script>

<template>
    <Head title="Mis Evidencias" />

    <AppLayout
        :breadcrumbs="[{ title: 'Mi Espacio', href: '/docente/evidencias' }]"
    >
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <div class="mb-6">
                <h1 class="flex items-center text-2xl font-bold text-slate-900">
                    <FileStack class="mr-3 h-6 w-6 text-indigo-600" />
                    Mis Entregas y Evidencias
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Visualiza tus etapas activas, carga evidencia y monitorea
                    aprobación de oficina y liberación final.
                </p>
            </div>

            <div
                v-if="!semester"
                class="rounded-md border-l-4 border-amber-400 bg-amber-50 p-4"
            >
                <p class="text-sm text-amber-700">
                    El semestre no está activo.
                </p>
            </div>

            <div v-else>
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <div class="min-w-[180px] flex-1">
                        <label
                            for="semester-filter"
                            class="mb-1 block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                            >Semestre</label
                        >
                        <select
                            id="semester-filter"
                            v-model="selectedSemesterId"
                            @change="handleSemesterChange"
                            class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        >
                            <option
                                v-for="s in semesters"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ s.name }}
                            </option>
                        </select>
                    </div>
                    <div class="min-w-[220px] flex-1">
                        <label
                            for="load-filter"
                            class="mb-1 block text-xs font-semibold tracking-wider text-slate-500 uppercase"
                            >Materia</label
                        >
                        <select
                            id="load-filter"
                            v-model="selectedTeachingLoadId"
                            @change="handleTeachingLoadChange"
                            class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        >
                            <option :value="null">Todas las materias</option>
                            <option
                                v-for="tl in teachingLoads"
                                :key="tl.id"
                                :value="tl.id"
                            >
                                {{ tl.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col gap-4 lg:flex-row lg:gap-6">
                    <div
                        class="flex w-full flex-col rounded-xl border border-slate-200 bg-white shadow-sm lg:max-h-[calc(100vh-12rem)] lg:w-1/3 lg:overflow-hidden"
                    >
                        <div class="border-b border-slate-200 bg-slate-50 p-4">
                            <h2 class="font-semibold text-slate-800">
                                Materias Asignadas
                            </h2>
                        </div>
                        <div class="flex-1 overflow-y-auto p-2">
                            <div
                                v-if="Object.keys(groupedTasks).length === 0"
                                class="p-4 text-center text-sm text-slate-500"
                            >
                                No tienes cargas académicas asignadas.
                            </div>

                            <div
                                v-for="(
                                    groupTasks, subjectName
                                ) in groupedTasks"
                                :key="subjectName"
                                class="mb-4"
                            >
                                <h3
                                    class="mb-2 px-3 text-xs font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    {{ subjectName }}
                                </h3>
                                <ul class="space-y-1">
                                    <li
                                        v-for="task in groupTasks"
                                        :key="taskKey(task)"
                                    >
                                        <button
                                            type="button"
                                            @click="selectTask(task)"
                                            class="w-full rounded-lg border px-3 py-2 text-left text-sm transition-colors"
                                            :class="
                                                selectedTask &&
                                                taskKey(selectedTask) ===
                                                    taskKey(task)
                                                    ? 'border-blue-200 bg-blue-50 text-blue-700 ring-1 ring-blue-500 ring-inset'
                                                    : 'border-transparent text-slate-700 hover:bg-slate-100'
                                            "
                                        >
                                            <div
                                                class="flex items-center justify-between gap-2"
                                            >
                                                <span
                                                    class="truncate font-medium"
                                                    >{{
                                                        task.requirement
                                                            .item_name
                                                    }}</span
                                                >
                                                <span
                                                    class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600"
                                                >
                                                    {{
                                                        task.requirement
                                                            .stage_label
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="mt-1 flex items-center justify-between"
                                            >
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide uppercase"
                                                    :class="
                                                        getStatusConfig(task)
                                                            .class
                                                    "
                                                >
                                                    {{
                                                        getStatusConfig(task)
                                                            .label
                                                    }}
                                                </span>
                                                <span
                                                    class="flex items-center text-xs text-slate-500"
                                                >
                                                    <FileIcon
                                                        class="mr-1 h-3 w-3"
                                                    />
                                                    {{
                                                        task.submission
                                                            .files_count
                                                    }}
                                                </span>
                                            </div>
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div
                        class="relative flex w-full min-w-0 flex-col rounded-xl border border-slate-200 bg-white shadow-sm lg:max-h-[calc(100vh-12rem)] lg:w-2/3 lg:overflow-hidden"
                    >
                        <div
                            v-if="!selectedTask"
                            class="flex flex-1 flex-col items-center justify-center p-8 text-center text-slate-400"
                        >
                            <FileStack class="mb-4 h-16 w-16 text-slate-200" />
                            <p class="text-lg font-medium text-slate-600">
                                Selecciona un documento
                            </p>
                            <p class="mt-1 text-sm">
                                Haz clic en un entregable para ver su detalle y
                                sus reglas operativas.
                            </p>
                        </div>

                        <div v-else class="flex min-w-0 flex-col lg:h-full">
                            <div class="border-b border-slate-200 bg-white p-6">
                                <div
                                    class="mb-4 flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between"
                                >
                                    <div class="min-w-0">
                                        <div
                                            class="mb-2 flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-600"
                                            >
                                                {{
                                                    selectedTask.requirement
                                                        .stage_label
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    shouldShowAvailabilityBadge(
                                                        selectedTask,
                                                    )
                                                "
                                                class="rounded-full border px-2 py-1 text-xs font-semibold"
                                                :class="
                                                    availabilityClasses[
                                                        selectedTask
                                                            .availability.code
                                                    ] ||
                                                    'border-slate-200 bg-slate-50 text-slate-700'
                                                "
                                            >
                                                {{
                                                    selectedTask.availability
                                                        .label
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    selectedTask.submission
                                                        .submitted_late ||
                                                    selectedTask.availability
                                                        .is_late
                                                "
                                                class="rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700"
                                            >
                                                Extemporánea
                                            </span>
                                        </div>
                                        <h2
                                            class="text-xl font-bold text-slate-900"
                                        >
                                            {{
                                                selectedTask.requirement
                                                    .item_name
                                            }}
                                        </h2>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{
                                                selectedTask.teaching_load
                                                    .subject_name
                                            }}
                                            - Grupo
                                            {{
                                                selectedTask.teaching_load.group
                                            }}
                                        </p>
                                    </div>
                                    <span
                                        class="inline-flex max-w-full shrink-0 items-center self-start rounded-full px-3 py-1 text-xs font-bold tracking-wider uppercase"
                                        :class="
                                            getStatusConfig(selectedTask).class
                                        "
                                    >
                                        <component
                                            :is="
                                                getStatusConfig(selectedTask)
                                                    .icon
                                            "
                                            class="mr-1.5 h-4 w-4"
                                        />
                                        {{
                                            getStatusConfig(selectedTask).label
                                        }}
                                    </span>
                                </div>

                                <div
                                    v-if="
                                        selectedTask.submission.ui_status ===
                                        'R'
                                    "
                                    class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"
                                >
                                    <div class="font-semibold">
                                        Esta evidencia fue rechazada
                                    </div>
                                    <p
                                        v-if="
                                            latestRejectionReason(selectedTask)
                                        "
                                        class="mt-1"
                                    >
                                        {{
                                            latestRejectionReason(selectedTask)
                                        }}
                                    </p>
                                    <p v-else class="mt-1">
                                        Puedes subir una corrección aquí mismo
                                        mientras la evidencia esté disponible.
                                    </p>
                                </div>

                                <div
                                    v-if="selectedTask.window"
                                    class="rounded-lg border border-blue-100 bg-blue-50 p-3 text-sm text-blue-800"
                                >
                                    <div>
                                        <strong>Apertura:</strong>
                                        {{
                                            formatDate(
                                                selectedTask.window.opens_at,
                                            )
                                        }}
                                    </div>
                                    <div>
                                        <strong>Cierre:</strong>
                                        {{
                                            formatDate(
                                                selectedTask.window.closes_at,
                                            )
                                        }}
                                    </div>
                                    <div class="mt-1 font-semibold">
                                        {{ selectedTask.window.state_label }}
                                    </div>
                                </div>

                                <div
                                    v-if="
                                        selectedTask.submission
                                            .office_approved_at ||
                                        selectedTask.submission
                                            .final_approved_at
                                    "
                                    class="mt-4 grid gap-3 md:grid-cols-2"
                                >
                                    <div
                                        v-if="
                                            selectedTask.submission
                                                .office_approved_at
                                        "
                                        class="rounded-lg border border-green-100 bg-green-50 p-3 text-sm text-green-800"
                                    >
                                        <div
                                            class="text-xs font-semibold uppercase"
                                        >
                                            Aprobado por oficina
                                        </div>
                                        <div class="mt-1 font-medium">
                                            {{
                                                selectedTask.submission
                                                    .office_approved_by
                                            }}
                                        </div>
                                        <div class="text-xs">
                                            {{
                                                selectedTask.submission
                                                    .office_approved_at
                                            }}
                                        </div>
                                    </div>
                                    <div
                                        v-if="
                                            selectedTask.submission
                                                .final_approved_at
                                        "
                                        class="rounded-lg border border-emerald-100 bg-emerald-50 p-3 text-sm text-emerald-800"
                                    >
                                        <div
                                            class="text-xs font-semibold uppercase"
                                        >
                                            Visto bueno final
                                        </div>
                                        <div class="mt-1 font-medium">
                                            {{
                                                selectedTask.submission
                                                    .final_approved_by
                                            }}
                                        </div>
                                        <div class="text-xs">
                                            {{
                                                selectedTask.submission
                                                    .final_approved_at
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-50 p-6"
                            >
                                <div
                                    class="mb-4 flex flex-wrap items-center justify-between gap-3"
                                >
                                    <h3
                                        class="text-sm font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Archivos adjuntos
                                    </h3>

                                    <button
                                        v-if="selectedTask.can_upload"
                                        type="button"
                                        @click="triggerUpload"
                                        :disabled="uploadForm.processing"
                                        class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-50"
                                    >
                                        <UploadCloud class="mr-1.5 h-4 w-4" />
                                        Subir archivo
                                    </button>
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        class="hidden"
                                        :accept="uploadAccept"
                                        @change="handleFileSelected"
                                    />
                                </div>

                                <ul
                                    v-if="
                                        selectedTask.submission.files.length > 0
                                    "
                                    class="divide-y divide-slate-100 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm"
                                >
                                    <li
                                        v-for="file in selectedTask.submission
                                            .files"
                                        :key="file.id"
                                        class="flex min-w-[680px] items-center justify-between gap-4 p-4 transition hover:bg-slate-50"
                                    >
                                        <div class="flex min-w-0 items-center">
                                            <div
                                                class="mr-4 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600"
                                            >
                                                <FileIcon class="h-5 w-5" />
                                            </div>
                                            <div class="min-w-0">
                                                <p
                                                    class="truncate text-sm font-medium text-slate-900"
                                                >
                                                    {{ file.file_name }}
                                                </p>
                                                <p
                                                    class="mt-0.5 text-xs text-slate-500"
                                                >
                                                    {{ formatBytes(file.size) }}
                                                    - Subido el
                                                    {{
                                                        formatDate(
                                                            file.uploaded_at,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            class="flex shrink-0 items-center gap-2"
                                        >
                                            <a
                                                v-if="
                                                    file.is_docx &&
                                                    file.can_edit_docx
                                                "
                                                :href="file.editor_url"
                                                class="inline-flex items-center rounded-md border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                                            >
                                                <Pencil
                                                    class="mr-1 h-3.5 w-3.5"
                                                />
                                                Editar Word
                                            </a>
                                            <a
                                                :href="file.download_url"
                                                class="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            >
                                                Descargar
                                            </a>
                                            <button
                                                v-if="file.can_delete"
                                                type="button"
                                                @click="
                                                    requestDeleteFile(file.id)
                                                "
                                                class="inline-flex items-center rounded-md border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                                            >
                                                <Trash2
                                                    class="mr-1 h-3.5 w-3.5"
                                                />
                                                Eliminar
                                            </button>
                                        </div>
                                    </li>
                                </ul>

                                <div
                                    v-else
                                    class="rounded-xl border border-dashed border-slate-300 bg-white py-10 text-center"
                                >
                                    <UploadCloud
                                        class="mx-auto mb-2 h-10 w-10 text-slate-300"
                                    />
                                    <p class="text-sm text-slate-500">
                                        No hay archivos adjuntos.
                                    </p>
                                </div>

                                <div
                                    v-if="selectedTask.submission.last_review"
                                    class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                                >
                                    <h3
                                        class="mb-2 text-sm font-semibold text-slate-900"
                                    >
                                        Última revisión
                                    </h3>
                                    <div
                                        class="flex flex-wrap items-center gap-2 text-xs"
                                    >
                                        <span
                                            class="rounded-full bg-slate-100 px-2 py-1 font-semibold text-slate-700"
                                        >
                                            {{
                                                selectedTask.submission
                                                    .last_review.stage ===
                                                'FINAL'
                                                    ? 'FINAL'
                                                    : 'OFICINA'
                                            }}
                                        </span>
                                        <span
                                            class="font-semibold"
                                            :class="
                                                selectedTask.submission
                                                    .last_review.decision ===
                                                'APPROVE'
                                                    ? 'text-green-700'
                                                    : 'text-rose-700'
                                            "
                                        >
                                            {{
                                                selectedTask.submission
                                                    .last_review.decision ===
                                                'APPROVE'
                                                    ? 'Aprobado'
                                                    : 'Rechazado'
                                            }}
                                        </span>
                                        <span class="text-slate-500">{{
                                            selectedTask.submission.last_review
                                                .reviewed_at
                                        }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">
                                        {{
                                            selectedTask.submission.last_review
                                                .reviewer_name
                                        }}
                                    </div>
                                    <div
                                        v-if="
                                            selectedTask.submission.last_review
                                                .comments
                                        "
                                        class="mt-2 text-sm text-slate-700"
                                    >
                                        {{
                                            selectedTask.submission.last_review
                                                .comments
                                        }}
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex flex-wrap justify-end gap-3 border-t border-slate-200 bg-white p-4"
                            >
                                <button
                                    v-if="
                                        selectedTask.submission.status === null
                                    "
                                    type="button"
                                    @click="initSubmission"
                                    :disabled="
                                        !selectedTask.can_initialize ||
                                        initForm.processing
                                    "
                                    class="inline-flex items-center rounded-lg bg-slate-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Inicializar entrega
                                </button>

                                <button
                                    v-else-if="selectedTask.can_submit"
                                    type="button"
                                    @click="submitEvidence"
                                    class="inline-flex items-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                                >
                                    <Send class="mr-2 -ml-1 h-4 w-4" />
                                    Enviar evidencia a revisión
                                </button>

                                <div
                                    v-else-if="
                                        footerAvailabilityLabel(selectedTask)
                                    "
                                    class="text-sm text-slate-500"
                                >
                                    {{ footerAvailabilityLabel(selectedTask) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <ConfirmDialog
        :open="showSubmitConfirm"
        title="Enviar evidencia"
        description="¿Se enviará esta evidencia para revisión? Podrás ajustar archivos mientras permanezca pendiente y no haya sido revisada."
        confirm-label="Enviar"
        variant="default"
        @update:open="showSubmitConfirm = $event"
        @confirm="confirmSubmitEvidence"
    />

    <ConfirmDialog
        :open="showDeleteConfirm"
        title="Eliminar archivo"
        description="¿Estás seguro de que deseas eliminar este archivo? Esta acción no se puede deshacer."
        confirm-label="Eliminar"
        variant="destructive"
        @update:open="
            (val: boolean) => {
                showDeleteConfirm = val;
                if (!val) deletingFileId = null;
            }
        "
        @confirm="confirmDeleteFile"
    />
</template>
