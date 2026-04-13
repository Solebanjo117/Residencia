# Baseline de rutas vivas y legado

Fecha: 2026-04-12
Origen: ejecucion de ASAD-P0-00

## Matriz Ruta -> Controller -> Vista -> Estado

| Ruta | Controller | Vista Inertia | Estado |
|---|---|---|---|
| /asesorias (GET) | Admin/AdvisoryController@index | SeguimientoDocente | VIVA (core) |
| /asesorias/{submission}/review (POST) | Admin/AdvisoryController@reviewEvidence | Accion desde SeguimientoDocente | VIVA (core, riesgo seguridad) |
| /docente/evidencias (GET) | Teacher/EvidenceController@index | Teacher/Evidencias/Index | VIVA (core) |
| /docente/evidencias/{submission}/upload (POST) | Teacher/EvidenceController@storeFile | Teacher/Evidencias/Index | VIVA (core) |
| /docente/evidencias/{submission}/submit (POST) | Teacher/EvidenceController@submit | Teacher/Evidencias/Index | VIVA (core) |
| /files/manager (GET) | FolderController@index | FileManager/Index | VIVA (core) |
| /files/folders/{folder}/upload (POST) | FileController@store | FileManager/Index | VIVA (core) |
| /oficina/revisiones (GET) | Admin/ReviewController@index | Oficina/PendingReviews | VIVA |
| /oficina/revisiones/{submission} (GET) | Admin/ReviewController@show | Oficina/ReviewDetail | VIVA |
| /oficina/revisiones/{submission}/status (POST) | Admin/ReviewController@updateStatus | Oficina/ReviewDetail | VIVA |
| /asesorias-horarios (GET) | AdvisoryScheduleController@index | Asesorias/Index | VIVA |
| /dashboard (GET) | Closure web.php | Dashboard | VIVA (placeholder) |
| /oficina/reportes (GET) | Closure web.php | Oficina/Reports | VIVA (stub) |
| /asesorias2 (GET/POST) | N/A | Asesorias2 | LEGADO (sin ruta) |

## Hallazgos de baseline

1. Seguimiento docente oficial es `SeguimientoDocente` sobre `/asesorias`.
2. `POST /asesorias/{submission}/review` esta en bloque autenticado general y requiere hardening en P0-01.
3. Existe metodo `initSubmission()` en Teacher/EvidenceController sin ruta registrada (brecha P1-01).
4. `Asesorias2.vue` referencia `/asesorias2`, pero esa ruta no existe en `routes/web.php`.
5. `Asesorias.vue` contiene logica heredada con alertas de demo y no es flujo oficial activo.

## Decision de congelamiento (P0/P1)

Durante P0 y P1:
1. No implementar cambios funcionales en `Asesorias.vue`.
2. No implementar cambios funcionales en `Asesorias2.vue`.
3. Concentrar cambios de seguimiento en `SeguimientoDocente.vue` y `Admin/AdvisoryController.php`.

## Evidencia tecnica usada

1. `routes/web.php`:
   - `/asesorias` (GET)
   - `/asesorias/{submission}/review` (POST)
   - rutas `/docente/evidencias/*`
2. `app/Http/Controllers/Admin/AdvisoryController.php` renderiza `SeguimientoDocente`.
3. `resources/js/pages/Asesorias2.vue` referencia `/asesorias2` sin ruta backend.
