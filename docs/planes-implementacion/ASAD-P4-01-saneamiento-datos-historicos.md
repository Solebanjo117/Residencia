# ASAD-P4-01 - Saneamiento de datos historicos

## Objetivo
Corregir inconsistencias de datos heredadas para que las nuevas reglas P0-P3 no rompan operacion ni reportes.

## Alcance inicial
- tablas `evidence_submissions`, `evidence_status_history`, `evidence_reviews`
- tablas `submission_windows`, `notification_schedules`
- tablas `folder_nodes`, `evidence_files`

## Investigacion previa
1. Identificar submissions con transiciones fuera de flujo institucional.
2. Detectar ventanas solapadas por semestre e item.
3. Detectar archivos sin submission o con rutas invalidas.
4. Medir impacto en reportes y seguimiento docente.

## Implementacion paso a paso
1. Crear script de auditoria SQL/Artisan para inconsistencias.
2. Definir reglas de correccion por tipo de inconsistencia.
3. Ejecutar saneamiento en entorno de staging.
4. Generar reporte antes y despues de correccion.

## Validacion
- reporte de inconsistencias en cero o dentro de umbral aceptado
- smoke test en `/asesorias`, `/docente/evidencias`, `/oficina/revisiones`

## Criterio de cierre
1. Datos historicos compatibles con reglas actuales.
2. Sin errores funcionales tras aplicar saneamiento.

## Resultado de ejecucion
1. Se implemento el comando `asad:audit-historical-data` en `app/Console/Commands/AuditHistoricalData.php`.
2. La auditoria cubre inconsistencias en:
	- transiciones y cadena de `evidence_status_history` vs `evidence_submissions`
	- submissions `SUBMITTED` sin `submitted_at`
	- submissions `APPROVED/REJECTED` sin registros en `evidence_reviews`
	- ventanas activas solapadas en `submission_windows`
	- schedules pendientes huerfanos/duplicados en `notification_schedules`
	- rutas de `evidence_files` fuera de su `folder_nodes.relative_path`
3. Se habilito modo saneamiento con `--fix` para correcciones seguras:
	- completar `submitted_at` faltante
	- crear revisiones sinteticas cuando falta traza minima
	- sincronizar historial en desalineaciones corregibles
	- desactivar ventanas activas solapadas
	- marcar schedules inconsistentes como enviados
	- corregir/mover rutas de archivos cuando es posible
4. Se agregaron pruebas en `tests/Feature/Console/AuditHistoricalDataCommandTest.php` para:
	- deteccion de inconsistencias y salida con codigo de error
	- saneamiento automatico con `--fix` y verificacion post-correccion
5. Validaciones ejecutadas:
	- `php artisan test tests/Feature/Console/AuditHistoricalDataCommandTest.php`: 2 passed.
	- `php artisan test tests/Feature/Console`: 3 passed.

## Riesgos
- cambios masivos de estado sin respaldo pueden afectar trazabilidad.

## Estimacion
8 a 16 horas

## Estado
Completado
