# ASAD-P2-01 - Matriz unica de formatos de archivo

## Objetivo
Eliminar discrepancias entre formatos permitidos en UI, validaciones y servicio de storage.

## Alcance inicial
- resources/js/pages/Teacher/Evidencias/Index.vue
- app/Http/Controllers/Teacher/EvidenceController.php
- app/Http/Controllers/FileController.php
- app/Services/StorageService.php

## Investigacion previa
1. Inventariar todas las reglas `mimes` y `accept` actuales.
2. Definir lista oficial por negocio.
3. Revisar si hay diferencia por tipo de evidencia (documento vs imagen).

## Implementacion paso a paso
1. Crear configuracion central (constante o config) de formatos.
2. Reusar configuracion en controladores y servicio.
3. Alinear atributo `accept` en UI.

## Validacion
- Pruebas feature por extension permitida y bloqueada.
- Prueba manual de mensaje uniforme de error.

## Criterio de cierre
1. Mismo set de formatos en toda la app.
2. No hay error por formato "permitido en UI y rechazado en backend".

## Resultado de ejecucion
1. Se creo `config/evidence.php` como fuente unica de formatos y limite de tamano.
2. `Teacher/EvidenceController`, `FileController` y `StorageService` consumen la misma matriz de extensiones.
3. `FolderController` y `Teacher/EvidenceController` ahora envian extensiones permitidas a Inertia.
4. `Teacher/Evidencias/Index.vue` y `FileManager/Index.vue` usan `accept` dinamico basado en props del backend.
5. Se agregaron pruebas de formato permitido/bloqueado en:
	- `tests/Feature/Teacher/EvidenceUnlockWindowTest.php`
	- `tests/Feature/Security/FileManagerUploadWorkflowTest.php`
6. Resultado de pruebas: 7 passed, 0 failed.

## Riesgos
- Cambiar formatos puede afectar usuarios con archivos historicos.

## Estimacion
4 a 6 horas

## Estado
Completado
