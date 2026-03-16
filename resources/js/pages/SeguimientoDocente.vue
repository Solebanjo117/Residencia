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

const isAdmin = computed(() => ['JEFE_OFICINA', 'JEFE_DEPTO'].includes(props.userRole));

function statusPillClasses(status) {
    if (status === 'A') return 'bg-green-100 text-green-700 ring-green-300';
    if (status === 'PA') return 'bg-yellow-100 text-yellow-700 ring-yellow-300';
    if (status === 'R') return 'bg-orange-100 text-orange-700 ring-orange-300';
    if (status === 'NE') return 'bg-red-100 text-red-700 ring-red-300';
    return 'bg-gray-100 text-gray-500 ring-gray-300'; // NA
}

function statusLabel(status) {
    const labels = { A: 'A', PA: 'PA', R: 'R', NE: 'NE', NA: 'NA' };
    return labels[status] || status;
}

function statusTooltip(status) {
    const tips = { A: 'Aprobado', PA: 'Pendiente de aprobación', R: 'Rechazado', NE: 'No evidencia', NA: 'No aplica' };
    return tips[status] || '';
}

// --- UI state ---
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
    return props.rows.filter((r) => {
        if (!q) return true;
        return (
            r.maestro.toLowerCase().includes(q) ||
            r.materia.toLowerCase().includes(q) ||
            r.clave_tecnm.toLowerCase().includes(q)
        );
    });
});

// --- Cell detail modal ---
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
    reviewComments.value = '';
}

function closeCellDetail() {
    cellModalOpen.value = false;
    cellModalData.value = null;
}

// --- Review (approve/reject) ---
const reviewComments = ref('');
const reviewLoading = ref(false);

function submitReview(decision) {
    const submissionId = cellModalData.value?.submission_id;
    if (!submissionId) return;

    if (decision === 'REJECT' && !reviewComments.value.trim()) {
        alert('Debes escribir un motivo para rechazar la evidencia.');
        return;
    }

    reviewLoading.value = true;

    const form = useForm({
        decision: decision,
        comments: reviewComments.value.trim() || null,
    });

    form.post(`/asesorias/${submissionId}/review`, {
        preserveScroll: true,
        onSuccess: () => {
            closeCellDetail();
        },
        onFinish: () => {
            reviewLoading.value = false;
        },
    });
}

// --- Export ---
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
        'MAESTRO', 'MATERIA', 'CARRERA', 'CLAVE_TECNM',
        ...props.columns.map(c => c.label.toUpperCase()),
        'ESTADO_FINAL',
    ];
    const lines = [
        headers.join(','),
        ...filteredRows.value.map((r) => {
            const values = [
                r.maestro, r.materia, r.carrera, r.clave_tecnm,
                ...props.columns.map(c => r.cells[c.key]?.status || 'NE'),
                r.estado_final,
            ].map((v) => `"${String(v).replaceAll('"', '""')}"`);
            return values.join(',');
        }),
    ];
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    downloadBlob(blob, `seguimiento_${semester.value || 'todos'}.csv`);
}

function exportXLSX() {
    const data = filteredRows.value.map((r) => {
        let row = { MAESTRO: r.maestro, MATERIA: r.materia, CARRERA: r.carrera, CLAVE_TECNM: r.clave_tecnm };
        props.columns.forEach(c => { row[c.label.toUpperCase()] = r.cells[c.key]?.status || 'NE'; });
        row.ESTADO_FINAL = r.estado_final;
        return row;
    });
    const ws = XLSX.utils.json_to_sheet(data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Seguimiento');
    const out = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
    const blob = new Blob([out], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    downloadBlob(blob, `seguimiento_${semester.value || 'todos'}.xlsx`);
}

function printPage() {
    exportMenuOpen.value = false;
    const tableEl = printTableRef.value;
    if (!tableEl) return;
    const clone = tableEl.cloneNode(true);
    clone.querySelectorAll('button').forEach((btn) => {
        const span = document.createElement('span');
        span.textContent = btn.textContent?.trim() || '';
        span.className = 'chip';
        btn.replaceWith(span);
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
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Seguimiento Docente', href: '/asesorias' }]">
        <div class="min-h-screen bg-gray-50">
            <div class="mx-auto max-w-full px-4 py-6 sm:px-6 lg:px-8">

                <!-- Header -->
                <div class="mb-4">
                    <h1 class="text-2xl font-semibold text-gray-900">Control de Seguimiento Docente</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        <span class="inline-flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-green-400"></span><b>A</b> = Aprobado</span>
                        <span class="ml-3 inline-flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-yellow-400"></span><b>PA</b> = Pendiente</span>
                        <span class="ml-3 inline-flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-orange-400"></span><b>R</b> = Rechazado</span>
                        <span class="ml-3 inline-flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-red-400"></span><b>NE</b> = No evidencia</span>
                        <span class="ml-3 inline-flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-gray-400"></span><b>NA</b> = No aplica</span>
                        <span v-if="isAdmin" class="ml-4 text-xs text-blue-600">Haz clic en una celda para revisar evidencia.</span>
                    </p>
                </div>

                <!-- Toolbar -->
                <div class="mb-4 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:flex-row md:items-center md:justify-between toolbar">
                    <div class="flex flex-1 items-center gap-3">
                        <div class="relative w-full max-w-2xl">
                            <input v-model="search" type="text"
                                class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2 pl-10 text-sm outline-none ring-0 focus:border-gray-300"
                                placeholder="Buscar por maestro, materia o clave..." />
                            <div class="pointer-events-none absolute left-3 top-2.5 text-gray-400">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M21 21l-4.3-4.3m1.3-5.2a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="hidden text-sm text-gray-500 md:inline">Semestre</span>
                            <select v-model="semester" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:border-gray-300">
                                <option v-for="s in semesters" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="relative flex items-center gap-2">
                        <button type="button" @click="exportMenuOpen = !exportMenuOpen"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 3v10m0 0l4-4m-4 4l-4-4M5 21h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Exportar
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="opacity-70"><path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <div v-if="exportMenuOpen" class="absolute right-0 top-11 z-20 w-44 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg" role="menu">
                            <button type="button" class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" @click="exportCSV(); exportMenuOpen = false;">CSV</button>
                            <button type="button" class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" @click="exportXLSX(); exportMenuOpen = false;">XLSX</button>
                        </div>
                        <button type="button" @click="printPage"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 9V4h12v5M6 18h12v2H6v-2zm0 0H5a3 3 0 01-3-3v-3a3 3 0 013-3h14a3 3 0 013 3v3a3 3 0 01-3 3h-1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Imprimir
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table ref="printTableRef" class="min-w-[900px] w-full table-auto">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-600">
                                    <th class="px-3 py-3 bg-gray-50 min-w-[160px] sticky-col-1">Maestro</th>
                                    <th class="px-3 py-3 bg-gray-50 min-w-[150px] sticky-col-2">Materia</th>
                                    <th class="px-3 py-3">Carrera</th>
                                    <th class="px-3 py-3">Clave TECNM</th>
                                    <th v-for="col in columns" :key="col.key" class="px-2 py-3 text-center min-w-[70px] max-w-[90px]">
                                        <span class="block leading-tight">{{ col.label }}</span>
                                    </th>
                                    <th class="px-3 py-3 text-center">Status Final</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in filteredRows" :key="row.id" class="hover:bg-gray-50/60">
                                    <td class="px-3 py-2 text-sm font-semibold text-gray-900 bg-white sticky-col-1">{{ row.maestro }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-800 bg-white sticky-col-2">{{ row.materia }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-700">{{ row.carrera }}</td>
                                    <td class="px-3 py-2 text-sm font-mono text-gray-700">{{ row.clave_tecnm }}</td>

                                    <!-- Dynamic evidence cells -->
                                    <td v-for="col in columns" :key="col.key" class="px-2 py-2 text-center">
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-full px-2.5 py-0.5 text-[11px] font-bold ring-1 transition-colors"
                                            :class="[
                                                statusPillClasses(row.cells[col.key]?.status || 'NE'),
                                                (isAdmin && row.cells[col.key]?.status !== 'NA') ? 'cursor-pointer hover:opacity-80' : 'cursor-default'
                                            ]"
                                            :title="statusTooltip(row.cells[col.key]?.status || 'NE')"
                                            @click="isAdmin && row.cells[col.key]?.status !== 'NA' ? openCellDetail(row, col) : null"
                                        >
                                            {{ statusLabel(row.cells[col.key]?.status || 'NE') }}
                                        </button>
                                    </td>

                                    <td class="px-3 py-2 text-center">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold ring-1"
                                            :class="statusPillClasses(row.estado_final)">
                                            {{ statusLabel(row.estado_final) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="filteredRows.length === 0">
                                    <td :colspan="4 + columns.length + 1" class="px-4 py-10 text-center text-sm text-gray-500">
                                        No hay resultados con esos filtros.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Summary -->
                <div class="mt-3 flex items-center gap-4 text-xs text-gray-500">
                    <span>{{ filteredRows.length }} registros</span>
                    <span class="flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-green-400"></span>A: {{ filteredRows.filter(r => r.estado_final === 'A').length }}</span>
                    <span class="flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-yellow-400"></span>PA: {{ filteredRows.filter(r => r.estado_final === 'PA').length }}</span>
                    <span class="flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-red-400"></span>NE: {{ filteredRows.filter(r => r.estado_final === 'NE').length }}</span>
                </div>
            </div>
        </div>

        <!-- Cell Detail / Review Modal -->
        <div v-if="cellModalOpen && cellModalData" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeCellDetail">
            <div class="w-full max-w-lg rounded-xl bg-white shadow-xl">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">{{ cellModalCol?.label }}</h2>
                        <p class="text-sm text-gray-600">{{ cellModalRow?.maestro }} — {{ cellModalRow?.materia }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold ring-1"
                            :class="statusPillClasses(cellModalData.status)">
                            {{ statusLabel(cellModalData.status) }}
                        </span>
                        <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100" @click="closeCellDetail">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>

                <div class="px-5 py-4 space-y-4">
                    <!-- Files list -->
                    <div>
                        <h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">Archivos subidos</h3>
                        <div v-if="cellModalData.files.length === 0" class="text-sm text-gray-400 italic">
                            No se han subido archivos para esta evidencia.
                        </div>
                        <ul v-else class="space-y-2">
                            <li v-for="f in cellModalData.files" :key="f.id"
                                class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-gray-900">{{ f.file_name }}</div>
                                    <div class="text-xs text-gray-500">{{ formatBytes(f.size) }} — {{ f.uploaded_at }}</div>
                                </div>
                                <a :href="`/files/${f.id}/download`"
                                    class="ml-3 rounded-lg border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                    Descargar
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Last review info -->
                    <div v-if="cellModalData.last_review" class="rounded-lg border border-gray-100 p-3 bg-gray-50">
                        <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Última revisión</div>
                        <div class="text-sm">
                            <span :class="cellModalData.last_review.decision === 'APPROVE' ? 'text-green-700' : 'text-red-700'" class="font-semibold">
                                {{ cellModalData.last_review.decision === 'APPROVE' ? 'Aprobado' : 'Rechazado' }}
                            </span>
                            <span class="text-gray-500 ml-2 text-xs">{{ cellModalData.last_review.reviewed_at }}</span>
                        </div>
                        <div v-if="cellModalData.last_review.comments" class="mt-1 text-sm text-gray-700">
                            "{{ cellModalData.last_review.comments }}"
                        </div>
                    </div>

                    <!-- Review actions (admin only, when submission exists and has files) -->
                    <div v-if="isAdmin && cellModalData.submission_id && cellModalData.files.length > 0 && !['A', 'NA'].includes(cellModalData.status)"
                        class="border-t border-gray-100 pt-4">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">Revisión</h3>
                        <textarea
                            v-model="reviewComments"
                            rows="2"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-gray-300"
                            placeholder="Comentarios (obligatorio para rechazar)..."
                        ></textarea>
                        <div class="mt-3 flex items-center gap-2">
                            <button type="button"
                                class="flex-1 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                                :disabled="reviewLoading"
                                @click="submitReview('APPROVE')">
                                Aprobar
                            </button>
                            <button type="button"
                                class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                                :disabled="reviewLoading"
                                @click="submitReview('REJECT')">
                                Rechazar
                            </button>
                        </div>
                    </div>

                    <!-- Info for NE status -->
                    <div v-if="cellModalData.status === 'NE' && cellModalData.files.length === 0"
                        class="rounded-lg border border-red-100 bg-red-50 p-3 text-sm text-red-700">
                        El docente aún no ha subido evidencia para este rubro. Los archivos se suben desde el <b>File Manager</b>.
                    </div>
                </div>

                <div class="flex justify-end border-t border-gray-100 px-5 py-4">
                    <button type="button" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="closeCellDetail">
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
