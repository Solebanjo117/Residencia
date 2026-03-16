<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    semesters: { type: Array, default: () => [] },
    currentSemester: { type: String, default: '' },
});

const search = ref('');
const semester = ref(props.currentSemester);
const printTableRef = ref(null);

watch(semester, (newVal) => {
    if (newVal !== props.currentSemester) {
        router.get('/asesorias-horarios', { semester: newVal }, { preserveState: true });
    }
});

const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.rows.filter((r) => {
        if (!q) return true;
        return (
            r.docente.toLowerCase().includes(q) ||
            r.materia.toLowerCase().includes(q) ||
            r.carrera.toLowerCase().includes(q) ||
            (r.aula && r.aula.toLowerCase().includes(q))
        );
    });
});

const dayCols = [
    { key: 'L', label: 'LUNES' },
    { key: 'M', label: 'MARTES' },
    { key: 'Mi', label: 'MIÉRCOLES' },
    { key: 'J', label: 'JUEVES' },
    { key: 'V', label: 'VIERNES' },
];

function printSchedule() {
    const tableEl = printTableRef.value;
    if (!tableEl) return;
    const clone = tableEl.cloneNode(true);
    const w = window.open('', '_blank', 'width=1000,height=700');
    if (!w) return;
    w.document.open();
    w.document.write(`<!doctype html>
<html lang="es"><head><meta charset="utf-8"/><title>Horario de Asesorías</title>
<style>
@page{size:A4 landscape;margin:12mm}html,body{font-family:Arial,sans-serif;color:#111}
h1{font-size:18px;margin:0 0 4px;text-align:center}p{font-size:12px;margin:0 0 12px;color:#444;text-align:center}
table{width:100%;border-collapse:collapse;margin-top:8px}th,td{border:1px solid #d1d5db;padding:6px 10px;font-size:11px;vertical-align:middle}
th{background:#f3f4f6;text-transform:uppercase;letter-spacing:.03em;font-weight:700;text-align:center}
td{text-align:left}
.day-cell{text-align:center}
tr:nth-child(even){background:#f9fafb}
</style></head><body>
<h1>Horario de Asesorías Docentes</h1>
<p>Semestre: ${semester.value}</p>
${clone.outerHTML}</body></html>`);
    w.document.close();
    w.onload = () => { w.focus(); w.print(); w.close(); };
}
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Asesorías - Horarios', href: '/asesorias-horarios' }]">
        <div class="min-h-screen bg-gray-50">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

                <!-- Header -->
                <div class="mb-4">
                    <h1 class="text-2xl font-semibold text-gray-900">Horario de Asesorías</h1>
                    <p class="mt-1 text-sm text-gray-600">Horarios de asesoría de todos los docentes por materia.</p>
                </div>

                <!-- Toolbar -->
                <div class="mb-4 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:flex-row md:items-center md:justify-between toolbar">
                    <div class="flex flex-1 items-center gap-3">
                        <div class="relative w-full max-w-md">
                            <input v-model="search" type="text"
                                class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2 pl-10 text-sm outline-none focus:border-gray-300"
                                placeholder="Buscar por docente, materia..." />
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
                    <button type="button" @click="printSchedule"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 9V4h12v5M6 18h12v2H6v-2zm0 0H5a3 3 0 01-3-3v-3a3 3 0 013-3h14a3 3 0 013 3v3a3 3 0 01-3 3h-1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Imprimir Horario
                    </button>
                </div>

                <!-- Schedule Table -->
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table ref="printTableRef" class="w-full table-auto">
                            <thead class="bg-gray-50">
                                <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-600">
                                    <th class="px-4 py-3 text-left min-w-[180px]">Materia</th>
                                    <th class="px-4 py-3 text-left min-w-[150px]">Docente</th>
                                    <th v-for="day in dayCols" :key="day.key"
                                        class="px-3 py-3 text-center min-w-[100px]">
                                        {{ day.label }}
                                    </th>
                                    <th class="px-4 py-3 text-left">Carrera</th>
                                    <th class="px-4 py-3 text-left">Aula</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in filteredRows" :key="row.id" class="hover:bg-gray-50/60">
                                    <td class="px-4 py-3 text-sm text-gray-800">{{ row.materia }}</td>
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ row.docente }}</td>
                                    <td v-for="day in dayCols" :key="day.key" class="px-3 py-3 text-center day-cell">
                                        <span v-if="row[day.key]"
                                            class="inline-block rounded-md bg-indigo-50 px-2 py-1 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200">
                                            {{ row[day.key] }}
                                        </span>
                                        <span v-else class="text-gray-300">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ row.carrera }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ row.aula || '—' }}</td>
                                </tr>
                                <tr v-if="filteredRows.length === 0">
                                    <td colspan="9" class="px-4 py-10 text-center text-sm text-gray-500">
                                        No hay horarios de asesoría registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Summary -->
                <div class="mt-3 text-xs text-gray-500">
                    {{ filteredRows.length }} registros
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print { .toolbar { display: none !important; } }
</style>
