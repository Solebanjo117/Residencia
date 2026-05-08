# CONTEXTO PARA REPORTE FINAL DE RESIDENCIA PROFESIONAL

Documento generado a partir del repositorio actual del proyecto `Residencia`.

Fuente principal: codigo actual del repositorio en `C:\Users\Yarick\Desktop\VS CODE\Residencia`.

Regla usada: no se redacta el reporte final definitivo; este archivo recopila informacion tecnica verificable para despues redactarlo con la estructura oficial del TecNM. Cuando un dato no aparece en el proyecto se marca como `PENDIENTE`.

---

# 1. Identificacion general del proyecto

| Dato | Informacion detectada | Evidencia |
|---|---|---|
| Nombre del proyecto | Residencia | `config/app.php`, `.env.example`, `resources/js/app.ts`, `resources/views/app.blade.php` |
| Objetivo general del sistema | Gestionar evidencias docentes, asesorias, revision institucional, estructura documental por semestre y control por roles. | `routes/web.php`, `app/Http/Controllers/Admin/AdvisoryController.php`, `app/Http/Controllers/Teacher/EvidenceController.php`, `app/Services/EvidenceService.php` |
| Tipo de sistema | Aplicacion web administrativa con backend Laravel e interfaz Inertia/Vue. | `composer.json`, `package.json`, `routes/web.php`, `resources/js/pages/*` |
| Tecnologias usadas | PHP 8.2, Laravel 12, Inertia.js 2, Vue 3, TypeScript, Tailwind CSS 4, Vite 7, SQLite, Playwright, Pest/PHPUnit. | `composer.json`, `package.json`, `.env.example`, `phpunit.xml`, `playwright.config.ts` |
| Frameworks usados | Laravel, Laravel Fortify, Laravel Socialite, Inertia Laravel, Vue 3, Tailwind CSS, reka-ui/shadcn-vue. | `composer.json`, `package.json`, `components.json` |
| Lenguajes usados | PHP, TypeScript, Vue SFC, JavaScript/ESM, CSS/Tailwind. | `app/**/*.php`, `resources/js/**/*.vue`, `resources/js/**/*.ts` |
| Base de datos usada | SQLite en entorno local por defecto. | `.env.example` (`DB_CONNECTION=sqlite`) |
| Herramientas de desarrollo | Composer, npm, Vite, Laravel Artisan, Pest/PHPUnit, Pint, ESLint, Prettier, Playwright, GitHub Actions. | `composer.json`, `package.json`, `.github/workflows/*` |
| Dependencias importantes backend | `laravel/framework`, `laravel/fortify`, `laravel/socialite`, `inertiajs/inertia-laravel`, `tightenco/ziggy`, `laravel/wayfinder`. | `composer.json` |
| Dependencias importantes frontend | `@inertiajs/vue3`, `vue`, `typescript`, `vite`, `tailwindcss`, `lucide-vue-next`, `reka-ui`, `xlsx`, `ziggy-js`. | `package.json` |
| Nombre de empresa u organizacion | Instituto Tecnologico de Piedras Negras / Tecnologico de Piedras Negras aparece por branding visual. | `public/images/logo-tecnologico-piedras-negras.svg`, `resources/js/pages/Welcome.vue` |
| Area o departamento de trabajo | PENDIENTE. El sistema maneja departamentos academicos, pero no confirma el area real del residente. | `app/Models/Department.php`, `resources/js/pages/Admin/Departments/Index.vue` |
| Usuarios principales | `DOCENTE`, `JEFE_OFICINA`, `JEFE_DEPTO`. | `app/Models/Role.php`, `resources/js/config/menu.ts`, `routes/web.php` |
| Problema principal | Controlar, revisar y dar seguimiento a evidencias docentes y documentos academicos por semestre, evitando dispersion manual y falta de trazabilidad. | Inferencia directa por modulos de evidencias, file manager, auditoria, reportes y ventanas: `app/Models/EvidenceSubmission.php`, `app/Models/EvidenceReview.php`, `app/Models\AuditLog.php` |

---

# 2. Resumen tecnico del proyecto

El sistema `Residencia` es una aplicacion web academica/institucional orientada al control de evidencias docentes, asesorias, revision administrativa y administracion de archivos por semestre. Opera con roles diferenciados: docentes cargan evidencias y asesorias; jefes de oficina revisan entregas; jefes de departamento configuran semestres, ventanas, matriz de evidencias y otorgan visto bueno final.

Esta dirigido principalmente a una institucion academica, con branding del Instituto Tecnologico de Piedras Negras. La informacion especifica del alumno residente, asesores, empresa formal y periodo de residencia no aparece en el repositorio y queda como `PENDIENTE`.

Modulos principales detectados:

- Autenticacion tradicional, 2FA y login social: `routes/web.php`, `app/Http/Controllers/Auth/SocialAuthController.php`, `resources/js/pages/auth/Login.vue`, `resources/js/pages/settings/TwoFactor.vue`.
- Dashboard por rol: `app/Http/Controllers/DashboardController.php`, `resources/js/pages/Dashboard.vue`.
- Evidencias docentes: `app/Http/Controllers/Teacher/EvidenceController.php`, `resources/js/pages/Teacher/Evidencias/Index.vue`.
- Seguimiento docente consolidado: `app/Http/Controllers/Admin/AdvisoryController.php`, `resources/js/pages/SeguimientoDocente.vue`.
- Revision de oficina y visto bueno final: `app/Http/Controllers/Admin/ReviewController.php`, `app/Services/EvidenceService.php`, `app/Policies/EvidenceSubmissionPolicy.php`.
- Asesorias y horarios: `app/Http/Controllers/Teacher/AdvisorySessionController.php`, `app/Http/Controllers/AdvisoryScheduleController.php`, `resources/js/pages/Docente/MyAdvisories.vue`, `resources/js/pages/Asesorias/Index.vue`.
- File manager y documentos: `app/Http/Controllers/FolderController.php`, `app/Http/Controllers/FileController.php`, `resources/js/pages/FileManager/Index.vue`.
- Editor DOCX basico: `app/Http/Controllers/DocxEditorController.php`, `app/Services/DocxEditorService.php`, `resources/js/pages/FileManager/DocxEditor.vue`.
- Administracion de catalogos: docentes, materias, rubros de evidencia, departamentos, semestres, cargas, ventanas, matriz y auditoria.
- Reportes: `app/Http/Controllers/Admin/ReportController.php`, `resources/js/pages/Oficina/Reports.vue`.
- Notificaciones: `app/Http/Controllers/NotificationController.php`, `app/Console/Commands/NotifyWindows.php`, `routes/console.php`.

Funcionalidades implementadas:

- Login, registro, recuperacion de password, verificacion de email y 2FA.
- Login social preparado para Google mediante Socialite.
- CRUD administrativo de departamentos, docentes, materias, rubros de evidencia, semestres, cargas academicas y ventanas.
- Matriz de evidencias por semestre/departamento.
- Semestre activo unico y seleccion por semestre.
- Generacion de carpetas por semestre/docente/materia y estructura documental.
- Subida, preview, descarga, reemplazo y eliminacion de archivos.
- Edicion DOCX basica dentro del sistema, con versionado.
- Flujo de evidencias con estados, historial, revision de oficina y visto bueno final.
- Reportes y exportacion CSV en reportes de oficina.
- Exportacion/impresion/XLSX en seguimiento docente por frontend.
- Pruebas feature, seguridad, dominio y e2e.

Funcionalidades incompletas o pendientes:

- Datos institucionales/personales para reporte: `PENDIENTE`.
- Manual oficial de usuario final: `PENDIENTE`; hay documentacion tecnica en `docs/`.
- Editor DOCX no equivale a Microsoft Word completo; tiene limitaciones documentales por diseno del MVP. Evidencia: `app/Services/DocxEditorService.php`, `resources/js/pages/FileManager/DocxEditor.vue`.
- Flujo de notificaciones depende de scheduler/queue en operacion real. Evidencia: `routes/console.php`, `app/Console/Commands/NotifyWindows.php`.
- Registro de producto/copyright: `PENDIENTE`.

Ejecucion local detectada:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan residencia:bootstrap --admin-name="Jefe de Departamento" --admin-email="admin@residencia.test" --admin-password="PasswordSeguro123!" --department="Sistemas"
npm run dev
```

Tambien existe script de desarrollo completo en `composer.json`:

```bash
composer run dev
```

Archivos clave para entender el sistema:

- `routes/web.php`
- `routes/console.php`
- `app/Models/Role.php`
- `app/Models/User.php`
- `app/Models/EvidenceSubmission.php`
- `app/Services/EvidenceService.php`
- `app/Services/EvidenceFlowService.php`
- `app/Services/FolderStructureService.php`
- `app/Http/Controllers/Admin/AdvisoryController.php`
- `app/Http/Controllers/Teacher/EvidenceController.php`
- `app/Http/Controllers/FileController.php`
- `resources/js/config/menu.ts`
- `resources/js/pages/SeguimientoDocente.vue`
- `resources/js/pages/FileManager/Index.vue`
- `database/migrations/*`

---

# 3. Estructura del repositorio

| Ruta | Tipo | Descripcion | Importancia para el reporte |
|---|---|---|---|
| `app/` | Backend Laravel | Contiene controladores, modelos, servicios, policies, jobs, commands y middleware. | Alta: explica arquitectura y logica del sistema. |
| `app/Http/Controllers/` | Controladores | Gestiona peticiones web y APIs internas. | Alta: evidencia funcional por modulo. |
| `app/Http/Controllers/Admin/` | Controladores admin | CRUDs, revision, reportes, matriz, semestres, ventanas. | Alta: soporta modulos administrativos. |
| `app/Http/Controllers/Teacher/` | Controladores docente | Dashboard docente, evidencias y asesorias docentes. | Alta: explica flujo del usuario docente. |
| `app/Models/` | Modelos Eloquent | Entidades del dominio y relaciones. | Alta: base para modelo de datos. |
| `app/Services/` | Servicios | Logica de negocio: evidencias, storage, auditoria, notificaciones, DOCX, carpetas. | Alta: explica separacion de responsabilidades. |
| `app/Policies/` | Autorizacion | Reglas de acceso a evidencias, archivos, carpetas y asesorias. | Alta: evidencia seguridad por rol. |
| `app/Console/Commands/` | Comandos Artisan | Notificaciones, auditoria historica, backup/restore. | Media/alta: automatizacion y operacion. |
| `bootstrap/app.php` | Config Laravel | Registra middleware `role` y rutas. | Alta: control de acceso. |
| `config/` | Configuracion | Configuracion de app, auth, services, evidence, logging, database. | Alta: entorno y dependencias. |
| `database/migrations/` | Base de datos | Define tablas y relaciones. | Alta: modelo de datos y diagramas. |
| `database/seeders/` | Datos iniciales/demo | Seeders de roles, usuarios demo, seguimiento, carpetas, asesorias. | Media: setup de pruebas/demo; no confundir con datos reales. |
| `resources/js/pages/` | Pantallas Inertia/Vue | Vistas principales del sistema. | Alta: evidencias visuales/capturas. |
| `resources/js/components/` | Componentes Vue | Layout, navegacion, file manager, UI reutilizable. | Media/alta: interfaz y experiencia de usuario. |
| `resources/js/config/menu.ts` | Navegacion | Menu por rol. | Alta: explica modulos visibles por usuario. |
| `resources/css/app.css` | Estilos | CSS base con Tailwind. | Media: interfaz visual. |
| `routes/web.php` | Rutas web | Define rutas principales y middleware. | Alta: mapa funcional del sistema. |
| `routes/console.php` | Scheduler/comandos | Programacion de notificaciones y backup; comando `residencia:bootstrap`. | Alta: instalacion limpia y automatizacion. |
| `tests/Feature/` | Pruebas backend | Pruebas de auth, admin, seguridad, evidencias, file manager, reportes. | Alta: evidencia de validacion del desarrollo. |
| `tests/e2e/` | Pruebas e2e | Smoke tests por rol con Playwright. | Media/alta: evidencia de pruebas de interfaz. |
| `.github/workflows/` | CI | Workflow de integracion continua. | Media: calidad del software. |
| `docs/` | Documentacion tecnica | Handoff, planes, operaciones, testing, notas legacy. | Alta: evidencia documental del proceso. |
| `public/images/logo-tecnologico-piedras-negras.svg` | Recurso visual | Logo institucional usado por el sistema. | Media: evidencia de branding institucional. |
| `vite.config.ts` | Build frontend | Configuracion Vite. | Media: stack frontend. |
| `composer.json` | Dependencias backend | Paquetes PHP, scripts, test/lint. | Alta: stack backend. |
| `package.json` | Dependencias frontend | Paquetes JS/TS, scripts build/e2e/lint. | Alta: stack frontend. |

---

# 4. Modulos o funcionalidades del sistema

## Modulo: Autenticacion y seguridad de cuenta

- Descripcion: permite acceso mediante correo/password, registro, recuperacion, verificacion de email y 2FA.
- Archivos relacionados: `config/fortify.php`, `routes/web.php`, `routes/settings.php`, `app/Actions/Fortify/CreateNewUser.php`, `resources/js/pages/auth/Login.vue`, `resources/js/pages/auth/Register.vue`, `resources/js/pages/settings/TwoFactor.vue`.
- Funcionalidades: login, registro, logout, password reset, 2FA, perfil, cambio de password.
- Flujo: usuario entra a login, autentica, pasa a `/dashboard`, puede gestionar perfil/2FA.
- Entradas: nombre, correo, password, codigo 2FA.
- Salidas: sesion autenticada, redireccion, validaciones.
- Capturas recomendadas: login, registro, configuracion de 2FA, perfil.
- Estado: terminado/parcial. Terminado para auth base; login social visual fue trabajado pero debe verificarse en UI real antes de capturar.

## Modulo: Login social

- Descripcion: integra Socialite para proveedores externos, actualmente Google habilitable por variables de entorno.
- Archivos relacionados: `app/Http/Controllers/Auth/SocialAuthController.php`, `app/Services/Auth/SocialAuthenticationService.php`, `app/Support/Auth/SocialProviderRegistry.php`, `database/migrations/2026_04_13_000001_create_social_accounts_table.php`, `.env.example`.
- Funcionalidades: redireccion a proveedor, callback, vinculacion por email, tabla `social_accounts`.
- Flujo: usuario selecciona proveedor, vuelve por callback, se autentica o se muestra error.
- Entradas: proveedor, token/callback OAuth.
- Salidas: sesion iniciada o error.
- Capturas recomendadas: boton/flujo de Google si esta visible y configurado.
- Estado: parcial. Backend existe; configuracion real de credenciales Google queda `PENDIENTE`.

## Modulo: Dashboard

- Descripcion: panel de indicadores y accesos rapidos adaptado por rol.
- Archivos relacionados: `app/Http/Controllers/DashboardController.php`, `resources/js/pages/Dashboard.vue`, `resources/js/pages/Teacher/Dashboard.vue`.
- Funcionalidades: resumen de pendientes, revisiones, liberaciones, notificaciones, ventanas proximas.
- Flujo: usuario autenticado entra a `/dashboard`.
- Entradas: usuario autenticado, semestre activo, datos de evidencias/notificaciones.
- Salidas: tarjetas de resumen y enlaces de accion.
- Capturas recomendadas: dashboard de docente, jefe de oficina y jefe de departamento.
- Estado: funcional.

## Modulo: Administracion de docentes

- Descripcion: permite agregar, editar, desactivar docentes y asignar departamentos/permisos de carpetas.
- Archivos relacionados: `app/Http/Controllers/Admin/TeacherController.php`, `app/Services/TeacherService.php`, `resources/js/pages/Admin/Teachers/Index.vue`, `database/migrations/2026_04_18_220000_add_folder_permission_keys_to_users_table.php`.
- Funcionalidades: alta manual, edicion, desactivacion, seleccion de permisos de estructura documental, regeneracion de carpetas.
- Flujo: jefe entra a `/admin/teachers`, crea docente, asigna departamentos/permisos, puede generar carpetas.
- Entradas: nombre, email, password, departamentos, permisos de carpeta.
- Salidas: usuario docente activo y estructura asociada.
- Capturas recomendadas: listado de docentes, modal/formulario de alta, seleccion de permisos.
- Estado: funcional.

## Modulo: Administracion de materias

- Descripcion: CRUD de materias para cargas academicas.
- Archivos relacionados: `app/Http/Controllers/Admin/SubjectController.php`, `resources/js/pages/Admin/Subjects/Index.vue`, `app/Models/Subject.php`.
- Funcionalidades: alta, edicion, eliminacion si no tiene cargas asociadas.
- Flujo: `/admin/subjects` -> agregar materia -> guardar.
- Entradas: clave, nombre.
- Salidas: materia disponible para asignacion de cargas.
- Capturas recomendadas: listado y formulario de materia.
- Estado: funcional.

## Modulo: Rubros de evidencia

- Descripcion: administra los tipos/rubros documentales que despues se usan en la matriz y ventanas.
- Archivos relacionados: `app/Http/Controllers/Admin/EvidenceItemController.php`, `resources/js/pages/Admin/EvidenceItems/Index.vue`, `app/Models/EvidenceItem.php`, `app/Models/EvidenceCategory.php`.
- Funcionalidades: alta, edicion, activacion/desactivacion, eliminacion protegida si ya hay matriz o submissions.
- Flujo: `/admin/evidence-items` -> agregar rubro -> usar en matriz/ventanas.
- Entradas: categoria, nombre, descripcion, requiere materia, activo.
- Salidas: rubro disponible para requirements/ventanas.
- Capturas recomendadas: pantalla de rubros.
- Estado: funcional.

## Modulo: Semestres

- Descripcion: administra semestres academicos, con regla de un semestre abierto/activo a la vez.
- Archivos relacionados: `app/Http/Controllers/Admin/SemesterController.php`, `app/Models/Semester.php`, `database/migrations/2026_04_13_000002_enforce_single_active_semester.php`, `resources/js/pages/Admin/Semesters/Index.vue`.
- Funcionalidades: crear, editar, cerrar, seleccionar semestre abierto; provisionar carpetas para docentes activos al abrir semestre.
- Flujo: jefe crea semestre en `/admin/semesters`; si queda `OPEN`, cierra otros abiertos y genera estructura.
- Entradas: nombre, fechas, status, periodo academico opcional.
- Salidas: semestre disponible para cargas, evidencias y carpetas.
- Capturas recomendadas: gestion de semestres con uno activo.
- Estado: funcional.

## Modulo: Cargas academicas

- Descripcion: asigna materias a docentes por semestre.
- Archivos relacionados: `app/Http/Controllers/Admin/TeachingLoadController.php`, `app/Models/TeachingLoad.php`, `resources/js/pages/Admin/TeachingLoads/Index.vue`, `resources/js/components/SearchableSelect.vue`.
- Funcionalidades: alta, edicion, eliminacion, filtro por semestre activo/default.
- Flujo: `/admin/teaching-loads` -> elegir docente, semestre, materia, grupo, horas -> guardar.
- Entradas: docente, semestre, materia, grupo, horas.
- Salidas: carga academica usada por evidencias, seguimiento y asesorias.
- Capturas recomendadas: formulario/listado de cargas.
- Estado: funcional.

## Modulo: Matriz de evidencias

- Descripcion: configura que rubros aplican por semestre y departamento.
- Archivos relacionados: `app/Http/Controllers/Admin/RequirementController.php`, `app/Models/EvidenceRequirement.php`, `resources/js/pages/Admin/Requirements/Matrix.vue`.
- Funcionalidades: marcar evidencias globales o por departamento, obligatorias u opcionales.
- Flujo: `/admin/requirements` -> seleccionar semestre -> marcar rubros -> guardar.
- Entradas: semestre, departamentos, rubros, obligatoriedad.
- Salidas: requisitos que alimentan evidencias docentes y seguimiento.
- Capturas recomendadas: matriz por departamento.
- Estado: funcional, con aplicabilidad manual; automatizacion por modalidad queda `PENDIENTE`.

## Modulo: Ventanas de entrega

- Descripcion: define fechas de apertura/cierre para rubros de evidencia.
- Archivos relacionados: `app/Http/Controllers/Admin/SubmissionWindowController.php`, `app/Models/SubmissionWindow.php`, `resources/js/pages/Admin/Windows/Index.vue`.
- Funcionalidades: crear/editar/eliminar ventanas, validar solapamiento de ventanas activas por semestre/evidencia.
- Flujo: `/admin/windows` -> seleccionar semestre/rubro -> definir fechas -> guardar.
- Entradas: semestre, evidencia, fecha apertura, fecha cierre, estado.
- Salidas: control de disponibilidad, bloqueo futuro y marca extemporanea.
- Capturas recomendadas: ventana activa y validacion.
- Estado: funcional.

## Modulo: Evidencias docentes

- Descripcion: permite al docente inicializar, cargar archivos y enviar evidencias de acuerdo con matriz, etapa y ventanas.
- Archivos relacionados: `app/Http/Controllers/Teacher/EvidenceController.php`, `resources/js/pages/Teacher/Evidencias/Index.vue`, `app/Services/EvidenceFlowService.php`, `app/Services/EvidenceService.php`.
- Funcionalidades: inicializar submission, subir archivo, enviar, ver estado, ver historial/revision, detectar extemporaneo.
- Flujo: docente entra a `/docente/evidencias`, inicia evidencia disponible, carga archivo y envia.
- Entradas: archivo, evidencia, carga academica.
- Salidas: `EvidenceSubmission`, `EvidenceFile`, historial y notificaciones.
- Capturas recomendadas: listado de tareas, carga de archivo, entrega enviada, evidencia rechazada/aprobada.
- Estado: funcional.

## Modulo: Seguimiento docente

- Descripcion: tabla consolidada por docente/materia/evidencia con estados visuales, exportacion e impresion.
- Archivos relacionados: `app/Http/Controllers/Admin/AdvisoryController.php`, `resources/js/pages/SeguimientoDocente.vue`, `app/Services/EvidenceFlowService.php`.
- Funcionalidades: busqueda, filtro por semestre, detalle de celda, estados `AO`, `VF`, `PA`, `BL`, `R`, `NE`, `NA`, impresion, exportacion CSV/XLSX.
- Flujo: usuario entra a `/asesorias`, selecciona semestre, consulta matriz y abre detalles.
- Entradas: semestre, busqueda, acciones de revision/aplicabilidad.
- Salidas: tabla administrativa, exportaciones, detalle/historial.
- Capturas recomendadas: tabla completa, modal de detalle, exportacion, impresion.
- Estado: funcional.

## Modulo: Revision de oficina y visto bueno final

- Descripcion: flujo institucional para aprobar/rechazar evidencias y liberar finalmente por jefe de departamento.
- Archivos relacionados: `app/Http/Controllers/Admin/ReviewController.php`, `app/Http/Controllers/Admin/AdvisoryController.php`, `app/Services/EvidenceService.php`, `app/Policies/EvidenceSubmissionPolicy.php`, `resources/js/pages/Oficina/PendingReviews.vue`, `resources/js/pages/Oficina/ReviewDetail.vue`.
- Funcionalidades: aprobar por oficina, rechazar con comentarios, registrar stage de revision, visto bueno final.
- Flujo: oficina revisa submission `SUBMITTED`; jefe depto libera si ya existe aprobacion de oficina.
- Entradas: decision, comentarios.
- Salidas: estado actualizado, historial, auditoria, notificaciones.
- Capturas recomendadas: pendientes de revision, detalle, historial aprobado/rechazado.
- Estado: funcional.

## Modulo: File Manager

- Descripcion: gestor documental jerarquico por semestre/docente/materia/carpeta.
- Archivos relacionados: `app/Http/Controllers/FolderController.php`, `app/Http/Controllers/FileController.php`, `app/Services/FolderStructureService.php`, `app/Services/StorageService.php`, `app/Policies/FolderNodePolicy.php`, `app/Policies/EvidenceFilePolicy.php`, `resources/js/pages/FileManager/Index.vue`, `resources/js/components/FileManager/FolderTree.vue`.
- Funcionalidades: explorar carpetas, subir, previsualizar, descargar, reemplazar, eliminar; separar semestres activos/no activos; acceso por rol.
- Flujo: usuario entra a `/files/manager`, selecciona carpeta, administra archivos.
- Entradas: archivos, carpeta seleccionada.
- Salidas: evidencia archivada, preview/descarga, auditoria.
- Capturas recomendadas: arbol de carpetas, subida de archivo, preview PDF, permisos por docente.
- Estado: funcional.

## Modulo: Editor DOCX

- Descripcion: editor web basico de documentos `.docx` con versionado y soporte limitado de contenido.
- Archivos relacionados: `app/Http/Controllers/DocxEditorController.php`, `app/Services/DocxEditorService.php`, `resources/js/pages/FileManager/DocxEditor.vue`, `database/migrations/2026_04_12_000003_add_docx_versioning_fields_to_evidence_files_table.php`.
- Funcionalidades: abrir DOCX, convertir a HTML editable, editar cuerpo/encabezado/pie, guardar reemplazo o nueva version.
- Flujo: desde File Manager se abre un `.docx`, se edita y se guarda.
- Entradas: contenido HTML, header/footer, modo de guardado.
- Salidas: archivo DOCX actualizado/versionado.
- Capturas recomendadas: editor DOCX, historial/version.
- Estado: parcial por naturaleza del MVP; no es editor Word completo.

## Modulo: Asesorias

- Descripcion: registro y consulta de asesorias academicas y horarios.
- Archivos relacionados: `app/Http/Controllers/Teacher/AdvisorySessionController.php`, `app/Http/Controllers/AdvisoryScheduleController.php`, `app/Services/AdvisoryService.php`, `resources/js/pages/Docente/MyAdvisories.vue`, `resources/js/pages/Asesorias/Index.vue`.
- Funcionalidades: crear/editar/eliminar sesiones, horarios, impresion con firmas, carga opcional de archivos.
- Flujo: docente registra asesoria en `/docente/asesorias`; autoridades consultan horarios en `/asesorias-horarios`.
- Entradas: fecha, tema, duracion, notas, carga/materia, archivos.
- Salidas: registro de asesoria y archivo asociado.
- Capturas recomendadas: listado de asesorias, formulario, impresion con firmas.
- Estado: funcional.

## Modulo: Reportes

- Descripcion: reportes consolidados por docente/semestre.
- Archivos relacionados: `app/Http/Controllers/Admin/ReportController.php`, `resources/js/pages/Oficina/Reports.vue`, `tests/Feature/Admin/OfficeReportsTest.php`.
- Funcionalidades: resumen, filtros, busqueda, exportacion CSV.
- Flujo: jefe oficina entra a `/oficina/reportes`, selecciona semestre/filtros y exporta.
- Entradas: semestre, busqueda, foco de estado.
- Salidas: tabla y CSV.
- Capturas recomendadas: pantalla de reportes y archivo CSV.
- Estado: funcional/parcial. Funcional para indicadores; puede mejorarse para graficas formales.

## Modulo: Auditoria

- Descripcion: registra acciones institucionales y permite consultarlas.
- Archivos relacionados: `app/Services/AuditService.php`, `app/Models/AuditLog.php`, `app/Http/Controllers/Admin/AuditController.php`, `resources/js/pages/Admin/AuditLogs.vue`.
- Funcionalidades: registrar cambios de estado, descargas, preview, acciones; listar bitacora.
- Flujo: acciones del sistema generan `audit_log`; admin consulta `/admin/audits`.
- Entradas: accion, entidad, metadata, usuario.
- Salidas: bitacora auditable.
- Capturas recomendadas: listado de auditoria.
- Estado: funcional.

## Modulo: Notificaciones

- Descripcion: alertas internas para entregas, ventanas y revisiones.
- Archivos relacionados: `app/Http/Controllers/NotificationController.php`, `app/Services/NotificationService.php`, `app/Console/Commands/NotifyWindows.php`, `app/Jobs/SendScheduledNotificationsJob.php`, `routes/console.php`, `resources/js/components/NotificationBell.vue`.
- Funcionalidades: notificaciones no leidas, marcar como leida, programar avisos de ventanas.
- Flujo: scheduler ejecuta `notify:windows`; UI muestra campana.
- Entradas: schedules, acciones de revision.
- Salidas: registros en `notifications`.
- Capturas recomendadas: campana de notificaciones.
- Estado: funcional/parcial. Requiere scheduler/queue operativo en ambiente real.

---

# 5. Base de datos

| Tabla | Campos principales | Modelo relacionado | Proposito | Relaciones |
|---|---|---|---|---|
| `users` | `id`, `name`, `email`, `password`, `role_id`, `is_active`, `folder_permission_keys` | `App\Models\User` | Usuarios del sistema. | Pertenece a `roles`; muchos a muchos con `departments`; tiene `teaching_loads`, `social_accounts`. |
| `roles` | `id`, `name` | `App\Models\Role` | Roles `DOCENTE`, `JEFE_OFICINA`, `JEFE_DEPTO`. | Tiene muchos `users`. |
| `departments` | `id`, `name` | `App\Models\Department` | Departamentos academicos. | Muchos a muchos con `users`; usado en requirements. |
| `user_department` | `user_id`, `department_id` | Pivot | Adscripcion usuario/departamento. | Une `users` y `departments`. |
| `academic_periods` | `name`, `code`, `start_date`, `end_date`, `status` | `App\Models\AcademicPeriod` | Periodos academicos generales. | Tiene semestres. |
| `semesters` | `name`, `start_date`, `end_date`, `status`, `academic_period_id` | `App\Models\Semester` | Semestres de trabajo. | Tiene cargas, requirements, ventanas, carpetas, submissions. |
| `subjects` | `code`, `name` | `App\Models\Subject` | Materias. | Tiene cargas academicas. |
| `teaching_loads` | `teacher_user_id`, `semester_id`, `subject_id`, `group_code`, `hours_per_week` | `App\Models\TeachingLoad` | Asignacion docente-materia-semestre. | Pertenece a docente, semestre y materia; tiene submissions y asesorias. |
| `evidence_categories` | `name`, `description` | `App\Models\EvidenceCategory` | Agrupa rubros de evidencia. | Tiene `evidence_items`. |
| `evidence_items` | `category_id`, `name`, `description`, `requires_subject`, `active` | `App\Models\EvidenceItem` | Rubros/documentos requeribles. | Pertenece a categoria; tiene requirements, submissions, formatos. |
| `evidence_formats` | `name`, `template_url`, `active` | `App\Models\EvidenceFormat` | Formatos/plantillas asociables. | Muchos a muchos con evidence items. |
| `evidence_item_formats` | `evidence_item_id`, `format_id` | Pivot | Relacion rubro-formato. | Une `evidence_items` y `evidence_formats`. |
| `evidence_requirements` | `semester_id`, `department_id`, `evidence_item_id`, `is_mandatory`, `applies_condition` | `App\Models\EvidenceRequirement` | Matriz de evidencias por semestre/departamento. | Pertenece a semestre, departamento y rubro. |
| `submission_windows` | `semester_id`, `evidence_item_id`, `opens_at`, `closes_at`, `status`, `created_by_user_id` | `App\Models\SubmissionWindow` | Ventanas de entrega. | Pertenece a semestre, evidence item y creador. |
| `storage_roots` | `name`, `base_path`, `is_active` | `App\Models\StorageRoot` | Raices de almacenamiento. | Tiene `folder_nodes`. |
| `folder_nodes` | `parent_id`, `storage_root_id`, `name`, `relative_path`, `owner_user_id`, `semester_id` | `App\Models\FolderNode` | Arbol de carpetas. | Relacion recursiva; pertenece a storage root, usuario owner y semestre. |
| `evidence_submissions` | `semester_id`, `teacher_user_id`, `evidence_item_id`, `teaching_load_id`, `status`, `submitted_at`, `submitted_late`, `office_reviewed_at`, `final_approved_at` | `App\Models\EvidenceSubmission` | Entrega de evidencia por docente/carga/rubro. | Tiene archivos, reviews, historial, unlocks. |
| `evidence_files` | `submission_id`, `folder_node_id`, `file_name`, `stored_relative_path`, `mime_type`, `uploaded_by_user_id`, `previous_version_file_id`, `root_file_id`, `is_current_version` | `App\Models\EvidenceFile` | Archivos de evidencia. | Pertenece a submission, carpeta y usuarios; versionado DOCX. |
| `evidence_reviews` | `submission_id`, `reviewed_by_user_id`, `decision`, `stage`, `comments`, `reviewed_at` | `App\Models\EvidenceReview` | Historial de dictamenes. | Pertenece a submission y reviewer. |
| `evidence_status_history` | `submission_id`, `old_status`, `new_status`, `changed_by_user_id`, `change_reason`, `changed_at` | `App\Models\EvidenceStatusHistory` | Bitacora de cambios de estado. | Pertenece a submission y usuario. |
| `resubmission_unlocks` | `submission_id`, `unlocked_by_user_id`, `unlocked_at`, `expires_at`, `reason` | `App\Models\ResubmissionUnlock` | Desbloqueos/prorrogas para reenvio. | Pertenece a submission y usuario desbloqueador. |
| `audit_log` | `user_id`, `action`, `entity_type`, `entity_id`, `at`, `metadata` | `App\Models\AuditLog` | Auditoria institucional. | Pertenece a user. |
| `notifications` | `user_id`, `type`, `title`, `message`, `is_read`, `read_at` | `App\Models\Notification` | Notificaciones internas. | Pertenece a user. |
| `notification_schedules` | `semester_id`, `evidence_item_id`, `notify_at`, `notification_type`, `is_sent` | `App\Models\NotificationSchedule` | Programacion de notificaciones. | Pertenece a semestre/evidence item. |
| `advisory_sessions` | `teaching_load_id`, `semester_id`, `session_date`, `topic`, `duration_minutes`, `notes`, `created_by_user_id`, `teacher_user_id` | `App\Models\AdvisorySession` | Sesiones de asesoria. | Pertenece a carga, semestre, usuario creador/docente. |
| `advisory_files` | `advisory_session_id`, `file_name`, `stored_relative_path`, `uploaded_by_user_id` | `App\Models\AdvisoryFile` | Archivos asociados a asesorias. | Pertenece a advisory session. |
| `advisory_schedules` | `teacher_user_id`, `day_of_week`, `starts_at`, `ends_at`, `location`, `created_by_user_id` | `App\Models\AdvisorySchedule` | Horarios de asesorias. | Pertenece a docente y creador. |
| `social_accounts` | `user_id`, `provider`, `provider_user_id`, `avatar_url` | `App\Models\SocialAccount` | Vinculacion OAuth. | Pertenece a user. |

Diagramas recomendados para el reporte:

- Diagrama entidad-relacion de usuarios, roles, departamentos, semestres, materias, cargas, evidencias, submissions, archivos y reviews.
- Diagrama de estados de evidencias: `DRAFT -> SUBMITTED -> APPROVED/REJECTED`, `APPROVED -> visto bueno final`, `NA/NE`, reactivacion.
- Diagrama de flujo por rol: docente, jefe de oficina, jefe de departamento.
- Diagrama de arquitectura Laravel + Inertia + Vue.

---

# 6. Rutas, controladores y flujo del sistema

| Ruta | Metodo | Controlador/Funcion | Vista/Componente | Descripcion |
|---|---|---|---|---|
| `/` | GET | Closure en `routes/web.php` | `resources/js/pages/Welcome.vue` | Pantalla inicial. |
| `/dashboard` | GET | `DashboardController@index` | `resources/js/pages/Dashboard.vue` | Panel principal por rol. |
| `/docente/dashboard` | GET | `Teacher\DashboardController@index` | `resources/js/pages/Teacher/Dashboard.vue` | Dashboard especifico docente. |
| `/docente/evidencias` | GET | `Teacher\EvidenceController@index` | `resources/js/pages/Teacher/Evidencias/Index.vue` | Lista de tareas/evidencias del docente. |
| `/docente/evidencias/init` | POST | `Teacher\EvidenceController@initSubmission` | Redireccion Inertia | Inicializa entrega. |
| `/docente/evidencias/{submission}/upload` | POST | `Teacher\EvidenceController@storeFile` | Redireccion Inertia | Sube archivo a evidencia. |
| `/docente/evidencias/{submission}/submit` | POST | `Teacher\EvidenceController@submit` | Redireccion Inertia | Envia evidencia a revision. |
| `/docente/asesorias` | GET/POST | `Teacher\AdvisorySessionController` | `resources/js/pages/Docente/MyAdvisories.vue` | Gestion de asesorias del docente. |
| `/oficina/revisiones` | GET | `Admin\ReviewController@index` | `resources/js/pages/Oficina/PendingReviews.vue` | Pendientes de revision. |
| `/oficina/revisiones/{submission}` | GET | `Admin\ReviewController@show` | `resources/js/pages/Oficina/ReviewDetail.vue` | Detalle de entrega. |
| `/oficina/revisiones/{submission}/status` | POST | `Admin\ReviewController@updateStatus` | Redireccion Inertia | Actualiza revision desde oficina. |
| `/oficina/reportes` | GET | `Admin\ReportController@index` | `resources/js/pages/Oficina/Reports.vue` | Reportes por docente/semestre. |
| `/admin/departments` | GET/POST/PUT/DELETE | `Admin\DepartmentController` | `resources/js/pages/Admin/Departments/Index.vue` | CRUD departamentos. |
| `/admin/teachers` | GET/POST/PUT/DELETE | `Admin\TeacherController` | `resources/js/pages/Admin/Teachers/Index.vue` | CRUD docentes. |
| `/admin/teachers/{teacher}/generate-folders` | POST | `Admin\TeacherController@generateFolders` | Redireccion Inertia | Regenera estructura de carpetas. |
| `/admin/subjects` | GET/POST/PUT/DELETE | `Admin\SubjectController` | `resources/js/pages/Admin/Subjects/Index.vue` | CRUD materias. |
| `/admin/evidence-items` | GET/POST/PUT/DELETE | `Admin\EvidenceItemController` | `resources/js/pages/Admin/EvidenceItems/Index.vue` | CRUD rubros de evidencia. |
| `/admin/semesters` | GET/POST/PUT/DELETE | `Admin\SemesterController` | `resources/js/pages/Admin/Semesters/Index.vue` | CRUD semestres. |
| `/admin/teaching-loads` | GET/POST/PUT/DELETE | `Admin\TeachingLoadController` | `resources/js/pages/Admin/TeachingLoads/Index.vue` | CRUD cargas academicas. |
| `/admin/requirements` | GET/POST | `Admin\RequirementController` | `resources/js/pages/Admin/Requirements/Matrix.vue` | Matriz de evidencias. |
| `/admin/windows` | GET/POST/PUT/DELETE | `Admin\SubmissionWindowController` | `resources/js/pages/Admin/Windows/Index.vue` | Ventanas de entrega. |
| `/admin/audits` | GET | `Admin\AuditController@index` | `resources/js/pages/Admin/AuditLogs.vue` | Consulta de auditoria. |
| `/asesorias` | GET | `Admin\AdvisoryController@index` | `resources/js/pages/SeguimientoDocente.vue` | Seguimiento docente consolidado. |
| `/asesorias/{submission}/review` | POST | `Admin\AdvisoryController@reviewEvidence` | Redireccion Inertia | Revision por oficina. |
| `/asesorias/{submission}/final-approval` | POST | `Admin\AdvisoryController@finalApprove` | Redireccion Inertia | Visto bueno final por jefe depto. |
| `/asesorias/cells/status` | POST | `Admin\AdvisoryController@upsertCellStatus` | Redireccion Inertia | Marca NA/reactiva celdas. |
| `/asesorias-horarios` | GET/POST/PUT/DELETE | `AdvisoryScheduleController` | `resources/js/pages/Asesorias/Index.vue` | Horarios de asesorias. |
| `/files/manager` | GET | `FolderController@index` | `resources/js/pages/FileManager/Index.vue` | Gestor de archivos. |
| `/files/folders/{folder}` | GET | `FolderController@show` | `resources/js/pages/FileManager/Index.vue` | Ver carpeta. |
| `/files/folders/{folder}/upload` | POST | `FileController@store` | Redireccion Inertia | Subir archivo. |
| `/files/{file}/preview` | GET | `FileController@preview` | Respuesta archivo | Preview inline. |
| `/files/{file}/download` | GET | `FileController@download` | Descarga | Descargar archivo. |
| `/files/{file}/replace` | POST | `FileController@replace` | Redireccion Inertia | Reemplazar archivo. |
| `/files/{file}` | DELETE | `FileController@destroy` | Redireccion Inertia | Eliminar archivo. |
| `/files/{file}/docx` | GET/POST | `DocxEditorController` | `resources/js/pages/FileManager/DocxEditor.vue` | Ver/guardar DOCX. |
| `/api/notifications` | GET | `NotificationController@getUnread` | JSON | Notificaciones no leidas. |
| `/api/notifications/read/{id?}` | POST | `NotificationController@markAsRead` | JSON | Marcar leidas. |
| `/auth/{provider}/redirect` | GET | `SocialAuthController@redirect` | Redirect OAuth | Login social. |
| `/auth/{provider}/callback` | GET | `SocialAuthController@callback` | Redirect | Callback OAuth. |

---

# 7. Evidencias del desarrollo

Capturas de pantalla recomendadas:

- Figura 1. Pantalla de inicio institucional (`resources/js/pages/Welcome.vue`).
- Figura 2. Pantalla de inicio de sesion (`resources/js/pages/auth/Login.vue`).
- Figura 3. Panel principal por rol (`resources/js/pages/Dashboard.vue`).
- Figura 4. Menu lateral con modulos por rol (`resources/js/config/menu.ts`, `resources/js/components/NavMain.vue`).
- Figura 5. CRUD de docentes (`resources/js/pages/Admin/Teachers/Index.vue`).
- Figura 6. CRUD de materias (`resources/js/pages/Admin/Subjects/Index.vue`).
- Figura 7. CRUD de rubros de evidencia (`resources/js/pages/Admin/EvidenceItems/Index.vue`).
- Figura 8. Administracion de semestres (`resources/js/pages/Admin/Semesters/Index.vue`).
- Figura 9. Cargas academicas por docente (`resources/js/pages/Admin/TeachingLoads/Index.vue`).
- Figura 10. Matriz de evidencias (`resources/js/pages/Admin/Requirements/Matrix.vue`).
- Figura 11. Ventanas de entrega (`resources/js/pages/Admin/Windows/Index.vue`).
- Figura 12. Mis evidencias del docente (`resources/js/pages/Teacher/Evidencias/Index.vue`).
- Figura 13. Subida de archivo en evidencia (`app/Http/Controllers/Teacher/EvidenceController.php`).
- Figura 14. Seguimiento docente consolidado (`resources/js/pages/SeguimientoDocente.vue`).
- Figura 15. Modal de detalle/historial de evidencia (`resources/js/pages/SeguimientoDocente.vue`).
- Figura 16. Pendientes de revision de oficina (`resources/js/pages/Oficina/PendingReviews.vue`).
- Figura 17. Detalle de revision (`resources/js/pages/Oficina/ReviewDetail.vue`).
- Figura 18. Reportes docentes (`resources/js/pages/Oficina/Reports.vue`).
- Figura 19. Exportacion CSV de reportes (`app/Http/Controllers/Admin/ReportController.php`).
- Figura 20. Gestor de archivos (`resources/js/pages/FileManager/Index.vue`).
- Figura 21. Preview de PDF/archivo (`app/Http/Controllers/FileController.php`).
- Figura 22. Editor DOCX (`resources/js/pages/FileManager/DocxEditor.vue`).
- Figura 23. Horarios de asesorias (`resources/js/pages/Asesorias/Index.vue`).
- Figura 24. Mis asesorias del docente (`resources/js/pages/Docente/MyAdvisories.vue`).
- Figura 25. Bitacora de auditoria (`resources/js/pages/Admin/AuditLogs.vue`).
- Figura 26. Campana de notificaciones (`resources/js/components/NotificationBell.vue`).
- Figura 27. Evidencia de pruebas automatizadas (`tests/Feature/*`, `tests/e2e/*`).

Codigo clave para anexos:

- `app/Services/EvidenceService.php`: maquina de transiciones y trazabilidad.
- `app/Services/EvidenceFlowService.php`: etapas, disponibilidad, bloqueo y estado visual.
- `app/Services/FolderStructureService.php`: creacion de estructura documental.
- `app/Policies/EvidenceSubmissionPolicy.php`: autorizacion de evidencias.
- `app/Policies/FolderNodePolicy.php`: permisos del file manager.
- `routes/web.php`: mapa completo de rutas.
- `database/migrations/*`: modelo de datos.

Diagramas recomendados:

- Diagrama ER de base de datos.
- Diagrama de arquitectura MVC/Inertia.
- Diagrama de flujo de evidencia.
- Diagrama de roles/permisos.
- Diagrama de estructura de carpetas por semestre/docente/materia.

---

# 8. Problemas a resolver

| Prioridad | Problema | Modulo que lo atiende | Evidencia en el proyecto |
|---|---|---|---|
| Alta | Dificultad para controlar entregas docentes por semestre, materia y rubro. | Evidencias, matriz, seguimiento docente. | `app/Http/Controllers/Teacher/EvidenceController.php`, `resources/js/pages/SeguimientoDocente.vue` |
| Alta | Falta de trazabilidad en aprobaciones, rechazos y cambios de estado. | Revision, historial, auditoria. | `app/Services/EvidenceService.php`, `app/Models/EvidenceStatusHistory.php`, `app/Services/AuditService.php` |
| Alta | Necesidad de diferenciar permisos entre docente, jefe de oficina y jefe de departamento. | Roles, middleware, policies. | `app/Models/Role.php`, `app/Http/Middleware/CheckRole.php`, `app/Policies/*` |
| Alta | Control manual complejo de ventanas de entrega y entregas extemporaneas. | Ventanas, flujo de evidencia. | `app/Http/Controllers/Admin/SubmissionWindowController.php`, `app/Services/EvidenceFlowService.php` |
| Media | Organizacion documental dispersa por docente/semestre/materia. | File manager, estructura de carpetas. | `app/Services/FolderStructureService.php`, `resources/js/pages/FileManager/Index.vue` |
| Media | Necesidad de reportes y tabla administrativa para seguimiento institucional. | Reportes y seguimiento docente. | `app/Http/Controllers/Admin/ReportController.php`, `resources/js/pages/Oficina/Reports.vue` |
| Media | Registro y consulta de asesorias academicas. | Asesorias. | `app/Http/Controllers/Teacher/AdvisorySessionController.php`, `resources/js/pages/Docente/MyAdvisories.vue` |
| Baja/Media | Edicion basica de documentos DOCX sin salir del sistema. | Editor DOCX. | `app/Services/DocxEditorService.php`, `resources/js/pages/FileManager/DocxEditor.vue` |

---

# 9. Objetivos

Objetivo general:

Desarrollar una aplicacion web para gestionar, organizar y dar seguimiento a evidencias docentes, asesorias academicas y revision institucional por semestre, mediante roles, ventanas de entrega, administracion documental, reportes y trazabilidad de estados.

Objetivos especificos:

1. Implementar autenticacion y control de acceso por roles para docentes, jefe de oficina y jefe de departamento.
2. Disenar una estructura de base de datos para semestres, departamentos, docentes, materias, cargas academicas, evidencias, archivos, revisiones y auditoria.
3. Desarrollar modulos administrativos para gestionar docentes, materias, rubros de evidencia, semestres, cargas academicas, ventanas de entrega y matriz de evidencias.
4. Permitir a los docentes cargar, enviar y consultar evidencias conforme a reglas de disponibilidad, etapas y ventanas de entrega.
5. Implementar un flujo de revision con aprobacion de oficina, rechazo con comentarios y visto bueno final de jefe de departamento.
6. Crear un gestor de archivos con permisos por rol, preview, descarga, reemplazo y edicion basica de documentos DOCX.
7. Generar reportes, exportaciones e impresion que apoyen el seguimiento docente y la toma de decisiones.
8. Incorporar pruebas automatizadas y documentacion tecnica para mejorar la confiabilidad y mantenibilidad del sistema.

---

# 10. Justificacion

El sistema es necesario porque centraliza procesos academicos que, por la naturaleza del proyecto, requieren control documental, seguimiento por semestre, evidencia verificable y revision por roles. El codigo muestra que el problema no se limita a guardar archivos: tambien se necesita saber que evidencia aplica, cuando se puede entregar, quien la reviso, si fue rechazada, si fue aprobada por oficina, si recibio visto bueno final y si fue entregada fuera de tiempo.

Beneficios detectados:

- Reduce dispersion de archivos mediante una estructura jerarquica por semestre/docente/materia: `app/Services/FolderStructureService.php`.
- Mejora trazabilidad con historial de estados y auditoria: `app/Services/EvidenceService.php`, `app/Models/EvidenceStatusHistory.php`, `app/Services/AuditService.php`.
- Facilita revision institucional diferenciada por roles: `routes/web.php`, `app/Policies/EvidenceSubmissionPolicy.php`.
- Permite configurar semestres, cargas, rubros y ventanas de entrega desde UI administrativa.
- Apoya reportes y exportaciones para seguimiento: `app/Http/Controllers/Admin/ReportController.php`, `resources/js/pages/SeguimientoDocente.vue`.
- Permite operacion incremental desde base limpia con `php artisan residencia:bootstrap`: `routes/console.php`.

A quien beneficia:

- Docentes: pueden cargar evidencias y asesorias en un solo sistema.
- Jefe de oficina: puede revisar entregas y consultar reportes.
- Jefe de departamento: puede configurar semestres/matriz/ventanas y liberar evidencias.
- Institucion: obtiene mejor control, trazabilidad y consulta documental.

Datos que requieren informacion externa:

- Proceso manual anterior real: `PENDIENTE`.
- Indicadores de ahorro de tiempo/costos: `PENDIENTE`.
- Area institucional exacta beneficiada: `PENDIENTE`.
- Politicas internas/normatividad TecNM especifica usada: `PENDIENTE`.

---

# 11. Marco teorico sugerido

| Concepto | Importancia para el proyecto | Aplicacion en el sistema | Archivo o modulo relacionado |
|---|---|---|---|
| Desarrollo web | El sistema es una aplicacion web accesible desde navegador. | Backend Laravel + frontend Vue/Inertia. | `composer.json`, `package.json`, `routes/web.php` |
| Arquitectura MVC | Laravel organiza modelos, vistas/controladores y logica de aplicacion. | Controladores, modelos Eloquent, vistas Inertia. | `app/Http/Controllers/*`, `app/Models/*`, `resources/js/pages/*` |
| Laravel | Framework backend principal. | Rutas, controladores, migraciones, policies, jobs, commands. | `composer.json`, `app/`, `routes/` |
| Inertia.js | Permite SPA sin API separada tradicional. | Render de componentes Vue desde controladores Laravel. | `Inertia::render` en controladores, `resources/js/pages/*` |
| Vue 3 y TypeScript | Construccion de interfaz interactiva. | Pantallas administrativas, file manager, dashboards. | `resources/js/pages/*`, `resources/js/components/*` |
| Base de datos relacional | El dominio requiere relaciones entre docentes, semestres, materias y evidencias. | Migraciones y modelos Eloquent. | `database/migrations/*`, `app/Models/*` |
| Autenticacion y autorizacion | El sistema depende de roles y permisos. | Fortify, middleware `role`, policies. | `config/fortify.php`, `app/Http/Middleware/CheckRole.php`, `app/Policies/*` |
| CRUD | Alta, consulta, actualizacion y baja de catalogos. | Docentes, materias, departamentos, rubros, semestres. | `app/Http/Controllers/Admin/*Controller.php` |
| Gestion documental | Organizacion, subida, preview y versionado de archivos. | File Manager y DOCX editor. | `app/Services/StorageService.php`, `app/Services/DocxEditorService.php` |
| Flujo de estados | Las evidencias pasan por estados controlados. | DRAFT, SUBMITTED, APPROVED, REJECTED, NA, NE. | `app/Services/EvidenceService.php`, `app/Enums/SubmissionStatus.php` |
| Auditoria | Es necesaria para trazabilidad institucional. | Bitacora `audit_log`. | `app/Services/AuditService.php`, `app/Models/AuditLog.php` |
| Pruebas de software | Verifican funcionalidades criticas. | Pest/PHPUnit y Playwright. | `tests/Feature/*`, `tests/e2e/*` |
| Integracion continua | Automatiza validaciones en repositorio. | Workflows de GitHub Actions. | `.github/workflows/*` |

---

# 12. Procedimiento y actividades realizadas

No se encontraron fechas exactas de cada actividad en una bitacora formal. Se puede reconstruir una cronologia tecnica con base en modulos, migraciones, documentacion y commits recientes (`git log`).

| Etapa | Actividad | Descripcion | Archivos relacionados | Resultado obtenido |
|---|---|---|---|---|
| 1 | Analisis de requerimientos | Se definio sistema academico para evidencias, asesorias, roles, ventanas y documentos. | `PROJECT_STATUS.md`, `docs/planes-implementacion/README.md`, `docs/HANDOFF-COMPANERO.md` | Contexto tecnico y plan de modulos. |
| 2 | Configuracion del stack | Configuracion Laravel, Inertia, Vue, TypeScript, Tailwind, Vite, Fortify. | `composer.json`, `package.json`, `vite.config.ts`, `config/fortify.php` | Base tecnologica del proyecto. |
| 3 | Diseno de base de datos | Creacion de tablas para usuarios, roles, departamentos, semestres, materias, evidencias, archivos y auditoria. | `database/migrations/*` | Modelo relacional funcional. |
| 4 | Implementacion de roles | Definicion de roles y middleware. | `app/Models/Role.php`, `app/Http/Middleware/CheckRole.php`, `bootstrap/app.php` | Control de acceso por perfil. |
| 5 | Autenticacion | Login, registro, verificacion, 2FA, social auth. | `config/fortify.php`, `app/Http/Controllers/Auth/SocialAuthController.php`, `resources/js/pages/auth/*` | Seguridad de acceso. |
| 6 | Administracion base | CRUD de departamentos, docentes, materias, rubros, semestres, cargas. | `app/Http/Controllers/Admin/*`, `resources/js/pages/Admin/*` | Captura manual de datos. |
| 7 | Matriz y ventanas | Configuracion de evidencias requeridas y periodos de entrega. | `RequirementController.php`, `SubmissionWindowController.php`, `Matrix.vue`, `Windows/Index.vue` | Reglas por semestre/departamento. |
| 8 | Flujo de evidencias | Inicializacion, carga, envio, revision, aprobacion, rechazo, final approval. | `Teacher/EvidenceController.php`, `Admin/ReviewController.php`, `EvidenceService.php`, `EvidenceFlowService.php` | Ciclo institucional de evidencias. |
| 9 | File Manager | Estructura de carpetas, permisos, subida, preview, descarga y reemplazo. | `FolderController.php`, `FileController.php`, `FolderStructureService.php`, `StorageService.php`, `FileManager/Index.vue` | Gestion documental integrada. |
| 10 | Editor DOCX | Apertura y edicion basica de DOCX con versionado. | `DocxEditorController.php`, `DocxEditorService.php`, `FileManager/DocxEditor.vue` | MVP de edicion documental. |
| 11 | Asesorias | Registro de sesiones y horarios de asesorias. | `Teacher/AdvisorySessionController.php`, `AdvisoryScheduleController.php`, `MyAdvisories.vue`, `Asesorias/Index.vue` | Modulo de asesorias academicas. |
| 12 | Reportes y seguimiento | Tabla de seguimiento, exportaciones, reportes por docente. | `SeguimientoDocente.vue`, `ReportController.php`, `Reports.vue` | Consulta administrativa. |
| 13 | Notificaciones | Notificaciones internas y scheduler. | `NotificationService.php`, `NotificationController.php`, `NotifyWindows.php`, `routes/console.php` | Alertas de sistema. |
| 14 | Pruebas | Feature tests, security tests, domain tests, e2e smoke. | `tests/Feature/*`, `tests/e2e/*` | Validacion automatizada. |
| 15 | Documentacion | Handoff, planes, operaciones, testing. | `docs/*`, `PROJECT_STATUS.md`, `MEMORY.md` | Base de transferencia tecnica. |
| 16 | Branding institucional | Remocion de textos starter y uso de logo institucional. | `resources/js/pages/Welcome.vue`, `public/images/logo-tecnologico-piedras-negras.svg`, `resources/js/components/AppLogoIcon.vue` | Interfaz mas lista para entrega. |

Commits recientes utiles como referencia:

- `1ef8666 Prepare institutional delivery workflow`
- `3ac57d5 feat: add social auth and semester workflow improvements`
- `9c91814 fix: unblock teacher uploads and provision semester folders`
- `2531eec feat: add versioned docx editor workflow`
- `fd1558b feat: extend evidence workflow for applicability and final approval`

---

# 13. Resultados obtenidos

## Resultados funcionales

- Sistema web con autenticacion y roles.
- Administracion de docentes, materias, rubros, departamentos, semestres, cargas, matriz y ventanas.
- Flujo de evidencias por docente con archivos y envio.
- Revision por oficina, rechazo con comentarios y visto bueno final.
- Seguimiento docente consolidado con filtros, exportacion e impresion.
- File manager con estructura institucional y permisos.
- Editor DOCX basico con versionado.
- Asesorias docentes y horarios.
- Reportes de oficina con exportacion CSV.
- Notificaciones internas.
- Auditoria de acciones relevantes.

## Resultados tecnicos

- Arquitectura Laravel + Inertia + Vue 3.
- Modelo relacional amplio con migraciones.
- Servicios de dominio para evidencia, flujo, storage, auditoria, notificaciones y carpetas.
- Policies para proteger archivos, carpetas y submissions.
- Pruebas automatizadas con Pest/PHPUnit y Playwright.
- Comando `residencia:bootstrap` para instalacion limpia sin datos demo.

## Resultados visuales o de interfaz

- Pantalla inicial institucional.
- Layout autenticado con menu lateral por rol.
- Tablas administrativas.
- Formularios CRUD.
- Modales/detalles de evidencias.
- File manager con arbol de carpetas.
- Editor DOCX web.
- Tablas exportables/imprimibles.

## Limitaciones

- No hay informacion personal/institucional completa para portada y secciones formales: `PENDIENTE`.
- El editor DOCX es MVP; no garantiza compatibilidad completa con todos los formatos avanzados de Word.
- Las notificaciones programadas requieren configuracion real de scheduler/queue.
- Las fuentes bibliograficas externas deben completarse manualmente con consulta oficial.
- No se encontro README principal.

## Mejoras futuras

- Completar manual formal de usuario con capturas reales.
- Agregar graficas en reportes.
- Refinar automatizacion de aplicabilidad por modalidad/materia.
- Fortalecer editor DOCX o integrarlo con una solucion especializada si se requiere fidelidad alta.
- Agregar instalador inicial visual para primer administrador.
- Completar documentacion de despliegue para servidor real.

---

# 14. Manual basico de uso del sistema

## Entrar al sistema

1. Abrir la URL local o de despliegue. En local puede ser `http://localhost` o dominio configurado por Herd.
2. Seleccionar iniciar sesion.
3. Ingresar correo y password.
4. Si aplica, completar 2FA.
5. El sistema redirige a `/dashboard`.

## Inicializar sistema limpio

1. Ejecutar migraciones:

```bash
php artisan migrate
```

2. Crear datos base institucionales:

```bash
php artisan residencia:bootstrap --admin-name="Jefe de Departamento" --admin-email="admin@residencia.test" --admin-password="PasswordSeguro123!" --department="Sistemas"
```

3. Entrar con el usuario creado.

## Navegar por modulos

- El menu se genera por rol desde `resources/js/config/menu.ts`.
- Docente ve evidencias, asesorias, file manager y seguimiento.
- Jefe de oficina ve revisiones, reportes, docentes, materias, auditoria y administracion compartida.
- Jefe de departamento ve semestres, ventanas, matriz, docentes, materias, cargas y seguimiento.

## Crear registros

- Departamentos: `/admin/departments`.
- Docentes: `/admin/teachers`.
- Materias: `/admin/subjects`.
- Rubros de evidencia: `/admin/evidence-items`.
- Semestres: `/admin/semesters`.
- Cargas academicas: `/admin/teaching-loads`.
- Ventanas: `/admin/windows`.

## Editar registros

- Usar botones de edicion en las tablas administrativas.
- Las rutas usan `PUT/PATCH` en recursos Laravel.

## Eliminar registros

- Usar botones de eliminar cuando esten disponibles.
- Algunas entidades bloquean eliminacion si tienen relaciones, por ejemplo materias con cargas y rubros usados en matriz/submissions.

## Subir archivos

1. Entrar a `/docente/evidencias` para flujo formal de evidencias o `/files/manager` para gestor documental.
2. Seleccionar evidencia/carpeta.
3. Adjuntar archivo permitido.
4. Guardar/subir.
5. En evidencias, enviar la entrega cuando tenga archivo.

## Exportar informacion

- Seguimiento docente: exportacion CSV/XLSX e impresion desde `resources/js/pages/SeguimientoDocente.vue`.
- Reportes oficina: exportacion CSV desde `app/Http/Controllers/Admin/ReportController.php`.

## Consultar informacion

- Dashboard: `/dashboard`.
- Seguimiento: `/asesorias`.
- Reportes: `/oficina/reportes`.
- Auditoria: `/admin/audits`.
- File Manager: `/files/manager`.

## Cerrar sesion

- Usar el menu de usuario en el layout autenticado. Archivos relacionados: `resources/js/components/NavUser.vue`, `resources/js/components/UserMenuContent.vue`.

---

# 15. Competencias desarrolladas o aplicadas

| Competencia | Como se aplico | Evidencia en el proyecto |
|---|---|---|
| Analisis de requerimientos | Identificacion de roles, evidencias, ventanas, revision, file manager y reportes. | `PROJECT_STATUS.md`, `docs/planes-implementacion/*` |
| Diseno de base de datos | Modelado de usuarios, roles, semestres, cargas, evidencias, archivos y auditoria. | `database/migrations/*`, `app/Models/*` |
| Programacion backend | Controladores, servicios, policies, comandos y jobs en Laravel. | `app/Http/Controllers/*`, `app/Services/*`, `app/Policies/*` |
| Programacion frontend | Pantallas Inertia/Vue con TypeScript y componentes UI. | `resources/js/pages/*`, `resources/js/components/*` |
| Seguridad y autorizacion | Middleware por rol, policies, 2FA, Socialite. | `app/Http/Middleware/CheckRole.php`, `app/Policies/*`, `config/fortify.php`, `SocialAuthController.php` |
| Gestion documental | File manager, carpetas, storage, preview, versionado DOCX. | `FileController.php`, `FolderController.php`, `StorageService.php`, `DocxEditorService.php` |
| Pruebas de software | Feature tests, security tests, e2e tests. | `tests/Feature/*`, `tests/e2e/*` |
| Resolucion de problemas | Correcciones de permisos de subida, migraciones pendientes, semestre activo, estructura de carpetas. | Tests como `TeacherFolderPermissionsTest.php`, `SemesterFolderProvisioningTest.php`, commits recientes. |
| Documentacion tecnica | Handoff, planes, runbooks y contexto. | `docs/HANDOFF-COMPANERO.md`, `docs/PROMPT-PARA-CODEX-COMPANERO.md`, `docs/operations/*` |
| Administracion del tiempo | Trabajo incremental por fases y prioridades. | `docs/planes-implementacion/*`, `PROJECT_STATUS.md` |
| Comunicacion con usuarios | Ajustes solicitados a UI, branding, flujo de entrega y reportes. | Cambios en `Welcome.vue`, `menu.ts`, vistas admin y file manager. |

---

# 16. Fuentes de informacion sugeridas

Fuentes internas ya existentes:

- `PROJECT_STATUS.md`
- `MEMORY.md`
- `docs/HANDOFF-COMPANERO.md`
- `docs/PROMPT-PARA-CODEX-COMPANERO.md`
- `docs/operations/BACKUP-RESTORE.md`
- `docs/operations/OBSERVABILIDAD-OPERATIVA.md`
- `docs/operations/RUNBOOK-RELEASE-ROLLBACK.md`
- `docs/testing/DOMINIO-MATRIZ-COBERTURA.md`
- `docs/testing/E2E-PLAYWRIGHT.md`
- `docs/planes-implementacion/*`

Fuentes externas sugeridas para citar, a verificar en redaccion final:

- Laravel. Documentacion oficial de Laravel 12. `PENDIENTE` completar URL/fecha de consulta.
- Laravel Fortify. Documentacion oficial. `PENDIENTE` completar URL/fecha de consulta.
- Laravel Socialite. Documentacion oficial. `PENDIENTE` completar URL/fecha de consulta.
- Inertia.js. Documentacion oficial. `PENDIENTE` completar URL/fecha de consulta.
- Vue.js. Documentacion oficial de Vue 3. `PENDIENTE` completar URL/fecha de consulta.
- TypeScript. Documentacion oficial. `PENDIENTE` completar URL/fecha de consulta.
- Tailwind CSS. Documentacion oficial. `PENDIENTE` completar URL/fecha de consulta.
- SQLite. Documentacion oficial. `PENDIENTE` completar URL/fecha de consulta.
- Playwright. Documentacion oficial. `PENDIENTE` completar URL/fecha de consulta.
- TecNM. Lineamientos o estructura oficial del reporte final de residencia. `PENDIENTE` agregar documento oficial usado.

Formato APA sugerido generico:

- Autor corporativo. (Año). *Titulo de la documentacion*. URL. Fecha de consulta: `PENDIENTE`.

---

# 17. Anexos recomendados

| Anexo | Contenido | Fuente o archivo | Motivo para incluirlo |
|---|---|---|---|
| Anexo A | Capturas de pantalla del sistema | `resources/js/pages/*` como guia de pantallas | Evidencia visual del resultado. |
| Anexo B | Diagrama entidad-relacion | `database/migrations/*`, `app/Models/*` | Explicar modelo de datos. |
| Anexo C | Diagrama de flujo de evidencias | `app/Services/EvidenceService.php`, `app/Services/EvidenceFlowService.php` | Explicar proceso institucional. |
| Anexo D | Codigo representativo de servicio de evidencias | `app/Services/EvidenceService.php` | Mostrar logica central. |
| Anexo E | Codigo representativo de File Manager | `app/Services/FolderStructureService.php`, `app/Http/Controllers/FileController.php` | Mostrar gestion documental. |
| Anexo F | Manual de usuario basico | Seccion 14 de este documento + capturas reales | Facilitar uso del sistema. |
| Anexo G | Evidencias de pruebas | `tests/Feature/*`, `tests/e2e/*`, salida de `php artisan test` | Validacion del desarrollo. |
| Anexo H | Documentacion tecnica/handoff | `docs/HANDOFF-COMPANERO.md` | Transferencia tecnica. |
| Anexo I | Configuracion de despliegue/local | `.env.example`, `composer.json`, `package.json` | Reproducibilidad. |
| Anexo J | Carta de autorizacion | PENDIENTE | Requerimiento institucional si aplica. |
| Anexo K | Registro de producto | PENDIENTE | Solo si aplica segun TecNM/empresa. |
| Anexo L | Enlace al repositorio | PENDIENTE confirmar URL final y permisos | Evidencia de codigo fuente. |

---

# 18. Informacion faltante para completar el reporte

- Nombre completo del alumno residente: `PENDIENTE`.
- Numero de control: `PENDIENTE`.
- Carrera: `PENDIENTE`.
- Instituto: aparece Tecnologico de Piedras Negras por branding, pero confirmar nombre oficial completo: `PENDIENTE`.
- Nombre oficial del proyecto segun anteproyecto: `PENDIENTE` confirmar si es solo `Residencia` o nombre mas formal.
- Asesor interno: `PENDIENTE`.
- Asesor externo: `PENDIENTE`.
- Empresa u organizacion receptora: `PENDIENTE`; el repo sugiere Instituto Tecnologico de Piedras Negras, pero se debe confirmar.
- Departamento o area de trabajo del residente: `PENDIENTE`.
- Puesto o actividades asignadas al residente: `PENDIENTE`.
- Periodo de residencia profesional: `PENDIENTE`.
- Fechas de inicio y fin: `PENDIENTE`.
- Lugar/ciudad: `PENDIENTE`.
- Agradecimientos: `PENDIENTE`.
- Resumen ejecutivo final redactado con datos reales: `PENDIENTE`.
- Proceso manual anterior y problemas reales de la institucion: `PENDIENTE`.
- Beneficiarios reales y numero aproximado de usuarios/docentes: `PENDIENTE`.
- Normatividad TecNM/interna aplicada: `PENDIENTE`.
- Actividades sociales realizadas en la empresa: `PENDIENTE`.
- Experiencia personal profesional adquirida: `PENDIENTE`.
- Fuentes externas formales con fecha de consulta: `PENDIENTE`.
- Capturas reales del sistema en funcionamiento: `PENDIENTE`.
- Diagramas finales: `PENDIENTE`.
- Carta de autorizacion de uso de informacion: `PENDIENTE`.
- Registro de producto, si aplica: `PENDIENTE`.
- URL del repositorio o evidencia de entrega: `PENDIENTE`.
- Datos de servidor/despliegue final: `PENDIENTE`.
- Credenciales demo para evaluacion, si se permiten: `PENDIENTE`.

---

# 19. Resumen compacto para ChatGPT

Proyecto: `Residencia`.

Descripcion breve: aplicacion web academica/institucional desarrollada con Laravel 12, Inertia.js 2, Vue 3 y TypeScript para gestionar evidencias docentes, asesorias, revisiones administrativas, ventanas de entrega, reportes, notificaciones, auditoria y archivos por semestre. El sistema usa roles `DOCENTE`, `JEFE_OFICINA` y `JEFE_DEPTO`.

Problema: el proyecto busca centralizar y controlar evidencias docentes y documentos academicos por semestre, evitando dispersion de archivos, falta de trazabilidad, dificultad de revision por roles y ausencia de reportes consolidados.

Objetivo general: desarrollar una aplicacion web para gestionar, organizar y dar seguimiento a evidencias docentes, asesorias academicas y revision institucional por semestre, mediante roles, ventanas de entrega, administracion documental, reportes y trazabilidad de estados.

Objetivos especificos:

1. Implementar autenticacion y control de acceso por roles.
2. Disenar base de datos para docentes, departamentos, semestres, materias, cargas, evidencias, archivos y revisiones.
3. Desarrollar CRUDs administrativos de docentes, materias, rubros, departamentos, semestres, cargas y ventanas.
4. Permitir al docente cargar, enviar y consultar evidencias conforme a matriz y ventanas.
5. Implementar revision de oficina, rechazo con comentarios y visto bueno final de jefe de departamento.
6. Integrar file manager con preview, descarga, reemplazo, permisos y edicion DOCX basica.
7. Generar reportes, exportaciones, impresion y auditoria.

Tecnologias: PHP 8.2, Laravel 12, Laravel Fortify, Laravel Socialite, Inertia.js 2, Vue 3, TypeScript, Tailwind CSS 4, Vite 7, SQLite, Ziggy, Wayfinder, reka-ui/shadcn-vue, Lucide, xlsx, Pest/PHPUnit, Playwright, Pint, ESLint, Prettier.

Modulos:

- Autenticacion, 2FA y login social.
- Dashboard por rol.
- CRUD de docentes, materias, rubros de evidencia, departamentos, semestres, cargas academicas y ventanas.
- Matriz de evidencias.
- Evidencias docentes.
- Revision de oficina y visto bueno final.
- Seguimiento docente consolidado.
- File Manager.
- Editor DOCX basico.
- Asesorias y horarios.
- Reportes.
- Notificaciones.
- Auditoria.

Base de datos: tablas principales `users`, `roles`, `departments`, `semesters`, `subjects`, `teaching_loads`, `evidence_categories`, `evidence_items`, `evidence_requirements`, `submission_windows`, `folder_nodes`, `evidence_submissions`, `evidence_files`, `evidence_reviews`, `evidence_status_history`, `resubmission_unlocks`, `audit_log`, `notifications`, `advisory_sessions`, `advisory_schedules`, `social_accounts`.

Actividades realizadas:

- Analisis tecnico y planificacion.
- Configuracion de Laravel/Inertia/Vue.
- Diseno de migraciones/modelos.
- Desarrollo de controladores y servicios.
- Implementacion de roles/policies.
- Desarrollo de UI por rol.
- Implementacion de evidencias, file manager, asesorias, reportes y auditoria.
- Implementacion de editor DOCX MVP.
- Pruebas automatizadas y documentacion tecnica.
- Preparacion de flujo de instalacion limpia con `php artisan residencia:bootstrap`.

Resultados:

- Sistema funcional con flujo institucional de evidencias.
- Administracion manual de datos base.
- Seguimiento y reportes.
- File manager con permisos.
- Pruebas automatizadas.
- Documentacion tecnica.

Evidencias disponibles:

- Pantallas Vue en `resources/js/pages/*`.
- Controladores en `app/Http/Controllers/*`.
- Servicios en `app/Services/*`.
- Modelos en `app/Models/*`.
- Migraciones en `database/migrations/*`.
- Tests en `tests/Feature/*` y `tests/e2e/*`.
- Documentacion en `docs/*`.

Pendientes para reporte final:

- Datos del alumno, numero de control, carrera, asesores, empresa/area, periodo y fechas.
- Agradecimientos, experiencia personal y actividades sociales.
- Capturas reales del sistema.
- Diagramas finales.
- Fuentes externas con formato APA y fecha de consulta.
- Carta de autorizacion y registro de producto si aplica.

