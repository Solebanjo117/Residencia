# Proyecto Residencia - Estado Completo

**Fecha de actualizacion**: 2026-04-12  
**Tipo de corte**: Auditoria tecnica integral + priorizacion ejecutable  
**Stack**: Laravel 12 + PHP 8.2 + Inertia.js v2 + Vue 3 + TypeScript + Tailwind CSS v4 + shadcn-vue

---

## Resumen Ejecutivo

Sistema ASAD para gestion de evidencias docentes por semestre, con roles institucionales:
- Docente: carga y envio de evidencias, registro de asesorias.
- Jefe de Oficina: revision y dictamen de evidencias.
- Jefe de Departamento: configuracion academica y supervision.

El proyecto tiene base funcional estable, pero la auditoria detecto brechas criticas en seguridad del flujo de revision y en consistencia de estados de evidencias. Estas brechas impactan directamente reglas institucionales.

---

## Estado General (Semaforo)

- Seguridad de flujo de evidencias: ROJO
- Reglas de negocio de transiciones: ROJO
- Notificaciones programadas: AMARILLO
- UX y reportes administrativos: AMARILLO
- Build y pruebas base: VERDE

---

## Validacion Tecnica Ejecutada

### Resultado de comandos

- `php artisan test`: OK, 41 pruebas en verde.
- `npm run build`: OK, compilacion completa sin errores.
- `composer test`: FAIL por estilo (Pint), 75 issues de formato.

### Observacion de cobertura

Las pruebas actuales cubren principalmente auth/settings/base dashboard. No hay cobertura robusta para:
- flujo completo de evidencias,
- autorizacion por rol en endpoints de negocio,
- ventanas de entrega y prorrogas,
- auditoria/historial de estados.

---

## Hallazgos Prioritarios (Actualizado)

## P0 - Bloqueantes (corregir primero)

### 1) Bypass de autorizacion en revision de evidencias
**Problema**: existe un endpoint de revision en ruta global autenticada sin middleware de rol y sin `authorize()` explicito en el controlador.

**Impacto**:
- Riesgo de que usuarios no autorizados alteren estados institucionales.
- Incumplimiento de regla "solo Jefe de Oficina revisa".

**Referencias**:
- `routes/web.php` (`POST /asesorias/{submission}/review`)
- `app/Http/Controllers/Admin/AdvisoryController.php` (`reviewEvidence`)

---

### 2) File Manager salta el workflow institucional y fuerza estado SUBMITTED
**Problema**: en carga por gestor de archivos se crea/recupera submission y se fuerza `status = SUBMITTED` sin pasar por `EvidenceService::changeStatus()`.

**Impacto**:
- Se omiten validaciones de transicion.
- Se rompe trazabilidad completa de cambios de estado.
- Se permite ruta alternativa fuera de las reglas de ventana/prorroga.

**Referencias**:
- `app/Http/Controllers/FileController.php`
- `app/Services/EvidenceService.php`

---

### 3) Matriz de transiciones demasiado permisiva
**Problema**: `ALLOWED_TRANSITIONS` permite saltos no institucionales (ej.: `DRAFT -> APPROVED`, `DRAFT -> NA`, `APPROVED -> SUBMITTED`).

**Impacto**:
- Riesgo de estados invalidos para auditoria institucional.
- Inconsistencia en reportes de cumplimiento.

**Referencias**:
- `app/Services/EvidenceService.php`

---

## P1 - Alta (iteracion inmediata)

### 4) Flujo docente incompleto: `initSubmission()` no enrutable
**Problema**: existe metodo para inicializar entrega, pero no hay ruta publicada. En UI hay tareas con `submission.id = null` y acciones que dependen de ese id.

**Impacto**:
- Bloqueo operativo en tareas nuevas.
- Fallos intermitentes en subida/envio desde la pantalla de evidencias.

**Referencias**:
- `app/Http/Controllers/Teacher/EvidenceController.php`
- `resources/js/pages/Teacher/Evidencias/Index.vue`
- `routes/web.php`

---

### 5) Notificaciones programadas no operativas de extremo a extremo
**Problema**:
- `SendScheduledNotificationsJob` no esta integrado en scheduler.
- comando `notify:windows` usa `teaching_loads.user_id` pero el schema define `teacher_user_id`.

**Impacto**:
- Recordatorios automaticos no confiables o no ejecutados.

**Referencias**:
- `app/Jobs/SendScheduledNotificationsJob.php`
- `app/Console/Commands/NotifyWindows.php`
- `routes/console.php`
- `database/migrations/2026_02_27_120003_create_academic_tables.php`

---

### 6) Error potencial en eliminacion de departamentos
**Problema**: `DepartmentController::destroy()` usa relaciones no definidas en `Department` (`requirements()`, `teachers()`).

**Impacto**:
- Error runtime al eliminar departamento.

**Referencias**:
- `app/Http/Controllers/Admin/DepartmentController.php`
- `app/Models/Department.php`

---

### 7) Prorrogas sin expiracion no consideradas en controlador docente
**Problema**: la relacion de unlock permite `expires_at = null` como activa, pero el controlador solo consulta `expires_at > now()`.

**Impacto**:
- Docente puede quedar bloqueado aun con desbloqueo valido.

**Referencias**:
- `app/Models/EvidenceSubmission.php`
- `app/Http/Controllers/Teacher/EvidenceController.php`

---

## P2 - Media (estabilidad y consistencia)

### 8) Inconsistencia de formatos de archivo
**Problema**:
- UI/controlador permiten `zip,rar`.
- `StorageService` rechaza esos formatos.

**Impacto**:
- Mala experiencia de usuario y errores evitables.

**Referencias**:
- `resources/js/pages/Teacher/Evidencias/Index.vue`
- `app/Http/Controllers/Teacher/EvidenceController.php`
- `app/Services/StorageService.php`

---

### 9) Alcance de JEFE_DEPTO inconsistente
**Problema**: `StorageService::getAccessibleRoots()` devuelve todo para JEFE_DEPTO, mientras `FolderNodePolicy` intenta filtrar por departamento.

**Impacto**:
- Riesgo de sobreexposicion de estructura.
- Comportamiento no predecible entre arbol y autorizacion puntual.

**Referencias**:
- `app/Services/StorageService.php`
- `app/Policies/FolderNodePolicy.php`

---

### 10) Ventanas sin control anti-solapamiento
**Problema**: falta regla para evitar ventanas activas duplicadas/solapadas por semestre + evidencia.

**Impacto**:
- Ambiguedad de apertura/cierre.
- Dificultad para validar envios fuera de tiempo.

**Referencias**:
- `app/Http/Controllers/Admin/SubmissionWindowController.php`
- `database/migrations/2026_02_27_120005_create_requirements_storage_tables.php`

---

### 11) Seeders fragiles para ambientes nuevos
**Problema**:
- `DatabaseSeeder` crea usuario base sin `role_id`.
- Seeders usan `role_id = 1` hardcodeado.

**Impacto**:
- Setup inicial inestable.
- Dependencia de orden/ids en base de datos.

**Referencias**:
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/SeguimientoSeeder.php`
- `database/seeders/FolderStructureSeeder.php`

---

## P3 - Baja (producto/UX/orden tecnico)

- `resources/js/pages/Dashboard.vue` sigue como placeholder de bajo valor operativo.
- `resources/js/pages/Oficina/Reports.vue` continua como stub basico.
- Duplicidad funcional entre `Asesorias.vue` y `Asesorias2.vue`.
- Mezcla de idioma ES/EN en varias vistas administrativas.

---

## Brechas Contra Reglas Institucionales

### Regla: solo Jefe de Oficina puede revisar/decidir
- Estado: INCUMPLIDA (hay endpoint sin blindaje de rol/policy).

### Regla: todo cambio de estado debe quedar en historial + auditoria
- Estado: PARCIAL (flujo via `EvidenceService` cumple; flujo via File Manager no).

### Regla: docente solo modifica en DRAFT/REJECTED y bajo ventana/prorroga
- Estado: PARCIAL (flujo docente principal valida; flujo alterno de archivos altera estado).

### Regla: trazabilidad y control institucional completos
- Estado: PARCIAL (audit trail existe, pero con rutas que pueden saltar flujo formal).

---

## Plan de Ejecucion por Fases (Priorizado)

> Planes individuales listos para ejecucion: `docs/planes-implementacion/`

### Lista de Priorizacion Consolidada
1. P0 (bloqueante): seguridad de revision, consistencia de workflow de estados, matriz de transiciones.
2. P1 (alta): continuidad operativa del flujo docente, notificaciones programadas, errores runtime en administracion.
3. P2 (media): consistencia funcional y de datos (formatos, permisos, ventanas, seeders).
4. P3 (baja): deuda de producto/UX (dashboard, reportes, duplicidad de pantallas, idioma).

### Fase 0 - Contencion de Riesgo (48 horas)
**Objetivo**: cerrar brechas que comprometen reglas institucionales y trazabilidad.

**Alcance**:
1. Blindar endpoint de revision para que solo Jefe de Oficina pueda decidir estados.
2. Forzar que cualquier cambio de estado pase por `EvidenceService::changeStatus()`.
3. Ajustar `ALLOWED_TRANSITIONS` al flujo institucional real.

**Entregables**:
1. Endpoint de revision protegido por middleware + policy + prueba de autorizacion.
2. Refactor de File Manager sin cambios directos de estado fuera de servicio.
3. Matriz de transiciones endurecida + pruebas de transiciones validas/no validas.

**Criterio de salida**:
1. No existe ruta funcional que cambie estado sin pasar por servicio.
2. Usuario no autorizado no puede revisar/aprobar/rechazar.
3. Se registra historial y auditoria en cada transicion.

### Fase 1 - Estabilizacion Operativa (Semana 1)
**Objetivo**: eliminar bloqueos de uso diario en el flujo docente y en procesos automaticos.

**Alcance**:
1. Publicar y conectar `initSubmission` (o estrategia equivalente de auto-init segura).
2. Corregir pipeline de notificaciones programadas (scheduler + columna correcta en query).
3. Corregir `DepartmentController::destroy()` y relaciones faltantes en `Department`.
4. Unificar logica de prorrogas para considerar `expires_at` nulo como activo.

**Entregables**:
1. Flujo de evidencias sin `submission.id` nulo bloqueante.
2. Notificaciones programadas ejecutando en entorno de prueba.
3. Eliminacion de departamentos sin error runtime.

**Criterio de salida**:
1. Docente puede iniciar, subir y enviar evidencia de punta a punta.
2. Tarea programada marca schedules como enviados y crea notificaciones.
3. Operaciones admin criticas sin excepciones por relaciones ausentes.

### Fase 2 - Consistencia de Dominio y Datos (Semana 2)
**Objetivo**: alinear comportamiento del sistema entre capas (UI, controlador, servicio, policy, BD).

**Alcance**:
1. Unificar formatos permitidos entre UI, validaciones y `StorageService`.
2. Definir criterio unico de alcance JEFE_DEPTO en arbol y policies.
3. Implementar validacion anti-solapamiento para ventanas por semestre + evidencia.
4. Rehacer seeders para arranque limpio sin ids hardcodeados.

**Entregables**:
1. Matriz de formatos documentada y aplicada en todos los puntos.
2. Permisos de carpetas consistentes y verificables.
3. Restriccion de ventanas duplicadas activa.
4. Seeders funcionales para entorno nuevo.

**Criterio de salida**:
1. No hay discrepancias entre lo que la UI permite y lo que backend acepta.
2. JEFE_DEPTO ve exactamente lo definido por negocio.
3. Instalacion desde cero deja sistema listo para operar.

### Fase 3 - Producto y Experiencia (Semana 3)
**Objetivo**: cerrar deuda visible para usuarios finales y jefaturas.

**Alcance**:
1. Implementar dashboard principal real (`resources/js/pages/Dashboard.vue`).
2. Implementar reportes de oficina funcionales (`resources/js/pages/Oficina/Reports.vue`).
3. Consolidar `Asesorias.vue` y `Asesorias2.vue` en un solo flujo mantenible.
4. Normalizar idioma de interfaz (ES/EN).

**Entregables**:
1. Dashboard con KPIs y accesos rapidos.
2. Reportes exportables con filtros minimos.
3. Una sola vista de asesorias con persistencia real de estados.

**Criterio de salida**:
1. Jefe de Oficina puede consultar y exportar reportes.
2. Usuario no ve placeholders en rutas core.
3. Menor costo de mantenimiento en frontend.

### Fase 4 - Hardening QA y Release (Semana 4)
**Objetivo**: consolidar calidad y prevenir regresiones.

**Alcance**:
1. Agregar pruebas de dominio para evidencias, ventanas y autorizacion por rol.
2. Integrar validaciones de estilo como gate de CI (`pint --test`).
3. Definir checklist de release por rol (Docente, Jefe Oficina, Jefe Depto).

**Entregables**:
1. Suite de pruebas de negocio (no solo auth/settings).
2. Pipeline de CI con estado claro (test + lint + build).
3. Acta de criterios de salida para pase a produccion.

**Criterio de salida**:
1. Sin regresiones en flujos criticos de evidencias.
2. Build y pruebas verdes en pipeline.
3. Riesgo operativo residual documentado y aceptado.

### Backlog Detallado por Tareas e Investigacion

## Fase 0 - P0 (Bloqueantes)

### ASAD-P0-01 - Blindaje de endpoint de revision
**Objetivo**: impedir que usuarios no autorizados revisen evidencias.

**Investigacion requerida**:
1. Mapear todas las rutas que cambian estado de evidencia.
2. Verificar middleware real aplicado en `routes/web.php`.
3. Confirmar uso de `authorize()` o policy en `reviewEvidence`.
4. Revisar si existen rutas alternas desde `SeguimientoDocente`.

**Implementacion**:
1. Mover endpoint al grupo de `role:JEFE_OFICINA` o agregar middleware puntual.
2. Aplicar policy explicita para accion de revision.
3. Validar que solo `SUBMITTED` pueda revisarse.

**Validacion**:
1. Prueba feature: DOCENTE y JEFE_DEPTO reciben 403.
2. Prueba feature: JEFE_OFICINA puede aprobar/rechazar.

**Dependencias**: ninguna.  
**Estimacion**: 4 a 6 horas.

---

### ASAD-P0-02 - Refactor File Manager para no forzar estados
**Objetivo**: que el gestor de archivos no rompa el workflow institucional.

**Investigacion requerida**:
1. Localizar todos los `update(['status' => ...])` fuera de `EvidenceService`.
2. Revisar flujo completo de `FileController::store/replace/destroy`.
3. Confirmar impacto en historial (`evidence_status_history`) y auditoria (`audit_log`).

**Implementacion**:
1. Eliminar cambios directos de estado desde `FileController`.
2. Encaminar transiciones por `EvidenceService::changeStatus()` cuando aplique.
3. Respetar ventana/prorroga antes de permitir envio/subida.

**Validacion**:
1. Prueba feature: subir archivo no cambia a `SUBMITTED` automaticamente.
2. Prueba feature: cada cambio de estado crea historial + audit log.

**Dependencias**: ASAD-P0-03.  
**Estimacion**: 6 a 10 horas.

---

### ASAD-P0-03 - Endurecimiento de transiciones de estado
**Objetivo**: alinear `ALLOWED_TRANSITIONS` con flujo institucional.

**Investigacion requerida**:
1. Definir transiciones validas con negocio (DRAFT, SUBMITTED, APPROVED, REJECTED, NA, NE).
2. Revisar todos los puntos que llaman `changeStatus()`.
3. Detectar transiciones historicas existentes que podrian dejar datos huerfanos.

**Implementacion**:
1. Ajustar matriz `ALLOWED_TRANSITIONS`.
2. Agregar mensajes de error claros por transicion invalida.
3. Cubrir flujos de rechazo + reenvio.

**Validacion**:
1. Pruebas unitarias por cada transicion permitida/no permitida.
2. Prueba de regresion de revision (approve/reject/NA/NE).

**Dependencias**: ninguna.  
**Estimacion**: 4 a 8 horas.

---

## Fase 1 - P1 (Alta)

### ASAD-P1-01 - Habilitar flujo `initSubmission`
**Objetivo**: eliminar bloqueos por `submission.id` nulo en UI docente.

**Investigacion requerida**:
1. Revisar tareas sin submission en `Teacher/Evidencias/Index`.
2. Confirmar si `initSubmission` tiene ruta expuesta.
3. Validar impacto en `firstOrCreate` y unicidad de `evidence_submissions`.

**Implementacion**:
1. Exponer ruta POST para `initSubmission`.
2. Integrar en UI boton/accion de inicializacion por tarea.
3. Controlar idempotencia para evitar duplicados.

**Validacion**:
1. Prueba feature: tarea nueva crea submission DRAFT.
2. Prueba UI/feature: luego permite upload y submit normal.

**Dependencias**: ASAD-P0-02.  
**Estimacion**: 5 a 8 horas.

---

### ASAD-P1-02 - Scheduler + fix de notificaciones programadas
**Objetivo**: activar notificaciones automáticas sin fallos de columna.

**Investigacion requerida**:
1. Revisar comando `notify:windows` y uso de columnas en `teaching_loads`.
2. Verificar programacion en `routes/console.php`.
3. Validar compatibilidad enum `notification_type` con tabla `notification_schedules`.

**Implementacion**:
1. Corregir `user_id` por `teacher_user_id` donde corresponda.
2. Registrar schedule del comando/job con frecuencia definida.
3. Ajustar validacion de tipos de notificacion programable.

**Validacion**:
1. Prueba de comando: schedules vencidos crean notificaciones y marcan `is_sent=true`.
2. Prueba de no duplicacion en ejecuciones consecutivas.

**Dependencias**: ninguna.  
**Estimacion**: 6 a 10 horas.

---

### ASAD-P1-03 - Fix relaciones `Department` y delete seguro
**Objetivo**: evitar error runtime en eliminacion de departamentos.

**Investigacion requerida**:
1. Confirmar relaciones usadas por `DepartmentController::destroy()`.
2. Revisar restricciones de FK en requerimientos y pivote usuario-departamento.

**Implementacion**:
1. Agregar relaciones faltantes (`requirements`, `teachers/users`) en modelo.
2. Ajustar controlador para usar relaciones reales.
3. Definir mensaje funcional para bloqueos por dependencias.

**Validacion**:
1. Prueba feature: departamento con dependencias no se elimina.
2. Prueba feature: departamento sin dependencias se elimina correctamente.

**Dependencias**: ninguna.  
**Estimacion**: 3 a 5 horas.

---

### ASAD-P1-04 - Unificacion de logica de prorrogas
**Objetivo**: que desbloqueos con `expires_at null` sean considerados activos en todo flujo docente.

**Investigacion requerida**:
1. Comparar logica de `activeResubmissionUnlock` vs queries directas en controlador.
2. Revisar pantallas y endpoints que validan ventana/prorroga.

**Implementacion**:
1. Reusar relacion/model scope para validar unlock activo en todos los puntos.
2. Evitar duplicidad de logica con queries manuales.

**Validacion**:
1. Prueba feature: unlock sin fecha permite upload/submit.
2. Prueba feature: unlock expirado bloquea upload/submit.

**Dependencias**: ASAD-P1-01.  
**Estimacion**: 3 a 5 horas.

---

## Fase 2 - P2 (Media)

### ASAD-P2-01 - Matriz unica de formatos permitidos
**Objetivo**: alinear lo que UI permite con lo que backend acepta.

**Investigacion requerida**:
1. Inventario de validaciones `mimes` en controladores.
2. Revisar lista de extensiones en `StorageService`.
3. Definir formatos institucionales permitidos por tipo de evidencia.

**Implementacion**:
1. Crear constante/config centralizada de formatos.
2. Consumir misma matriz en UI y backend.

**Validacion**:
1. Prueba feature por formato permitido y bloqueado.
2. Prueba manual de mensaje de error consistente.

**Dependencias**: ASAD-P0-02.  
**Estimacion**: 4 a 6 horas.

---

### ASAD-P2-02 - Alcance JEFE_DEPTO consistente
**Objetivo**: eliminar contradiccion entre servicio de arbol y policies.

**Investigacion requerida**:
1. Definir alcance deseado con negocio (todo vs solo su depto).
2. Revisar `StorageService::getAccessibleRoots` y `FolderNodePolicy`.
3. Medir impacto en performance si se filtra arbol completo.

**Implementacion**:
1. Unificar criterio en servicio + policy + UI.
2. Ajustar consultas para evitar sobrecarga.

**Validacion**:
1. Prueba feature por rol y departamento.
2. Prueba de no filtracion de carpetas fuera de alcance.

**Dependencias**: ninguna.  
**Estimacion**: 5 a 8 horas.

---

### ASAD-P2-03 - Validacion anti-solapamiento de ventanas
**Objetivo**: evitar ventanas activas superpuestas por semestre y evidencia.

**Investigacion requerida**:
1. Definir regla exacta de negocio para solapamiento.
2. Revisar datos actuales de `submission_windows`.
3. Evaluar si se requiere migracion con indice unico parcial o validacion en servicio.

**Implementacion**:
1. Agregar validacion en create/update de ventanas.
2. Opcional: reforzar en BD con constraint/indice.

**Validacion**:
1. Prueba feature: intento de solape retorna error de validacion.
2. Prueba feature: ventana no solapada se guarda correctamente.

**Dependencias**: ninguna.  
**Estimacion**: 5 a 9 horas.

---

### ASAD-P2-04 - Seeders robustos sin ids hardcodeados
**Objetivo**: permitir bootstrap confiable en cualquier ambiente.

**Investigacion requerida**:
1. Revisar orden de creacion de catalogos y dependencias FK.
2. Detectar referencias por id fijo (ej. `role_id = 1`).
3. Definir dataset minimo institucional para entorno nuevo.

**Implementacion**:
1. Reescribir seeders por nombre/slug en lugar de ids fijos.
2. Garantizar que `DatabaseSeeder` ejecute escenario completo.

**Validacion**:
1. `migrate:fresh --seed` sin errores.
2. Login y flujo base operativos en BD limpia.

**Dependencias**: ninguna.  
**Estimacion**: 6 a 12 horas.

---

## Fase 3 - P3 (Baja / Producto)

### ASAD-P3-01 - Dashboard principal productivo
**Objetivo**: reemplazar placeholder por vista de valor operativo.

**Investigacion requerida**:
1. Definir KPIs por rol (pendientes, aprobadas, rechazadas, proximas ventanas).
2. Revisar queries disponibles y costo de carga.

**Implementacion**:
1. Crear controlador/props con metricas.
2. Implementar layout final en `Dashboard.vue`.

**Validacion**:
1. Carga correcta por rol.
2. Sin placeholders en pantalla principal.

**Dependencias**: Fase 0 y 1 cerradas.  
**Estimacion**: 8 a 14 horas.

---

### ASAD-P3-02 - Reportes Oficina productivos
**Objetivo**: habilitar reportes reales para Jefe de Oficina.

**Investigacion requerida**:
1. Definir reportes minimos (cumplimiento por docente, estado por evidencia, atrasos).
2. Elegir formato de salida inicial (tabla + CSV/XLSX/PDF).

**Implementacion**:
1. Backend de agregaciones y filtros.
2. UI de reportes con exportacion minima.

**Validacion**:
1. Reporte coincide con datos de BD.
2. Exportes funcionales con filtros.

**Dependencias**: ASAD-P0-03, ASAD-P1-02.  
**Estimacion**: 10 a 18 horas.

---

### ASAD-P3-03 - Consolidacion de vistas de asesorias
**Objetivo**: reducir duplicidad `Asesorias.vue` y `Asesorias2.vue`.

**Investigacion requerida**:
1. Comparar componentes/funciones duplicadas.
2. Definir vista unica (tabla/cards con switch de presentacion).

**Implementacion**:
1. Consolidar en una sola pagina mantenible.
2. Eliminar codigo duplicado y rutas no necesarias.

**Validacion**:
1. Misma funcionalidad con menos codigo.
2. Sin regresion en exportes y filtros.

**Dependencias**: ASAD-P3-02.  
**Estimacion**: 8 a 16 horas.

---

### ASAD-P3-04 - Normalizacion de idioma en UI
**Objetivo**: coherencia de interfaz (espanol institucional).

**Investigacion requerida**:
1. Inventario de textos EN/ES en vistas admin.
2. Definir glosario unico de terminos.

**Implementacion**:
1. Unificar labels, mensajes y acciones.
2. Evitar mezcla de idiomas en nuevas vistas.

**Validacion**:
1. Revision de UX por flujo admin/docente.
2. No quedan labels criticos en ingles.

**Dependencias**: ASAD-P3-01, ASAD-P3-02.  
**Estimacion**: 4 a 8 horas.

---

## Cambios Previos Relevantes (Historico Conservado)

- Fortalecimiento de politicas y middleware de rol en rutas principales.
- Generacion automatica de estructura de carpetas por semestre/carga.
- Mejoras de navegacion en File Manager y menu por rol.
- Refactors de controladores administrativos y uso de enums.

Estas mejoras siguen vigentes, pero la auditoria de 2026-04-12 identifica brechas transversales que ahora son prioridad superior.

---

## Proxima Accion Recomendada

Implementar de inmediato el paquete P0 (3 fixes) y agregar pruebas de regresion para:
- autorizacion de revision,
- transiciones de estado,
- restricciones de envio por ventana/prorroga,
- trazabilidad de cambios.

---

**Ultima actualizacion**: 2026-04-12 (auditoria integral y repriorizacion tecnica)