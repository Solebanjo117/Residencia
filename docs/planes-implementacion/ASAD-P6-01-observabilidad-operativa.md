# ASAD-P6-01 - Observabilidad operativa

## Objetivo
Mejorar diagnostico de incidentes en produccion con logs y metricas utiles.

## Alcance inicial
- logs de flujos criticos (evidencias, revision, notificaciones)
- eventos de error y latencia
- trazas de comandos programados

## Investigacion previa
1. Revisar configuracion actual de `config/logging.php`.
2. Identificar puntos sin logging de negocio.
3. Definir eventos clave para monitoreo.

## Implementacion paso a paso
1. Estandarizar formato de logs por modulo.
2. Agregar logs de contexto en acciones criticas.
3. Incluir identificadores de usuario, submission y rol cuando aplique.

## Validacion
- ejecutar flujos y verificar logs estructurados
- simular error y confirmar rastreabilidad

## Criterio de cierre
1. Se puede reconstruir un incidente desde logs.
2. Comandos programados dejan evidencia de ejecucion.

## Resultado de ejecucion
1. Se agrego canal dedicado `operations` en `config/logging.php`.
2. Se agregaron variables de entorno en `.env.example`:
	- `LOG_OPERATIONS_LEVEL`
	- `LOG_OPERATIONS_DAYS`
3. Se instrumentaron logs estructurados con contexto y latencia en flujos criticos:
	- `app/Http/Controllers/Teacher/EvidenceController.php`
	- `app/Http/Controllers/Admin/ReviewController.php`
	- `app/Services/EvidenceService.php`
	- `app/Services/NotificationService.php`
	- `app/Jobs/SendScheduledNotificationsJob.php`
	- `app/Console/Commands/NotifyWindows.php`
4. Se documento catalogo de eventos y operacion en `docs/operations/OBSERVABILIDAD-OPERATIVA.md`.

## Validacion ejecutada
- `php artisan test tests/Feature/Security/OfficeReviewStatusAuthorizationTest.php`: 3 passed.
- `php artisan test tests/Feature/Domain/EvidenceStatusTransitionsTest.php`: 4 passed.
- Verificacion de eventos en `storage/logs/operations-*.log` (eventos `notify_windows.*`, `review.*`, `evidence.*`, `backup.*`, `restore.*`).

## Riesgos
- exceso de log puede afectar rendimiento o costo de almacenamiento.

## Estimacion
6 a 12 horas

## Estado
Completado
