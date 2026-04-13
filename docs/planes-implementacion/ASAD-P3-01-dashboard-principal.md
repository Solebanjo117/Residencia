# ASAD-P3-01 - Dashboard principal productivo

## Objetivo
Reemplazar placeholder por dashboard con valor operacional por rol.

## Alcance inicial
- resources/js/pages/Dashboard.vue
- app/Http/Controllers (endpoint dashboard)
- consultas de metricas en modelos/servicios

## Investigacion previa
1. Definir KPIs minimos por rol.
2. Validar disponibilidad de datos y costo de consultas.
3. Definir comportamiento con semestre no activo.

## Implementacion paso a paso
1. Diseñar props y consultas de metricas.
2. Implementar tarjetas de resumen y accesos rapidos.
3. Mostrar alertas de ventanas y pendientes.

## Validacion
- Prueba manual por rol.
- Build frontend y smoke test de dashboard.

## Criterio de cierre
1. No quedan placeholders en dashboard principal.
2. Muestra metricas utiles y navegacion rapida.

## Resultado de ejecucion
1. Se creó `app/Http/Controllers/DashboardController.php` con métricas y accesos rápidos por rol.
2. La ruta `GET /dashboard` ahora usa el controlador y deja de renderizar una vista estática.
3. `resources/js/pages/Dashboard.vue` fue reemplazada por un panel funcional con:
	- KPIs por rol,
	- accesos rápidos,
	- próximos cierres de ventanas,
	- alerta cuando no existe semestre activo.
4. Pruebas agregadas en `tests/Feature/DashboardOverviewTest.php` para validar props y acciones por rol.
5. Resultado de pruebas: 4 passed, 0 failed (`DashboardTest` + `DashboardOverviewTest`).

## Riesgos
- Consultas costosas sin cache pueden impactar carga inicial.

## Estimacion
8 a 14 horas

## Estado
Completado
