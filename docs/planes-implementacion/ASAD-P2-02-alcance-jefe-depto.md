# ASAD-P2-02 - Alcance de JEFE_DEPTO consistente

## Objetivo
Unificar reglas de visibilidad de carpetas para Jefe de Departamento.

## Alcance inicial
- app/Services/StorageService.php
- app/Policies/FolderNodePolicy.php
- app/Http/Controllers/FolderController.php
- tests/Feature/FileManager (roles)

## Investigacion previa
1. Definir alcance de negocio final (todo o solo su departamento).
2. Revisar contradicciones entre arbol de roots y policy por nodo.
3. Medir impacto de performance en consultas de arbol.

## Implementacion paso a paso
1. Aplicar un unico criterio en servicio y policy.
2. Ajustar consultas para evitar sobrecarga al filtrar.
3. Verificar que UI no cachee nodos fuera de alcance.

## Validacion
- Pruebas feature por rol y departamento.
- Navegacion manual por carpetas limite.

## Criterio de cierre
1. JEFE_DEPTO solo ve lo permitido por negocio.
2. No hay fugas de informacion por inconsistencias entre capas.

## Resultado de ejecucion
1. `StorageService::getAccessibleRoots()` separa ahora el alcance de `JEFE_OFICINA` y `JEFE_DEPTO`.
2. Para `JEFE_DEPTO` se construye un arbol filtrado a docentes de sus departamentos, incluyendo solo ancestros necesarios.
3. `FolderController::show()` filtra `contents.folders` y `contents.files` por policy antes de responder a Inertia.
4. Se agregaron pruebas en `tests/Feature/Security/JefeDeptoFolderScopeTest.php` para:
	- arbol visible por departamento,
	- contenido de carpeta filtrado,
	- bloqueo de acceso directo a carpeta de otro departamento.
5. Resultado de pruebas: 3 passed, 0 failed.

## Riesgos
- Cambios de alcance pueden afectar expectativa actual de usuarios.

## Estimacion
5 a 8 horas

## Estado
Completado
