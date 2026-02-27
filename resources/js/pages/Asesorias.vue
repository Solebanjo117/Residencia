<script setup>
import { computed, ref } from "vue";
import * as XLSX from "xlsx";

/**
 * Estados permitidos
 */
const STATUS_ORDER = ["NA", "OK", "NE"];

function nextStatus(current) {
  const idx = STATUS_ORDER.indexOf(current);
  return STATUS_ORDER[(idx + 1) % STATUS_ORDER.length];
}

function statusPillClasses(status) {
  if (status === "OK") return "bg-green-100 text-green-700 ring-green-200";
  if (status === "NE") return "bg-red-100 text-red-700 ring-red-200";
  return "bg-gray-100 text-gray-700 ring-gray-200";
}

function statusLabel(status) {
  return status;
}

/**
 * Ref a la tabla (para impresión)
 */
const printTableRef = ref(null);

/**
 * Datos fijos (sin BD)
 */
const rows = ref([
  {
    id: 1,
    maestro: "Dr. María González",
    materia: "Matemáticas Avanzadas",
    carrera: "Ingeniería en Sistemas",
    clave_tecnm: "SCC-1001",
    semestre: "2025-1",
    ev_diagnostico: "OK",
    seg_sd2: "OK",
    seg_sd4: "NE",
    reportes_docs: [
      { name: "Diagnóstico.pdf", type: "PDF" },
      { name: "Seguimiento_SD2.docx", type: "DOCX" },
      { name: "Evidencias.zip", type: "ZIP" },
    ],
    estado_final: "NE",
  },
  {
    id: 2,
    maestro: "Dr. María González",
    materia: "Cálculo Diferencial",
    carrera: "Ingeniería Industrial",
    clave_tecnm: "SCC-1002",
    semestre: "2025-1",
    ev_diagnostico: "OK",
    seg_sd2: "OK",
    seg_sd4: "OK",
    reportes_docs: [
      { name: "Lista_Asistencia.xlsx", type: "XLSX" },
      { name: "Parcial_1.pdf", type: "PDF" },
      { name: "Parcial_2.pdf", type: "PDF" },
      { name: "Parcial_3.pdf", type: "PDF" },
      { name: "Reporte_Final.pdf", type: "PDF" },
    ],
    estado_final: "OK",
  },
  {
    id: 3,
    maestro: "Mtro. Carlos Ramírez",
    materia: "Programación Web",
    carrera: "Ingeniería en Sistemas",
    clave_tecnm: "SCC-2001",
    semestre: "2025-1",
    ev_diagnostico: "OK",
    seg_sd2: "NE",
    seg_sd4: "NA",
    reportes_docs: [{ name: "Evidencia_1.png", type: "IMG" }],
    estado_final: "NE",
  },
  {
    id: 4,
    maestro: "Mtro. Carlos Ramírez",
    materia: "Bases de Datos",
    carrera: "Ingeniería en Sistemas",
    clave_tecnm: "SCC-2002",
    semestre: "2025-1",
    ev_diagnostico: "OK",
    seg_sd2: "NA",
    seg_sd4: "NA",
    reportes_docs: [
      { name: "Rubrica.pdf", type: "PDF" },
      { name: "Avance_SD2.pdf", type: "PDF" },
    ],
    estado_final: "NA",
  },
  {
    id: 5,
    maestro: "Dra. Ana López",
    materia: "Física II",
    carrera: "Ingeniería Mecánica",
    clave_tecnm: "SCC-3001",
    semestre: "2025-1",
    ev_diagnostico: "OK",
    seg_sd2: "OK",
    seg_sd4: "OK",
    reportes_docs: [
      { name: "Practica_1.pdf", type: "PDF" },
      { name: "Practica_2.pdf", type: "PDF" },
      { name: "Practica_3.pdf", type: "PDF" },
      { name: "Acta_Final.pdf", type: "PDF" },
    ],
    estado_final: "OK",
  },
  {
    id: 6,
    maestro: "Dr. Roberto Sánchez",
    materia: "Química Orgánica",
    carrera: "Ingeniería Química",
    clave_tecnm: "SCC-4001",
    semestre: "2025-2",
    ev_diagnostico: "NA",
    seg_sd2: "NA",
    seg_sd4: "NA",
    reportes_docs: [],
    estado_final: "NA",
  },
]);

/**
 * UI state
 */
const search = ref("");
const semester = ref("2025-1");
const exportMenuOpen = ref(false);

const semesters = computed(() => {
  const set = new Set(rows.value.map((r) => r.semestre));
  return Array.from(set).sort();
});

const filteredRows = computed(() => {
  const q = search.value.trim().toLowerCase();
  return rows.value
    .filter((r) => (semester.value ? r.semestre === semester.value : true))
    .filter((r) => {
      if (!q) return true;
      return (
        r.maestro.toLowerCase().includes(q) ||
        r.materia.toLowerCase().includes(q) ||
        r.clave_tecnm.toLowerCase().includes(q)
      );
    });
});

/**
 * Click handlers
 */
function toggleFieldStatus(row, field) {
  row[field] = nextStatus(row[field]);
}

function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

function exportCSV() {
  const headers = [
    "MAESTRO",
    "MATERIA",
    "CARRERA",
    "CLAVE_TECNM",
    "EV_DIAGNOSTICO",
    "SEGUIMIENTO_SD2",
    "SEGUIMIENTO_SD4",
    "REPORTES_DOCS",
    "ESTADO_FINAL",
    "SEMESTRE",
  ];

  const lines = [
    headers.join(","),
    ...filteredRows.value.map((r) => {
      const docsCount = r.reportes_docs?.length ?? 0;
      const values = [
        r.maestro,
        r.materia,
        r.carrera,
        r.clave_tecnm,
        r.ev_diagnostico,
        r.seg_sd2,
        r.seg_sd4,
        String(docsCount),
        r.estado_final,
        r.semestre,
      ].map((v) => `"${String(v).replaceAll('"', '""')}"`);
      return values.join(",");
    }),
  ];

  const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
  downloadBlob(blob, `seguimiento_${semester.value || "todos"}.csv`);
}

function exportXLSX() {
  const data = filteredRows.value.map((r) => ({
    MAESTRO: r.maestro,
    MATERIA: r.materia,
    CARRERA: r.carrera,
    CLAVE_TECNM: r.clave_tecnm,
    EV_DIAGNOSTICO: r.ev_diagnostico,
    SEGUIMIENTO_SD2: r.seg_sd2,
    SEGUIMIENTO_SD4: r.seg_sd4,
    REPORTES_DOCS: r.reportes_docs?.length ?? 0,
    ESTADO_FINAL: r.estado_final,
    SEMESTRE: r.semestre,
  }));

  const ws = XLSX.utils.json_to_sheet(data);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Asesorias");

  const out = XLSX.write(wb, { bookType: "xlsx", type: "array" });
  const blob = new Blob([out], {
    type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
  });

  downloadBlob(blob, `seguimiento_${semester.value || "todos"}.xlsx`);
}

/**
 * Imprimir SOLO la tabla en una ventana limpia
 */
function printPage() {
  exportMenuOpen.value = false;

  const tableEl = printTableRef.value;
  if (!tableEl) {
    alert("No se encontró la tabla para imprimir.");
    return;
  }

  // Clonar la tabla
  const clone = tableEl.cloneNode(true);

  // Convertir botones a texto "chip"
  clone.querySelectorAll("button").forEach((btn) => {
    const span = document.createElement("span");
    span.textContent = btn.textContent?.trim() || "";
    span.className = "chip";
    btn.replaceWith(span);
  });

  // Quitar última columna (Acciones) para impresión
  const removeActions = true;
  if (removeActions) {
    const ths = clone.querySelectorAll("thead th");
    if (ths.length) ths[ths.length - 1].remove();

    clone.querySelectorAll("tbody tr").forEach((tr) => {
      const tds = tr.querySelectorAll("td");
      if (tds.length) tds[tds.length - 1].remove();
    });
  }

  const w = window.open("", "_blank", "width=1200,height=800");
  if (!w) {
    alert("El navegador bloqueó la ventana emergente para imprimir.");
    return;
  }

  const title = "Control de Seguimiento Docente";
  const subtitle = `Semestre: ${semester.value}`;

  w.document.open();
  w.document.write(`
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>${title}</title>
  <style>
    @page { size: A4 landscape; margin: 10mm; }
    html, body { font-family: Arial, sans-serif; color: #111; }
    h1 { font-size: 16px; margin: 0 0 4px 0; }
    p  { font-size: 11px; margin: 0 0 10px 0; color: #444; }

    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #e5e7eb; padding: 6px 8px; font-size: 10px; vertical-align: top; }
    th { background: #f3f4f6; text-transform: uppercase; letter-spacing: .03em; }

    .chip {
      display: inline-block;
      padding: 2px 8px;
      border: 1px solid #d1d5db;
      border-radius: 999px;
      font-size: 10px;
      line-height: 1.3;
      white-space: nowrap;
    }

    tr, td, th { page-break-inside: avoid; }
  </style>
</head>
<body>
  <h1>${title}</h1>
  <p>${subtitle}</p>
  ${clone.outerHTML}
</body>
</html>
  `);
  w.document.close();

  // Imprimir cuando cargue (sin meter <script> dentro del HTML)
  w.onload = () => {
    w.focus();
    w.print();
    w.close();
  };
}

/**
 * Modal: Docs
 */
const docsModalOpen = ref(false);
const docsModalRow = ref(null);

function openDocs(row) {
  docsModalRow.value = row;
  docsModalOpen.value = true;
}
function closeDocs() {
  docsModalOpen.value = false;
  docsModalRow.value = null;
}

/**
 * Modal: Ver
 */
const viewModalOpen = ref(false);
const viewModalRow = ref(null);

function openView(row) {
  viewModalRow.value = row;
  viewModalOpen.value = true;
}
function closeView() {
  viewModalOpen.value = false;
  viewModalRow.value = null;
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

      <!-- Header -->
      <div class="mb-4">
        <h1 class="text-2xl font-semibold text-gray-900">
          Control de Seguimiento Docente
        </h1>
        <p class="mt-1 text-sm text-gray-600">
          Vista preliminar (sin base de datos). Estados: NA / OK / NE.
        </p>
      </div>

      <!-- Toolbar -->
      <div
        class="mb-4 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:flex-row md:items-center md:justify-between toolbar"
      >
        <div class="flex flex-1 items-center gap-3">
          <div class="relative w-full max-w-2xl">
            <input
              v-model="search"
              type="text"
              class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2 pl-10 text-sm outline-none ring-0 focus:border-gray-300"
              placeholder="Buscar por maestro, materia o clave..."
            />
            <div class="pointer-events-none absolute left-3 top-2.5 text-gray-400">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path
                  d="M21 21l-4.3-4.3m1.3-5.2a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                />
              </svg>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <span class="hidden text-sm text-gray-500 md:inline">Semestre</span>
            <select
              v-model="semester"
              class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:border-gray-300"
            >
              <option v-for="s in semesters" :key="s" :value="s">
                {{ s }}
              </option>
            </select>
          </div>
        </div>

        <div class="relative flex items-center gap-2">
          <!-- Export menu -->
          <button
            type="button"
            @click="exportMenuOpen = !exportMenuOpen"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            aria-haspopup="menu"
            :aria-expanded="exportMenuOpen"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path
                d="M12 3v10m0 0l4-4m-4 4l-4-4M5 21h14"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            Exportar
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="opacity-70">
              <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>

          <div
            v-if="exportMenuOpen"
            class="absolute right-0 top-11 z-20 w-44 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg"
            role="menu"
          >
            <button
              type="button"
              class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
              role="menuitem"
              @click="exportCSV(); exportMenuOpen = false;"
            >
              Exportar CSV
            </button>
            <button
              type="button"
              class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
              role="menuitem"
              @click="exportXLSX(); exportMenuOpen = false;"
            >
              Exportar XLSX
            </button>
          </div>

          <button
            type="button"
            @click="printPage"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path
                d="M6 9V4h12v5M6 18h12v2H6v-2zm0 0H5a3 3 0 01-3-3v-3a3 3 0 013-3h14a3 3 0 013 3v3a3 3 0 01-3 3h-1"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            Imprimir
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
          <table ref="printTableRef" class="min-w-[1100px] w-full table-auto">
            <thead class="bg-gray-50">
              <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                <th class="px-4 py-3">Maestro</th>
                <th class="px-4 py-3">Materia</th>
                <th class="px-4 py-3">Carrera</th>
                <th class="px-4 py-3">Clave Tecnm</th>
                <th class="px-4 py-3">Ev. Diagnóstico</th>
                <th class="px-4 py-3">Seguimiento SD2</th>
                <th class="px-4 py-3">Seguimiento SD4</th>
                <th class="px-4 py-3">Reportes / Evidencias</th>
                <th class="px-4 py-3">Estado Final</th>
                <th class="px-4 py-3">Acciones</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
              <tr v-for="row in filteredRows" :key="row.id" class="hover:bg-gray-50/60">
                <td class="px-4 py-4 text-sm font-semibold text-gray-900">
                  {{ row.maestro }}
                </td>

                <td class="px-4 py-4 text-sm text-gray-800">
                  {{ row.materia }}
                </td>

                <td class="px-4 py-4 text-sm text-gray-700">
                  {{ row.carrera }}
                </td>

                <td class="px-4 py-4 text-sm font-mono text-gray-700">
                  {{ row.clave_tecnm }}
                </td>

                <td class="px-4 py-4">
                  <button
                    type="button"
                    @click="toggleFieldStatus(row, 'ev_diagnostico')"
                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1"
                    :class="statusPillClasses(row.ev_diagnostico)"
                  >
                    {{ statusLabel(row.ev_diagnostico) }}
                  </button>
                </td>

                <td class="px-4 py-4">
                  <button
                    type="button"
                    @click="toggleFieldStatus(row, 'seg_sd2')"
                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1"
                    :class="statusPillClasses(row.seg_sd2)"
                  >
                    {{ statusLabel(row.seg_sd2) }}
                  </button>
                </td>

                <td class="px-4 py-4">
                  <button
                    type="button"
                    @click="toggleFieldStatus(row, 'seg_sd4')"
                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1"
                    :class="statusPillClasses(row.seg_sd4)"
                  >
                    {{ statusLabel(row.seg_sd4) }}
                  </button>
                </td>

                <td class="px-4 py-4">
                  <button
                    type="button"
                    class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                    @click="openDocs(row)"
                  >
                    {{ row.reportes_docs.length }} docs
                  </button>
                </td>

                <td class="px-4 py-4">
                  <button
                    type="button"
                    @click="toggleFieldStatus(row, 'estado_final')"
                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1"
                    :class="statusPillClasses(row.estado_final)"
                  >
                    {{ statusLabel(row.estado_final) }}
                  </button>
                </td>

                <td class="px-4 py-4">
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-100"
                    @click="openView(row)"
                  >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                      <path
                        d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"
                        stroke="currentColor"
                        stroke-width="2"
                      />
                      <path
                        d="M12 15a3 3 0 100-6 3 3 0 000 6z"
                        stroke="currentColor"
                        stroke-width="2"
                      />
                    </svg>
                    Ver
                  </button>
                </td>
              </tr>

              <tr v-if="filteredRows.length === 0">
                <td colspan="10" class="px-4 py-10 text-center text-sm text-gray-500">
                  No hay resultados con esos filtros.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal Docs -->
      <div
        v-if="docsModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 modal-layer"
        @click.self="closeDocs"
      >
        <div class="w-full max-w-lg rounded-xl bg-white shadow-lg">
          <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <div>
              <h2 class="text-base font-semibold text-gray-900">Documentos</h2>
              <p class="text-sm text-gray-600">
                {{ docsModalRow?.maestro }} — {{ docsModalRow?.materia }}
              </p>
            </div>
            <button
              type="button"
              class="rounded-lg p-2 text-gray-500 hover:bg-gray-100"
              @click="closeDocs"
              aria-label="Cerrar"
            >
              ✕
            </button>
          </div>

          <div class="px-5 py-4">
            <div v-if="(docsModalRow?.reportes_docs?.length ?? 0) === 0" class="text-sm text-gray-600">
              No hay documentos.
            </div>

            <ul v-else class="space-y-2">
              <li
                v-for="(d, idx) in docsModalRow.reportes_docs"
                :key="idx"
                class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2"
              >
                <div class="min-w-0">
                  <div class="truncate text-sm font-medium text-gray-900">
                    {{ d.name }}
                  </div>
                  <div class="text-xs text-gray-500">{{ d.type }}</div>
                </div>

                <button
                  type="button"
                  class="rounded-lg border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                  @click="alert('Sin BD por ahora: aquí luego abrirás/descargarás el documento.')"
                >
                  Abrir
                </button>
              </li>
            </ul>
          </div>

          <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
            <button
              type="button"
              class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
              @click="closeDocs"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>

      <!-- Modal Ver -->
      <div
        v-if="viewModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 modal-layer"
        @click.self="closeView"
      >
        <div class="w-full max-w-xl rounded-xl bg-white shadow-lg">
          <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <div>
              <h2 class="text-base font-semibold text-gray-900">Detalle</h2>
              <p class="text-sm text-gray-600">
                {{ viewModalRow?.maestro }} — {{ viewModalRow?.materia }}
              </p>
            </div>
            <button
              type="button"
              class="rounded-lg p-2 text-gray-500 hover:bg-gray-100"
              @click="closeView"
              aria-label="Cerrar"
            >
              ✕
            </button>
          </div>

          <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-2">
            <div class="rounded-lg border border-gray-100 p-3">
              <div class="text-xs font-semibold text-gray-500">Carrera</div>
              <div class="mt-1 text-sm text-gray-900">{{ viewModalRow?.carrera }}</div>
            </div>

            <div class="rounded-lg border border-gray-100 p-3">
              <div class="text-xs font-semibold text-gray-500">Clave Tecnm</div>
              <div class="mt-1 font-mono text-sm text-gray-900">{{ viewModalRow?.clave_tecnm }}</div>
            </div>

            <div class="rounded-lg border border-gray-100 p-3">
              <div class="text-xs font-semibold text-gray-500">Ev. Diagnóstico</div>
              <div class="mt-1 text-sm">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                  :class="statusPillClasses(viewModalRow?.ev_diagnostico)"
                >
                  {{ viewModalRow?.ev_diagnostico }}
                </span>
              </div>
            </div>

            <div class="rounded-lg border border-gray-100 p-3">
              <div class="text-xs font-semibold text-gray-500">Seguimiento SD2</div>
              <div class="mt-1 text-sm">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                  :class="statusPillClasses(viewModalRow?.seg_sd2)"
                >
                  {{ viewModalRow?.seg_sd2 }}
                </span>
              </div>
            </div>

            <div class="rounded-lg border border-gray-100 p-3">
              <div class="text-xs font-semibold text-gray-500">Seguimiento SD4</div>
              <div class="mt-1 text-sm">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                  :class="statusPillClasses(viewModalRow?.seg_sd4)"
                >
                  {{ viewModalRow?.seg_sd4 }}
                </span>
              </div>
            </div>

            <div class="rounded-lg border border-gray-100 p-3">
              <div class="text-xs font-semibold text-gray-500">Estado final</div>
              <div class="mt-1 text-sm">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                  :class="statusPillClasses(viewModalRow?.estado_final)"
                >
                  {{ viewModalRow?.estado_final }}
                </span>
              </div>
            </div>

            <div class="rounded-lg border border-gray-100 p-3 sm:col-span-2">
              <div class="text-xs font-semibold text-gray-500">Reportes/Evidencias</div>
              <div class="mt-1 text-sm text-gray-900">
                {{ (viewModalRow?.reportes_docs?.length ?? 0) }} documentos
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
            <button
              type="button"
              class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
              @click="closeView"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>

      <div class="mt-4 text-xs text-gray-500">
        Tip: da click en los “chips” (NA/OK/NE) para alternar estados.
      </div>
    </div>
  </div>
</template>

<!-- Puedes dejar esto mínimo: si alguien hace Ctrl+P, no sale feo -->
<style>
@media print {
  .toolbar,
  .modal-layer {
    display: none !important;
  }
}
</style>