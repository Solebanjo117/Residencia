<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import * as XLSX from 'xlsx';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    semesters: { type: Array, default: () => [] },
    columns: { type: Array, default: () => [] },
    currentSemester: { type: String, default: '' },
    userRole: { type: String, default: '' },
});

const canOfficeReview = computed(() => props.userRole === 'JEFE_OFICINA');
const canFinalApprove = computed(() => props.userRole === 'JEFE_DEPTO');
const canManageApplicability = computed(() => ['JEFE_OFICINA', 'JEFE_DEPTO'].includes(props.userRole));

function statusPillClasses(status) {
    if (status === 'VF') return 'bg-emerald-100 text-emerald-800 ring-emerald-300';
    if (status === 'AO') return 'bg-green-100 text-green-800 ring-green-300';
    if (status === 'PA') return 'bg-amber-100 text-amber-800 ring-amber-300';
    if (status === 'R') return 'bg-rose-100 text-rose-800 ring-rose-300';
    if (status === 'BL') return 'bg-blue-100 text-blue-800 ring-blue-300';
    if (status === 'NE') return 'bg-red-100 text-red-700 ring-red-300';
    return 'bg-slate-100 text-slate-700 ring-slate-300';
}

function statusLabel(status) {
    const labels = {
        VF: 'VF',
        AO: 'AO',
        PA: 'PA',
        R: 'R',
        BL: 'BL',
        NE: 'NE',
        NA: 'NA',
    };

    return labels[status] || status;
}

function statusTooltip(cell) {
    if (!cell) return '';

    const base = {
        VF: 'Visto bueno final del jefe de departamento',
        AO: 'Aprobado por oficina, pendiente de liberacion final',
        PA: 'Pendiente de envio o revision',
        R: 'Rechazado',
        BL: cell.availability?.label || 'Bloqueado',
        NE: 'Sin evidencia cargada',
        NA: 'No aplica',
    };

    return base[cell.status] || '';
}

function availabilityToneClasses(code) {
    if (code === 'OPEN') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    if (code === 'LATE' || code === 'UNLOCKED') return 'bg-amber-50 text-amber-700 border-amber-200';
    if (code === 'UPCOMING' || code === 'STAGE_LOCKED' || code === 'HISTORICAL') return 'bg-blue-50 text-blue-700 border-blue-200';
    if (code === 'NOT_CONFIGURED') return 'bg-rose-50 text-rose-700 border-rose-200';
    return 'bg-slate-50 text-slate-700 border-slate-200';
}

function shouldShowAvailabilityBadge(availability) {
    return availability?.code && availability.code !== 'NOT_CONFIGURED';
}

function exportStatus(cell) {
    if (!cell) return 'NE';

    let value = statusLabel(cell.status);
    if (cell.is_late && !['NA', 'BL', 'NE'].includes(cell.status)) {
        value += ' (EXT)';
    }

    return value;
}

const printTableRef = ref(null);
const search = ref('');
const exportMenuOpen = ref(false);
const semester = ref(props.currentSemester);

watch(semester, (newVal) => {
    if (newVal !== props.currentSemester) {
        router.get('/asesorias', { semester: newVal }, { preserveState: true });
    }
});

const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.rows.filter((row) => {
        if (!q) return true;

        return (
            row.maestro.toLowerCase().includes(q) ||
            row.materia.toLowerCase().includes(q) ||
            row.clave_tecnm.toLowerCase().includes(q)
        );
    });
});

const cellModalOpen = ref(false);
const cellModalData = ref(null);
const cellModalRow = ref(null);
const cellModalCol = ref(null);

function openCellDetail(row, col) {
    const cell = row.cells[col.key];
    if (!cell) return;

    cellModalData.value = cell;
    cellModalRow.value = row;
    cellModalCol.value = col;
    cellModalOpen.value = true;
    reviewForm.reset();
    finalApprovalForm.reset();
    cellStatusForm.reset();
}

function closeCellDetail() {
    cellModalOpen.value = false;
    cellModalData.value = null;
    cellModalRow.value = null;
    cellModalCol.value = null;
}

const reviewForm = useForm({
    decision: 'APPROVE',
    comments: '',
});

const finalApprovalForm = useForm({
    comments: '',
});

const cellStatusForm = useForm({
    teaching_load_id: null,
    evidence_item_id: null,
    status: 'NA',
    comments: '',
});

function submitReview(decision) {
    const submissionId = cellModalData.value?.submission_id;
    if (!submissionId) return;

    if (decision === 'REJECT' && !reviewForm.comments.trim()) {
        alert('Debes escribir un motivo para rechazar la evidencia.');
        return;
    }

    reviewForm.decision = decision;
    reviewForm.post(`/asesorias/${submissionId}/review`, {
        preserveScroll: true,
        onSuccess: closeCellDetail,
    });
}

function submitFinalApproval() {
    const submissionId = cellModalData.value?.submission_id;
    if (!submissionId) return;

    finalApprovalForm.post(`/asesorias/${submissionId}/final-approval`, {
        preserveScroll: true,
        onSuccess: closeCellDetail,
    });
}

function updateCellStatus(status) {
    if (!cellModalData.value) return;

    cellStatusForm.teaching_load_id = cellModalData.value.teaching_load_id;
    cellStatusForm.evidence_item_id = cellModalData.value.evidence_item_id;
    cellStatusForm.status = status;

    cellStatusForm.post('/asesorias/cells/status', {
        preserveScroll: true,
        onSuccess: closeCellDetail,
    });
}

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

function exportCSV() {
    const headers = [
        'MAESTRO',
        'MATERIA',
        'CARRERA',
        'CLAVE_TECNM',
        ...props.columns.map((column) => column.label.toUpperCase()),
        'ESTADO_FINAL',
    ];

    const lines = [
        headers.join(','),
        ...filteredRows.value.map((row) => {
            const values = [
                row.maestro,
                row.materia,
                row.carrera,
                row.clave_tecnm,
                ...props.columns.map((column) => exportStatus(row.cells[column.key])),
                statusLabel(row.estado_final),
            ].map((value) => `"${String(value).replaceAll('"', '""')}"`);

            return values.join(',');
        }),
    ];

    downloadBlob(new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' }), `seguimiento_${semester.value || 'todos'}.csv`);
}

function exportXLSX() {
    const data = filteredRows.value.map((row) => {
        const result = {
            MAESTRO: row.maestro,
            MATERIA: row.materia,
            CARRERA: row.carrera,
            CLAVE_TECNM: row.clave_tecnm,
        };

        props.columns.forEach((column) => {
            result[column.label.toUpperCase()] = exportStatus(row.cells[column.key]);
        });

        result.ESTADO_FINAL = statusLabel(row.estado_final);

        return result;
    });

    const ws = XLSX.utils.json_to_sheet(data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Seguimiento');
    const out = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });

    downloadBlob(
        new Blob([out], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }),
        `seguimiento_${semester.value || 'todos'}.xlsx`,
    );
}

function printPage() {
    exportMenuOpen.value = false;
    const tableEl = printTableRef.value;
    if (!tableEl) return;

    const clone = tableEl.cloneNode(true);
    clone.querySelectorAll('button').forEach((button) => {
        const span = document.createElement('span');
        span.textContent = button.textContent?.trim() || '';
        span.className = 'chip';
        button.replaceWith(span);
    });

    const w = window.open('', '_blank', 'width=1200,height=800');
    if (!w) return;

    w.document.open();
    w.document.write(`<!doctype html>
<html lang="es"><head><meta charset="utf-8"/><title>Control de Seguimiento Docente</title>
<style>
@page{size:A4 landscape;margin:10mm}html,body{font-family:Arial,sans-serif;color:#111}
h1{font-size:16px;margin:0 0 4px}p{font-size:11px;margin:0 0 10px;color:#444}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #e5e7eb;padding:4px 6px;font-size:9px;vertical-align:top}
th{background:#f3f4f6;text-transform:uppercase;letter-spacing:.03em}
.chip{display:inline-block;padding:2px 6px;border:1px solid #d1d5db;border-radius:999px;font-size:9px}
tr,td,th{page-break-inside:avoid}
</style></head><body>
<h1>Control de Seguimiento Docente</h1><p>Semestre: ${semester.value}</p>
${clone.outerHTML}</body></html>`);
    w.document.close();
    w.onload = () => { w.focus(); w.print(); w.close(); };
}

function formatBytes(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Seguimiento Docente', href: '/asesorias' }]">
        <div class="min-h-screen bg-slate-50">
            <div class="mx-auto max-w-full px-4 py-6 sm:px-6 lg:px-8">
                <div class="mb-4">
                    <h1 class="text-2xl font-semibold text-slate-900">Control de Seguimiento Docente</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        <span class="inline-flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-green-500"></span><b>AO</b> = Aprobado por oficina</span>
                        <span class="ml-3 inline-flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span><b>VF</b> = Visto bueno final</span>
                        <span class="ml-3 inline-flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-amber-400"></span><b>PA</b> = Pendiente</span>
                        <span class="ml-3 inline-flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-blue-400"></span><b>BL</b> = Bloqueado</span>
                        <span class="ml-3 inline-flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-rose-400"></span><b>R</b> = Rechazado</span>
                        <span class="ml-3 inline-flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-red-400"></span><b>NE</b> = No evidencia</span>
                        <span class="ml-3 inline-flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-full bg-slate-400"></span><b>NA</b> = No aplica</span>
                    </p>
                </div>

                <div class="toolbar mb-4 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-1 items-center gap-3">
                        <div class="relative w-full max-w-2xl">
                            <input
                                v-model="search"
                                type="text"
                                class="w-full rounded-lg border border-slate-200 bg-white px-4 py-2 pl-10 text-sm outline-none focus:border-slate-300"
                                placeholder="Buscar por maestro, materia o clave..."
                            />
                            <div class="pointer-events-none absolute left-3 top-2.5 text-slate-400">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M21 21l-4.3-4.3m1.3-5.2a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="hidden text-sm text-slate-500 md:inline">Semestre</span>
                            <select v-model="semester" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-slate-300">
                                <option v-for="s in semesters" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="relative flex items-center gap-2">
                        <button
                            type="button"
                            @click="exportMenuOpen = !exportMenuOpen"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Exportar
                        </button>
                        <div v-if="exportMenuOpen" class="absolute right-0 top-11 z-20 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                            <button type="button" class="w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50" @click="exportCSV(); exportMenuOpen = false;">CSV</button>
                            <button type="button" class="w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50" @click="exportXLSX(); exportMenuOpen = false;">XLSX</button>
                        </div>
                        <button
                            type="button"
                            @click="printPage"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Imprimir
                        </button>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table ref="printTableRef" class="min-w-[900px] w-full table-auto">
                            <thead class="bg-slate-50">
                                <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                                    <th class="sticky-col-1 min-w-[160px] bg-slate-50 px-3 py-3">Maestro</th>
                                    <th class="sticky-col-2 min-w-[150px] bg-slate-50 px-3 py-3">Materia</th>
                                    <th class="px-3 py-3">Carrera</th>
                                    <th class="px-3 py-3">Clave TECNM</th>
                                    <th v-for="col in columns" :key="col.key" class="min-w-[88px] max-w-[96px] px-2 py-3 text-center">
                                        <span class="block leading-tight">{{ col.label }}</span>
                                        <span class="mt-1 block text-[10px] normal-case text-slate-400">{{ col.stage_label }}</span>
                                    </th>
                                    <th class="px-3 py-3 text-center">Status Final</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="row in filteredRows" :key="row.id" class="hover:bg-slate-50/60">
                                    <td class="sticky-col-1 bg-white px-3 py-2 text-sm font-semibold text-slate-900">{{ row.maestro }}</td>
                                    <td class="sticky-col-2 bg-white px-3 py-2 text-sm text-slate-800">{{ row.materia }}</td>
                                    <td class="px-3 py-2 text-sm text-slate-700">{{ row.carrera }}</td>
                                    <td class="px-3 py-2 text-sm font-mono text-slate-700">{{ row.clave_tecnm }}</td>

                                    <td v-for="col in columns" :key="col.key" class="px-2 py-2 text-center">
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold ring-1 transition-colors hover:opacity-85"
                                            :class="statusPillClasses(row.cells[col.key]?.status || 'NE')"
                                            :title="statusTooltip(row.cells[col.key])"
                                            @click="openCellDetail(row, col)"
                                        >
                                            <span>{{ statusLabel(row.cells[col.key]?.status || 'NE') }}</span>
                                            <span v-if="row.cells[col.key]?.is_late" class="text-[9px]">EXT</span>
                                        </button>
                                    </td>

                                    <td class="px-3 py-2 text-center">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold ring-1" :class="statusPillClasses(row.estado_final)">
                                            {{ statusLabel(row.estado_final) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="filteredRows.length === 0">
                                    <td :colspan="4 + columns.length + 1" class="px-4 py-10 text-center text-sm text-slate-500">
                                        No hay resultados con esos filtros.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-slate-500">
                    <span>{{ filteredRows.length }} registros</span>
                    <span>VF: {{ filteredRows.filter((row) => row.estado_final === 'VF').length }}</span>
                    <span>AO: {{ filteredRows.filter((row) => row.estado_final === 'AO').length }}</span>
                    <span>PA: {{ filteredRows.filter((row) => row.estado_final === 'PA').length }}</span>
                    <span>BL: {{ filteredRows.filter((row) => row.estado_final === 'BL').length }}</span>
                    <span>NE: {{ filteredRows.filter((row) => row.estado_final === 'NE').length }}</span>
                </div>
            </div>
        </div>

        <div v-if="cellModalOpen && cellModalData" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeCellDetail">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">{{ cellModalCol?.label }}</h2>
                        <p class="text-sm text-slate-600">{{ cellModalRow?.maestro }} - {{ cellModalRow?.materia }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold ring-1" :class="statusPillClasses(cellModalData.status)">
                            {{ statusLabel(cellModalData.status) }}
                        </span>
                        <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click="closeCellDetail">X</button>
                    </div>
                </div>

                <div class="space-y-4 px-5 py-4">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded-full border px-2 py-1 font-semibold text-slate-700">{{ cellModalData.stage_label }}</span>
                        <span
                            v-if="shouldShowAvailabilityBadge(cellModalData.availability)"
                            class="rounded-full border px-2 py-1 font-semibold"
                            :class="availabilityToneClasses(cellModalData.availability?.code)"
                        >
                            {{ cellModalData.availability?.label }}
                        </span>
                        <span v-if="cellModalData.is_late" class="rounded-full border border-amber-200 bg-amber-50 px-2 py-1 font-semibold text-amber-700">
                            Entrega extemporanea
                        </span>
                    </div>

                    <div v-if="cellModalData.office_approved_at || cellModalData.final_approved_at" class="grid gap-3 md:grid-cols-2">
                        <div v-if="cellModalData.office_approved_at" class="rounded-lg border border-green-100 bg-green-50 p-3 text-sm text-green-800">
                            <div class="text-xs font-semibold uppercase">Aprobacion de oficina</div>
                            <div class="mt-1 font-medium">{{ cellModalData.office_approved_by || 'Sin nombre' }}</div>
                            <div class="text-xs">{{ cellModalData.office_approved_at }}</div>
                        </div>
                        <div v-if="cellModalData.final_approved_at" class="rounded-lg border border-emerald-100 bg-emerald-50 p-3 text-sm text-emerald-800">
                            <div class="text-xs font-semibold uppercase">Visto bueno final</div>
                            <div class="mt-1 font-medium">{{ cellModalData.final_approved_by || 'Sin nombre' }}</div>
                            <div class="text-xs">{{ cellModalData.final_approved_at }}</div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase text-slate-500">Archivos subidos</h3>
                        <div v-if="cellModalData.files.length === 0" class="text-sm italic text-slate-400">
                            No se han subido archivos para esta evidencia.
                        </div>
                        <ul v-else class="space-y-2">
                            <li v-for="file in cellModalData.files" :key="file.id" class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-slate-900">{{ file.file_name }}</div>
                                    <div class="text-xs text-slate-500">{{ formatBytes(file.size) }} - {{ file.uploaded_at }}</div>
                                </div>
                                <a :href="`/files/${file.id}/download`" class="ml-3 rounded-lg border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    Descargar
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div v-if="cellModalData.review_trail?.length" class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <div class="mb-2 text-xs font-semibold uppercase text-slate-500">Historial de revision</div>
                        <ul class="space-y-2">
                            <li v-for="review in cellModalData.review_trail" :key="`${review.stage}-${review.reviewed_at}`" class="rounded-lg border border-white bg-white px-3 py-2 text-sm">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">{{ review.stage === 'FINAL' ? 'FINAL' : 'OFICINA' }}</span>
                                    <span class="font-semibold" :class="review.decision === 'APPROVE' ? 'text-green-700' : 'text-rose-700'">
                                        {{ review.decision === 'APPROVE' ? 'Aprobado' : 'Rechazado' }}
                                    </span>
                                    <span class="text-xs text-slate-500">{{ review.reviewed_at }}</span>
                                </div>
                                <div class="mt-1 text-xs text-slate-500">{{ review.reviewer_name }}</div>
                                <div v-if="review.comments" class="mt-1 text-sm text-slate-700">{{ review.comments }}</div>
                            </li>
                        </ul>
                    </div>

                    <div v-if="canManageApplicability && cellModalData.can_mark_na && cellModalData.status !== 'NA' && !['AO', 'VF'].includes(cellModalData.status)" class="rounded-lg border border-slate-100 p-3">
                        <h3 class="mb-2 text-xs font-semibold uppercase text-slate-500">Aplicabilidad</h3>
                        <textarea
                            v-model="cellStatusForm.comments"
                            rows="2"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-slate-300"
                            placeholder="Motivo opcional para marcar no aplica..."
                        ></textarea>
                        <div class="mt-3 flex items-center gap-2">
                            <button type="button" class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" :disabled="cellStatusForm.processing" @click="updateCellStatus('NA')">
                                Marcar NA
                            </button>
                            <button v-if="cellModalData.can_reactivate" type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="cellStatusForm.processing" @click="updateCellStatus('DRAFT')">
                                Reactivar
                            </button>
                        </div>
                    </div>

                    <div v-if="canOfficeReview && cellModalData.can_office_review" class="rounded-lg border border-slate-100 p-3">
                        <h3 class="mb-2 text-xs font-semibold uppercase text-slate-500">Revision de oficina</h3>
                        <textarea
                            v-model="reviewForm.comments"
                            rows="2"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-slate-300"
                            placeholder="Comentarios (obligatorio para rechazar)..."
                        ></textarea>
                        <div class="mt-3 flex items-center gap-2">
                            <button type="button" class="flex-1 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50" :disabled="reviewForm.processing" @click="submitReview('APPROVE')">
                                Aprobar oficina
                            </button>
                            <button type="button" class="flex-1 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50" :disabled="reviewForm.processing" @click="submitReview('REJECT')">
                                Rechazar
                            </button>
                        </div>
                    </div>

                    <div v-if="canFinalApprove && cellModalData.can_final_approve" class="rounded-lg border border-slate-100 p-3">
                        <h3 class="mb-2 text-xs font-semibold uppercase text-slate-500">Visto bueno final</h3>
                        <textarea
                            v-model="finalApprovalForm.comments"
                            rows="2"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-slate-300"
                            placeholder="Comentario opcional del jefe de departamento..."
                        ></textarea>
                        <div class="mt-3 flex items-center gap-2">
                            <button type="button" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50" :disabled="finalApprovalForm.processing" @click="submitFinalApproval">
                                Liberar evidencia
                            </button>
                        </div>
                    </div>

                    <div v-if="cellModalData.status === 'NE' && cellModalData.files.length === 0" class="rounded-lg border border-red-100 bg-red-50 p-3 text-sm text-red-700">
                        El docente aun no ha subido evidencia para este rubro.
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-100 px-5 py-4">
                    <button type="button" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeCellDetail">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print { .toolbar { display: none !important; } }
.sticky-col-1 {
    position: sticky !important;
    left: 0;
    z-index: 10;
}
.sticky-col-2 {
    position: sticky !important;
    left: 160px;
    z-index: 10;
}
.sticky-col-2::after {
    content: '';
    position: absolute;
    right: -4px;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(to right, rgba(0,0,0,0.06), transparent);
    pointer-events: none;
}
</style>
