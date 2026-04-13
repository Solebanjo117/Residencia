# ASAD-P1-02 - Scheduler y fix de notificaciones programadas

## Objetivo
Activar notificaciones programadas confiables y evitar errores por columnas incorrectas.

## Alcance inicial
- app/Console/Commands/NotifyWindows.php
- app/Jobs/SendScheduledNotificationsJob.php
- routes/console.php
- app/Services/NotificationService.php

## Investigacion previa
1. Revisar uso de columnas de `teaching_loads` en comando/job.
2. Confirmar mecanismo elegido: comando, job o ambos.
3. Validar compatibilidad de enum `notification_type` con schema.

## Implementacion paso a paso
1. Corregir `user_id` por `teacher_user_id` donde aplique.
2. Registrar tarea programada en `routes/console.php`.
3. Evitar doble envio entre comando y job (definir uno como fuente principal).
4. Agregar logs minimos de ejecucion para trazabilidad.

## Validacion
- php artisan test tests/Feature/Console/NotifyWindowsCommandTest.php
- php artisan schedule:list

## Criterio de cierre
1. Schedules vencidos se envian una sola vez.
2. No hay excepciones por columna inexistente.

## Resultado de ejecucion
1. `NotifyWindows` ahora usa `teacher_user_id` en lugar de `user_id`.
2. `NotifyWindows` filtra ventanas activas (`status = ACTIVE`).
3. Scheduler registrado en `routes/console.php` con `notify:windows` cada 5 minutos.
4. Corregido `NotificationSchedule` con `public $timestamps = false` para evitar writes a `updated_at` inexistente.
5. Prueba agregada en `tests/Feature/Console/NotifyWindowsCommandTest.php`.
6. Resultado de pruebas: 1 passed, 0 failed.

## Riesgos
- Duplicidad de notificaciones si quedan dos mecanismos activos sin control.

## Estimacion
6 a 10 horas

## Estado
Completado
