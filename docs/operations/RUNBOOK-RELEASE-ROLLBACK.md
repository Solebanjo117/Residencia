# Runbook Release y Rollback

## Objetivo
Estandarizar despliegue y rollback para reducir errores operativos y acortar recuperacion ante incidentes.

## Roles
- Responsable tecnico: ejecuta despliegue/rollback.
- Soporte funcional: valida smoke tests por rol.
- Coordinacion: comunica go/no-go.

## Checklist previa al release
1. CI en verde (lint, test, build, e2e-smoke).
2. Snapshot reciente disponible con `php artisan ops:backup --name=pre-release`.
3. Cambio revisado y aprobado en PR.
4. Ventana de mantenimiento comunicada.

## Secuencia de release
1. Poner app en mantenimiento:
```bash
php artisan down --render="errors::503"
```
2. Actualizar codigo y dependencias:
```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```
3. Migraciones:
```bash
php artisan migrate --force
```
4. Limpiar caches:
```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
5. Levantar app:
```bash
php artisan up
```

## Smoke tests post-release
1. Autenticacion (usuario valido).
2. Docente: acceso a evidencias y carga de archivo.
3. Oficina: acceso a revisiones y cambio de estado.
4. Departamento: acceso a ventanas/reportes.
5. Comandos:
```bash
php artisan notify:windows
```

## Criterios de rollback
Ejecutar rollback si ocurre cualquiera de estos:
1. Error de login generalizado.
2. Error 500 sostenido en flujo de evidencias.
3. Falla de migracion con impacto de integridad.
4. Regresion critica de autorizacion por rol.

## Secuencia de rollback
1. Poner app en mantenimiento:
```bash
php artisan down --render="errors::503"
```
2. Volver a tag/commit estable.
3. Restaurar datos desde snapshot previo:
```bash
php artisan ops:restore <snapshot-pre-release> --force
```
4. Reinstalar dependencias/build si aplica:
```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```
5. Limpiar caches y levantar app:
```bash
php artisan optimize:clear
php artisan up
```

## Comunicacion
- Inicio release: notificar ventana y alcance.
- Go-live exitoso: confirmar cierre con hora.
- Rollback: comunicar causa, snapshot aplicado y ETA de estabilidad.

## Evidencia minima a conservar
- Snapshot usado (`storage/app/backups/<snapshot>`).
- Resultado de CI del release.
- Registro de comandos ejecutados.
- Resultado de smoke tests post-release.
