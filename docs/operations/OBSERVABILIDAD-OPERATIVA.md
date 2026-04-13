# Observabilidad Operativa

## Objetivo
Estandarizar trazabilidad de flujos criticos (evidencias, revisiones, notificaciones y comandos programados) para diagnostico rapido de incidentes.

## Canal de logs
- Canal: `operations`
- Archivo: `storage/logs/operations-YYYY-MM-DD.log`
- Configuracion: [config/logging.php](config/logging.php)
- Variables de entorno:
  - `LOG_OPERATIONS_LEVEL` (default: `info`)
  - `LOG_OPERATIONS_DAYS` (default: `30`)

## Campos de contexto esperados
- `actor_user_id`
- `actor_role_id`
- `submission_id`
- `semester_id`
- `evidence_item_id`
- `duration_ms`

## Eventos principales
- Evidencias docente:
  - `evidence.init_submission`
  - `evidence.submit_forbidden`
  - `evidence.submit_invalid_state`
  - `evidence.submit_window_closed`
  - `evidence.submit_without_files`
  - `evidence.submitted`
  - `evidence.file_uploaded`
  - `evidence.file_upload_failed`
- Revision oficina:
  - `review.status_update_requested`
  - `review.status_updated`
  - `review.status_update_failed`
- Servicio de evidencias:
  - `evidence.status_changed`
  - `evidence.review_completed`
  - `evidence.resubmission_unlocked`
- Notificaciones:
  - `notifications.scheduled`
  - `notifications.immediate_sent`
  - `notifications.job.started`
  - `notifications.job.schedule_processed`
  - `notifications.job.schedule_failed`
  - `notifications.job.completed`
- Comando programado:
  - `notify_windows.started`
  - `notify_windows.schedule_dispatched`
  - `notify_windows.window_not_found`
  - `notify_windows.completed`

## Verificacion rapida
1. Ejecutar flujo docente de carga/envio y revisar `evidence.*` en el log.
2. Ejecutar revision de oficina (aprobar/rechazar) y validar eventos `review.*`.
3. Ejecutar `php artisan notify:windows` y validar trazas de inicio/fin + latencia por schedule.
4. Forzar un error controlado (por ejemplo, ventana inexistente) y confirmar evento `*.failed` o `*.window_not_found`.

## Comandos utiles
```bash
# Ultimos eventos operativos
Get-Content storage/logs/operations-$(Get-Date -Format yyyy-MM-dd).log -Tail 100

# Filtrar eventos de una submission
Select-String -Path storage/logs/operations-*.log -Pattern '"submission_id":123'
```
