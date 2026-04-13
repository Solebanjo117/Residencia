# ASAD-P0-03 - Endurecer transiciones de estado

## Objetivo
Alinear las transiciones de evidencia con el flujo institucional oficial.

## Alcance inicial
- app/Services/EvidenceService.php
- app/Enums/SubmissionStatus.php
- tests/Unit y tests/Feature de transiciones

## Investigacion previa
1. Definir tabla final de transiciones permitidas con negocio.
2. Mapear llamados actuales a `changeStatus()`.
3. Revisar datos historicos para detectar combinaciones invalidas existentes.

## Implementacion paso a paso
1. Ajustar `ALLOWED_TRANSITIONS`.
2. Mejorar mensajes de excepcion de transicion no permitida.
3. Cubrir flujos especiales: rechazo, NA, NE, reenvio.
4. Agregar pruebas unitarias por estado origen.

## Validacion
- php artisan test tests/Feature/Domain/EvidenceStatusTransitionsTest.php

## Criterio de cierre
1. No existen saltos como DRAFT->APPROVED, REJECTED->APPROVED o APPROVED->SUBMITTED.
2. Todas las rutas de negocio siguen transiciones validas.

## Resultado de ejecucion
1. `ALLOWED_TRANSITIONS` endurecido en `app/Services/EvidenceService.php`.
2. `DRAFT` ya no permite `APPROVED`/`REJECTED` directos.
3. `APPROVED` se vuelve estado terminal por defecto (sin reapertura implícita).
4. `REJECTED` ya no permite salto directo a `APPROVED`.
5. Pruebas agregadas en `tests/Feature/Domain/EvidenceStatusTransitionsTest.php`.
6. Resultado de pruebas: 4 passed, 0 failed.

## Riesgos
- Datos existentes en estado inesperado pueden requerir script de saneamiento.

## Estimacion
4 a 8 horas

## Estado
Completado
