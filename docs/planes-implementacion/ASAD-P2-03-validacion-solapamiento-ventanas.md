# ASAD-P2-03 - Validacion anti-solapamiento de ventanas

## Objetivo
Evitar ventanas activas superpuestas por semestre y evidencia.

## Alcance inicial
- app/Http/Controllers/Admin/SubmissionWindowController.php
- app/Models/SubmissionWindow.php
- database/migrations (si aplica constraint)
- tests/Feature/Admin (windows)

## Investigacion previa
1. Definir regla exacta de solapamiento con negocio.
2. Revisar datos actuales para identificar conflictos existentes.
3. Decidir si la defensa sera solo aplicacion o tambien BD.

## Implementacion paso a paso
1. Agregar validacion de conflicto en store/update.
2. Bloquear guardado si existe ventana solapada.
3. Opcional: crear indice o constraint adicional para reforzar.

## Validacion
- Prueba feature de rechazo por solape.
- Prueba feature de creacion/edicion valida sin solape.

## Criterio de cierre
1. No se pueden crear ventanas conflictivas.
2. Mensajes de validacion claros para admin.

## Resultado de ejecucion
1. `SubmissionWindowController` valida solapamiento activo en `store()` y `update()`.
2. La regla aplica por combinacion `semester_id + evidence_item_id` y rango `opens_at/closes_at`.
3. Solo se bloquean solapes cuando la ventana a guardar queda en estado `ACTIVE`.
4. Mensaje de error unificado: ventana activa ya solapada para mismo semestre/evidencia.
5. Pruebas agregadas en `tests/Feature/Admin/SubmissionWindowOverlapValidationTest.php` para:
	- bloqueo en creacion con solape,
	- alta valida sin solape,
	- bloqueo en actualizacion con solape.
6. Resultado de pruebas: 3 passed, 0 failed.

## Riesgos
- Datos historicos solapados pueden requerir limpieza previa.

## Estimacion
5 a 9 horas

## Estado
Completado
