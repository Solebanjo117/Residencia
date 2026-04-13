# ASAD-P2-04 - Seeders robustos sin ids hardcodeados

## Objetivo
Permitir bootstrap completo del sistema en ambiente nuevo sin errores de FK.

## Alcance inicial
- database/seeders/DatabaseSeeder.php
- database/seeders/SeguimientoSeeder.php
- database/seeders/FolderStructureSeeder.php
- nuevos seeders de catalogos si son necesarios

## Investigacion previa
1. Definir dataset minimo obligatorio para operar.
2. Revisar dependencia de roles por id fijo.
3. Verificar orden de siembra por FKs.

## Implementacion paso a paso
1. Reemplazar ids hardcodeados por busqueda por nombre/slug.
2. Completar seed de catalogos base (roles, departamentos, items, etc).
3. Ajustar `DatabaseSeeder` para orquestar todo en orden.

## Validacion
- php artisan migrate:fresh --seed
- Prueba de login y acceso por rol en base limpia

## Criterio de cierre
1. Siembra completa sin errores.
2. Sistema usable inmediatamente despues del seed.

## Resultado de ejecucion
1. `DatabaseSeeder` ahora crea/asegura roles por nombre y usuarios base por rol sin IDs hardcodeados.
2. `DatabaseSeeder` orquesta en orden: `SeguimientoSeeder`, `FolderStructureSeeder`, `AdvisoryScheduleSeeder`.
3. `SeguimientoSeeder` reemplazo `role_id = 1` por lookup del rol `DOCENTE` y asegura categoria `I_CARGA_ACADEMICA` por nombre.
4. `FolderStructureSeeder` reemplazo `PRAGMA` especifico de SQLite por `Schema::disableForeignKeyConstraints()` y lookup de rol por nombre.
5. `AdvisoryScheduleSeeder` dejo de depender del nombre fijo `ENE-JUN 2026` y usa semestre `OPEN` o el mas reciente.
6. Validacion ejecutada: `php artisan migrate:fresh --seed` en limpio, sin errores.


## Riesgos
- Siembra demasiado pesada para entorno de desarrollo rapido.

## Estimacion
6 a 12 horas

## Estado
Completado
