# Proyecto Residencia - Estado Operativo

**Fecha de actualizacion**: 2026-05-18
**Tipo de corte**: Comparacion de backlog/documentos contra codigo actual despues de commits 2026-05-10 a 2026-05-15
**Stack**: Laravel 12 + PHP 8.2 + Fortify + Inertia.js v2 + Vue 3 + TypeScript + Tailwind CSS v4

---

## Resumen Ejecutivo

Sistema ASAD para gestion institucional de evidencias docentes por semestre y carga docente.

El backlog critico del corte 2026-04-12 ya no representa el estado real. Los commits del 10 al 15 de mayo cerraron los P0/P1/P2 principales: blindaje de revision, flujo de estados, carga por File Manager sin auto-submit, inicializacion docente, notificaciones programadas, prorrogas, formatos, ventanas, reportes, dashboards, observabilidad, backups y hardening de archivos.

Este documento deja fuera hallazgos ya resueltos y conserva solo pendientes vigentes o deuda tecnica que todavia aparece en codigo actual.

---

## Estado General

- Seguridad de flujo de evidencias: VERDE
- Reglas de negocio de transiciones: VERDE
- File Manager y archivos de evidencias: VERDE con deuda localizada
- Notificaciones programadas: AMARILLO
- Seguimiento docente y revision institucional: VERDE
- Reportes, dashboards y observabilidad: VERDE
- Deuda tecnica/documental: AMARILLO

---

## Cambios Confirmados del 10 al 18 de Mayo

Rango revisado con `git log --since="2026-05-10 00:00" --until="2026-05-18 23:59"`.

Commits incluidos:
- `8ed1b3a` 2026-05-10: verificacion de email para usuarios registrados.
- `36ff775` 2026-05-10: correccion de formato/lint CI.
- `b9576e7` 2026-05-11: carga de archivos desde celdas de seguimiento docente.
- `98205c3` 2026-05-11: notificaciones a docentes por decisiones manuales en seguimiento.
- `db7b433` 2026-05-11: visibilidad de evidencias en File Manager.
- `4d5754c` 2026-05-11: carga por File Manager despues de aprobacion.
- `ad75f46` 2026-05-11: ventanas por evidencia y modalidad.
- `f67da16` 2026-05-11: resaltado de carpetas de proyectos individuales.
- `85ccc3e` 2026-05-14: etiquetas y orden de etapas de evidencias.
- `1f621c4` 2026-05-15: permisos de oficina y notificaciones accionables.

No hay commits locales fechados 2026-05-16 a 2026-05-18 en este checkout.

---

## Hallazgos Resueltos

### Revision de evidencias blindada

Estado: resuelto.

Evidencia:
- `routes/web.php`: `POST /asesorias/{submission}/review` tiene middleware `role:JEFE_OFICINA`.
- `app/Http/Controllers/Admin/AdvisoryController.php`: `reviewEvidence()` ejecuta `authorize('review', $submission)` y delega en `EvidenceService::review()`.
- `tests/Feature/Security/AsesoriasReviewAuthorizationTest.php`: cubre rechazo de docente y jefe de departamento, exito de jefe de oficina y comentario obligatorio al rechazar.
- `tests/Feature/Security/OfficeReviewStatusAuthorizationTest.php`: cubre autorizacion del flujo de revision de oficina.

### File Manager ya no fuerza `SUBMITTED`

Estado: resuelto.

Evidencia:
- `app/Http/Controllers/FileController.php`: `store()`, `replace()` y `destroy()` actualizan `last_updated_at`, pero no cambian el estado a `SUBMITTED`.
- `app/Services/StorageService.php`: centraliza validacion de extension, MIME, tamano, nombre seguro y ruta dentro de carpeta.
- `tests/Feature/Security/FileManagerUploadWorkflowTest.php`: cubre que File Manager no autoenvia, respeta permisos, rechaza ZIP, rechaza MIME incorrecto y bloquea rutas fuera de alcance.

### Transiciones institucionales endurecidas

Estado: resuelto.

Evidencia:
- `app/Services/EvidenceService.php`: `ALLOWED_TRANSITIONS` ya no permite `DRAFT -> APPROVED` ni `APPROVED -> SUBMITTED`.
- `EvidenceService::changeStatus()` registra historial y auditoria.
- `tests/Feature/Domain/EvidenceStatusTransitionsTest.php`: cubre transiciones permitidas/no permitidas.

### Inicializacion docente disponible

Estado: resuelto.

Evidencia:
- `routes/web.php`: `POST /docente/evidencias/init` publicado como `docente.evidencias.init`.
- `app/Http/Controllers/Teacher/EvidenceController.php`: `initSubmission()` valida carga, matriz activa, ventana/etapa y crea la entrega en `DRAFT`.
- `tests/Feature/Teacher/EvidenceInitializationTest.php`: cubre creacion e idempotencia.

### Notificaciones programadas operativas

Estado: resuelto parcialmente; ver pendiente vigente `P1-NOTIF-01`.

Evidencia de lo resuelto:
- `routes/console.php`: `notify:windows` se ejecuta cada cinco minutos.
- `app/Console/Commands/NotifyWindows.php`: usa `teaching_loads.teacher_user_id` y marca schedules como enviados.
- `tests/Feature/Console/NotifyWindowsCommandTest.php`: cubre ejecucion del comando.

### Departamentos, prorrogas, formatos y ventanas

Estado: resuelto.

Evidencia:
- `app/Models/Department.php`: expone `teachers()` y `requirements()`.
- `app/Http/Controllers/Admin/DepartmentController.php`: usa esas relaciones antes de eliminar.
- `app/Models/EvidenceSubmission.php`: `activeResubmissionUnlock()` considera `expires_at = null` como activo.
- `config/evidence.php`: define la matriz unica de extensiones/MIME.
- `app/Http/Controllers/Admin/SubmissionWindowController.php`: bloquea ventanas activas solapadas por semestre, evidencia y modalidad.
- Pruebas: `DepartmentDeletionGuardTest.php`, `EvidenceUnlockWindowTest.php`, `SubmissionWindowOverlapValidationTest.php`.

### Producto, operaciones y QA

Estado: resuelto como base operativa.

Evidencia:
- `app/Http/Controllers/DashboardController.php` y `resources/js/pages/Dashboard.vue`: dashboard general por rol.
- `app/Http/Controllers/Admin/ReportController.php` y `resources/js/pages/Oficina/Reports.vue`: reportes agregados y export CSV.
- `tests/Feature/DashboardOverviewTest.php` y `tests/Feature/Admin/OfficeReportsTest.php`.
- `docs/operations/OBSERVABILIDAD-OPERATIVA.md`, `BACKUP-RESTORE.md`, `RUNBOOK-RELEASE-ROLLBACK.md`.
- `tests/Feature/Console/AuditHistoricalDataCommandTest.php` y `OpsBackupRestoreCommandTest.php`.

---

## Pendientes Vigentes

### P1-NOTIF-01 - Unificar estrategia de notificaciones programadas

Estado: pendiente vigente.

Problema:
- Hay dos implementaciones para schedules vencidos: `app/Console/Commands/NotifyWindows.php` y `app/Jobs/SendScheduledNotificationsJob.php`.
- El scheduler ejecuta solo el comando (`routes/console.php`), mientras el job conserva otra logica y otros textos.

Riesgo:
- Divergencia futura en filtros, tipos de notificacion, textos, relacion de entidad y logs.

Referencias concretas:
- `routes/console.php`
- `app/Console/Commands/NotifyWindows.php`
- `app/Jobs/SendScheduledNotificationsJob.php`
- `app/Services/NotificationService.php`
- `tests/Feature/Console/NotifyWindowsCommandTest.php`

Criterio de cierre:
- Elegir una sola ruta operativa: comando que delega al job/servicio, o eliminar el job si no sera usado.
- Mantener un unico set de pruebas para schedules vencidos, no duplicado.

### P1-FILE-01 - Reducir heuristica carpeta -> rubro de evidencia

Estado: pendiente vigente.

Problema:
- `FileController::store()` infiere el `EvidenceItem` desde el nombre de carpeta.
- `matchFolderToEvidenceItem()` usa un mapa de palabras clave, por lo que renombres o nuevas carpetas pueden asociar archivos al rubro incorrecto.

Riesgo:
- Evidencias cargadas desde File Manager pueden quedar vinculadas a un rubro distinto al esperado si el nombre de carpeta cambia.

Referencias concretas:
- `app/Http/Controllers/FileController.php`
- `app/Services/FolderStructureService.php`
- `tests/Feature/Security/FileManagerUploadWorkflowTest.php`

Criterio de cierre:
- Persistir una referencia explicita de rubro en carpeta/metadato o resolverla desde la estructura generada, no por texto visible.
- Cubrir con prueba de carpeta renombrada o alias institucional.

### P2-APPLIES-01 - Activar `applies_condition` en UI/flujo vivo

Estado: pendiente vigente.

Problema:
- `evidence_requirements.applies_condition` existe y se clona entre semestres, pero no hay UI viva ni regla de evaluacion operacional visible.

Riesgo:
- Reglas de aplicabilidad avanzadas quedan como dato pasivo y pueden confundirse con una funcionalidad implementada.

Referencias concretas:
- `database/migrations/2026_02_27_120005_create_requirements_storage_tables.php`
- `app/Models/EvidenceRequirement.php`
- `app/Http/Controllers/Admin/SemesterController.php`
- `resources/js/pages/Admin/Requirements/Matrix.vue`
- `tests/Feature/Admin/EvidenceApplicabilityWorkflowTest.php`

Criterio de cierre:
- Definir condiciones soportadas, mostrar/editar desde matriz y aplicar en `EvidenceFlowService` o en el generador de tareas.

### P2-ADVISORY-01 - Resolver alcance real de `AdvisoryFile`

Estado: pendiente vigente condicionado.

Problema:
- Existen tabla/modelo de `advisory_files`, pero el flujo actual de `/docente/asesorias` trabaja con horarios semanales (`AdvisorySchedule`) y no expone un flujo vivo de adjuntos de asesorias.
- Documentos antiguos mencionaban descarga publica por `/storage/...`; en el codigo actual no se encontro consumo vivo desde `Docente/MyAdvisories.vue`.

Riesgo:
- Si se reactiva carga/descarga de adjuntos de asesorias, debe hacerse con controlador y policy, no con URLs publicas.

Referencias concretas:
- `app/Models/AdvisoryFile.php`
- `app/Models/AdvisorySession.php`
- `app/Http/Controllers/Teacher/AdvisorySessionController.php`
- `resources/js/pages/Docente/MyAdvisories.vue`
- `database/migrations/2026_02_27_120007_create_audit_advisory_tables.php`

Criterio de cierre:
- Confirmar si adjuntos de asesorias siguen en alcance funcional.
- Si siguen, crear endpoints autorizados de preview/download y pruebas de acceso.
- Si no siguen, documentar el modelo/tabla como legado o removerlos en una limpieza planificada.

### P3-DOCS-01 - Limpiar documentos historicos que ya no son backlog

Estado: pendiente vigente.

Problema:
- Hay documentos historicos que aun contienen diagnosticos anteriores o notas de handoff.
- No deben usarse como backlog vigente sin validar contra codigo.

Referencias concretas:
- `docs/HANDOFF-COMPANERO.md`
- `docs/DIAGNOSTICO-ESTADO-REAL-Y-PLAN-ADJUNTOS-ASESORIAS-2026-04-18.md`
- `docs/legacy-notes/`

Criterio de cierre:
- Marcar cada documento como historico, migrar solo decisiones vigentes a `PROJECT_STATUS.md` o a docs operativas, y evitar duplicar backlog.

---

## Validacion Tecnica Recomendada

Por ser cambio documental no se requiere suite completa, pero para confirmar el estado descrito:

```bash
composer test:critical
composer test:domain
npm run lint:check
npm run build
```

Para verificar las piezas citadas de forma mas focalizada:

```bash
php artisan test tests/Feature/Security/AsesoriasReviewAuthorizationTest.php tests/Feature/Security/FileManagerUploadWorkflowTest.php tests/Feature/Teacher/EvidenceInitializationTest.php tests/Feature/Admin/SubmissionWindowOverlapValidationTest.php tests/Feature/Console/NotifyWindowsCommandTest.php
```

---

## Fuente de Verdad Operativa

- Codigo actual y rutas activas tienen prioridad sobre documentos historicos.
- `docs/planes-implementacion/` queda como registro de planes cerrados, no como backlog vivo.
- `docs/testing/DOMINIO-MATRIZ-COBERTURA.md` resume cobertura de reglas institucionales.
- `docs/operations/` resume runbooks operativos vigentes.

---

**Ultima actualizacion**: 2026-05-18
