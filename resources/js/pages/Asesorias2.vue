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
 * Seguimientos (por ahora mapeamos:
 * SEG 01 -> seg_sd2
 * SEG 02 -> seg_sd4
 *
 * Más adelante puedes agregar SEG 03/SEG 04 sin cambiar el UI:
 * solo agrega otro objeto aquí y agrega datos en rows.followups.
 */
const FOLLOWUPS = [
  { key: "seg1", label: "SEG 01", legacyField: "seg_sd2" },
  { key: "seg2", label: "SEG 02", legacyField: "seg_sd4" },
];

/**
 * Ref tabla para impresión
 */
const printTableRef = ref(null);

/**
 * Datos fijos (sin BD)
 * Agregamos followups con checklist/evidencias/notas para cada SEG.
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
    followups: {
      seg1: {
        status: "OK",
        notas: "Se entregó checklist parcial.",
        evidencias: [
          { name: "Lista_asistencia_seg01.pdf", type: "PDF" },
          { name: "Rubrica_seg01.pdf", type: "PDF" },
        ],
        checklist: [
          { id: "c1", label: "Planeación entregada", done: true },
          { id: "c2", label: "Lista de asistencia", done: true },
          { id: "c3", label: "Evidencias parciales", done: false },
          { id: "c4", label: "Instrumentación didáctica", done: true },
        ],
      },
      seg2: {
        status: "NE",
        notas: "Faltan evidencias del parcial 2.",
        evidencias: [{ name: "Parcial_2_pendiente.txt", type: "TXT" }],
        checklist: [
          { id: "c1", label: "Evidencias SD4", done: false },
          { id: "c2", label: "Calificaciones capturadas", done: false },
          { id: "c3", label: "Acta parcial", done: true },
        ],
      },
    },
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
    followups: {
      seg1: {
        status: "OK",
        notas: "Todo completo.",
        evidencias: [{ name: "Evidencias_seg01.zip", type: "ZIP" }],
        checklist: [
          { id: "c1", label: "Planeación entregada", done: true },
          { id: "c2", label: "Lista de asistencia", done: true },
          { id: "c3", label: "Evidencias parciales", done: true },
          { id: "c4", label: "Instrumentación didáctica", done: true },
        ],
      },
      seg2: {
        status: "OK",
        notas: "Evidencias SD4 completas.",
        evidencias: [{ name: "Evidencias_seg02.zip", type: "ZIP" }],
        checklist: [
          { id: "c1", label: "Evidencias SD4", done: true },
          { id: "c2", label: "Calificaciones capturadas", done: true },
          { id: "c3", label: "Acta parcial", done: true },
        ],
      },
    },
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
    followups: {
      seg1: {
        status: "NE",
        notas: "Faltan documentos obligatorios.",
        evidencias: [],
        checklist: [
          { id: "c1", label: "Planeación entregada", done: false },
          { id: "c2", label: "Lista de asistencia", done: true },
          { id: "c3", label: "Evidencias parciales", done: false },
        ],
      },
      seg2: {
        status: "NA",
        notas: "Aún no inicia.",
        evidencias: [],
        checklist: [
          { id: "c1", label: "Evidencias SD4", done: false },
          { id: "c2", label: "Calificaciones capturadas", done: false },
        ],
      },
    },
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
    followups: {
      seg1: {
        status: "NA",
        notas: "",
        evidencias: [{ name: "Avance_SD2.pdf", type: "PDF" }],
        checklist: [
          { id: "c1", label: "Planeación entregada", done: false },
          { id: "c2", label: "Lista de asistencia", done: false },
        ],
      },
      seg2: {
        status: "NA",
        notas: "",
        evidencias: [],
        checklist: [{ id: "c1", label: "Evidencias SD4", done: false }],
      },
    },
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
    followups: {
      seg1: {
        status: "OK",
        notas: "Correcto.",
        evidencias: [{ name: "Evidencias_seg01.pdf", type: "PDF" }],
        checklist: [
          { id: "c1", label: "Planeación entregada", done: true },
          { id: "c2", label: "Lista de asistencia", done: true },
        ],
      },
      seg2: {
        status: "OK",
        notas: "Correcto.",
        evidencias: [{ name: "Evidencias_seg02.pdf", type: "PDF" }],
        checklist: [
          { id: "c1", label: "Evidencias SD4", done: true },
          { id: "c2", label: "Calificaciones capturadas", done: true },
        ],
      },
    },
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
    followups: {
      seg1: {
        status: "NA",
        notas: "",
        evidencias: [],
        checklist: [
          { id: "c1", label: "Planeación entregada", done: false },
          { id: "c2", label: "Lista de asistencia", done: false },
        ],
      },
      seg2: {
        status: "NA",
        notas: "",
        evidencias: [],
        checklist: [
          { id: "c1", label: "Evidencias SD4", done: false },
          { id: "c2", label: "Calificaciones capturadas", done: false },
        ],
      },
    },
  },
]);

/**
 * UI state
 */
const search = ref("");
const semester = ref("2025-1");
const exportMenuOpen = ref(false);

/**
 * EXPAND: qué seguimiento está “abierto” (expande columnas a la derecha)
 */
const expandedFollowupKey = ref(null); // "seg1" | "seg2" | null

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
 * Helpers para followups
 */
function ensureFollowup(row, key) {
  if (!row.followups) row.followups = {};
  if (!row.followups[key]) {
    row.followups[key] = {
      status: "NA",
      notas: "",
      evidencias: [],
      checklist: [],
    };
  }
  return row.followups[key];
}

function followupStatus(row, key) {
  const fu = ensureFollowup(row, key);
  return fu.status ?? "NA";
}

function setFollowupStatus(row, key, newStatus) {
  const fu = ensureFollowup(row, key);
  fu.status = newStatus;

  // Mantener compatibilidad con tu estructura actual (no romper nada)
  const map = FOLLOWUPS.find((f) => f.key === key);
  if (map?.legacyField) row[map.legacyField] = newStatus;
}

function toggleFollowupStatus(row, key) {
  const current = followupStatus(row, key);
  setFollowupStatus(row, key, nextStatus(current));
}

function checklistProgress(row, key) {
  const fu = ensureFollowup(row, key);
  const total = fu.checklist?.length ?? 0;
  if (total === 0) return { done: 0, total: 0, pct: 0 };
  const done = fu.checklist.filter((c) => !!c.done).length;
  const pct = Math.round((done / total) * 100);
  return { done, total, pct };
}

/**
 * % por actividad (Ev. Diagnóstico / SEG / Reportes)
 * - Ev diagnóstico: si OK => 100, NE => 50, NA => 0
 * - Reportes: basado en cantidad (simple)
 * - Seguimientos: checklistProgress
 */
function pctFromStatus(status) {
  if (status === "OK") return 100;
  if (status === "NE") return 50;
  return 0;
}

function reportsPct(row) {
  // ejemplo simple: 0 docs => 0%, 1-2 => 50%, 3+ => 100%
  const n = row.reportes_docs?.length ?? 0;
  if (n === 0) return 0;
  if (n <= 2) return 50;
  return 100;
}

/**
 * Click handlers (campos ya existentes)
 */
function toggleFieldStatus(row, field) {
  row[field] = nextStatus(row[field]);

  // Si tocas los fields legacy, reflejarlo al followup correspondiente
  const map = FOLLOWUPS.find((f) => f.legacyField === field);
  if (map) {
    const fu = ensureFollowup(row, map.key);
    fu.status = row[field];
  }
}

/**
 * Expand / collapse
 */
function toggleExpandFollowup(key) {
  expandedFollowupKey.value = expandedFollowupKey.value === key ? null : key;
}
const expandedFollowupMeta = computed(() => {
  if (!expandedFollowupKey.value) return null;
  return FOLLOWUPS.find((f) => f.key === expandedFollowupKey.value) || null;
});

/**
 * Export helpers
 */
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
    "EV_DIAGNOSTICO_PCT",
    "SEG_01_STATUS",
    "SEG_01_PCT",
    "SEG_02_STATUS",
    "SEG_02_PCT",
    "REPORTES_DOCS",
    "REPORTES_PCT",
    "ESTADO_FINAL",
    "SEMESTRE",
  ];

  const lines = [
    headers.join(","),
    ...filteredRows.value.map((r) => {
      const pSeg1 = checklistProgress(r, "seg1").pct;
      const pSeg2 = checklistProgress(r, "seg2").pct;
      const docsCount = r.reportes_docs?.length ?? 0;

      const values = [
        r.maestro,
        r.materia,
        r.carrera,
        r.clave_tecnm,
        r.ev_diagnostico,
        String(pctFromStatus(r.ev_diagnostico)),
        followupStatus(r, "seg1"),
        String(pSeg1),
        followupStatus(r, "seg2"),
        String(pSeg2),
        String(docsCount),
        String(reportsPct(r)),
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
  const data = filteredRows.value.map((r) => {
    const pSeg1 = checklistProgress(r, "seg1").pct;
    const pSeg2 = checklistProgress(r, "seg2").pct;
    const docsCount = r.reportes_docs?.length ?? 0;

    return {
      MAESTRO: r.maestro,
      MATERIA: r.materia,
      CARRERA: r.carrera,
      CLAVE_TECNM: r.clave_tecnm,
      EV_DIAGNOSTICO: r.ev_diagnostico,
      EV_DIAGNOSTICO_PCT: pctFromStatus(r.ev_diagnostico),
      SEG_01_STATUS: followupStatus(r, "seg1"),
      SEG_01_PCT: pSeg1,
      SEG_02_STATUS: followupStatus(r, "seg2"),
      SEG_02_PCT: pSeg2,
      REPORTES_DOCS: docsCount,
      REPORTES_PCT: reportsPct(r),
      ESTADO_FINAL: r.estado_final,
      SEMESTRE: r.semestre,
    };
  });

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
 * Imprimir SOLO la tabla (ventana limpia)
 */
function printPage() {
  exportMenuOpen.value = false;

  const tableEl = printTableRef.value;
  if (!tableEl) {
    alert("No se encontró la tabla para imprimir.");
    return;
  }

  const clone = tableEl.cloneNode(true);

  // Convertir botones en texto
  clone.querySelectorAll("button").forEach((btn) => {
    const span = document.createElement("span");
    span.textContent = btn.textContent?.trim() || "";
    span.className = "chip";
    btn.replaceWith(span);
  });

  // Quitar última columna "Acciones" siempre en impresión
  const ths = clone.querySelectorAll("thead th");
  if (ths.length) ths[ths.length - 1].remove();
  clone.querySelectorAll("tbody tr").forEach((tr) => {
    const tds = tr.querySelectorAll("td");
    if (tds.length) tds[tds.length - 1].remove();
  });

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
 * Modal: Ver (detalle general)
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

/**
 * Modal: Detalle de Seguimiento (SEG 01 / SEG 02)
 */
const followupModalOpen = ref(false);
const followupModalRow = ref(null);
const followupModalKey = ref(null);

function openFollowupDetail(row, key) {
  followupModalRow.value = row;
  followupModalKey.value = key;
  followupModalOpen.value = true;
}
function closeFollowupDetail() {
  followupModalOpen.value = false;
  followupModalRow.value = null;
  followupModalKey.value = null;
}

const followupModalMeta = computed(() => {
  if (!followupModalKey.value) return null;
  return FOLLOWUPS.find((f) => f.key === followupModalKey.value) || null;
});
const followupModalData = computed(() => {
  if (!followupModalRow.value || !followupModalKey.value) return null;
  return ensureFollowup(followupModalRow.value, followupModalKey.value);
});
const followupModalProgress = computed(() => {
  if (!followupModalRow.value || !followupModalKey.value) return { done: 0, total: 0, pct: 0 };
  return checklistProgress(followupModalRow.value, followupModalKey.value);
});
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

        <div v-if="expandedFollowupMeta" class="mt-2 text-xs text-gray-600">
          Mostrando columnas extra de: <span class="font-semibold">{{ expandedFollowupMeta.label }}</span>
          <button
            type="button"
            class="ml-2 rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50"
            @click="expandedFollowupKey = null"
          >
            Cerrar expansión
          </button>
        </div>
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

                <th class="px-4 py-3">
                  Ev. Diagnóstico
                  <div class="mt-1 text-[10px] font-normal normal-case text-gray-500">(% simple)</div>
                </th>

                <!-- Seguimientos (SEG 01 / SEG 02) -->
                <th class="px-4 py-3" v-for="f in FOLLOWUPS" :key="f.key">
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-2 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-50"
                    @click="toggleExpandFollowup(f.key)"
                    :title="expandedFollowupKey === f.key ? 'Cerrar expansión' : 'Expandir columnas a la derecha'"
                  >
                    {{ f.label }}
                    <span class="opacity-70">{{ expandedFollowupKey === f.key ? "◀" : "▶" }}</span>
                  </button>
                </th>

                <th class="px-4 py-3">Reportes / Evidencias</th>
                <th class="px-4 py-3">Estado Final</th>
                <th class="px-4 py-3">Acciones</th>

                <!-- Columnas EXTRA al expandir un seguimiento -->
                <template v-if="expandedFollowupMeta">
                  <th class="px-4 py-3 bg-indigo-50 text-indigo-700">
                    {{ expandedFollowupMeta.label }} % realizado
                  </th>
                  <th class="px-4 py-3 bg-indigo-50 text-indigo-700">
                    Checklist
                  </th>
                  <th class="px-4 py-3 bg-indigo-50 text-indigo-700">
                    Evidencias
                  </th>
                  <th class="px-4 py-3 bg-indigo-50 text-indigo-700">
                    Notas
                  </th>
                  <th class="px-4 py-3 bg-indigo-50 text-indigo-700">
                    Detalle
                  </th>
                </template>
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

                <!-- EV Diagnóstico -->
                <td class="px-4 py-4">
                  <button
                    type="button"
                    @click="toggleFieldStatus(row, 'ev_diagnostico')"
                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1"
                    :class="statusPillClasses(row.ev_diagnostico)"
                    title="Click para alternar NA/OK/NE"
                  >
                    {{ statusLabel(row.ev_diagnostico) }} ({{ pctFromStatus(row.ev_diagnostico) }}%)
                  </button>
                </td>

                <!-- SEG 01 / SEG 02 -->
                <td class="px-4 py-4" v-for="f in FOLLOWUPS" :key="`${row.id}-${f.key}`">
                  <div class="flex items-center gap-2">
                    <!-- chip mantiene toggle de status -->
                    <button
                      type="button"
                      @click="toggleFollowupStatus(row, f.key)"
                      class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1"
                      :class="statusPillClasses(followupStatus(row, f.key))"
                      title="Click para alternar NA/OK/NE"
                    >
                      {{ statusLabel(followupStatus(row, f.key)) }}
                      ({{ checklistProgress(row, f.key).pct }}%)
                    </button>

                    <!-- icono para EXPAND (no cambia status) -->
                    <button
                      type="button"
                      class="rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                      :title="expandedFollowupKey === f.key ? 'Cerrar expansión' : 'Expandir columnas del seguimiento'"
                      @click="toggleExpandFollowup(f.key)"
                    >
                      {{ expandedFollowupKey === f.key ? "◀" : "▶" }}
                    </button>
                  </div>
                </td>

                <!-- Docs (global) -->
                <td class="px-4 py-4">
                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                      @click="openDocs(row)"
                      title="Ver documentos"
                    >
                      {{ row.reportes_docs.length }} docs
                    </button>

                    <span class="text-xs text-gray-500">
                      ({{ reportsPct(row) }}%)
                    </span>
                  </div>
                </td>

                <!-- Estado final -->
                <td class="px-4 py-4">
                  <button
                    type="button"
                    @click="toggleFieldStatus(row, 'estado_final')"
                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1"
                    :class="statusPillClasses(row.estado_final)"
                    title="Click para alternar NA/OK/NE"
                  >
                    {{ statusLabel(row.estado_final) }}
                  </button>
                </td>

                <!-- Acciones -->
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

                <!-- Columnas EXTRA al expandir -->
                <template v-if="expandedFollowupMeta">
                  <td class="px-4 py-4 bg-indigo-50/40">
                    <span class="inline-flex rounded-full border border-indigo-200 bg-white px-3 py-1 text-xs font-semibold text-indigo-700">
                      {{ checklistProgress(row, expandedFollowupMeta.key).pct }}%
                    </span>
                  </td>

                  <td class="px-4 py-4 bg-indigo-50/40">
                    <div class="text-sm text-gray-800">
                      <span class="font-semibold">{{ checklistProgress(row, expandedFollowupMeta.key).done }}</span>
                      /
                      {{ checklistProgress(row, expandedFollowupMeta.key).total }}
                      <span class="text-xs text-gray-500">items</span>
                    </div>
                  </td>

                  <td class="px-4 py-4 bg-indigo-50/40">
                    <div class="flex items-center gap-2">
                      <span class="text-sm text-gray-800">
                        {{ ensureFollowup(row, expandedFollowupMeta.key).evidencias?.length ?? 0 }}
                      </span>
                      <span class="text-xs text-gray-500">archivos</span>
                    </div>
                  </td>

                  <td class="px-4 py-4 bg-indigo-50/40">
                    <div class="max-w-[260px] truncate text-sm text-gray-700">
                      {{ ensureFollowup(row, expandedFollowupMeta.key).notas || "—" }}
                    </div>
                  </td>

                  <td class="px-4 py-4 bg-indigo-50/40">
                    <button
                      type="button"
                      class="rounded-lg border border-indigo-200 bg-white px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50"
                      @click="openFollowupDetail(row, expandedFollowupMeta.key)"
                    >
                      Ver detalle
                    </button>
                  </td>
                </template>
              </tr>

              <tr v-if="filteredRows.length === 0">
                <td :colspan="expandedFollowupMeta ? 15 : 10" class="px-4 py-10 text-center text-sm text-gray-500">
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
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                      :class="statusPillClasses(viewModalRow?.ev_diagnostico)">
                  {{ viewModalRow?.ev_diagnostico }} ({{ pctFromStatus(viewModalRow?.ev_diagnostico) }}%)
                </span>
              </div>
            </div>

            <div class="rounded-lg border border-gray-100 p-3">
              <div class="text-xs font-semibold text-gray-500">SEG 01</div>
              <div class="mt-1 text-sm">
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                      :class="statusPillClasses(followupStatus(viewModalRow, 'seg1'))">
                  {{ followupStatus(viewModalRow, 'seg1') }} ({{ checklistProgress(viewModalRow, 'seg1').pct }}%)
                </span>
              </div>
            </div>

            <div class="rounded-lg border border-gray-100 p-3">
              <div class="text-xs font-semibold text-gray-500">SEG 02</div>
              <div class="mt-1 text-sm">
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                      :class="statusPillClasses(followupStatus(viewModalRow, 'seg2'))">
                  {{ followupStatus(viewModalRow, 'seg2') }} ({{ checklistProgress(viewModalRow, 'seg2').pct }}%)
                </span>
              </div>
            </div>

            <div class="rounded-lg border border-gray-100 p-3">
              <div class="text-xs font-semibold text-gray-500">Estado final</div>
              <div class="mt-1 text-sm">
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                      :class="statusPillClasses(viewModalRow?.estado_final)">
                  {{ viewModalRow?.estado_final }}
                </span>
              </div>
            </div>

            <div class="rounded-lg border border-gray-100 p-3 sm:col-span-2">
              <div class="text-xs font-semibold text-gray-500">Reportes/Evidencias</div>
              <div class="mt-1 text-sm text-gray-900">
                {{ (viewModalRow?.reportes_docs?.length ?? 0) }} documentos ({{ reportsPct(viewModalRow) }}%)
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

      <!-- Modal Seguimiento Detalle -->
      <div
        v-if="followupModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 modal-layer"
        @click.self="closeFollowupDetail"
      >
        <div class="w-full max-w-2xl rounded-xl bg-white shadow-lg">
          <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <div>
              <h2 class="text-base font-semibold text-gray-900">
                Detalle {{ followupModalMeta?.label }}
              </h2>
              <p class="text-sm text-gray-600">
                {{ followupModalRow?.maestro }} — {{ followupModalRow?.materia }}
              </p>
              <p class="mt-1 text-xs text-gray-500">
                Progreso: {{ followupModalProgress.pct }}% ({{ followupModalProgress.done }}/{{ followupModalProgress.total }})
              </p>
            </div>
            <button
              type="button"
              class="rounded-lg p-2 text-gray-500 hover:bg-gray-100"
              @click="closeFollowupDetail"
              aria-label="Cerrar"
            >
              ✕
            </button>
          </div>

          <div class="grid grid-cols-1 gap-4 px-5 py-4 md:grid-cols-2">
            <!-- Checklist -->
            <div class="rounded-lg border border-gray-100 p-4">
              <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Checklist</div>

              <div v-if="(followupModalData?.checklist?.length ?? 0) === 0" class="mt-2 text-sm text-gray-600">
                No hay checklist definido.
              </div>

              <ul v-else class="mt-3 space-y-2">
                <li
                  v-for="item in followupModalData.checklist"
                  :key="item.id"
                  class="flex items-start gap-2 rounded-lg border border-gray-100 px-3 py-2"
                >
                  <input type="checkbox" class="mt-1" v-model="item.done" />
                  <div class="min-w-0">
                    <div class="text-sm font-semibold text-gray-900">
                      {{ item.label }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ item.done ? "Completado" : "Pendiente" }}
                    </div>
                  </div>
                </li>
              </ul>
              <p class="mt-3 text-xs text-gray-500">
                (Por ahora es visual. Sigue sin BD.)
              </p>
            </div>

            <!-- Evidencias + Notas -->
            <div class="space-y-4">
              <div class="rounded-lg border border-gray-100 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Evidencias</div>

                <div v-if="(followupModalData?.evidencias?.length ?? 0) === 0" class="mt-2 text-sm text-gray-600">
                  No hay evidencias.
                </div>

                <ul v-else class="mt-3 space-y-2">
                  <li
                    v-for="(e, idx) in followupModalData.evidencias"
                    :key="idx"
                    class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2"
                  >
                    <div class="min-w-0">
                      <div class="truncate text-sm font-medium text-gray-900">{{ e.name }}</div>
                      <div class="text-xs text-gray-500">{{ e.type }}</div>
                    </div>
                    <button
                      type="button"
                      class="rounded-lg border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                      @click="alert('Sin BD por ahora: aquí luego abrirás/descargarás la evidencia.')"
                    >
                      Abrir
                    </button>
                  </li>
                </ul>
              </div>

              <div class="rounded-lg border border-gray-100 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Notas</div>
                <textarea
                  v-model="followupModalData.notas"
                  rows="4"
                  class="mt-2 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-gray-300"
                  placeholder="Escribe notas del seguimiento..."
                />
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
            <button
              type="button"
              class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
              @click="closeFollowupDetail"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>

      <!-- Hint -->
      <div class="mt-4 text-xs text-gray-500">
        Tip: click en los chips (NA/OK/NE) para alternar estados. Click en SEG 01/SEG 02 para expandir columnas.
      </div>
    </div>
  </div>
</template>

<style>
@media print {
  .toolbar,
  .modal-layer {
    display: none !important;
  }
}
</style>