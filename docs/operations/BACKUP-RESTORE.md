# Backup y Recuperacion

## Alcance implementado
- Backup automatico y manual para:
  - Base de datos sqlite.
  - Archivos de `storage/app` (excluye `storage/app/backups` para evitar recursion).
- Restore completo o parcial (`db` o `files`).
- Evidencia en `manifest.json` por snapshot.

## Comandos
```bash
# Backup manual (db + files)
php artisan ops:backup --name=manual

# Backup solo base de datos
php artisan ops:backup --name=manual-db --no-files

# Restore completo
php artisan ops:restore <snapshot> --force

# Restore solo base de datos
php artisan ops:restore <snapshot> --only=db --force

# Restore solo archivos
php artisan ops:restore <snapshot> --only=files --force
```

## Programacion automatica
En [routes/console.php](routes/console.php) se agenda:
- `ops:backup --name=auto` diario a las `02:00` con `withoutOverlapping()`.

## Estructura del snapshot
```text
storage/app/backups/<snapshot>/
  manifest.json
  database.sqlite
  storage_app/
    ...
```

## Notas operativas
- Esta implementacion soporta sqlite para backup/restore automatico.
- Si el motor de produccion cambia (MySQL/PostgreSQL), se debe extender la estrategia con dump nativo del motor.
- Durante restore de archivos, se conserva `storage/app/backups` y se repone el resto desde el snapshot.

## Validacion recomendada
1. Generar snapshot de prueba.
2. Modificar archivo de evidencia de prueba y (si aplica) sqlite local.
3. Restaurar snapshot.
4. Confirmar recuperacion funcional minima:
   - Login operativo.
   - Pantalla de evidencias con archivos visibles.
   - Ejecucion de `php artisan test tests/Feature/Console/OpsBackupRestoreCommandTest.php`.
