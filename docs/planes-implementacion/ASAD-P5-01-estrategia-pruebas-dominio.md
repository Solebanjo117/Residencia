# ASAD-P5-01 - Estrategia de pruebas de dominio

## Objetivo
Construir una suite minima que proteja reglas criticas de negocio.

## Alcance inicial
- transiciones de evidencia
- permisos por rol
- ventanas y prorrogas
- file manager vinculado a submissions

## Investigacion previa
1. Mapear casos criticos de negocio no cubiertos.
2. Definir matriz de pruebas por rol y modulo.
3. Identificar fixtures/seeds de test necesarios.

## Implementacion paso a paso
1. Crear pruebas unitarias para servicios de dominio.
2. Crear pruebas feature por endpoints criticos.
3. Estandarizar factories para datos institucionales.

## Validacion
- ejecutar suite completa y medir cobertura de casos criticos
- verificar que tests fallen cuando se rompe regla clave

## Criterio de cierre
1. Existe cobertura para P0 y P1.
2. Pipeline detecta regresiones de reglas institucionales.

## Resultado de ejecucion
1. Se agrego testsuite `Domain` en `phpunit.xml` para ejecutar pruebas de dominio y seguridad critica.
2. Se agregaron scripts de composer:
	- `test:domain`
	- `test:critical`
3. Se agrego prueba de autorizacion faltante en `tests/Feature/Security/OfficeReviewStatusAuthorizationTest.php`.
4. Se documento matriz de cobertura de dominio en `docs/testing/DOMINIO-MATRIZ-COBERTURA.md`.

## Validacion ejecutada
- `php artisan test tests/Feature/Security/OfficeReviewStatusAuthorizationTest.php`: 3 passed.
- `composer test:domain`: 25 passed, 110 assertions.

## Riesgos
- tests lentos por dependencia excesiva de BD real.

## Estimacion
10 a 20 horas

## Estado
Completado
