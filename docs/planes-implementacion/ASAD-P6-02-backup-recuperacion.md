# ASAD-P6-02 - Backup y recuperacion

## Objetivo
Definir y validar estrategia de respaldo y recuperacion para BD y archivos.

## Alcance inicial
- base de datos principal
- storage de evidencias y asesorias
- politicas de retencion y restauracion

## Investigacion previa
1. Confirmar motor de BD por ambiente (SQLite local, motor prod).
2. Identificar directorios criticos de archivos.
3. Definir RPO y RTO objetivo con negocio.

## Implementacion paso a paso
1. Diseñar rutina automatica de backup.
2. Documentar procedimiento de restauracion.
3. Probar restauracion en ambiente de prueba.

## Validacion
- backup generado y verificado
- restore completo con prueba funcional basica

## Criterio de cierre
1. Existe procedimiento de backup/restore probado.
2. Equipo puede recuperar servicio dentro del RTO definido.

## Resultado de ejecucion
1. Se agrego comando `ops:backup` en `app/Console/Commands/OpsBackup.php`.
2. Se agrego comando `ops:restore` en `app/Console/Commands/OpsRestore.php`.
3. Se agendo backup automatico diario en `routes/console.php`:
	- `ops:backup --name=auto` a las 02:00 (`withoutOverlapping`).
4. Se agrego documentacion operativa en `docs/operations/BACKUP-RESTORE.md`.
5. Se agregaron pruebas de backup/restore en `tests/Feature/Console/OpsBackupRestoreCommandTest.php`.

## Validacion ejecutada
- `php artisan test tests/Feature/Console/OpsBackupRestoreCommandTest.php`: 2 passed.
- `php artisan list --raw | Select-String "ops:backup|ops:restore"`: comandos registrados.
- `php artisan schedule:list`: backup automatico visible en scheduler.

## Riesgos
- respaldos incompletos de archivos huérfanos o no versionados.

## Estimacion
8 a 16 horas

## Estado
Completado
