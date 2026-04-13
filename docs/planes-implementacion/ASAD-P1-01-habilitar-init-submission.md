# ASAD-P1-01 - Habilitar flujo initSubmission

## Objetivo
Garantizar que una tarea sin submission pueda inicializarse sin bloquear al docente.

## Alcance inicial
- routes/web.php
- app/Http/Controllers/Teacher/EvidenceController.php
- resources/js/pages/Teacher/Evidencias/Index.vue
- tests/Feature (flujo docente)

## Investigacion previa
1. Verificar tareas con `submission.id = null` en el render actual.
2. Confirmar si existe ruta publicada para `initSubmission`.
3. Revisar unicidad de `evidence_submissions` para evitar duplicados.

## Implementacion paso a paso
1. Exponer ruta POST para inicializacion.
2. Conectar accion en frontend antes de upload/submit cuando no exista submission.
3. Mantener `firstOrCreate` idempotente y seguro.
4. Mostrar feedback claro en UI cuando se crea la entrega.

## Validacion
- php artisan test tests/Feature/Teacher/EvidenceInitializationTest.php

## Criterio de cierre
1. No hay bloqueos por id nulo.
2. No se crean submissions duplicadas.

## Resultado de ejecucion
1. Ruta agregada: `POST /docente/evidencias/init` (`docente.evidencias.init`).
2. UI docente permite `Inicializar Entrega` cuando la tarea no tiene submission.
3. Mejorado manejo de keys en lista para evitar colisiones cuando `submission.id` es null.
4. Tipos de `Task` actualizados para soportar `id` y `status` nulos durante inicialización.
5. Pruebas agregadas en `tests/Feature/Teacher/EvidenceInitializationTest.php`.
6. Resultado de pruebas: 2 passed, 0 failed.

## Riesgos
- Condiciones de carrera en clicks multiples si no se controla deshabilitado temporal.

## Estimacion
5 a 8 horas

## Estado
Completado
