# ASAD-P4-02 - Limpieza de legado y artefactos

## Objetivo
Reducir deuda tecnica eliminando rutas, vistas y archivos no utilizados por el flujo vivo.

## Alcance inicial
- `resources/js/pages/Asesorias.vue`
- `resources/js/pages/Asesorias2.vue`
- placeholders no activos en flujo productivo
- archivos temporales en raiz (solo con confirmacion)

## Investigacion previa
1. Confirmar matriz de rutas vivas definida en ASAD-P0-00.
2. Listar imports/referencias activas a vistas legado.
3. Revisar impacto en menu y breadcrumbs.

## Implementacion paso a paso
1. Marcar vistas legado como deprecated y remover referencias.
2. Eliminar codigo muerto validado.
3. Limpiar artefactos temporales con checklist aprobado.

## Validacion
- build frontend sin imports rotos
- navegacion por menus sin rutas huérfanas

## Criterio de cierre
1. No hay codigo muerto conectado a rutas activas.
2. Estructura de frontend simplificada y mantenible.

## Resultado de ejecucion
1. Se verifico la matriz viva de rutas y vistas sin referencias funcionales a `Asesorias.vue` o `Asesorias2.vue` en codigo activo.
2. Se elimino el artefacto temporal sensible `temp_script.php` de la raiz del repositorio.
3. Se elimino el componente huerfano `resources/js/components/PlaceholderPattern.vue` (sin referencias activas).
4. Se organizo documentacion legacy moviendo `estructura_detectada Actualiazada2.txt` a `docs/legacy-notes/estructura-detectada-actualizada2.txt` para limpiar la raiz sin perder contexto historico.
5. Validaciones ejecutadas:
	- `npm run build`: exitoso.
	- `php artisan test tests/Feature/Seguimiento/AsesoriasConsolidationTest.php tests/Feature/Console/AuditHistoricalDataCommandTest.php`: 4 passed.

## Riesgos
- borrar un archivo con uso indirecto no detectado.

## Estimacion
4 a 10 horas

## Estado
Completado
