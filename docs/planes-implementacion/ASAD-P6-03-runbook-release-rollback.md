# ASAD-P6-03 - Runbook de release y rollback

## Objetivo
Estandarizar despliegues con checklist, validaciones post-release y rollback seguro.

## Alcance inicial
- secuencia de release por ambiente
- migraciones y seeders
- smoke tests post deploy
- rollback de codigo y datos

## Investigacion previa
1. Levantar proceso actual de despliegue.
2. Identificar pasos manuales propensos a error.
3. Definir puntos de control y go/no-go.

## Implementacion paso a paso
1. Crear runbook en markdown con pasos exactos.
2. Agregar checklist previa y posterior al despliegue.
3. Definir plan de rollback tecnico y comunicacional.

## Validacion
- ejecutar simulacro de release y rollback en staging
- documentar tiempos y ajustes

## Criterio de cierre
1. Despliegue repetible por cualquier miembro autorizado.
2. Rollback claro y probado.

## Resultado de ejecucion
1. Se creo runbook operativo en `docs/operations/RUNBOOK-RELEASE-ROLLBACK.md` con:
	- checklist pre-release,
	- secuencia de release,
	- smoke tests post-release,
	- criterios de rollback,
	- secuencia de rollback,
	- lineamientos de comunicacion.
2. El runbook integra comandos de respaldo/recuperacion de P6-02 (`ops:backup`, `ops:restore`).
3. Se definio evidencia minima operativa para auditoria de despliegues.

## Validacion ejecutada
- Revision tecnica del runbook contra scripts reales del proyecto (`composer`, `npm`, `artisan`).
- Verificacion de comandos operativos disponibles en entorno local (`ops:backup`, `ops:restore`, `notify:windows`).

## Riesgos
- rollback incompleto si hay migraciones irreversibles.

## Estimacion
6 a 10 horas

## Estado
Completado
