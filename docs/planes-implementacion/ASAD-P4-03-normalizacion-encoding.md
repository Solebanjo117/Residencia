# ASAD-P4-03 - Normalizacion de encoding y textos corruptos

## Objetivo
Eliminar problemas de caracteres corruptos y estandarizar UTF-8 en backend y frontend.

## Alcance inicial
- `PROJECT_STATUS.md`, `MEMORY.md`, docs y textos de UI
- controladores con mensajes de validacion
- vistas con strings mezclados

## Investigacion previa
1. Detectar archivos con mojibake o codificacion inconsistente.
2. Revisar configuracion de editor y git para encoding.
3. Definir norma: UTF-8 sin BOM.

## Implementacion paso a paso
1. Corregir archivos afectados por bloques.
2. Estandarizar mensajes institucionales en espanol limpio.
3. Agregar guia breve de encoding en docs de contribucion.

## Validacion
- busqueda de caracteres corruptos en repositorio
- revision manual de pantallas clave

## Criterio de cierre
1. No quedan textos con simbolos corruptos en modulos core.
2. Nuevos commits mantienen encoding consistente.

## Resultado de ejecucion
1. Se escaneo `app/**`, `resources/**` y `docs/**` en busca de secuencias comunes de mojibake (`Ã`, `Â`, `â€™`, `â€œ`, `â€`, `ï»¿`) sin hallazgos en modulos core.
2. Se detectaron archivos frontend con UTF-8 BOM y se normalizaron a UTF-8 sin BOM:
	- `resources/js/pages/auth/TwoFactorChallenge.vue`
	- `resources/js/pages/Docente/MyAdvisories.vue`
	- `resources/js/pages/FileManager/Index.vue`
	- `resources/js/pages/Oficina/ReviewDetail.vue`
	- `resources/js/pages/Teacher/Evidencias/Index.vue`
3. Se agrego guia de contribucion de encoding en `docs/CONTRIBUCION-ENCODING.md` con reglas y comandos de verificacion/correccion.

## Validacion ejecutada
- Verificacion de BOM en `app`, `bootstrap`, `config`, `database`, `docs`, `resources`, `routes`, `tests`: sin archivos con BOM tras normalizacion.
- Busqueda de mojibake en `app/**`, `resources/**`, `docs/**`: sin coincidencias.
- `npm run build`: exitoso tras normalizacion.

## Riesgos
- cambios masivos de texto pueden generar conflictos de merge.

## Estimacion
4 a 8 horas

## Estado
Completado
