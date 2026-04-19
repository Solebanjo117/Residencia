# Diagnostico Tecnico Del Estado Real Y Plan De Endurecimiento (2026-04-18)

## 1. Objetivo

Documentar el estado real del sistema Residencia con base en codigo vivo (backend, rutas, policies, frontend y pruebas), identificar discrepancias contra documentacion interna y dejar un plan seguro para la prioridad:

- Blindar adjuntos de asesorias (evitar acceso publico por /storage).

Este documento no propone reescritura del sistema ni cambio de stack.

---

## 2. Fuente De Verdad Y Metodo

Se uso esta prioridad:

1. Codigo actual
2. Rutas activas
3. Controladores/Servicios/Policies/Modelos
4. Paginas frontend
5. Pruebas
6. Documentacion interna

### Archivos base auditados (extracto principal)

- routes/web.php
- routes/console.php
- app/Providers/FortifyServiceProvider.php
- app/Services/EvidenceService.php
- app/Services/EvidenceFlowService.php
- app/Http/Controllers/Admin/AdvisoryController.php
- app/Http/Controllers/Admin/ReviewController.php
- app/Http/Controllers/Teacher/EvidenceController.php
- app/Http/Controllers/Teacher/AdvisorySessionController.php
- app/Http/Controllers/FileController.php
- app/Http/Controllers/FolderController.php
- app/Http/Controllers/DocxEditorController.php
- app/Services/StorageService.php
- app/Services/FolderStructureService.php
- app/Services/DocxEditorService.php
- app/Policies/EvidenceSubmissionPolicy.php
- app/Policies/EvidenceFilePolicy.php
- app/Policies/FolderNodePolicy.php
- app/Policies/AdvisorySessionPolicy.php
- resources/js/pages/SeguimientoDocente.vue
- resources/js/pages/Teacher/Evidencias/Index.vue
- resources/js/pages/Docente/MyAdvisories.vue
- resources/js/pages/FileManager/Index.vue
- resources/js/pages/FileManager/DocxEditor.vue
- resources/js/pages/Oficina/ReviewDetail.vue
- resources/js/pages/Dashboard.vue
- tests/Feature/Domain/EvidenceStatusTransitionsTest.php
- tests/Feature/Security/FileManagerUploadWorkflowTest.php
- tests/Feature/Security/AsesoriasReviewAuthorizationTest.php
- docs/HANDOFF-COMPANERO.md

---

## 3. Resumen Ejecutivo Del Estado Real

## 3.1 Autenticacion

- Fortify y 2FA: operativos.
- Login social Google/Socialite: no se detecta implementacion activa en codigo actual.

Evidencia:

- Fortify activo y autenticacion custom con is_active en app/Providers/FortifyServiceProvider.php.
- 2FA habilitado en config/fortify.php.
- No existen SocialAuthController, SocialProviderRegistry ni rutas /auth/{provider} en codigo actual.
- composer.json no incluye laravel/socialite en dependencias actuales.

## 3.2 Flujo De Evidencias

- Inicializacion docente si existe en ruta y controlador.
- Cambios de estado institucional pasan por EvidenceService::changeStatus().
- Seguimiento de disponibilidad y etapas vive en EvidenceFlowService.

Evidencia:

- Ruta POST docente/evidencias/init registrada en routes/web.php.
- Transiciones definidas en ALLOWED_TRANSITIONS dentro de app/Services/EvidenceService.php.
- UI status y availability en app/Services/EvidenceFlowService.php.
- Pruebas de transicion validan bloqueos criticos (draft->approved, approved->submitted).

## 3.3 Seguimiento Docente (/asesorias)

- Vista unificada activa en SeguimientoDocente.vue.
- Review y final approval cuentan con middleware de rol y authorize en controlador.

Evidencia:

- routes/web.php define middleware JEFE_OFICINA para review y JEFE_DEPTO para final approval.
- app/Http/Controllers/Admin/AdvisoryController.php usa authorize('review') y authorize('finalApprove').
- tests/Feature/Security/AsesoriasReviewAuthorizationTest.php cubre permisos por rol.

## 3.4 File Manager + DOCX

- File Manager opera con policy + StorageService + audit en flujos principales.
- No se detecta auto-cambio a SUBMITTED al subir desde file manager (permanece DRAFT).
- Editor DOCX esta activo con versionado y cobertura de pruebas.

Evidencia:

- app/Http/Controllers/FileController.php crea submission en DRAFT si no existe.
- tests/Feature/Security/FileManagerUploadWorkflowTest.php verifica que upload no auto-submite.
- app/Http/Controllers/DocxEditorController.php + app/Services/DocxEditorService.php.
- tests/Feature/FileManager/DocxEditorWorkflowTest.php cubre round-trip con imagenes/listas/tipografia.

## 3.5 Semestres Y Ventanas

- Flujo actual usa patron OPEN o latest en controladores.
- No existe metodo Semester::activeOrLatest() en el modelo actual.
- Regla "solo un OPEN" no esta reforzada por constraint DB ni cierre automatico en controlador de semestre.

Evidencia:

- app/Models/Semester.php no tiene activeOrLatest().
- Multiples controladores consultan Semester::where('status', 'OPEN')->first() ?? latest.
- app/Http/Controllers/Admin/SemesterController.php valida OPEN/CLOSED pero no cierra otros OPEN automaticamente.
- migration de semesters define enum OPEN/CLOSED pero sin unicidad por estado OPEN.

## 3.6 Notificaciones Programadas

- Existe scheduler para notify:windows cada 5 min.
- Coexisten comando y job programado (duplicidad de estrategia).

Evidencia:

- routes/console.php agenda Schedule::command('notify:windows')->everyFiveMinutes().
- app/Console/Commands/NotifyWindows.php y app/Jobs/SendScheduledNotificationsJob.php coexistentes.

---

## 4. Hallazgo Critico Vigente (Prioridad P0 Operativa)

## Adjuntos de asesorias expuestos por storage publico

Estado actual:

- Se suben a disk public.
- Frontend usa enlace directo /storage/{stored_relative_path}.
- No hay endpoint dedicado de descarga autorizada para AdvisoryFile.
- No hay policy especifica para AdvisoryFile.

Evidencia:

- app/Http/Controllers/Teacher/AdvisorySessionController.php (storeAs en disk public).
- resources/js/pages/Docente/MyAdvisories.vue (href '/storage/' + stored_relative_path).
- app/Policies/AdvisorySessionPolicy.php controla sesiones, no adjuntos por archivo.

Impacto:

- Exposicion de archivos si se conoce o deduce la ruta.
- Falta de auditoria de descarga de adjuntos de asesoria.
- Inconsistencia respecto al modelo seguro de EvidenceFile (descarga via controlador + policy + auditoria).

---

## 5. Discrepancias Documentacion Vs Codigo

## 5.1 Handoff reporta Socialite funcional

Documentacion:

- docs/HANDOFF-COMPANERO.md menciona login social Google y archivos de social auth.

Codigo real:

- No se encuentran controladores/rutas/modelos de social auth activos.
- composer.json no contiene laravel/socialite.

Decision tecnica:

- Tomar codigo como estado vigente.
- Marcar seccion de login social del handoff como desactualizada.

## 5.2 activeOrLatest reportado como helper institucional

Contexto funcional solicitado:

- Se menciona Semester::activeOrLatest() como default.

Codigo real:

- No existe ese metodo en app/Models/Semester.php.
- El comportamiento equivalente se implementa repetidamente en controladores.

Decision tecnica:

- Mantener realidad de codigo actual.
- Si se desea consolidar, hacerlo como mejora posterior y con pruebas.

## 5.3 Agrupacion visual semestres activos/no activos en File Manager

Contexto funcional solicitado:

- Se describe agrupacion en arbol por semestres activos/no activos.

Codigo real:

- El arbol se renderiza por estructura de folderTree sin categoria visual explicita por estado de semestre.

Decision tecnica:

- Tratarlo como oportunidad UX posterior, no como bug bloqueante para seguridad.

---

## 6. Acoplamientos Y Riesgos Colaterales Reales

## 6.1 Policies y seguridad

- Si se endurecen adjuntos de asesorias, hay que mantener alcance por rol y por departamento (JEFE_DEPTO).
- Riesgo: 403 falsos positivos para usuarios legitimos si no se replica logica de scope.

## 6.2 Estado de submission

- La prioridad de adjuntos de asesorias no debe tocar ALLOWED_TRANSITIONS ni workflow de evidence_submissions.
- Riesgo: mezclar cambios de seguridad de asesorias con refactor de estado puede ampliar superficie de regresion.

## 6.3 Semestres historicos y activos

- Los paths de asesorias usan semestre en carpeta, por lo que migracion debe preservar consistencia historica.
- Riesgo: romper referencias de archivos antiguos si solo se cambia frontend y no se migra almacenamiento.

## 6.4 File manager y versionado

- EvidenceFile ya tiene flujo privado/auditado; conviene emular patron, no duplicar logica insegura.
- Riesgo: introducir un mini file manager paralelo para asesorias en vez de endpoint puntual seguro.

## 6.5 Pruebas

- Hay buena cobertura en evidencias/revision/file manager/docx.
- Hay hueco de cobertura especifica para advisory_files (acceso y descarga).

---

## 7. Archivos Candidatos A Tocar (Para Prioridad Adjuntos Asesorias)

Objetivo: cerrar acceso publico directo y pasar a descarga autorizada.

1. app/Http/Controllers/Teacher/AdvisorySessionController.php
2. routes/web.php
3. app/Policies/AdvisorySessionPolicy.php (o nueva policy de AdvisoryFile)
4. app/Services/StorageService.php (helper de validacion de path de asesorias)
5. resources/js/pages/Docente/MyAdvisories.vue
6. tests/Feature/Security/AsesoriasReviewAuthorizationTest.php (extender)
7. tests/Feature/Security/AdvisoryFilesAccessTest.php (nuevo recomendado)

Nota:

- Si se decide separar responsabilidades, crear:
  - app/Http/Controllers/AdvisoryFileController.php
  - app/Policies/AdvisoryFilePolicy.php

---

## 8. Plan De Implementacion Seguro Por Fases

## Fase 0 - Preparacion Y Corte De Riesgo

- Inventariar advisory_files existentes y validar paths actuales.
- Definir politica final de acceso por rol:
  - DOCENTE: solo propios.
  - JEFE_OFICINA: total.
  - JEFE_DEPTO: solo docentes dentro de sus departamentos.
- Definir estrategia de migracion de archivos historicos public->private.

Resultado esperado:

- Matriz de permisos y lista de archivos migrables sin perdida.

## Fase 1 - Endurecimiento De Descarga

- Agregar endpoint autenticado de descarga para advisory files.
- Validar autorizacion por policy en endpoint.
- Registrar auditoria de descargas.

Resultado esperado:

- Ningun adjunto depende de URL publica para acceso legitimo.

## Fase 2 - Cambio De Upload y Frontend

- Guardar nuevos adjuntos en almacenamiento privado.
- Cambiar links en MyAdvisories.vue para usar ruta segura.
- Mantener compatibilidad temporal para registros previos mientras migra.

Resultado esperado:

- Nuevos archivos nacen seguros y UI usa solo endpoints controlados.

## Fase 3 - Migracion Historica

- Mover fisicamente adjuntos de asesorias desde public a private.
- Actualizar stored_relative_path si aplica.
- Verificar checksums/tamano y accesibilidad por rol.

Resultado esperado:

- Inventario historico protegido y consistente.

## Fase 4 - Pruebas y Validacion Operativa

- Agregar/ajustar pruebas feature de acceso por rol.
- Ejecutar suites minimas:
  - pruebas de seguridad relacionadas
  - pruebas de seguimiento/revision
  - smoke de file manager/docx para evitar regresion transversal
- Validar manual en UI con usuario DOCENTE, JEFE_OFICINA y JEFE_DEPTO.

Resultado esperado:

- Cambio de seguridad implementado sin romper flujo institucional.

---

## 9. Criterios De Aceptacion Para La Prioridad

1. No existe consumo funcional de /storage/... para adjuntos de asesorias.
2. Descarga de adjuntos exige auth y autorizacion por rol/scope.
3. Se audita descarga de adjuntos (registro en audit_log).
4. DOCENTE no puede descargar archivos de otros docentes.
5. JEFE_DEPTO respeta alcance por departamento.
6. No se rompe:
   - flujo de evidencias
   - seguimiento docente
   - file manager/docx

---

## 10. Estado De Ejecucion De Esta Auditoria

- Se realizo auditoria en modo lectura.
- No se aplicaron cambios de codigo en esta etapa.
- Este documento sirve como base para iniciar implementacion controlada.

---

## 11. Recomendacion Inmediata

Ejecutar primero la prioridad de seguridad de adjuntos de asesorias (Fases 0-2) antes de atacar mejoras de heuristica carpeta->rubro o unificacion de notificaciones, porque es el riesgo con mayor impacto institucional y menor costo de intervencion focalizada.

---

## 12. Anexo De Contexto Operativo (Audio Transcrito 2026-04-15)

Este anexo captura contexto funcional compartido en audio por usuarios operativos.

Importante:

- Este contenido es contexto de negocio y no reemplaza la validacion en codigo.
- Debe tomarse como insumo para backlog funcional y ajuste de reportes.

### 12.1 Reglas de negocio descritas en el audio

1. Dentro del bloque operativo de asesorias (identificado visualmente como azul) existen tres subacciones bajo un mismo proyecto:
  - asesorias de clase
  - tutorias
  - residencias
2. Un docente puede tener una combinacion parcial de esas subacciones.
3. En reportes institucionales, la redaccion por docente debe reflejar solo lo que realmente aplica.
4. Se requiere un concentrado de apoyo para identificar que docentes tienen tutorados y cuales tienen residentes.
5. Se trabaja con un machote consolidado y varias hojas de apoyo (incluyendo concentrados y seguimiento).
6. En el seguimiento operativo:
  - hay actividades que nacen por solicitud (por ejemplo, material didactico o curso)
  - hay actividades obligatorias sin solicitud previa
7. En el segundo seguimiento se revisa avance al 50 por ciento para reporte intermedio.
8. Se busca que el sistema actualice estatus automaticamente para evitar revision manual repetitiva de casos pendientes.
9. Se solicita capacidad administrativa para activar nuevamente a un docente por semestre desde el directorio cuando regresa.

### 12.2 Implicaciones funcionales para Residencia

1. Reporteria:
  - El machote de reporte debe soportar redaccion condicional por docente.
  - No debe mostrar tutorias o residencias cuando no existan para ese docente.
2. Aplicabilidad:
  - La logica de aplica/no aplica podria requerir mayor granularidad en asesorias, separando subacciones.
3. Seguimiento:
  - El tablero debe distinguir con claridad avance por etapa (incluyendo el corte del 50 por ciento) y estatus por subaccion cuando aplique.
4. Operacion administrativa:
  - El modulo de docentes debe contemplar reactivacion controlada por semestre con trazabilidad.
5. Datos de apoyo:
  - El concentrado de tutorados y residentes debe considerarse como fuente de entrada para el reporte final.

### 12.3 Relacion con prioridades actuales

Este anexo no cambia la prioridad P0 de seguridad de adjuntos de asesorias. Sin embargo, agrega una linea funcional para la siguiente etapa:

1. Ajustar modelo de seguimiento para cubrir subacciones de asesorias (clase, tutorias, residencias) con aplicabilidad por docente.
2. Incorporar redaccion condicional en reportes para evitar inconsistencias narrativas.
3. Alinear activacion de docentes por semestre con flujo administrativo existente.

### 12.4 Nota de validacion requerida

Antes de convertir este anexo en desarrollo, validar con responsables operativos:

1. Definicion exacta de cada subaccion y sus evidencias minimas.
2. Regla oficial de cuando una subaccion es obligatoria vs por solicitud.
3. Formato final esperado del machote y campos condicionales obligatorios.
4. Criterio exacto para reactivacion de docentes por semestre (quien autoriza, cuando aplica, y auditoria requerida).
