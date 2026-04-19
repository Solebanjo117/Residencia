# HANDOFF TECNICO DEL PROYECTO RESIDENCIA

## 1. Resumen ejecutivo

`Residencia` es un sistema institucional para gestion de evidencia docente, revision administrativa, asesorias y organizacion documental por semestre. El sistema esta pensado para operar con persistencia real, reglas de estado, roles institucionales y trazabilidad, no como una demo visual aislada.

Hoy el proyecto ya tiene una base funcional amplia:
- autenticacion tradicional con Laravel Fortify, 2FA y login social con Google via Socialite
- dashboards por rol
- flujo docente de evidencias con inicializacion, carga, envio, historial y estados
- seguimiento docente unificado en `/asesorias`
- revision de oficina y visto bueno final de jefatura
- file manager con arbol por semestre/docente, preview PDF/imagen y editor DOCX versionado
- administracion de semestres, ventanas, docentes, cargas, departamentos y matriz de evidencias
- reportes y auditoria
- notificaciones internas y tareas programadas

Estado general real del repo:
- base operativa: solida
- reglas institucionales centrales: mayormente implementadas
- UX y consistencia textual: mixtas
- automatizacion y algunos acoplamientos de legado: aun requieren seguimiento

## 2. Objetivo funcional del sistema

El dominio funcional del proyecto se centra en:
- control de evidencias docentes por semestre, carga docente y rubro institucional
- definicion de que evidencias aplican por semestre/departamento mediante matriz
- carga de archivos, sustitucion, versionado y consulta historica
- revision de evidencias por `JEFE_OFICINA`
- visto bueno final por `JEFE_DEPTO`
- gestion de ventanas de entrega con apertura, cierre y carga extemporanea
- seguimiento docente unificado en forma de tabla administrativa
- registro y consulta de asesorias docentes
- file manager con estructura documental institucional por semestre
- reportes por docente y auditoria de acciones relevantes
- notificaciones operativas para ventanas y cambios de flujo
- autenticacion y autorizacion por rol

## 3. Stack tecnologico real detectado

Backend:
- PHP `^8.2` (`composer.json`)
- Laravel `^12.0`
- Laravel Fortify `^1.30`
- Laravel Socialite `*`
- Inertia Laravel `^2.0`
- Wayfinder `^0.1.11`
- Ziggy `^2.5`

Frontend:
- Vue `^3.5.13`
- TypeScript
- Inertia Vue `^2.0.0`
- Tailwind CSS `^4.0.9`
- `reka-ui`
- `lucide-vue-next`
- `xlsx`
- Vite `^7.1.3`

Calidad, testing y tooling:
- Pest `^3.8`
- PHPUnit `^11.5.3`
- Laravel Pint `^1.24`
- Playwright `^1.55.0`
- ESLint + Prettier

Configuracion relevante:
- `vite.config.ts` habilita SSR, Tailwind v4 y plugin Wayfinder
- `phpunit.xml` define suites `Unit`, `Domain` y `Feature`
- `playwright.config.ts` arranca `php artisan serve` para pruebas e2e

## 4. Arquitectura general

### Backend

La app sigue una arquitectura Laravel clasica con capas diferenciadas:
- rutas en `routes/web.php` y `routes/console.php`
- controladores HTTP en `app/Http/Controllers/*`
- logica de negocio transversal en `app/Services/*`
- autorizacion fina en `app/Policies/*`
- modelos Eloquent en `app/Models/*`
- enums de estado en `app/Enums/*`
- comandos/jobs para automatizacion en `app/Console/Commands/*` y `app/Jobs/*`

Patrones observados:
- Fortify maneja el auth tradicional y 2FA desde `app/Providers/FortifyServiceProvider.php`
- Socialite entra como flujo paralelo en `app/Http/Controllers/Auth/SocialAuthController.php`
- `EvidenceService` actua como nucleo del workflow institucional de evidencias
- `EvidenceFlowService` concentra reglas de disponibilidad, etapas y mapeo a estado UI
- `StorageService` centraliza validacion, persistencia y versionado de archivos
- `FolderStructureService` materializa la estructura institucional de carpetas

### Frontend

El frontend usa Inertia + Vue 3 + TypeScript:
- layouts principales en `resources/js/layouts/AppLayout.vue` y `resources/js/layouts/AuthLayout.vue`
- paginas en `resources/js/pages/**/*`
- menu por rol en `resources/js/config/menu.ts`
- auth compartido por Inertia desde `app/Http/Middleware/HandleInertiaRequests.php`
- componentes UI reutilizables en `resources/js/components/*`

### Flujo Laravel + Inertia + Vue

Patron dominante:
1. Laravel resuelve la ruta y prepara datos del dominio.
2. El controlador renderiza una pagina Inertia con props.
3. Vue pinta tabla/formulario/editor.
4. Las mutaciones vuelven al backend via `router`, `useForm` o enlaces Inertia.
5. Las policies, middleware y servicios garantizan reglas de negocio y autorizacion.

### Policies y middleware

Middleware de rol:
- `bootstrap/app.php` registra el alias `role`
- `app/Http/Middleware/CheckRole.php` bloquea acceso por rol de forma simple

Policies clave:
- `app/Policies/EvidenceSubmissionPolicy.php`
- `app/Policies/EvidenceFilePolicy.php`
- `app/Policies/FolderNodePolicy.php`
- `app/Policies/AdvisorySessionPolicy.php`

## 5. Mapa de modulos

### Autenticacion
- Proposito: login tradicional por correo/password, registro, reset, verificacion y settings.
- Rutas: Fortify + `routes/settings.php` + `routes/web.php`.
- Backend: `app/Providers/FortifyServiceProvider.php`, `app/Models/User.php`, `config/auth.php`.
- Frontend: `resources/js/pages/auth/*`, `resources/js/pages/settings/*`.
- Services/Policies: Fortify nativo; `User::is_active` se valida al autenticar.
- Estado actual: `funcional`.

### Login social
- Proposito: permitir acceso con Google sin romper el login tradicional.
- Rutas: `/auth/{provider}/redirect`, `/auth/{provider}/callback`.
- Backend: `app/Http/Controllers/Auth/SocialAuthController.php`, `app/Services/Auth/SocialAuthenticationService.php`, `app/Support/Auth/SocialProviderRegistry.php`, `app/Models/SocialAccount.php`.
- Frontend: `resources/js/pages/auth/Login.vue`.
- Servicios/Policies: no usa policy propia; depende de `SocialProviderRegistry` + Fortify post-login.
- Estado actual: `funcional`, pero el boton solo aparece si `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` y `GOOGLE_REDIRECT_URI` estan configurados.

### 2FA
- Proposito: challenge de segundo factor sobre login tradicional y social.
- Rutas/Vistas: Fortify `two-factor-challenge`, `resources/js/pages/auth/TwoFactorChallenge.vue`.
- Backend: `app/Providers/FortifyServiceProvider.php`, modelo `User` con campos de 2FA.
- Estado actual: `funcional`.

### Dashboard
- Proposito: panel principal por rol con metricas, acciones rapidas y proximos cierres.
- Rutas: `/dashboard`, `/docente/dashboard`.
- Backend: `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/Teacher/DashboardController.php`.
- Frontend: `resources/js/pages/Dashboard.vue`, `resources/js/pages/Teacher/Dashboard.vue`.
- Services/Policies: `EvidenceFlowService`.
- Estado actual: `funcional`, aunque hay duplicidad parcial entre dashboard general y dashboard docente.

### Evidencias de docente
- Proposito: inicializar entregas, cargar archivos, revisar disponibilidad, enviar a revision y consultar historial.
- Rutas: `/docente/evidencias`, `/docente/evidencias/init`, `/docente/evidencias/{submission}/upload`, `/docente/evidencias/{submission}/submit`.
- Backend: `app/Http/Controllers/Teacher/EvidenceController.php`.
- Frontend: `resources/js/pages/Teacher/Evidencias/Index.vue`.
- Services/Policies: `EvidenceFlowService`, `EvidenceService`, `StorageService`, `EvidenceSubmissionPolicy`, `EvidenceFilePolicy`.
- Estado actual: `funcional`.

### Asesorias
- Proposito: registrar sesiones de asesoria por docente y consultar horario institucional.
- Rutas: `/docente/asesorias`, `/asesorias-horarios`.
- Backend: `app/Http/Controllers/Teacher/AdvisorySessionController.php`, `app/Http/Controllers/AdvisoryScheduleController.php`.
- Frontend: `resources/js/pages/Docente/MyAdvisories.vue`, `resources/js/pages/Asesorias/Index.vue`.
- Services/Policies: `AdvisoryService`, `AdvisorySessionPolicy`.
- Estado actual: `funcional`, con una observacion importante: los adjuntos de asesorias se sirven via `/storage/...` desde `public`, no via controlador/policy.

### Revision de oficina
- Proposito: concentrar envios `SUBMITTED` y permitir aprobacion/rechazo.
- Rutas: `/oficina/revisiones`, `/oficina/revisiones/{submission}`, `/oficina/revisiones/{submission}/status`.
- Backend: `app/Http/Controllers/Admin/ReviewController.php`.
- Frontend: `resources/js/pages/Oficina/PendingReviews.vue`, `resources/js/pages/Oficina/ReviewDetail.vue`.
- Services/Policies: `EvidenceService`, `EvidenceSubmissionPolicy`.
- Estado actual: `funcional`.

### Administracion
- Proposito: CRUDs y configuracion institucional compartida entre `JEFE_OFICINA` y `JEFE_DEPTO`.
- Rutas: `/admin/departments`, `/admin/teachers`, `/admin/teaching-loads`, `/admin/requirements`, `/admin/semesters`, `/admin/windows`, `/admin/audits`.
- Backend: `DepartmentController`, `TeacherController`, `TeachingLoadController`, `RequirementController`, `SemesterController`, `SubmissionWindowController`, `AuditController`.
- Frontend: `resources/js/pages/Admin/*`.
- Services/Policies: `TeacherService`, `FolderStructureService`.
- Estado actual: `funcional` en CRUD base; `RequirementController` sigue limitado a presencia + `is_mandatory`, sin UI para `applies_condition`.

### File Manager
- Proposito: navegar carpetas institucionales, subir, reemplazar, borrar, descargar y agrupar semestres activos/no activos.
- Rutas: `/files/manager`, `/files/folders/{folder}`, `/files/folders/{folder}/upload`, `/files/{file}/replace`, `/files/{file}`, `/files/{file}/download`, `/files/{file}/preview`.
- Backend: `app/Http/Controllers/FolderController.php`, `app/Http/Controllers/FileController.php`.
- Frontend: `resources/js/pages/FileManager/Index.vue`, `resources/js/components/FileManager/FolderTree.vue`.
- Services/Policies: `StorageService`, `FolderStructureService`, `FolderNodePolicy`, `EvidenceFilePolicy`.
- Estado actual: `funcional`.

### Editor DOCX
- Proposito: abrir y editar `.docx` dentro del sistema con versionado seguro.
- Rutas: `/files/{file}/docx` (GET/POST).
- Backend: `app/Http/Controllers/DocxEditorController.php`, `app/Services/DocxEditorService.php`.
- Frontend: `resources/js/pages/FileManager/DocxEditor.vue`.
- Services/Policies: `StorageService`, `EvidenceFilePolicy`.
- Estado actual: `parcial funcional` (MVP real, no editor Word completo).

### Preview/descarga de archivos
- Proposito: ver PDF/imagenes inline y descargar archivos con auditoria.
- Rutas: `/files/{file}/preview`, `/files/{file}/download`.
- Backend: `FileController::preview()` y `FileController::download()`.
- Frontend: modal de preview en `resources/js/pages/FileManager/Index.vue`.
- Estado actual: `funcional`.

### Notificaciones
- Proposito: campana de notificaciones, envio inmediato y programacion de avisos de ventanas.
- Rutas: `/api/notifications`, `/api/notifications/read/{id?}`.
- Backend: `app/Http/Controllers/NotificationController.php`, `app/Services/NotificationService.php`, `app/Console/Commands/NotifyWindows.php`, `app/Jobs/SendScheduledNotificationsJob.php`.
- Frontend: `resources/js/components/NotificationBell.vue`.
- Estado actual: `parcial`. La campana funciona; la automatizacion existe por comando scheduler y por job, pero hay duplicidad conceptual.

### Auditoria
- Proposito: bitacora persistente de acciones de negocio y operaciones.
- Rutas: `/admin/audits`.
- Backend: `app/Http/Controllers/Admin/AuditController.php`, `app/Services/AuditService.php`, comando `asad:audit-historical-data`.
- Frontend: `resources/js/pages/Admin/AuditLogs.vue`.
- Estado actual: `funcional`.

### Reportes
- Proposito: consolidado docente por semestre con export CSV y filtros operativos.
- Rutas: `/oficina/reportes`.
- Backend: `app/Http/Controllers/Admin/ReportController.php`.
- Frontend: `resources/js/pages/Oficina/Reports.vue`.
- Estado actual: `funcional`.

### Ventanas de entrega
- Proposito: definir calendarios por semestre/rubro y evitar solapamientos activos.
- Rutas: `/admin/windows`.
- Backend: `app/Http/Controllers/Admin/SubmissionWindowController.php`.
- Frontend: `resources/js/pages/Admin/Windows/Index.vue`.
- Services/Policies: `EvidenceFlowService`.
- Estado actual: `funcional`.

### Estructura de carpetas
- Proposito: crear y mantener arbol institucional por semestre y docente.
- Rutas relacionadas: `/admin/teachers/{teacher}/generate-folders`, alta/apertura de semestre, alta de carga docente.
- Backend: `app/Services/FolderStructureService.php`, `TeacherController`, `SemesterController`, `TeachingLoadController`.
- Estado actual: `funcional`.

### Seguimiento docente
- Proposito: tablero consolidado tipo administrativa para visualizar rubros por carga y operar revision/aplicabilidad.
- Rutas: `/asesorias`, `/asesorias/{submission}/review`, `/asesorias/{submission}/final-approval`, `/asesorias/cells/status`.
- Backend: `app/Http/Controllers/Admin/AdvisoryController.php`.
- Frontend: `resources/js/pages/SeguimientoDocente.vue`.
- Services/Policies: `EvidenceFlowService`, `EvidenceService`, `EvidenceSubmissionPolicy`.
- Estado actual: `funcional`.

## 6. Roles y permisos

### DOCENTE

Puede:
- autenticarse y usar dashboard general y dashboard docente
- ver solo sus cargas y sus evidencias
- inicializar submissions, cargar archivos, enviar, descargar sus archivos
- operar solo carpetas propias en file manager
- registrar y eliminar sus propias asesorias
- editar un DOCX solo si puede reemplazar el archivo segun estado/policy

No puede:
- revisar evidencias de otros
- aprobar por oficina
- registrar visto bueno final
- entrar a `/oficina/*` o `/admin/*`

Puntos sensibles:
- `EvidenceSubmissionPolicy::update`
- `EvidenceFilePolicy::replace/delete`
- `FolderNodePolicy::view/upload`

### JEFE_OFICINA

Puede:
- ver el seguimiento global
- revisar evidencias `SUBMITTED`
- aprobar/rechazar y generar unlocks
- consultar reportes
- acceder a auditoria
- ver y operar todo el file manager
- administrar docentes, departamentos, cargas, semestres, requirements y ventanas via `/admin/*`

No puede:
- registrar visto bueno final en endpoints de seguimiento

Puntos sensibles:
- `EvidenceSubmissionPolicy::review`
- `Admin/ReviewController` y `Admin/AdvisoryController::reviewEvidence`

### JEFE_DEPTO

Puede:
- administrar semestres, ventanas, requirements, docentes, departamentos y cargas
- ver seguimiento docente
- registrar visto bueno final cuando una submission ya fue aprobada por oficina
- marcar `NA` o reactivar celdas en seguimiento
- ver y operar todo el file manager
- consultar asesorias segun alcance de departamentos

No puede:
- usar el endpoint de revision de oficina en `/asesorias/{submission}/review`

Puntos sensibles:
- `EvidenceSubmissionPolicy::finalApprove`
- `EvidenceSubmissionPolicy::markAsNA`

### Middleware, zonas compartidas y autorizacion

Middleware de rutas:
- `/docente/*` usa `role:DOCENTE`
- `/oficina/*` usa `role:JEFE_OFICINA`
- `/depto/*` usa `role:JEFE_DEPTO` pero hoy redirige a `/admin/*`
- `/admin/*` usa `role:JEFE_OFICINA,JEFE_DEPTO`
- `/files/*` es `auth` + policy

Zonas compartidas:
- `/dashboard`
- `/files/manager`
- `/asesorias`
- `/asesorias-horarios`

Puntos sensibles de autorizacion:
- revision y final approval en seguimiento
- replace/delete/upload de archivos segun estado de submission
- acceso a carpetas de file manager
- adjuntos de asesorias, porque hoy salen por `/storage/...`

## 7. Flujo de evidencias

### Resumen del flujo

1. La matriz de `evidence_requirements` define que rubros aplican por semestre/departamento.
2. `Teacher/EvidenceController::index()` arma tareas por carga docente + requirement.
3. El docente puede inicializar una entrega (`/docente/evidencias/init`) cuando `EvidenceFlowService` la considera disponible.
4. La carga de archivos puede hacerse desde `Mis Evidencias` o desde `File Manager`.
5. El docente envia la evidencia y `EvidenceService::changeStatus()` la pasa a `SUBMITTED`.
6. `JEFE_OFICINA` revisa y decide `APPROVED` o `REJECTED`.
7. Si aprueba oficina, `JEFE_DEPTO` puede registrar el visto bueno final.
8. Todos los cambios dejan historial en `evidence_status_history`; las revisiones dejan registros en `evidence_reviews`.

### Inicializacion

Archivo clave: `app/Http/Controllers/Teacher/EvidenceController.php`

- `initSubmission()` crea `evidence_submissions` en `DRAFT`
- exige que el docente sea duenio de la carga
- valida que el rubro exista en su matriz activa
- usa `EvidenceFlowService::resolveAvailability()` y `isStageUnlocked()`

### Subida de archivos

Dos caminos:

1. `Teacher/EvidenceController::storeFile()`
- exige submission en `DRAFT` o `REJECTED`
- valida ventana/etapa
- usa `StorageService::storeEvidence()`

2. `FileController::store()`
- parte desde una carpeta del file manager
- resuelve heuristica carpeta -> `EvidenceItem`
- crea o reutiliza submission
- para docentes, respeta estado/policy
- si no hay ventana configurada, `fileManagerAvailability()` crea el estado especial `FILE_MANAGER_DRAFT`: permite cargar en borrador sin enviar
- oficina/depto pueden bypass de disponibilidad

### Envio

`Teacher/EvidenceController::submit()`
- solo permite envio desde `DRAFT` o `REJECTED`
- requiere al menos un archivo
- resuelve disponibilidad con `EvidenceFlowService`
- marca `submitted_late` si aplica
- delega el cambio a `EvidenceService::changeStatus()`

### Revision y aprobacion

`EvidenceService::review()`
- crea `EvidenceReview` stage `OFFICE`
- cambia status a `APPROVED` o `REJECTED`
- rellena `office_reviewed_at` y `office_reviewed_by_user_id` al aprobar
- notifica al docente

`EvidenceService::finalApprove()`
- crea `EvidenceReview` stage `FINAL`
- no cambia el enum de status; usa `final_approved_at` y `final_approved_by_user_id`
- deja trazabilidad separada de oficina vs jefatura

### Rechazo y reenvio

- `REJECTED` queda habilitado para nueva carga y envio
- `EvidenceService::unlockForResubmission()` crea `resubmission_unlocks`
- `ReviewController::updateStatus()` crea unlock automatico de 3 dias al rechazar via pantalla de oficina

### Historial de estados

`EvidenceService::changeStatus()` registra:
- `old_status`
- `new_status`
- `changed_by_user_id`
- `change_reason`
- `changed_at`

### Maquina de estados

Fuente: `app/Services/EvidenceService.php`

`ALLOWED_TRANSITIONS` actual:
- `DRAFT -> SUBMITTED, NA, NE`
- `SUBMITTED -> APPROVED, REJECTED, NA, NE`
- `APPROVED ->` ninguna transicion directa
- `REJECTED -> SUBMITTED, NA, NE`
- `NA -> DRAFT`
- `NE -> DRAFT`

### Relacion con ventanas y etapas

Fuente: `app/Services/EvidenceFlowService.php`

Conceptos actuales:
- `OPEN`: ventana abierta
- `LATE`: fuera de ventana regular pero disponible como extemporanea
- `UNLOCKED`: habilitada por unlock
- `UPCOMING`: futura
- `STAGE_LOCKED`: bloqueada por secuencia de etapas
- `NOT_CONFIGURED`: no hay ventana configurada
- `HISTORICAL`: semestre no activo; solo consulta
- `NA`: no aplica

`stageOrder()` hoy se infiere por nombre de item:
- horario / instrum -> etapa 0
- diagnostica -> etapa 1
- seguimientos / SD2 / SD4 / final -> etapas posteriores

Observacion:
- la secuencia existe, pero es heuristica por nombre; no esta modelada explicitamente en BD.

## 8. Modelo de datos y entidades importantes

### Usuarios, roles y departamentos
- `users` tiene `role_id`, `is_active` y campos Fortify/2FA
- `roles` define `DOCENTE`, `JEFE_OFICINA`, `JEFE_DEPTO`
- `departments` se relaciona con `users` via `user_department`
- un docente puede estar asociado a varios departamentos

### Semestres y periodos
- `academic_periods`: capa superior de planeacion academica
- `semesters`: unidad operativa principal del sistema
- solo puede haber un semestre `OPEN` a la vez; `Semester::active()` lo resuelve
- `SemesterController` puede clonar requirements y cargas desde el semestre mas reciente con datos

### Cargas docentes
- `teaching_loads` enlaza docente + semestre + materia + grupo
- la mayoria del dominio de evidencias y asesorias se apoya en `teaching_load_id`

### Evidencias
- `evidence_categories` agrupa rubros
- `evidence_items` define cada evidencia concreta
- `evidence_formats` existe como catalogo, pero el upload real hoy usa `config/evidence.php`
- `evidence_requirements` une semestre + item + departamento + obligatoriedad

### Submissions
- `evidence_submissions` es la entidad central del workflow
- identifica una entrega por `semester_id + teacher_user_id + evidence_item_id + teaching_load_id`
- guarda status, timestamps de envio, extemporaneidad y aprobaciones

### Reviews
- `evidence_reviews` guarda revisiones de oficina/final
- ahora tiene columna `stage` (`OFFICE`, `FINAL`)

### Historial
- `evidence_status_history` guarda la trazabilidad de transiciones

### Ventanas
- `submission_windows` define apertura/cierre por semestre + evidencia

### Archivos
- `folder_nodes` modela el arbol de carpetas
- `evidence_files` guarda metadata, path, hash, uploader y versionado DOCX
- `storage_roots` define la raiz de almacenamiento activa

### Notificaciones
- `notifications` para campana y avisos
- `notification_schedules` para programacion de ventanas

### Asesorias
- `advisory_sessions` y `advisory_files`

### Social auth
- `social_accounts` enlaza usuarios locales con proveedores externos

## 9. Frontend actual

### Organizacion

- paginas Inertia en `resources/js/pages/**/*`
- layouts en `resources/js/layouts/*`
- menu por rol en `resources/js/config/menu.ts`
- UI compartida con componentes de `resources/js/components/*`

### Pantallas principales por rol

Docente:
- `resources/js/pages/Dashboard.vue` (general)
- `resources/js/pages/Teacher/Dashboard.vue`
- `resources/js/pages/Teacher/Evidencias/Index.vue`
- `resources/js/pages/Docente/MyAdvisories.vue`
- `resources/js/pages/FileManager/Index.vue`
- `resources/js/pages/FileManager/DocxEditor.vue`

Jefe de oficina:
- `resources/js/pages/Oficina/PendingReviews.vue`
- `resources/js/pages/Oficina/ReviewDetail.vue`
- `resources/js/pages/Oficina/Reports.vue`
- `resources/js/pages/SeguimientoDocente.vue`

Jefe de departamento:
- `resources/js/pages/Admin/Semesters/Index.vue`
- `resources/js/pages/Admin/Requirements/Matrix.vue`
- `resources/js/pages/Admin/Windows/Index.vue`
- `resources/js/pages/Admin/Teachers/Index.vue`
- `resources/js/pages/Admin/TeachingLoads/Index.vue`
- `resources/js/pages/SeguimientoDocente.vue`

### Pantallas incompletas o con observaciones

- `resources/js/pages/Teacher/Dashboard.vue`: funcional, pero duplicada conceptualmente respecto al dashboard general y con UI mas antigua
- `resources/js/pages/Docente/MyAdvisories.vue`: funcional, pero los adjuntos salen por `/storage/...` y el texto tiene mojibake
- varias pantallas mantienen mezcla ES/EN y algunos problemas de encoding

### Pantallas clave a revisar primero si alguien va a tocar negocio

1. `resources/js/pages/SeguimientoDocente.vue`
2. `resources/js/pages/Teacher/Evidencias/Index.vue`
3. `resources/js/pages/FileManager/Index.vue`
4. `resources/js/pages/FileManager/DocxEditor.vue`
5. `resources/js/pages/Oficina/ReviewDetail.vue`
6. `resources/js/pages/Dashboard.vue`

## 10. Sistema de archivos y documentos

### File Manager

Controlado por:
- `app/Http/Controllers/FolderController.php`
- `app/Http/Controllers/FileController.php`
- `app/Services/StorageService.php`
- `app/Policies/FolderNodePolicy.php`
- `app/Policies/EvidenceFilePolicy.php`

Caracteristicas actuales:
- agrupa visualmente en `Semestres activos` y `Semestres no activos`
- docente ve solo su arbol
- oficina/depto ven todo
- subir, reemplazar, eliminar, descargar
- preview inline para PDF/imagen
- acceso con auditoria en download/preview

### Preview PDF

- se sirve via `FileController::preview()`
- usa `response()->file(...)` con `Content-Disposition: inline`
- se embebe en `iframe` dentro de `resources/js/pages/FileManager/Index.vue`

### DOCX

Rutas:
- `GET /files/{file}/docx`
- `POST /files/{file}/docx`

Capacidades del MVP actual:
- abrir `.docx` dentro de la app
- editar cuerpo
- editar encabezado y pie solo si el archivo ya los trae
- conservar y reescribir tipografia explicita, negritas, cursivas, subrayado, listas simples, tablas simples, imagenes incrustadas, alineacion/sangria/espaciado basico
- guardar como nueva revision segura
- historial de versiones dentro del mismo editor

Lo que no hace hoy:
- no crea encabezados/pies nuevos si el docx no los tiene
- no maneja layout avanzado de Word, comentarios nativos, control de cambios, SmartArt, macros, tablas complejas o multiples secciones sofisticadas

### Riesgos y limitaciones

- `DocxEditorService` es extenso y delicado; cualquier cambio puede afectar compatibilidad binaria del `.docx`
- `FileController::matchFolderToEvidenceItem()` sigue siendo heuristico por nombre de carpeta
- `FolderStructureSeeder` borra `evidence_files` y `folder_nodes` al reconstruir; es util en desarrollo, peligroso si alguien lo corre sin entender el impacto
- los adjuntos de asesorias no usan el mismo flujo seguro del file manager

## 11. Notificaciones y automatizacion

### Lo que existe

Frontend/API:
- campana de notificaciones en `resources/js/components/NotificationBell.vue`
- endpoints `NotificationController::getUnread()` y `markAsRead()`

Backend:
- `NotificationService::notifyImmediate()`
- `NotificationService::schedule()`
- comando `notify:windows`
- job `SendScheduledNotificationsJob`
- scheduler en `routes/console.php`:
  - `notify:windows` cada 5 minutos
  - `ops:backup --name=auto` diario a las 02:00

Otros comandos operativos:
- `asad:audit-historical-data`
- `ops:backup`
- `ops:restore`

### Que parece a medias

- hoy conviven dos mecanismos para dispatch de schedules: `NotifyWindows` y `SendScheduledNotificationsJob`
- el scheduler usa el comando, no el job
- eso no rompe el sistema, pero deja duplicidad de estrategia

### Riesgos operativos

- si alguien cambia la logica de ventanas, debe revisar tanto `NotifyWindows` como `NotificationService` y pruebas de consola
- `ops:restore` es intrusivo sobre sqlite y `storage/app`; debe usarse con mucho cuidado

## 12. Pruebas, build y calidad

### Como correr el proyecto

Con Herd o servidor local:
```powershell
composer install
npm install
php artisan migrate --seed
npm run dev
```

Si no usas Herd:
```powershell
php artisan serve
```

### Como correr tests

Suite completa:
```powershell
php artisan test
```

Suite de dominio:
```powershell
composer test:domain
```

Regresion critica:
```powershell
composer test:critical
```

### Como correr build

```powershell
npm run build
```

### E2E

```powershell
npm run e2e:prepare
npm run e2e:install
npm run e2e
```

### Herramientas de calidad

- `composer lint` / `vendor/bin/pint`
- `npm run lint`
- `npm run format`

### Cobertura real observada en el repo

Buena cobertura en:
- auth, 2FA y social auth
- seguimiento
- file manager y seguridad de archivos
- flujo de estado de evidencias
- semestre activo unico
- reportes de oficina
- provisioning de carpetas
- comandos de auditoria/backup/notify
- editor DOCX

Cobertura menos fuerte en:
- CRUDs administrativos de UI (departamentos, docentes, teaching loads)
- comportamiento fino del frontend mas alla de smoke/e2e
- asesorias docente y sus adjuntos

Nota honesta:
- en esta transferencia no se reejecutaron `php artisan test` ni `npm run build`; el diagnostico se basa en codigo, rutas, pruebas existentes y el estado reciente del repositorio.

## 13. Estado actual real del proyecto

### Ya funcional

- auth tradicional con Fortify
- 2FA
- login social con Google listo para usar si el entorno esta configurado
- dashboard general y dashboard docente
- flujo docente de evidencias
- seguimiento docente consolidado
- aprobacion de oficina + visto bueno final
- reports de oficina
- auditoria
- ventanas de entrega con validacion anti-solapamiento
- semestre activo unico y default automatico
- visibilidad historica de semestres cerrados en seguimiento
- file manager con control por rol
- preview PDF/imagen
- editor DOCX con versionado
- agrupacion del arbol en semestres activos/no activos
- provision automatica de carpetas al abrir semestre

### Parcial o con alcance limitado

- `applies_condition` existe en modelo/migracion, pero no hay UI ni motor vivo para automatizar modalidad/NA condicional
- el editor DOCX es un MVP robusto, no un editor Word completo
- notificaciones programadas tienen implementacion funcional, pero duplicada entre comando y job
- `Teacher/Dashboard.vue` y `Dashboard.vue` se pisan parcialmente en proposito
- la matriz de evidencias no expone reglas mas complejas que presencia + obligatorio/opcional

### Pendiente o deuda tecnica visible

- normalizacion de idioma/encoding en varias vistas y mensajes
- endurecer y unificar el flujo de archivos adjuntos de asesorias
- reducir la heuristica carpeta -> evidencia en `FileController`
- decidir una sola estrategia para notificaciones programadas
- mejorar documentacion interna vieja para que no siga contradiciendo el codigo

### Partes delicadas

- `app/Services/DocxEditorService.php`
- `app/Services/EvidenceService.php`
- `app/Services/EvidenceFlowService.php`
- `app/Http/Controllers/FileController.php`
- `app/Services/FolderStructureService.php`
- `app/Http/Controllers/Admin/AdvisoryController.php`

## 14. Discrepancias entre documentacion y codigo

### `PROJECT_STATUS.md`

El documento dice:
- corte `2026-04-12`
- `php artisan test`: 41 pruebas
- dashboard aun placeholder
- `Oficina/Reports.vue` stub
- brechas criticas rojas en revision, transiciones, ventanas y file manager
- menciona duplicidad con `Asesorias.vue` y `Asesorias2.vue`

El codigo actual muestra:
- existe `app/Http/Controllers/DashboardController.php` y `resources/js/pages/Dashboard.vue` con metricas reales
- existe `app/Http/Controllers/Admin/ReportController.php` y `resources/js/pages/Oficina/Reports.vue` funcionales
- `resources/js/pages/Asesorias.vue` y `resources/js/pages/Asesorias2.vue` ya no existen
- hay muchas mas pruebas que las 41 reportadas; el arbol actual incluye suites de auth, dominio, seguridad, seguimiento, DOCX, consola y e2e
- Socialite ya esta integrado y versionado DOCX ya existe, cosas no mencionadas en ese corte

Estado vigente:
- el codigo actual invalida varias conclusiones de `PROJECT_STATUS.md`; hoy ese documento funciona mejor como fotografia historica del backlog que como estado real vigente.

### `MEMORY.md`

El documento dice:
- `Dashboard.vue` vacio placeholder
- `Oficina/Reports.vue` stub
- cambios de estado en asesorias no persisten
- notifications job nunca se dispatcha

El codigo actual muestra:
- dashboard principal y reports ya son modulos reales
- los cambios de estado en seguimiento se persisten via `EvidenceService`
- `routes/console.php` si programa `notify:windows`
- sigue existiendo duplicidad entre comando y job, pero no es correcto decir que todo esta desconectado

Estado vigente:
- `MEMORY.md` esta claramente desactualizado.

### `docs/planes-implementacion/*`

Los planes describen backlog y orden de trabajo al 2026-04-12. Muchos puntos ya se implementaron:
- baseline de ruta viva
- scheduler de notificaciones
- dashboard principal
- reportes
- consolidacion de asesorias
- observabilidad
- backup/restore
- hardening de archivos

Estado vigente:
- utiles como bitacora historica del roadmap, no como lista de pendientes exacta.

### `docs/legacy-notes/estructura-detectada-actualizada2.txt`

El archivo describe una estructura esperada de carpetas y requisitos de negocio historicos.

El codigo actual muestra:
- la estructura base si fue incorporada en `FolderStructureService`
- el requisito de docx editable y registro de cambios esta parcialmente cumplido con `DocxEditorService` + versionado en `evidence_files`
- varios textos del archivo son insumos de negocio, no estado actual del sistema

Estado vigente:
- referencia historica valida, no especificacion tecnica actual.

## 15. Prioridades recomendadas

### P0

#### 1. Blindar adjuntos de asesorias
- Problema: `resources/js/pages/Docente/MyAdvisories.vue` enlaza archivos via `/storage/{path}` y `AdvisorySessionController` guarda en disco `public`.
- Impacto: acceso menos controlado que el flujo de `EvidenceFile`; posible exposicion por URL si se conoce la ruta.
- Archivos involucrados: `app/Http/Controllers/Teacher/AdvisorySessionController.php`, `resources/js/pages/Docente/MyAdvisories.vue`.
- Recomendacion: mover advisory files a un controlador de descarga/preview con policy equivalente a `EvidenceFilePolicy`.

#### 2. Reducir heuristica folder -> evidence item
- Problema: `FileController::matchFolderToEvidenceItem()` depende de strings de carpeta.
- Impacto: cambios de naming pueden asociar archivos al rubro incorrecto.
- Archivos involucrados: `app/Http/Controllers/FileController.php`, `app/Services/FolderStructureService.php`.
- Recomendacion: modelar mapeo explicito entre nodos/rubros o persistir metadatos en `folder_nodes`.

### P1

#### 3. Unificar estrategia de notificaciones programadas
- Problema: conviven `NotifyWindows` y `SendScheduledNotificationsJob`.
- Impacto: deuda tecnica, duplicidad de mantenimiento y ambiguedad operativa.
- Archivos involucrados: `app/Console/Commands/NotifyWindows.php`, `app/Jobs/SendScheduledNotificationsJob.php`, `app/Services/NotificationService.php`, `routes/console.php`.
- Recomendacion: elegir un solo mecanismo y dejar el otro claramente deprecado o eliminado.

#### 4. Hacer viva la logica de `applies_condition`
- Problema: la columna existe, pero hoy la aplicabilidad avanzada se resuelve manualmente via `NA`.
- Impacto: el dominio aun no refleja modalidad/materia/NA condicional de forma automatizable.
- Archivos involucrados: `app/Models/EvidenceRequirement.php`, `app/Http/Controllers/Admin/RequirementController.php`, `resources/js/pages/Admin/Requirements/Matrix.vue`, `app/Services/EvidenceFlowService.php`.
- Recomendacion: definir primero un formato de condicion y luego exponer UI minima para capturarla.

### P2

#### 5. Normalizar idioma y encoding
- Problema: hay mojibake y mezcla ES/EN en varias vistas y seeders.
- Impacto: UX inconsistente y mantenimiento mas dificil.
- Archivos involucrados: `resources/js/pages/**/*`, `database/seeders/*`, algunos mensajes de servicios.
- Recomendacion: atacar por modulo, sin mezclar cambios de negocio.

#### 6. Revisar duplicidad entre dashboard general y dashboard docente
- Problema: ambos existen con metricas relacionadas, pero con contratos y estilo distintos.
- Impacto: duplicacion de concepto y potencial divergencia funcional.
- Archivos involucrados: `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/Teacher/DashboardController.php`, `resources/js/pages/Dashboard.vue`, `resources/js/pages/Teacher/Dashboard.vue`.
- Recomendacion: decidir si ambos viven con propositos distintos o si el docente deberia converger al dashboard general.

### P3

#### 7. Refinar UI de matriz y CRUD admin
- Problema: varias pantallas admin son funcionales pero visualmente dispares y sin cobertura fina de UX.
- Impacto: deuda de experiencia, no bloqueo tecnico.
- Archivos involucrados: `resources/js/pages/Admin/*`.
- Recomendacion: limpiar por tandas y acompanar con pruebas de humo o snapshots.

#### 8. Profundizar el editor DOCX solo despues de estabilizar su cobertura
- Problema: el MVP ya es potente y facil de romper.
- Impacto: riesgo alto de regresion documental si se refactoriza sin pruebas.
- Archivos involucrados: `app/Services/DocxEditorService.php`, `resources/js/pages/FileManager/DocxEditor.vue`, `tests/Feature/FileManager/DocxEditorWorkflowTest.php`.
- Recomendacion: cualquier mejora debe salir acompaniada de casos de round-trip adicionales.

## 16. Guia rapida para mi companero

### Por donde empezar a leer

Orden recomendado:
1. `routes/web.php`
2. `app/Services/EvidenceService.php`
3. `app/Services/EvidenceFlowService.php`
4. `app/Http/Controllers/Admin/AdvisoryController.php`
5. `app/Http/Controllers/Teacher/EvidenceController.php`
6. `app/Http/Controllers/FileController.php`
7. `app/Services/FolderStructureService.php`
8. `resources/js/pages/SeguimientoDocente.vue`
9. `resources/js/pages/Teacher/Evidencias/Index.vue`
10. `resources/js/pages/FileManager/Index.vue`
11. `resources/js/pages/FileManager/DocxEditor.vue`

### Modulos que hay que tocar con mas cuidado

- workflow de evidencias
- file manager
- editor DOCX
- semestre activo / bootstrap de semestre
- seguimiento docente

### Comandos utiles

```powershell
composer install
npm install
php artisan migrate --seed
npm run dev
php artisan test
composer test:domain
composer test:critical
npm run build
```

### Que no romper

- login tradicional Fortify
- reglas de rol de `/docente/*`, `/oficina/*`, `/admin/*`
- separacion entre aprobacion de oficina y visto bueno final
- versionado de `evidence_files`
- provision automatica de carpetas por semestre
- comportamiento historico de semestres cerrados en `/asesorias`

## 17. Indice de archivos importantes

### Auth y acceso
- `routes/web.php`
- `app/Providers/FortifyServiceProvider.php`
- `app/Http/Controllers/Auth/SocialAuthController.php`
- `app/Services/Auth/SocialAuthenticationService.php`
- `app/Support/Auth/SocialProviderRegistry.php`
- `app/Models/User.php`
- `app/Models/SocialAccount.php`
- `resources/js/pages/auth/Login.vue`

### Dominio de evidencias
- `app/Services/EvidenceService.php`
- `app/Services/EvidenceFlowService.php`
- `app/Models/EvidenceSubmission.php`
- `app/Models/EvidenceReview.php`
- `app/Models/EvidenceRequirement.php`
- `app/Models/EvidenceFile.php`
- `app/Policies/EvidenceSubmissionPolicy.php`
- `app/Policies/EvidenceFilePolicy.php`

### Seguimiento y revision
- `app/Http/Controllers/Admin/AdvisoryController.php`
- `app/Http/Controllers/Admin/ReviewController.php`
- `resources/js/pages/SeguimientoDocente.vue`
- `resources/js/pages/Oficina/PendingReviews.vue`
- `resources/js/pages/Oficina/ReviewDetail.vue`
- `resources/js/pages/Oficina/Reports.vue`

### Docente
- `app/Http/Controllers/Teacher/EvidenceController.php`
- `app/Http/Controllers/Teacher/AdvisorySessionController.php`
- `app/Http/Controllers/Teacher/DashboardController.php`
- `resources/js/pages/Teacher/Evidencias/Index.vue`
- `resources/js/pages/Teacher/Dashboard.vue`
- `resources/js/pages/Docente/MyAdvisories.vue`

### File manager y documentos
- `app/Http/Controllers/FolderController.php`
- `app/Http/Controllers/FileController.php`
- `app/Http/Controllers/DocxEditorController.php`
- `app/Services/StorageService.php`
- `app/Services/FolderStructureService.php`
- `app/Services/DocxEditorService.php`
- `app/Policies/FolderNodePolicy.php`
- `resources/js/pages/FileManager/Index.vue`
- `resources/js/pages/FileManager/DocxEditor.vue`
- `resources/js/components/FileManager/FolderTree.vue`

### Administracion
- `app/Http/Controllers/Admin/SemesterController.php`
- `app/Http/Controllers/Admin/SubmissionWindowController.php`
- `app/Http/Controllers/Admin/RequirementController.php`
- `app/Http/Controllers/Admin/TeacherController.php`
- `app/Http/Controllers/Admin/TeachingLoadController.php`
- `app/Http/Controllers/Admin/DepartmentController.php`
- `resources/js/pages/Admin/Semesters/Index.vue`
- `resources/js/pages/Admin/Windows/Index.vue`
- `resources/js/pages/Admin/Requirements/Matrix.vue`
- `resources/js/pages/Admin/Teachers/Index.vue`
- `resources/js/pages/Admin/TeachingLoads/Index.vue`
- `resources/js/pages/Admin/Departments/Index.vue`

### Auditoria, notificaciones y ops
- `app/Http/Controllers/Admin/AuditController.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Services/NotificationService.php`
- `app/Services/AuditService.php`
- `app/Console/Commands/NotifyWindows.php`
- `app/Console/Commands/AuditHistoricalData.php`
- `app/Console/Commands/OpsBackup.php`
- `app/Console/Commands/OpsRestore.php`
- `routes/console.php`
- `resources/js/pages/Admin/AuditLogs.vue`

### Config y pruebas
- `config/evidence.php`
- `config/services.php`
- `phpunit.xml`
- `playwright.config.ts`
- `tests/Feature/**/*`
- `tests/e2e/*`

