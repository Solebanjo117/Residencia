# ASAD-P3-03 - Consolidar vistas de asesorias

## Objetivo
Eliminar duplicidad entre `Asesorias.vue` y `Asesorias2.vue`.

## Alcance inicial
- resources/js/pages/Asesorias.vue
- resources/js/pages/Asesorias2.vue
- rutas/controlador vinculados a ambas vistas

## Investigacion previa
1. Comparar funcionalidades exclusivas de cada vista.
2. Definir layout final (tabla, cards o mixto con toggle).
3. Revisar impacto en exportes y acciones de estado.

## Implementacion paso a paso
1. Diseñar una vista unica con componentes reutilizables.
2. Migrar funciones de exporte y filtros a esa vista.
3. Retirar ruta y archivo duplicado.

## Validacion
- Pruebas manuales de filtros, exportes y acciones.
- Build frontend sin referencias rotas.

## Criterio de cierre
1. Existe una sola vista mantenible de asesorias.
2. No hay regresiones funcionales frente a las dos vistas previas.

## Resultado de ejecucion
1. Se eliminaron las vistas legacy duplicadas:
	- `resources/js/pages/Asesorias.vue`
	- `resources/js/pages/Asesorias2.vue`
2. Se conserva `SeguimientoDocente` como única vista viva de seguimiento para `/asesorias`.
3. Pruebas agregadas en `tests/Feature/Seguimiento/AsesoriasConsolidationTest.php` para validar:
	- componente vivo en `/asesorias`,
	- ruta legacy `/asesorias2` no disponible.
4. Validación adicional: `npm run build` exitoso sin referencias rotas.
5. Resultado de pruebas: 4 passed, 0 failed (incluyendo regresión de reportes en corrida conjunta).

## Riesgos
- Pueden perderse comportamientos ocultos de una de las vistas si no se inventarian antes.

## Estimacion
8 a 16 horas

## Estado
Completado
