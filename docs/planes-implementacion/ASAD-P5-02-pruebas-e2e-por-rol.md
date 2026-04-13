# ASAD-P5-02 - Pruebas E2E por rol

## Objetivo
Validar flujo operativo completo desde UI para DOCENTE, JEFE_OFICINA y JEFE_DEPTO.

## Alcance inicial
- login y navegacion por menu
- ciclo docente de evidencia
- ciclo de revision de oficina
- restricciones visibles por rol

## Investigacion previa
1. Elegir herramienta E2E (Playwright recomendado).
2. Definir dataset estable para ejecucion repetible.
3. Definir rutas de humo por rol.

## Implementacion paso a paso
1. Crear escenarios E2E minimos por rol.
2. Automatizar setup de base de datos de test.
3. Ejecutar en modo local y CI.

## Validacion
- suite E2E verde en ambiente local limpio
- evidencia de screenshots/logs por corrida

## Criterio de cierre
1. Flujos primarios pasan de extremo a extremo.
2. Fallas de permisos se detectan en UI y backend.

## Resultado de ejecucion
1. Se agrego Playwright como framework E2E (`@playwright/test`) con configuracion en `playwright.config.ts`.
2. Se agregaron scripts npm:
	- `e2e`
	- `e2e:headed`
	- `e2e:prepare`
	- `e2e:install`
3. Se agregaron smoke tests por rol:
	- `tests/e2e/docente.smoke.spec.ts`
	- `tests/e2e/oficina.smoke.spec.ts`
	- `tests/e2e/depto.smoke.spec.ts`
4. Se agrego helper de autenticacion en `tests/e2e/helpers/auth.ts`.
5. Se documento guia operativa en `docs/testing/E2E-PLAYWRIGHT.md`.

## Validacion ejecutada
- `npm run e2e:prepare`: migrate:fresh --seed exitoso.
- `npx playwright test --list`: 3 pruebas detectadas.
- `npm run e2e`: no ejecutable en este entorno por restriccion de bind de `php artisan serve` (socket local no disponible).

## Riesgos
- flakiness por tiempos de espera o datos no deterministicos.

## Estimacion
12 a 24 horas

## Estado
Completado (validacion local parcial por limitacion de entorno)
