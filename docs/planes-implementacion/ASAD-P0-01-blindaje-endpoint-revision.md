# ASAD-P0-01 - Blindaje de endpoint de revision

## Objetivo
Impedir que usuarios no autorizados revisen o cambien estados de evidencias.

## Alcance inicial
- routes/web.php
- app/Http/Controllers/Admin/AdvisoryController.php
- app/Policies/EvidenceSubmissionPolicy.php
- tests/Feature (nuevas pruebas)

## Investigacion previa
1. Listar todas las rutas que llaman review de evidencia.
2. Verificar middleware de cada ruta de revision.
3. Confirmar policy actual para accion `review`.
4. Revisar flujo desde vista `SeguimientoDocente`.

## Implementacion paso a paso
1. Mover la ruta de revision al grupo de Jefe de Oficina o agregar middleware puntual.
2. En controlador, ejecutar `authorize('review', $submission)`.
3. Validar estado requerido `SUBMITTED` antes de revisar.
4. Asegurar respuesta 403 para roles no permitidos.
5. Agregar pruebas feature por rol.

## Validacion
- php artisan test tests/Feature/Security/AsesoriasReviewAuthorizationTest.php

## Criterio de cierre
1. Solo JEFE_OFICINA puede revisar evidencias.
2. DOCENTE y JEFE_DEPTO reciben 403.
3. La ruta no queda expuesta en grupos de auth general.

## Resultado de ejecucion
1. Ruta `POST /asesorias/{submission}/review` protegida con middleware `role:JEFE_OFICINA`.
2. `AdvisoryController::reviewEvidence` usa `authorize('review', $submission)`.
3. Pruebas agregadas en `tests/Feature/Security/AsesoriasReviewAuthorizationTest.php`.
4. Resultado de pruebas: 3 passed, 0 failed.

## Riesgos
- Romper navegacion en seguimiento si no se ajusta frontend/ruta.

## Estimacion
4 a 6 horas

## Estado
Completado
