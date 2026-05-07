# PROMPT PARA CODEX COMPANERO

## A. Identidad de la tarea

Estas entrando a un proyecto existente llamado `Residencia`.

Tu trabajo debe hacerse **encima del codigo actual**, sin reescribir el sistema desde cero y sin cambiar el stack principal:
- Laravel 12
- PHP 8.2
- Fortify + 2FA
- Socialite
- Inertia.js v2
- Vue 3
- TypeScript
- Tailwind CSS v4

No elimines rutas, pantallas o modulos existentes solo porque haya una forma "mas limpia" de rehacerlos.

Antes de modificar cualquier cosa:
1. lee y valida el estado real del codigo
2. detecta impactos colaterales
3. entrega diagnostico corto + archivos a tocar + riesgos + plan
4. y solo despues propone implementacion

## B. Contexto tecnico real del proyecto

`Residencia` es un sistema institucional para:
- gestion de evidencias docentes por semestre y carga docente
- revision de evidencias por `JEFE_OFICINA`
- visto bueno final por `JEFE_DEPTO`
- estructura de carpetas por semestre/docente
- file manager con preview y versionado de archivos
- editor DOCX dentro del sistema
- asesorias docentes
- ventanas de entrega
- reportes y auditoria
- notificaciones y automatizacion operativa

### Roles
- `DOCENTE`
- `JEFE_OFICINA`
- `JEFE_DEPTO`

### Rutas clave
- `/dashboard`
- `/docente/dashboard`
- `/docente/evidencias`
- `/docente/asesorias`
- `/oficina/revisiones`
- `/oficina/reportes`
- `/admin/semesters`
- `/admin/windows`
- `/admin/requirements`
- `/admin/teachers`
- `/admin/teaching-loads`
- `/admin/departments`
- `/admin/audits`
- `/asesorias`
- `/asesorias-horarios`
- `/files/manager`
- `/files/{file}/docx`

### Arquitectura viva
- el auth tradicional entra por Fortify (`app/Providers/FortifyServiceProvider.php`)
- el login social entra por `app/Http/Controllers/Auth/SocialAuthController.php`
- el workflow de evidencias depende sobre todo de:
  - `app/Services/EvidenceService.php`
  - `app/Services/EvidenceFlowService.php`
  - `app/Http/Controllers/Teacher/EvidenceController.php`
  - `app/Http/Controllers/Admin/AdvisoryController.php`
- el file manager depende sobre todo de:
  - `app/Http/Controllers/FolderController.php`
  - `app/Http/Controllers/FileController.php`
  - `app/Services/StorageService.php`
  - `app/Services/FolderStructureService.php`
- el editor DOCX depende de:
  - `app/Http/Controllers/DocxEditorController.php`
  - `app/Services/DocxEditorService.php`

### Flujo real de evidencias
- la matriz `evidence_requirements` define los rubros por semestre/departamento
- el docente inicializa `evidence_submissions` en `DRAFT`
- puede subir archivos desde `Mis Evidencias` o desde el File Manager
- `EvidenceService::changeStatus()` controla transiciones y registra `evidence_status_history`
- `JEFE_OFICINA` revisa submissions `SUBMITTED`
- `JEFE_DEPTO` registra el visto bueno final
- `EvidenceReview` distingue `OFFICE` y `FINAL`

### Estados institucionales
- enum de submission: `DRAFT`, `SUBMITTED`, `APPROVED`, `REJECTED`, `NA`, `NE`
- transiciones vivas en `EvidenceService::ALLOWED_TRANSITIONS`
- disponibilidad UI en `EvidenceFlowService`:
  - `OPEN`
  - `LATE`
  - `UNLOCKED`
  - `UPCOMING`
  - `STAGE_LOCKED`
  - `NOT_CONFIGURED`
  - `HISTORICAL`
  - `NA`

### Seguimiento docente
- ruta viva: `/asesorias`
- backend: `app/Http/Controllers/Admin/AdvisoryController.php`
- frontend: `resources/js/pages/SeguimientoDocente.vue`
- ahi viven:
  - tabla administrativa por carga/rubro
  - export CSV/XLSX
  - impresion
  - detalle por celda
  - aprobacion de oficina
  - visto bueno final
  - marcado manual `NA`

### File Manager y archivos
- docente: solo su carpeta
- jefe oficina / jefe depto: acceso total
- PDF e imagenes: preview inline
- DOCX: editor propio con versionado en `evidence_files`
- el arbol visual se agrupa en:
  - `Semestres activos`
  - `Semestres no activos`
- cuidado: el mapeo carpeta -> `EvidenceItem` sigue siendo heuristico en `FileController`

### Semestres
- solo puede existir un semestre `OPEN` a la vez
- `Semester::activeOrLatest()` se usa como default en varias pantallas
- al abrir un semestre se cierran los otros abiertos
- `SemesterController` puede clonar requirements y teaching loads desde el semestre mas reciente con datos
- `FolderStructureService` provisiona carpetas al abrir semestre y al crear cargas

### Notificaciones y ops
- `NotificationController` atiende la campana
- `NotificationService` envia y agenda notificaciones
- `notify:windows` se ejecuta cada 5 minutos desde `routes/console.php`
- existen tambien:
  - `asad:audit-historical-data`
  - `ops:backup`
  - `ops:restore`
- hay duplicidad conceptual entre `NotifyWindows` y `SendScheduledNotificationsJob`

## C. Fuente de verdad

Usa esta prioridad de verdad:
1. codigo actual
2. rutas activas
3. controladores / servicios / policies / modelos
4. paginas frontend
5. pruebas
6. documentacion interna

No asumas que `PROJECT_STATUS.md` o `MEMORY.md` estan al dia.
De hecho, ambos documentos ya quedaron desfasados respecto al codigo actual.

Si encuentras conflicto entre docs y codigo:
- documentalo
- explica que documento dice que
- muestra que hace realmente el codigo
- y toma el codigo como estado vigente

## D. Reglas de trabajo

- Trabaja sobre el codigo existente.
- No simplifiques ni reinicies arquitectura sin justificacion fuerte.
- No cambies Laravel + Inertia + Vue 3 + TypeScript.
- No rompas Fortify, Socialite, rutas ni roles.
- No rompas el flujo de evidencias, el file manager ni el seguimiento.
- Antes de tocar algo, revisa controladores, servicios, policies y paginas afectadas.
- Identifica impacto colateral en:
  - policies
  - estado de submission
  - pruebas existentes
  - semestres activos/historicos
  - file manager y versionado
- Si el cambio toca workflow institucional o seguridad, explica riesgos antes de proponer refactor grande.

## E. Mapa de archivos clave

Empieza por leer:
- `docs/HANDOFF-COMPANERO.md`
- `routes/web.php`
- `app/Services/EvidenceService.php`
- `app/Services/EvidenceFlowService.php`
- `app/Http/Controllers/Admin/AdvisoryController.php`
- `app/Http/Controllers/Admin/ReviewController.php`
- `app/Http/Controllers/Teacher/EvidenceController.php`
- `app/Http/Controllers/FileController.php`
- `app/Http/Controllers/FolderController.php`
- `app/Services/StorageService.php`
- `app/Services/FolderStructureService.php`
- `app/Http/Controllers/DocxEditorController.php`
- `app/Services/DocxEditorService.php`
- `resources/js/pages/SeguimientoDocente.vue`
- `resources/js/pages/Teacher/Evidencias/Index.vue`
- `resources/js/pages/FileManager/Index.vue`
- `resources/js/pages/FileManager/DocxEditor.vue`
- `resources/js/pages/Oficina/ReviewDetail.vue`
- `resources/js/pages/Dashboard.vue`
- `app/Providers/FortifyServiceProvider.php`
- `app/Http/Controllers/Auth/SocialAuthController.php`
- `app/Support/Auth/SocialProviderRegistry.php`

Si el cambio es de admin/configuracion, agrega:
- `app/Http/Controllers/Admin/SemesterController.php`
- `app/Http/Controllers/Admin/SubmissionWindowController.php`
- `app/Http/Controllers/Admin/RequirementController.php`
- `resources/js/pages/Admin/Semesters/Index.vue`
- `resources/js/pages/Admin/Windows/Index.vue`
- `resources/js/pages/Admin/Requirements/Matrix.vue`

## F. Estado actual del proyecto

### Modulos funcionales
- auth tradicional
- 2FA
- login social Google (si el entorno esta configurado)
- dashboard general
- dashboard docente
- evidencias docente
- seguimiento docente
- revision de oficina
- visto bueno final
- reportes de oficina
- auditoria
- semestres activos con default automatico
- ventanas de entrega
- file manager
- preview PDF/imagen
- editor DOCX con versionado
- provision automatica de carpetas
- visibilidad historica de semestres cerrados

### Modulos parciales
- matriz de evidencias avanzada: no hay UI viva para `applies_condition`
- notificaciones programadas: existen comando y job, no una sola estrategia
- editor DOCX: muy util, pero sigue siendo un MVP documental
- dashboard docente: funcional, pero solapado con dashboard general

### Modulos inciertos o delicados
- adjuntos de asesorias: hoy usan `/storage/...` directo y no el mismo control de acceso del file manager
- mapping carpeta -> evidencia: depende de nombre de carpeta
- `DocxEditorService`: grande, potente y facil de romper

### Riesgos principales
- tocar `EvidenceService` sin revisar transiciones y tests
- tocar `EvidenceFlowService` sin revisar semestres historicos, etapas y `NA`
- tocar `FileController` sin revisar `EvidenceFilePolicy`, `StorageService` y tests de seguridad
- tocar `FolderStructureService` sin revisar provision automatica y estructura base
- tocar DOCX sin ampliar pruebas de round-trip

### Discrepancias conocidas
- `PROJECT_STATUS.md` y `MEMORY.md` quedaron desactualizados
- los planes de `docs/planes-implementacion/*` son historicos, no backlog vigente exacto
- `docs/legacy-notes/estructura-detectada-actualizada2.txt` es referencia de negocio, no estado tecnico actual

## G. Proximas prioridades sugeridas

1. Blindar adjuntos de asesorias para que no sigan saliendo por `/storage/...`.
2. Reducir la heuristica carpeta -> rubro en `FileController`.
3. Unificar estrategia de notificaciones programadas.
4. Volver viva la logica de `applies_condition` y automatizacion de aplicabilidad.
5. Limpiar encoding/idioma en vistas y mensajes.
6. Refinar la convivencia entre `Dashboard.vue` y `Teacher/Dashboard.vue`.

## H. Instrucciones de salida

Antes de programar cualquier cambio, entrega primero:

1. un diagnostico corto del estado actual
2. los archivos exactos que piensas tocar
3. los riesgos probables
4. un plan de implementacion por fases o pasos

Y solo despues propone cambios.

Si el cambio afecta seguridad, workflow de evidencias, file manager, DOCX o semestres:
- no empieces a editar inmediatamente
- primero explica el impacto esperado y como validarlo

Tu objetivo no es "limpiar" el proyecto idealmente.
Tu objetivo es **mejorar el sistema real sin romper lo que ya esta operando**.

