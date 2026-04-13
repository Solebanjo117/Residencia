# ASAD-P3-04 - Normalizar idioma de UI

## Objetivo
Homologar textos de interfaz al espanol institucional.

## Alcance inicial
- resources/js/pages/Admin/**
- recursos compartidos de labels y mensajes
- menus y acciones de navegacion

## Investigacion previa
1. Inventariar textos EN/ES en vistas y componentes.
2. Definir glosario institucional de terminos.
3. Revisar lugares con strings hardcodeados.

## Implementacion paso a paso
1. Sustituir labels y textos en ingles.
2. Homologar mensajes de validacion y acciones.
3. Evitar nuevas cadenas mixtas en cambios futuros.

## Validacion
- Recorrrido manual por modulos admin/oficina/docente.
- Revisar consistencia de textos en menus y formularios.

## Criterio de cierre
1. No quedan etiquetas criticas en ingles.
2. La UI mantiene consistencia de termino funcional.

## Resultado de ejecucion
1. Se normalizaron etiquetas globales del menú en `resources/js/config/menu.ts`:
	- `Dashboard` -> `Panel Principal`
	- `File Manager` -> `Gestor de Archivos`
2. Se tradujo la vista `resources/js/pages/FileManager/Index.vue` en encabezados, acciones, estados y mensajes vacíos.
3. Se tradujeron vistas administrativas clave:
	- `resources/js/pages/Admin/Semesters/Index.vue`
	- `resources/js/pages/Admin/Teachers/Index.vue`
	- `resources/js/pages/Admin/TeachingLoads/Index.vue`
4. Se homologó confirmación en `resources/js/pages/Admin/Windows/Index.vue`.
5. Validaciones ejecutadas:
	- `php artisan test` focalizado: 6 passed, 0 failed.
	- `npm run build`: exitoso.

## Riesgos
- Cambios de texto pueden romper pruebas E2E basadas en labels exactos.

## Estimacion
4 a 8 horas

## Estado
Completado
