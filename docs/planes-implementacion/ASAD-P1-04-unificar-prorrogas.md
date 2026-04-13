# ASAD-P1-04 - Unificar logica de prorrogas

## Objetivo
Aplicar una sola regla de unlock activo en todos los flujos docentes.

## Alcance inicial
- app/Models/EvidenceSubmission.php
- app/Http/Controllers/Teacher/EvidenceController.php
- app/Policies/EvidenceSubmissionPolicy.php
- tests/Feature (ventana/prorroga)

## Investigacion previa
1. Comparar condiciones actuales en modelo, policy y controlador.
2. Verificar comportamiento de `expires_at = null`.
3. Definir una API interna unica para consultar unlock activo.

## Implementacion paso a paso
1. Reusar `activeResubmissionUnlock` o crear scope unico.
2. Reemplazar queries manuales duplicadas en controlador.
3. Alinear politica y flujo de upload/submit.

## Validacion
- php artisan test --filter=EvidenceController
- Casos: unlock sin fecha, unlock expirado, sin unlock

## Criterio de cierre
1. Unlock sin fecha se trata como activo en todos los puntos.
2. Unlock expirado bloquea consistentemente.

## Resultado de ejecucion
1. `Teacher/EvidenceController` reemplazo consultas manuales de unlock por `hasActiveUnlock()` usando `activeResubmissionUnlock()`.
2. `submit()` y `storeFile()` ahora comparten la misma regla para prorroga activa, incluyendo `expires_at = null`.
3. Se agregaron pruebas en `tests/Feature/Teacher/EvidenceUnlockWindowTest.php` para:
	- submit con prorroga sin expiracion,
	- upload con prorroga sin expiracion y ventana cerrada.
4. Resultado de pruebas: 3 passed, 0 failed.

## Riesgos
- Diferencias de timezone en comparacion de fechas.

## Estimacion
3 a 5 horas

## Estado
Completado
