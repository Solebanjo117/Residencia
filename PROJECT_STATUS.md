# Proyecto Residencia — Estado Completo

**Fecha**: 2026-03-16
**Stack**: Laravel 12 + PHP 8.2 + Inertia.js v2 + Vue 3 + TypeScript + Tailwind CSS v4 + shadcn-vue

---

## 📋 Resumen Ejecutivo

Sistema de gestión de actividades de apoyo a la docencia (ASAD). Permite:
- **Docentes**: cargar evidencias de actividades, registrar asesorías
- **Jefe de Oficina**: revisar y aprobar evidencias, generar reportes
- **Jefe de Departamento**: ver carpetas de docentes de su(s) departamento(s)
- **Roles**: DOCENTE, JEFE_OFICINA, JEFE_DEPTO (en BD `roles` + middleware `role:ROLE_NAME`)

---

## ✅ Cambios Realizados (Esta Sesión)

### 1. Correcciones de Seguridad & Acceso
- ✅ Añadido trait `AuthorizesRequests` al `Controller` base (fix `authorize()` undefined)
- ✅ Políticas actualizadas: `EvidenceSubmissionPolicy`, `AdvisorySessionPolicy`, `FolderNodePolicy` con scope por departamento para JEFE_DEPTO
- ✅ Rutas protegidas con middleware `role:` para `/oficina/*`, `/depto/*`, `/admin/*`
- ✅ Asesorías movidas dentro del grupo `auth` (evita null Auth::user())
- ✅ Check `is_active` en `FortifyServiceProvider` para rechazar login de users inactivos

### 2. Gestión de Carpetas (File Manager)
- ✅ Corregida `FolderNode::class` — añadido `const UPDATED_AT = null` (la migración solo define `created_at`)
- ✅ `FolderStructureService::generateFullStructure()` — firma simplificada (resuelve StorageRoot internamente)
- ✅ `SemesterController::store()` ahora llama `generateFullStructure()` para TODOS los docentes activos al crear semestre
- ✅ `TeachingLoadController::store()` llama `generateFullStructure()` (estructura institucional completa)
- ✅ `StorageService::getAccessibleRoots()` actualizado:
  - JEFE_OFICINA: ve todo
  - JEFE_DEPTO: ve todo (antes solo su depto, ahora ve todas las carpetas)
  - DOCENTE: solo ve sus propias carpetas

### 3. Nuevas Funcionalidades
- ✅ **Generar carpetas para docente**: `POST /admin/teachers/{teacher}/generate-folders`
  - Botón verde "📁" en `Teachers/Index.vue`
  - Genera estructura en TODOS los semestres para un docente registrado tarde

### 4. Fixes Frontend
- ✅ `Asesorias.vue`: añadido `const exportMenuOpen = ref(false)` (faltaba)
- ✅ `Asesorias2.vue`: reemplazado hardcoded demo data con props reales
- ✅ `Teacher/Dashboard.vue`: ruta de file manager corregida (`/files/manager`)
- ✅ `FileManager/Index.vue`: removido hardcoded `submission_id: 1`
- ✅ Menu: añadido "Mis Evidencias" para DOCENTE, "Auditoria" para JEFE_OFICINA
- ✅ Paginación implementada en admin pages (Semesters, Teachers, TeachingLoads, Windows)
- ✅ Eliminadas páginas huérfanas: `Docente/MyEvidence.vue`, `Depto/Semesters.vue`, `Depto/Windows.vue`

### 5. Controllers & Services Refactorizados
- ✅ `Admin/AuditController.php`: Eloquent `AuditLog::with('user')` en lugar de raw SQL
- ✅ `Admin/ReviewController.php`: enums vs strings para status
- ✅ `Admin/TeachingLoadController.php`: constructor injection de `FolderStructureService`
- ✅ `Teacher/DashboardController.php`: semestre con `status = 'OPEN'` (no deprecated `is_active`)
- ✅ `StorageService::deleteEvidence()`: Now calls `$file->delete()` properly para SoftDeletes

### 6. Seeders & Fixtures
- ❌ NO implementado — ver "Prioridad MEDIA" abajo

---

## 🔴 PRIORIDAD ALTA — Funcionalidad Crítica Faltante

### 1. Dashboard Principal es Placeholder
**Ubicación**: `resources/js/pages/Dashboard.vue`
**Problema**: Contiene solo `PlaceholderPattern` components — no muestra:
  - Resumen de evidencias (pendientes, aprobadas, rechazadas)
  - Semestre activo
  - Últimas noticias
  - Acceso rápido a funciones

**Impacto**: Es la primera pantalla que ve el usuario tras login — experiencia deficiente
**Acción**: Crear dashboard real con stats, badges, quick links

---

### 2. Reportes (Oficina) es Stub Vacío
**Ubicación**: `resources/js/pages/Oficina/Reports.vue`
**Problema**: Página contiene solo `<h1>Reportes de Docentes</h1>` y una línea de descripción
**Impacto**: JEFE_OFICINA no puede generar reportes
**Acción**: Implementar generador de reportes (PDF, gráficos, filtros)

---

### 3. Cambios de Estado en Asesorías NO PERSISTEN
**Ubicación**: `Asesorias.vue:toggleFieldStatus`, `Asesorias2.vue:toggleFieldStatus`
**Problema**:
  - Función `toggleFieldStatus()` solo muta el estado local reactivo
  - NO hay endpoint `/api/asesorias/{id}/status` para guardar cambios
  - Los cambios se pierden al recargar la página

**Impacto**: Los cambios NA/OK/NE no se guardan — datos perdidos
**Acción**: Implementar API endpoint para persistencia

---

### 4. Botón "Abrir" Documento en Asesorías
**Ubicación**: `Asesorias.vue:511`
**Problema**:
```javascript
alert('Sin BD por ahora: aqui luego abriras/descargaras el documento.')
```
**Impacto**: No se pueden descargar documentos de advisory sessions
**Acción**: Conectar a `AdvisoryFile` model y endpoint de descarga

---

### 5. Evidencias del Docente Sin Delete/Replace
**Ubicación**: `Teacher/Evidencias/Index.vue:261`
**Problema**: Comentario `"Add delete/replace buttons here if needed later"` — no hay botones para eliminar o reemplazar archivos enviados
**Impacto**: Docente no puede corregir archivos ya subidos
**Acción**: Agregar botones + endpoints para delete/replace en `EvidenceFile`

---

### 6. SendScheduledNotificationsJob Nunca se Despacha
**Ubicación**: `app/Jobs/SendScheduledNotificationsJob.php`
**Problema**:
  - Job existe pero NO se despacha desde ningún lado
  - NO hay entrada en scheduler (`app/Console/Kernel.php`)
  - NO hay controlador que lo dispache manualmente
**Impacto**: Notificaciones programadas nunca se envían
**Acción**:
  - Registrar en scheduler (`$schedule->job(SendScheduledNotificationsJob::class)->everyFiveMinutes();`) O
  - Crear endpoint para dispatch manual (JEFE_OFICINA)

---

### 7. NotificationService::schedule() Deuda Técnica
**Ubicación**: `app/Services/NotificationService.php`
**Problema**: Método `schedule(Semester, EvidenceItem, Carbon, NotificationType)` existe pero NUNCA se llama
**Impacto**: Notificaciones programadas no se crean (relacionado con #6)
**Acción**: Integrar en workflow — p.ej., cuando se crea `SubmissionWindow`, programar notificaciones

---

## 🟡 PRIORIDAD MEDIA — Datos & Configuración

### 8. Seeders Incompletos
**Problema**: Sistema DEPENDE de datos iniciales pero solo existe `DatabaseSeeder.php`:
  - Crea 1 usuario test SIN `role_id` (viola FK)
  - NO siembra: departments, subjects, evidence_items, evidence_formats, academic_periods, storage_roots

**Impacto**:
  - Nuevo deploy = sistema vacío sin datos
  - No se puede testear sin hacer inserts manuales
  - `TeacherService::createTeacher()` falla si no hay departments

**Acción**: Crear seeders para cada tabla lookup

---

### 9. Enum Mismatch en Notifications
**Problema**:
  - BD: `notification_schedules.notification_type` ENUM('WINDOW_OPEN', 'WINDOW_CLOSING')
  - PHP: `NotificationType` enum tiene 6 values (NEW_ASSIGNMENT, WINDOW_OPEN, WINDOW_CLOSING, SUBMISSION_APPROVED, SUBMISSION_REJECTED, GENERAL)
  - Si `NotificationService::schedule(..., NotificationType::NEW_ASSIGNMENT, ...)` se usa, insert fallará

**Impacto**: Si se implementa #6 y #7, puede fallar en runtime
**Acción**: Actualizar migración para ENUM(...6 values...) O limitar PHP enum a 2 values

---

## 🟢 PRIORIDAD BAJA — QA & UX

### 10. Idioma Mezclado en Admin
**Problema**: Pages en inglés mientras resto de app es español:
  - `Admin/TeachingLoads/Index.vue`
  - `Admin/Semesters/Index.vue`
  - `Admin/Teachers/Index.vue`
  - `Admin/Requirements/Matrix.vue`

**Acción**: Traducir a español O estandarizar en un idioma

---

### 11. Asesorias.vue & Asesorias2.vue Duplicadas
**Problema**: Dos páginas casi idénticas (mismo controller, mismo data) con layouts diferentes
**Impacto**: Confunde usuarios, overhead de mantenimiento
**Acción**: Fusionar en 1 page con toggle de vista (tabla vs cards)

---

### 12. ReviewDetail.vue Descarga de Archivos
**Problema**: Links directos `'/storage/' + file.stored_relative_path` — si no se ejecutó `php artisan storage:link`, falla
**Acción**: Usar endpoint `/files/{file}/download` en lugar de acceso directo

---

### 13. FileManager Solo Acepta .docx
**Problema**: Upload `accept=".docx"` — demasiado restrictivo
**Acción**: Permitir PDF, imágenes, otros formatos documentales

---

### 14. Footer del Sidebar con Starter Kit Links
**Ubicación**: `AppSidebar.vue` footer
**Problema**: Links a `github.com/laravel/vue-starter-kit` y `laravel.com/docs/starter-kits`
**Acción**: Remover o reemplazar con links relevantes

---

### 15. Relaciones Faltantes en Modelos
**Modelos sin hasMany inversos:**
  - `User`: `submissions()`, `notifications()`, `auditLogs()`, `createdAdvisorySessions()`, `folderNodes()`
  - `Semester`: `submissions()`, `advisorySessions()`, `folderNodes()`, `notificationSchedules()`
  - `Department`: `requirements()`
  - `EvidenceItem`: `submissions()`, `requirements()`, `submissionWindows()`, `notificationSchedules()`
  - `StorageRoot`: `folderNodes()`

**Impacto**: Queries ineficientes (no se puede usar eager loading), código menos legible
**Acción**: Agregar todas las relaciones (rápido pero tedioso)

---

## 📊 Estructura de Carpetas

```
app/
├── Services/
│   ├── AdvisoryService.php          ✅ recordSession()
│   ├── AuditService.php             ✅ log()
│   ├── EvidenceService.php          ✅ changeStatus(), review(), unlockForResubmission()
│   ├── FolderStructureService.php   ✅ ensureSemesterFolder(), ensureTeacherFolder(), generateFullStructure()
│   ├── NotificationService.php      ⚠️ schedule() nunca llamada
│   ├── StorageService.php           ✅ storeEvidence(), deleteEvidence(), getAccessibleRoots()
│   └── TeacherService.php           ✅ createTeacher(), updateTeacher()
├── Models/
│   ├── User.php                     ⚠️ relaciones faltantes
│   ├── Semester.php                 ⚠️ relaciones faltantes
│   ├── EvidenceSubmission.php       ✅ completo
│   ├── FolderNode.php               ✅ UPDATED_AT = null corregido
│   ├── StorageRoot.php              ⚠️ falta hasMany folderNodes
│   └── ...
├── Auth/
│   └── Fortify/
│       └── ...                      ✅ authenticateUsing() con is_active check
└── Http/Controllers/
    ├── Admin/
    │   ├── SemesterController.php    ✅ store() genera carpetas
    │   ├── TeacherController.php     ✅ generateFolders() action
    │   ├── TeachingLoadController.php ✅ store() llama generateFullStructure()
    │   └── ...
    └── FolderController.php          ✅ getAccessibleRoots()
database/
├── migrations/
│   └── 30 tables OK (no orphans)
└── seeders/
    └── DatabaseSeeder.php           ⚠️ solo 1 seeder, incompleto
resources/js/
├── pages/
│   ├── Dashboard.vue                ⚠️ STUB placeholder
│   ├── Oficina/
│   │   ├── Reports.vue              ⚠️ COMPLETELY EMPTY
│   │   ├── PendingReviews.vue       ✅
│   │   └── ReviewDetail.vue         ✅
│   ├── Teacher/
│   │   ├── Dashboard.vue            ✅
│   │   └── Evidencias/Index.vue     ⚠️ sin delete/replace buttons
│   ├── Admin/
│   │   ├── Teachers/Index.vue       ✅ + generateFolders button
│   │   ├── Semesters/Index.vue      ✅ mixed English
│   │   ├── TeachingLoads/Index.vue  ✅ mixed English
│   │   └── ...
│   ├── Asesorias.vue                ⚠️ status no persiste, "Abrir" = alert
│   └── Asesorias2.vue               ⚠️ duplicate, same issues
└── components/
    └── FileManager/
        └── FolderTree.vue           ✅
```

---

## 🔐 Controles de Acceso (Verificados)

| Ruta | DOCENTE | JEFE_OFICINA | JEFE_DEPTO | Middleware |
|------|---------|--------------|-----------|------------|
| `/docente/*` | ✅ propia | ❌ | ❌ | `role:DOCENTE` |
| `/oficina/*` | ❌ | ✅ | ❌ | `role:JEFE_OFICINA` |
| `/depto/*` | ❌ | ❌ | ✅ | `role:JEFE_DEPTO` |
| `/admin/*` | ❌ | ✅ | ✅ | `role:JEFE_OFICINA,JEFE_DEPTO` |
| `/files/*` (archivos) | ✅ propias | ✅ todas | ✅ depto only | Policies |

---

## 🗂️ Reglas de Negocio (Implementadas)

### Carpetas (FolderNode)
- **Estructura**: `StorageRoot > Semestre > Docente > [Horario, Instrumentaciones, Evaluacion Diagnostica, Evidencias, Proyectos]`
- **Creación**: Al crear Semestre se genera estructura para todos los docentes existentes
- **Permisos**: JEFE_OFICINA ve todo; JEFE_DEPTO ve su depto; DOCENTE ve solo suyas

### Evidencias (Submission Workflow)
- **Estados**: DRAFT → SUBMITTED → APPROVED/REJECTED, o NA, o NE
- **Transiciones permitidas**: Validadas en `EvidenceService::ALLOWED_TRANSITIONS`
- **Revisión**: JEFE_OFICINA aprueba/rechaza con comments

### Asesorías
- **Creación**: DOCENTE registra sesión (fecha, tema, notas)
- **Archivos**: Se pueden subir documentos (advisory_files)
- **Estado**: NA (no aplica), OK (completada), NE (no evaluada) — **NO persisten cambios**

---

## 🎯 Siguiente Acción Recomendada

**Ordén de prioridad para la próxima sesión:**

1. **[ALTA]** Dashboard real — mostrará impacto inmediato
2. **[ALTA]** Persistencia de estados Asesorías — fix crítico
3. **[ALTA]** Reportes básico — JEFE_OFICINA necesita algo
4. **[MEDIA]** Seeders completos — preparar para producción
5. **[MEDIA]** Notificaciones programadas — workflow completo
6. **[BAJA]** Relaciones en modelos — mejora técnica
7. **[BAJA]** Idioma consistente — UX polish

---

## 📝 Notas de Desarrollo

### Convenciones Establecidas
- Enums para estados de negocio (SubmissionStatus, WindowStatus, SemesterStatus, etc.)
- Policies para autorización de recursos (todos los Models críticos)
- Services para lógica de negocio (no en controllers)
- `firstOrCreate` en FolderStructureService para idempotencia
- SoftDeletes en EvidenceFile para audit trail

### Configuraciones Importantes
- **Auth**: Fortify + 2FA TOTP
- **File Storage**: Laravel's `storage/` + `storeAs()` con path relativo
- **Timezone**: Verificar en `.env` (afecta timestamps)
- **Queue**: No configurado — seeders y notificaciones son síncronos por ahora

### Archivos Clave para Edit
- `app/Http/Controllers/Admin/SemesterController.php` — lógica de creación
- `app/Services/FolderStructureService.php` — generación de estructura
- `resources/js/pages/Dashboard.vue` — necesita contenido real
- `resources/js/pages/Oficina/Reports.vue` — vacío
- `app/Services/NotificationService.php` + `app/Jobs/SendScheduledNotificationsJob.php` — integrar

---

## 🚀 Tips para Próximas Sesiones

1. Antes de cualquier cambio, corre `npm run build` para verificar TypeScript
2. Las migraciones SoftDeletes usan `softDeletesTz()` — asegúrate de usar eso
3. El `role:` middleware es un alias personalizado en `bootstrap/app.php`
4. Todas las Views de admin usan Inertia + inertia props (no hay session flash except messages)
5. Los enums se castean automáticamente en modelos — úsalos por valor (`.value`) en queries

---

**Última actualización**: 2026-03-16 (tras análisis completo + implementaciones)
