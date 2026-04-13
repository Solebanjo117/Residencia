# ASAD-P0-00 - Baseline de ruta viva y congelamiento de legado

## Objetivo
Definir con evidencia tecnica que rutas y pantallas son "vivas" para implementacion inmediata y cuales quedan fuera por ser legado.

## Alcance inicial
- routes/web.php
- app/Http/Controllers/Admin/AdvisoryController.php
- app/Http/Controllers/Teacher/AdvisorySessionController.php
- resources/js/pages/SeguimientoDocente.vue
- resources/js/pages/Asesorias.vue
- resources/js/pages/Asesorias2.vue

## Investigacion previa
1. Confirmar rutas vivas de seguimiento en backend.
2. Confirmar controlador real que renderiza seguimiento unificado.
3. Validar si `/asesorias2` existe o es solo referencia frontend.
4. Identificar archivos heredados con codigo no conectado a ruta.
5. Identificar semantica de `POST /asesorias/{submission}/review` y su ubicacion de seguridad.

## Implementacion paso a paso
1. Documentar matriz "Ruta -> Controller -> Vista -> Estado (vivo/legado)".
2. Etiquetar explicitamente vistas legado para no usarlas en fases P0/P1.
3. Agregar regla de trabajo: todo cambio funcional de seguimiento debe impactar solo `SeguimientoDocente.vue` salvo excepcion justificada.
4. Registrar decision tecnica en `PROJECT_STATUS.md` y en este plan.

## Validacion
1. Verificacion manual de navegacion por rutas vivas.
2. Verificar que no se programen cambios P0/P1 en vistas sin ruta activa.
3. Confirmar con grep que no existe ruta `/asesorias2`.

## Criterio de cierre
1. Existe baseline aprobado y versionado.
2. El equipo tiene claro que pantalla es oficial para seguimiento.
3. Las siguientes tareas P0/P1 quedan acotadas a archivos vivos.

## Resultado de ejecucion
1. Baseline generado en `docs/planes-implementacion/baseline-rutas-vivas-legado-2026-04-12.md`.
2. Ruta oficial de seguimiento confirmada: `/asesorias` con `SeguimientoDocente`.
3. Vistas `Asesorias.vue` y `Asesorias2.vue` clasificadas como legado para P0/P1.

## Riesgos
- Implementar sobre vistas legado puede duplicar trabajo y ocultar bugs en produccion.

## Estimacion
2 a 4 horas

## Estado
Completado (baseline inicial)
