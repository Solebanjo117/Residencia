# ASAD-P3-02 - Reportes de Oficina productivos

## Objetivo
Entregar reportes operativos para Jefe de Oficina con filtros y exportacion.

## Alcance inicial
- resources/js/pages/Oficina/Reports.vue
- app/Http/Controllers/Admin (reportes)
- servicios de agregacion/exportacion

## Investigacion previa
1. Definir reportes minimos con usuarios clave.
2. Definir formato de exportacion inicial (CSV/XLSX/PDF).
3. Verificar consistencia de estados para metricas.

## Implementacion paso a paso
1. Crear endpoint de consulta agregada por filtros.
2. Implementar tabla base de reportes en UI.
3. Agregar exportacion del resultado filtrado.

## Validacion
- Prueba manual de filtros.
- Validar conteos contra BD en muestra controlada.

## Criterio de cierre
1. Jefe de Oficina puede consultar y exportar reportes.
2. Reportes reflejan estados reales de evidencia.

## Resultado de ejecucion
1. Se creó `app/Http/Controllers/Admin/ReportController.php` con:
	- agregación por docente,
	- filtros por semestre, búsqueda y estado,
	- exportación CSV sobre el mismo filtro activo.
2. La ruta `GET /oficina/reportes` ya consume controlador y no una vista estática.
3. `resources/js/pages/Oficina/Reports.vue` se reemplazó por una pantalla funcional con:
	- KPIs resumen,
	- filtros operativos,
	- tabla consolidada,
	- botón de exportación CSV.
4. Pruebas agregadas en `tests/Feature/Admin/OfficeReportsTest.php` para:
	- agregación correcta,
	- exportación CSV con filtros.
5. Resultado de pruebas: 2 passed, 0 failed.

## Riesgos
- Cambios en reglas de estado pueden alterar historicos de reporte.

## Estimacion
10 a 18 horas

## Estado
Completado
