# ASAD-P7-01 - Hardening de seguridad de archivos y descargas

## Objetivo
Endurecer controles de upload/download/reemplazo para minimizar riesgos de seguridad.

## Alcance inicial
- `FileController`, `StorageService`, policies de archivos y carpetas
- validacion de MIME, extension, tamano y nombre
- reglas de acceso por rol y ownership

## Investigacion previa
1. Revisar validaciones actuales de tipo y tamano.
2. Evaluar riesgo de path traversal y nombres maliciosos.
3. Confirmar controles de autorizacion en descarga y reemplazo.
4. Definir politicas de antivirus/escaneo si aplica.

## Implementacion paso a paso
1. Unificar validaciones fuertes de upload en un punto central.
2. Asegurar sanitizacion de nombres y rutas almacenadas.
3. Refinar autorizacion en descarga/reemplazo/borrado.
4. Agregar auditoria adicional para acciones sensibles.

## Validacion
- pruebas de seguridad basica sobre upload/download
- pruebas de acceso cruzado entre roles

## Criterio de cierre
1. No hay bypass de acceso a archivos fuera de alcance.
2. Archivos potencialmente peligrosos son bloqueados.
3. Acciones sensibles quedan registradas.

## Resultado de ejecucion
1. Se centralizo validacion fuerte de uploads en `app/Services/StorageService.php`:
	- validacion de extension/mime/tamano,
	- sanitizacion de nombre de archivo,
	- normalizacion de rutas relativas,
	- verificacion de alcance de ruta dentro de carpeta asignada.
2. Se endurecio flujo de `app/Http/Controllers/FileController.php`:
	- descarga validada contra alcance de carpeta,
	- upload/reemplazo con manejo consistente de errores de seguridad,
	- auditoria para acciones sensibles de archivo.
3. Se reforzo autorizacion de borrado en `app/Policies/EvidenceFilePolicy.php` para respetar ownership y estado de submission.
4. Se incorporaron reglas de seguridad de upload en `config/evidence.php` (extensiones, MIME y limites de nombre).
5. Se ampliaron regresiones de seguridad en `tests/Feature/Security/FileManagerUploadWorkflowTest.php` para cubrir:
	- mismatch MIME-extension,
	- nombres maliciosos con intento de traversal,
	- descarga fuera de alcance,
	- borrado en estado no editable.

## Validacion ejecutada
- `php artisan test tests/Feature/Security/FileManagerUploadWorkflowTest.php`: 9 passed.
- `php artisan test tests/Feature/Security`: 18 passed.
- `php artisan test tests/Feature/Teacher/EvidenceUnlockWindowTest.php`: 3 passed.

## Riesgos
- endurecer demasiado puede bloquear formatos legitimos no contemplados.

## Estimacion
10 a 18 horas

## Estado
Completado
