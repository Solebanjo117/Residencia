# ASAD-P5-03 - Pipeline CI con gates

## Objetivo
Definir un pipeline de integracion continua que bloquee merges con regresiones.

## Alcance inicial
- GitHub Actions
- validacion de estilo, tests y build
- reportes de artefactos de fallo

## Investigacion previa
1. Revisar workflows existentes en `.github/workflows`.
2. Definir orden optimo: lint -> test -> build.
3. Determinar tiempos y cache de dependencias.

## Implementacion paso a paso
1. Crear workflow de CI principal.
2. Agregar gates obligatorios por rama principal.
3. Publicar artifact de logs y reportes en fallo.

## Validacion
- PR de prueba con fallo intencional bloqueada
- PR valida pasa todos los checks

## Criterio de cierre
1. No se puede mergear sin checks verdes.
2. Equipo tiene visibilidad clara del estado del build.

## Resultado de ejecucion
1. Se reemplazaron workflows separados por un workflow unificado en `.github/workflows/ci.yml`.
2. Se definieron gates secuenciales:
	- `lint`
	- `test`
	- `build`
	- `e2e-smoke`
3. Se agregaron artefactos de fallo:
	- `laravel-logs`
	- `playwright-report`
	- `playwright-test-results`
4. Se agrego script frontend `lint:check` en `package.json` para ejecucion no destructiva en CI.

## Validacion ejecutada
- `php artisan test tests/Feature/Console/NotifyWindowsCommandTest.php tests/Feature/Security/OfficeReviewStatusAuthorizationTest.php`: 4 passed.
- `npm run lint:check`: falla por deuda previa de lint en repositorio (110 errores existentes).
- `npm run format:check`: falla por deuda previa de formato en repositorio (22 archivos).

## Nota operativa
El pipeline queda implementado como gate estricto; para que todas las ramas pasen en verde se requiere resolver la deuda de lint/format ya existente.

## Riesgos
- pipeline lento si no se optimiza cache.

## Estimacion
6 a 12 horas

## Estado
Completado (gates implementados, deuda tecnica visible)
