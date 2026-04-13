# ASAD-P0-02 - Refactor File Manager para no forzar estados

## Objetivo
Eliminar cambios de estado directos desde File Manager y respetar workflow institucional.

## Alcance inicial
- app/Http/Controllers/FileController.php
- app/Services/EvidenceService.php
- app/Services/StorageService.php
- tests/Feature (flujo file manager)

## Investigacion previa
1. Buscar todos los `update(['status' => ...])` fuera de `EvidenceService`.
2. Revisar como `FileController::store`, `replace` y `destroy` impactan submission.
3. Verificar si se genera historial y auditoria en esos caminos.

## Implementacion paso a paso
1. Quitar forzado de `SUBMITTED` en FileController.
2. Mantener upload como accion de archivo, no de estado.
3. Cuando aplique cambio de estado, usar `EvidenceService::changeStatus()`.
4. Reusar validaciones de ventana y prorroga en flujo de upload.
5. Agregar pruebas de no transicion implicita.

## Validacion
- php artisan test tests/Feature/Security/FileManagerUploadWorkflowTest.php

## Criterio de cierre
1. Upload/reemplazo no cambia estado automaticamente.
2. Todo cambio de estado crea historial y auditoria.
3. No existe bypass de workflow por gestor de archivos.

## Resultado de ejecucion
1. Eliminado forzado de `SUBMITTED` en `FileController::store`.
2. Se aplica `authorize('update', $submission)` antes de subir archivo.
3. Se valida ventana activa o prorroga en upload y replace.
4. Upload/reemplazo ahora solo actualizan `last_updated_at` y no alteran estado.
5. Pruebas agregadas en `tests/Feature/Security/FileManagerUploadWorkflowTest.php`.
6. Resultado de pruebas: 3 passed, 0 failed.

## Riesgos
- Cambios en UX: usuarios que esperaban submit automatico requeriran ajuste visual.

## Estimacion
6 a 10 horas

## Estado
Completado
